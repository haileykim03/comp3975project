<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ReturnLog;
use Cloudinary\Cloudinary;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index()
    {
        return response()->json(
            Item::with(['finder', 'owner'])->orderBy('created_at', 'desc')->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|max:255',
            'color' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'location' => 'required|string|max:255',
            'finder_id' => 'required|exists:users,id',
            'found_at' => 'nullable|date',
            'image' => 'nullable|image|max:12288',
        ]);

        \Log::error('Validation passed, uploading image', ['has_image' => $request->hasFile('image')]);

        try {
            $imageData = $this->uploadImageToCloudinary($request->file('image'));
        } catch (\Exception $e) {
            \Log::error('Image upload failed', ['error' => $e->getMessage(), 'file' => $request->file('image') ? $request->file('image')->getClientOriginalName() : 'no file']);
            return response()->json([
                'message' => 'The image failed to upload.',
                'errors' => [
                    'image' => ['The image failed to upload.']
                ]
            ], 422);
        }

        $item = Item::create([
            'name' => $request->name,
            'description' => $request->description,
            'category' => $request->category,
            'color' => $request->color,
            'brand' => $request->brand,
            'location' => $request->location,
            'image_url' => $imageData['url'] ?? null,
            'image_public_id' => $imageData['public_id'] ?? null,
            'finder_id' => $request->finder_id,
            'owner_id' => null,
            'status' => 'pending',
            'found_at' => $request->found_at,
        ]);

        return response()->json($item->load(['finder', 'owner']), 201);
    }

    public function show(string $id)
    {
        $item = Item::with(['finder', 'owner'])->find($id);

        if (!$item) {
            return response()->json(['message' => 'Item not found'], 404);
        }

        return response()->json($item);
    }

    public function update(Request $request, string $id)
    {
        $item = Item::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|max:255',
            'color' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'location' => 'required|string|max:255',
            'finder_id' => 'required|exists:users,id',
            'owner_id' => 'nullable|exists:users,id',
            'status' => 'required|in:pending,active,claim pending,returned',
            'found_at' => 'nullable|date',
            'image' => 'nullable|image|max:12288',
        ]);

        $isClaimRequest =
            $validated['status'] === 'claim pending' &&
            !empty($validated['owner_id']);

        if ($isClaimRequest) {
            if ($item->status !== 'active') {
                return response()->json([
                    'message' => 'This item is no longer available for claim.',
                ], 409);
            }

            if (!empty($item->owner_id) && (int) $item->owner_id !== (int) $validated['owner_id']) {
                return response()->json([
                    'message' => 'This item has already been claimed by another user.',
                ], 409);
            }
        }

        try {
            $imageData = $this->uploadImageToCloudinary($request->file('image'), $item->image_public_id);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'The image failed to upload.',
                'errors' => [
                    'image' => ['The image failed to upload.']
                ]
            ], 422);
        }

        if ($imageData !== null) {
            $validated['image_url'] = $imageData['url'];
            $validated['image_public_id'] = $imageData['public_id'];
        }

        $wasReturnedBefore = $item->status === 'returned';

        $item->update($validated);

        if (
            !$wasReturnedBefore &&
            $item->status === 'returned' &&
            $item->owner_id !== null
        ) {
            ReturnLog::create([
                'item_id' => $item->id,
                'finder_id' => $item->finder_id,
                'owner_id' => $item->owner_id,
                'returned_at' => now(),
            ]);
        }

        return response()->json($item->load(['finder', 'owner']));
    }

    public function claim(Request $request, string $id)
    {
        $item = Item::findOrFail($id);
        $user = $request->user();

        $validated = $request->validate([
            'owner_id' => 'required|exists:users,id',
        ]);

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($item->status !== 'active') {
            return response()->json([
                'message' => 'This item is no longer available for claim.',
            ], 409);
        }

        if (!empty($item->owner_id) && (int) $item->owner_id !== (int) $user->id) {
            return response()->json([
                'message' => 'This item has already been claimed by another user.',
            ], 409);
        }

        $item->owner_id = $validated['owner_id'];
        $item->status = 'claim pending';
        $item->save();

        return response()->json([
            'message' => 'Claim submitted successfully.',
            'item' => $item,
        ]);
    }

    public function destroy(string $id)
    {
        $item = Item::find($id);

        if (!$item) {
            return response()->json(['message' => 'Item not found'], 404);
        }

        if ($item->image_public_id) {
            $this->deleteCloudinaryImage($item->image_public_id);
        }

        $item->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    private function uploadImageToCloudinary($image, ?string $existingPublicId = null): ?array
    {
        if (!$image) {
            return null;
        }

        \Log::error('Uploading image to Cloudinary', ['file' => $image->getClientOriginalName(), 'size' => $image->getSize(), 'mime' => $image->getMimeType()]);

        $config = [
            'cloud' => [
                'cloud_name' => config('services.cloudinary.cloud_name'),
                'api_key' => config('services.cloudinary.api_key'),
                'api_secret' => config('services.cloudinary.api_secret'),
            ],
        ];

        $cloudinary = new Cloudinary($config);

        if ($existingPublicId) {
            $this->deleteCloudinaryImage($existingPublicId);
        }

        try {
            $uploadResult = $cloudinary->uploadApi()->upload($image->getRealPath(), [
                'folder' => 'bcit_lost_found/items',
                'public_id' => 'item_' . uniqid(),
                'overwrite' => true,
                'resource_type' => 'auto',
            ]);

            \Log::error('Cloudinary upload result', ['result' => $uploadResult]);

            return [
                'url' => $uploadResult['secure_url'] ?? $uploadResult['url'] ?? null,
                'public_id' => $uploadResult['public_id'] ?? null,
            ];
        } catch (\Exception $e) {
            \Log::error('Cloudinary upload exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    private function deleteCloudinaryImage(string $publicId): void
    {
        $config = [
            'cloud' => [
                'cloud_name' => config('services.cloudinary.cloud_name'),
                'api_key' => config('services.cloudinary.api_key'),
                'api_secret' => config('services.cloudinary.api_secret'),
            ],
        ];

        $cloudinary = new Cloudinary($config);
        $cloudinary->uploadApi()->destroy($publicId, ['resource_type' => 'image']);
    }
}