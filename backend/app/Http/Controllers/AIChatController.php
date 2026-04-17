<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Services\GithubModelsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AIChatController extends Controller
{
    /**
     * The GitHub models service.
     *
     * @var GithubModelsService
     */
    private GithubModelsService $githubModelsService;

    /**
     * Creates a new controller instance.
     *
     * @param GithubModelsService $githubModelsService the AI service
     */
    public function __construct(GithubModelsService $githubModelsService)
    {
        $this->githubModelsService = $githubModelsService;
    }

    /**
     * Handles AI chat search requests.
     *
     * @param Request $request the HTTP request
     * @return JsonResponse
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'previous_structured_data' => 'nullable|array',
            'conversation_messages' => 'nullable|array',
        ]);

        $message = trim($validated['message']);
        $previousStructuredData = $validated['previous_structured_data'] ?? null;
        $conversationMessages = $validated['conversation_messages'] ?? [];

        $lastRequestedField = $previousStructuredData['last_requested_field'] ?? null;

        $mergedStructuredData = $previousStructuredData ?? [
            'item_type' => null,
            'category' => null,
            'color' => null,
            'brand' => null,
            'location' => null,
            'lost_time' => null,
            'keywords' => [],
            'attributes' => [],
            'skipped_fields' => [],
            'last_requested_field' => null,
            'needs_followup' => true,
            'followup_question' => null,
        ];

        $aiStructuredData = $this->githubModelsService->extractStructuredData($message, $conversationMessages);
        $ruleStructuredData = $this->extractDetailsFromMessageRules($message);

        $mergedStructuredData = $this->mergeStructuredData(
            $mergedStructuredData,
            $aiStructuredData
        );

        $mergedStructuredData = $this->mergeStructuredData(
            $mergedStructuredData,
            $ruleStructuredData
        );

        $mergedStructuredData = $this->applyDirectAnswerToRequestedField(
            $message,
            $mergedStructuredData,
            $lastRequestedField
        );

        if ($lastRequestedField !== null && $this->isSkipAnswerForField($message, $lastRequestedField)) {
            $skippedFields = $mergedStructuredData['skipped_fields'] ?? [];

            if (!in_array($lastRequestedField, $skippedFields, true)) {
                $skippedFields[] = $lastRequestedField;
            }

            $mergedStructuredData['skipped_fields'] = $skippedFields;
        }

        if ($lastRequestedField === 'attributes' && $this->isNegativeFeatureAnswer($message)) {
            $skippedFields = $mergedStructuredData['skipped_fields'] ?? [];

            if (!in_array('attributes', $skippedFields, true)) {
                $skippedFields[] = 'attributes';
            }

            $mergedStructuredData['skipped_fields'] = $skippedFields;
        }

        $mergedStructuredData = $this->normalizeStructuredData($mergedStructuredData, $message);

        $nextQuestion = $this->determineNextQuestion($mergedStructuredData);

        if ($nextQuestion !== null) {
            $mergedStructuredData['needs_followup'] = true;
            $mergedStructuredData['followup_question'] = $nextQuestion['question'];
            $mergedStructuredData['last_requested_field'] = $nextQuestion['field'];

            $matches = [];
            $assistantReply = $nextQuestion['question'];
        } else {
            $mergedStructuredData['needs_followup'] = false;
            $mergedStructuredData['followup_question'] = null;
            $mergedStructuredData['last_requested_field'] = null;

            $matches = $this->findMatches($mergedStructuredData);
            $assistantReply = $this->buildAssistantReply($message, $mergedStructuredData, $matches);
        }

        $conversationMessages[] = [
            'role' => 'assistant',
            'content' => $assistantReply,
        ];

        return response()->json([
            'structured_data' => $mergedStructuredData,
            'matches' => $matches,
            'assistant_reply' => $assistantReply,
            'conversation_messages' => $conversationMessages,
        ]);
    }

    /**
     * Merges old and new structured data.
     *
     * @param array<string, mixed>|null $previous previous structured data
     * @param array<string, mixed> $current current structured data
     * @return array<string, mixed>
     */
    private function mergeStructuredData(?array $previous, array $current): array
    {
        $base = $previous ?? [
            'item_type' => null,
            'category' => null,
            'color' => null,
            'brand' => null,
            'location' => null,
            'lost_time' => null,
            'keywords' => [],
            'attributes' => [],
            'skipped_fields' => [],
            'last_requested_field' => null,
            'needs_followup' => true,
            'followup_question' => null,
        ];

        $base['keywords'] = $base['keywords'] ?? [];
        $base['attributes'] = $base['attributes'] ?? [];
        $base['skipped_fields'] = $base['skipped_fields'] ?? [];

        foreach (['item_type', 'category', 'color', 'brand', 'location', 'lost_time'] as $field) {
            if (!empty($current[$field])) {
                $base[$field] = $current[$field];
            }
        }

        $base['keywords'] = array_values(array_unique(array_filter(array_merge(
            $base['keywords'],
            $current['keywords'] ?? []
        ))));

        $base['attributes'] = array_values(array_unique(array_filter(array_merge(
            $base['attributes'],
            $current['attributes'] ?? []
        ))));

        return $base;
    }

    /**
     * Detects whether the user is saying they do not know or remember.
     *
     * @param string $message the user message
     * @return bool
     */
    private function isUnknownAnswer(string $message): bool
    {
        $normalized = Str::lower(trim($message));

        $unknownPhrases = [
            "i don't know",
            'i dont know',
            "don't know",
            'dont know',
            "i do not know",
            "i don't remember",
            'i dont remember',
            "don't remember",
            'dont remember',
            'not sure',
            'no idea',
            'unknown',
            'idk',
            'nope',
            'nah',
        ];

        foreach ($unknownPhrases as $phrase) {
            if ($normalized === $phrase || Str::contains($normalized, $phrase)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detects whether the user wants to skip the currently requested field.
     *
     * @param string $message the user message
     * @param string|null $lastRequestedField the last requested field
     * @return bool
     */
    private function isSkipAnswerForField(string $message, ?string $lastRequestedField): bool
    {
        $normalized = Str::lower(trim($message));

        if ($this->isUnknownAnswer($message)) {
            return true;
        }

        $optionalFields = ['brand', 'lost_time', 'location'];

        if (
            in_array($lastRequestedField, $optionalFields, true) &&
            in_array($normalized, ['no', 'n', 'none'], true)
        ) {
            return true;
        }

        return false;
    }
    
    /**
     * Detects whether the user is saying there is no extra feature.
     *
     * @param string $message the user message
     * @return bool
     */
    private function isNegativeFeatureAnswer(string $message): bool
    {
        $normalized = Str::lower(trim($message));

        $negativePhrases = [
            'no',
            'none',
            'nothing',
            'nope',
            'not really',
            'no feature',
            'no special feature',
            'nothing special',
        ];

        foreach ($negativePhrases as $phrase) {
            if ($normalized === $phrase || Str::contains($normalized, $phrase)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Applies a direct short answer to the last requested field when possible.
     *
     * @param string $message the user message
     * @param array<string, mixed> $structuredData merged structured data
     * @param string|null $lastRequestedField last requested field
     * @return array<string, mixed>
     */
    private function applyDirectAnswerToRequestedField(
        string $message,
        array $structuredData,
        ?string $lastRequestedField
    ): array {
        if ($lastRequestedField === null) {
            return $structuredData;
        }

        $rawValue = trim($message);
        $value = Str::lower($rawValue);

        if ($value === '') {
            return $structuredData;
        }

        if (
            $this->isSkipAnswerForField($message, $lastRequestedField) ||
            ($lastRequestedField === 'attributes' && $this->isNegativeFeatureAnswer($message))
        ) {
            return $structuredData;
        }

        if ($lastRequestedField === 'lost_time' && empty($structuredData['lost_time'])) {
            if (
                Str::contains($value, 'morning') ||
                preg_match('/\b(6|7|8|9|10|11)\s*am\b/', $value)
            ) {
                $structuredData['lost_time'] = 'Morning';
                return $structuredData;
            }

            if (
                Str::contains($value, 'afternoon') ||
                preg_match('/\b(12|1|2|3|4)\s*pm\b/', $value)
            ) {
                $structuredData['lost_time'] = 'Afternoon';
                return $structuredData;
            }

            if (
                Str::contains($value, 'evening') ||
                preg_match('/\b(5|6|7|8)\s*pm\b/', $value)
            ) {
                $structuredData['lost_time'] = 'Evening';
                return $structuredData;
            }

            if (
                Str::contains($value, 'night') ||
                preg_match('/\b(9|10|11)\s*pm\b/', $value)
            ) {
                $structuredData['lost_time'] = 'Night';
                return $structuredData;
            }

            $structuredData['lost_time'] = ucfirst($rawValue);
            return $structuredData;
        }

        if ($lastRequestedField === 'color' && empty($structuredData['color'])) {
            $structuredData['color'] = ucfirst($rawValue);
            return $structuredData;
        }

        if ($lastRequestedField === 'location' && empty($structuredData['location'])) {
            $structuredData['location'] = strtoupper($rawValue);
            return $structuredData;
        }

        if ($lastRequestedField === 'brand' && empty($structuredData['brand'])) {
            $structuredData['brand'] = ucfirst($rawValue);
            $structuredData['keywords'][] = $value;
            $structuredData['keywords'] = array_values(array_unique($structuredData['keywords']));
            return $structuredData;
        }

        if ($lastRequestedField === 'category' && empty($structuredData['category'])) {
            $structuredData['keywords'][] = $value;
            $structuredData['keywords'] = array_values(array_unique($structuredData['keywords']));
            $structuredData['category'] = ucfirst($rawValue);
            return $structuredData;
        }

        if ($lastRequestedField === 'attributes') {
            $structuredData['attributes'][] = $rawValue;
            $structuredData['attributes'] = array_values(
                array_unique(array_filter($structuredData['attributes']))
            );
            return $structuredData;
        }

        return $structuredData;
    }

    /**
     * Extracts simple lost-item details from a free-form message using rules.
     *
     * @param string $message the user message
     * @return array<string, mixed>
     */
    private function extractDetailsFromMessageRules(string $message): array
    {
        $text = Str::lower(trim($message));

        $data = [
            'category' => null,
            'color' => null,
            'location' => null,
            'brand' => null,
            'lost_time' => null,
            'attributes' => [],
            'keywords' => [],
        ];

        $categories = [
            'wallet', 'phone', 'backpack', 'bag', 'laptop',
            'tablet', 'airpods', 'keys', 'key', 'card', 'bottle',
        ];

        $colors = [
            'black', 'white', 'red', 'blue', 'green',
            'pink', 'gray', 'grey', 'silver', 'gold', 'brown',
        ];

        foreach ($categories as $category) {
            if (preg_match('/\b' . preg_quote($category, '/') . '\b/', $text)) {
                $data['category'] = ucfirst($category);
                $data['keywords'][] = $category;
                break;
            }
        }

        foreach ($colors as $color) {
            if (preg_match('/\b' . preg_quote($color, '/') . '\b/', $text)) {
                $data['color'] = ucfirst($color);
                $data['keywords'][] = $color;
                break;
            }
        }

        if (
            preg_match('/\b(?:at|near|in)\s+the\s+([a-z][a-z0-9\s-]{1,40})/i', $text, $matches) ||
            preg_match('/\b(?:at|near|in)\s+([a-z][a-z0-9\s-]{1,40})/i', $text, $matches)
        ) {
            $location = trim($matches[1]);
            $location = preg_replace(
                '/\b(this|today|yesterday|morning|moring|afternoon|evening|night)\b.*$/i',
                '',
                $location
            );
            $location = trim((string) $location);

            if ($location !== '') {
                $data['location'] = ucwords($location);
                $data['keywords'][] = Str::lower($location);
            }
        }

        if (
            Str::contains($text, 'this morning') ||
            Str::contains($text, 'in the morning') ||
            Str::contains($text, 'today morning') ||
            Str::contains($text, 'this moring') ||
            preg_match('/\bmorning\b/i', $text)
        ) {
            $data['lost_time'] = 'Morning';
        } elseif (
            Str::contains($text, 'this afternoon') ||
            Str::contains($text, 'in the afternoon') ||
            preg_match('/\bafternoon\b/i', $text)
        ) {
            $data['lost_time'] = 'Afternoon';
        } elseif (
            Str::contains($text, 'this evening') ||
            Str::contains($text, 'in the evening') ||
            preg_match('/\bevening\b/i', $text)
        ) {
            $data['lost_time'] = 'Evening';
        } elseif (
            Str::contains($text, 'tonight') ||
            Str::contains($text, 'at night') ||
            preg_match('/\bnight\b/i', $text)
        ) {
            $data['lost_time'] = 'Night';
        } elseif (preg_match('/\b(6|7|8|9|10|11)\s*am\b/i', $text)) {
            $data['lost_time'] = 'Morning';
        } elseif (preg_match('/\b(12|1|2|3|4)\s*pm\b/i', $text)) {
            $data['lost_time'] = 'Afternoon';
        } elseif (preg_match('/\b(5|6|7|8)\s*pm\b/i', $text)) {
            $data['lost_time'] = 'Evening';
        } elseif (preg_match('/\b(9|10|11)\s*pm\b/i', $text)) {
            $data['lost_time'] = 'Night';
        }

        $data['keywords'] = array_values(array_unique(array_filter($data['keywords'])));

        return $data;
    }

    /**
     * Normalizes structured data to fit the application's item categories.
     *
     * @param array<string, mixed> $structuredData extracted search data
     * @return array<string, mixed>
     */
    private function normalizeStructuredData(array $structuredData, string $rawMessage = ''): array
    {
        $category = Str::lower((string) ($structuredData['category'] ?? ''));
        $itemType = Str::lower((string) ($structuredData['item_type'] ?? ''));
        $brand = Str::lower((string) ($structuredData['brand'] ?? ''));
        $location = Str::lower((string) ($structuredData['location'] ?? ''));
        $raw = Str::lower($rawMessage);

        $keywords = collect($structuredData['keywords'] ?? [])
            ->map(fn (mixed $keyword): string => Str::lower((string) $keyword))
            ->values()
            ->all();

        $attributes = collect($structuredData['attributes'] ?? [])
            ->map(fn (mixed $attribute): string => Str::lower((string) $attribute))
            ->values()
            ->all();

        $allSignals = array_filter(array_merge(
            [$category, $itemType, $brand, $location, $raw],
            $keywords,
            $attributes
        ));

        foreach ($allSignals as $signal) {
            if (
                Str::contains($signal, 'iphone') ||
                Str::contains($signal, 'phone') ||
                Str::contains($signal, 'cellphone') ||
                Str::contains($signal, 'cell phone') ||
                Str::contains($signal, 'mobile')
            ) {
                $structuredData['category'] = 'Phone';
                $structuredData['item_type'] = 'phone';
                break;
            }

            if (
                Str::contains($signal, 'airpods') ||
                Str::contains($signal, 'earbuds') ||
                Str::contains($signal, 'ear buds')
            ) {
                $structuredData['category'] = 'Earbuds';
                $structuredData['item_type'] = 'earbuds';
                break;
            }

            if (
                Str::contains($signal, 'headphone') ||
                Str::contains($signal, 'headset') ||
                Str::contains($signal, 'head set')
            ) {
                $structuredData['category'] = 'Headphones';
                $structuredData['item_type'] = 'headphones';
                break;
            }

            if (
                Str::contains($signal, 'wallet') ||
                Str::contains($signal, 'card holder') ||
                Str::contains($signal, 'purse')
            ) {
                $structuredData['category'] = 'Wallet';
                $structuredData['item_type'] = 'wallet';
                break;
            }

            if (
                Str::contains($signal, 'backpack') ||
                Str::contains($signal, 'school bag') ||
                Str::contains($signal, 'bag')
            ) {
                $structuredData['category'] = 'Backpack';
                $structuredData['item_type'] = 'backpack';
                break;
            }

            if (
                Str::contains($signal, 'keychain') ||
                Str::contains($signal, 'keys') ||
                Str::contains($signal, 'key')
            ) {
                $structuredData['category'] = 'Keys';
                $structuredData['item_type'] = 'keys';
                break;
            }

            if (
                Str::contains($signal, 'water bottle') ||
                Str::contains($signal, 'hydro flask') ||
                Str::contains($signal, 'flask') ||
                Str::contains($signal, 'bottle')
            ) {
                $structuredData['category'] = 'Bottle';
                $structuredData['item_type'] = 'bottle';
                break;
            }

            if (
                Str::contains($signal, 'student card') ||
                Str::contains($signal, 'id card') ||
                Str::contains($signal, 'bcit card') ||
                Str::contains($signal, 'identification') ||
                $signal === 'id' ||
                Str::contains($signal, 'card')
            ) {
                $structuredData['category'] = 'ID';
                $structuredData['item_type'] = 'id';
                break;
            }

            if (
                Str::contains($signal, 'macbook') ||
                Str::contains($signal, 'notebook computer') ||
                Str::contains($signal, 'laptop')
            ) {
                $structuredData['category'] = 'Laptop';
                $structuredData['item_type'] = 'laptop';
                break;
            }
        }

        if (empty($structuredData['lost_time'])) {
            if (Str::contains($raw, 'morning')) {
                $structuredData['lost_time'] = 'Morning';
            } elseif (Str::contains($raw, 'afternoon')) {
                $structuredData['lost_time'] = 'Afternoon';
            } elseif (Str::contains($raw, 'evening')) {
                $structuredData['lost_time'] = 'Evening';
            } elseif (Str::contains($raw, 'night')) {
                $structuredData['lost_time'] = 'Night';
            }
        }

        return $structuredData;
    }
    /**
     * Determines the next follow-up question and target field.
     *
     * @param array<string, mixed> $structuredData search data
     * @return array<string, string>|null
     */
    private function determineNextQuestion(array $structuredData): ?array
    {
        $skipped = $structuredData['skipped_fields'] ?? [];

        if (
            empty($structuredData['category']) &&
            empty($structuredData['item_type']) &&
            !in_array('category', $skipped, true)
        ) {
            return [
                'field' => 'category',
                'question' => 'What kind of item did you lose?',
            ];
        }

        if (
            empty($structuredData['location']) &&
            !in_array('location', $skipped, true)
        ) {
            return [
                'field' => 'location',
                'question' => 'Where do you think you lost it?',
            ];
        }

        if (
            empty($structuredData['lost_time']) &&
            !in_array('lost_time', $skipped, true)
        ) {
            return [
                'field' => 'lost_time',
                'question' => 'Do you remember around what time you lost it? For example, morning, afternoon, evening, or night.',
            ];
        }

        if (
            empty($structuredData['color']) &&
            !in_array('color', $skipped, true)
        ) {
            return [
                'field' => 'color',
                'question' => 'What color is it?',
            ];
        }

        if (
            empty($structuredData['brand']) &&
            !in_array('brand', $skipped, true)
        ) {
            return [
                'field' => 'brand',
                'question' => 'Do you know the brand?',
            ];
        }

        if (
            count($structuredData['attributes'] ?? []) === 0 &&
            !in_array('attributes', $skipped, true)
        ) {
            return [
                'field' => 'attributes',
                'question' => 'Does it have any unique feature, such as a case, sticker, keychain, or scratch?',
            ];
        }

        return null;
    }

    /**
     * Finds matching items from the database.
     *
     * @param array<string, mixed> $structuredData extracted search data
     * @return array<int, array<string, mixed>>
     */
    private function findMatches(array $structuredData): array
    {
        $items = Item::query()
            ->where('status', 'active')
            ->get();

        $scored = $items->map(function (Item $item) use ($structuredData): array {
            $score = $this->calculateScore($item, $structuredData);

            return [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'category' => $item->category,
                'color' => $item->color,
                'brand' => $item->brand,
                'location' => $item->location,
                'finder_id' => null,
                'owner_id' => null,
                'found_at' => $item->found_at,
                'image_url' => $item->image_url,
                'similarity_score' => $score,
            ];
        });

        return $scored
            ->filter(fn (array $item): bool => $item['similarity_score'] > 0)
            ->sortByDesc('similarity_score')
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * Calculates a similarity score for an item.
     *
     * Category must match exactly.
     *
     * @param Item $item the item being scored
     * @param array<string, mixed> $structuredData extracted search data
     * @return int
     */
    private function calculateScore(Item $item, array $structuredData): int
    {
        $itemName = Str::lower($item->name ?? '');
        $itemDescription = Str::lower($item->description ?? '');
        $itemCategory = Str::lower($item->category ?? '');
        $itemColor = Str::lower($item->color ?? '');
        $itemBrand = Str::lower($item->brand ?? '');
        $itemLocation = Str::lower($item->location ?? '');

        $category = Str::lower((string) ($structuredData['category'] ?? ''));
        $color = Str::lower((string) ($structuredData['color'] ?? ''));
        $brand = Str::lower((string) ($structuredData['brand'] ?? ''));
        $location = Str::lower((string) ($structuredData['location'] ?? ''));
        $lostTime = Str::lower((string) ($structuredData['lost_time'] ?? ''));

        $keywords = collect($structuredData['keywords'] ?? [])
            ->map(fn (mixed $keyword): string => Str::lower((string) $keyword))
            ->filter()
            ->values();

        $attributes = collect($structuredData['attributes'] ?? [])
            ->map(fn (mixed $attribute): string => Str::lower((string) $attribute))
            ->filter()
            ->values();

        if ($category === '' || $itemCategory !== $category) {
            return 0;
        }

        $score = 50;

        if ($location !== '') {
            if ($itemLocation === $location) {
                $score += 20;
            } elseif (
                Str::contains($itemLocation, $location) ||
                Str::contains($location, $itemLocation)
            ) {
                $score += 10;
            }
        }

        if ($lostTime !== '' && !empty($item->found_at)) {
            $foundHour = (int) date('G', strtotime((string) $item->found_at));
            $foundTimeOfDay = '';

            if ($foundHour < 12) {
                $foundTimeOfDay = 'morning';
            } elseif ($foundHour < 17) {
                $foundTimeOfDay = 'afternoon';
            } elseif ($foundHour < 21) {
                $foundTimeOfDay = 'evening';
            } else {
                $foundTimeOfDay = 'night';
            }

            if ($foundTimeOfDay === $lostTime) {
                $score += 8;
            }
        }

        if ($color !== '' && $itemColor !== '' && $itemColor === $color) {
            $score += 10;
        }

        if ($brand !== '' && $itemBrand !== '' && $itemBrand === $brand) {
            $score += 10;
        }

        foreach ($keywords as $keyword) {
            if (
                Str::contains($itemName, $keyword) ||
                Str::contains($itemDescription, $keyword)
            ) {
                $score += 3;
            }
        }

        foreach ($attributes as $attribute) {
            if (Str::contains($itemDescription, $attribute)) {
                $score += 4;
            }
        }

        return min($score, 100);
    }

    /**
     * Builds the assistant reply shown to the user.
     *
     * @param string $userMessage the user message
     * @param array<string, mixed> $structuredData extracted search data
     * @param array<int, array<string, mixed>> $matches match results
     * @return string
     */
    private function buildAssistantReply(string $userMessage, array $structuredData, array $matches): string
    {
        if (count($matches) === 0) {
            return 'I could not find a strong match with the details provided. You can try adding more details if you remember them later.';
        }

        $detailsText = $this->formatSearchDetails($structuredData);
        $matchCount = count($matches);
        $matchText = $matchCount === 1 ? 'match' : 'matches';

        $summary = "I found {$matchCount} possible {$matchText} based on your description: {$detailsText}\n\nHere are the items:";

        foreach ($matches as $idx => $match) {
            $matchNumber = $idx + 1;
            $score = round($match['similarity_score'], 0);
            $summary .= "\n\n#{$matchNumber} - {$match['name']} (Category: {$match['category']}) - {$score}% match";
            if (!empty($match['location'])) {
                $summary .= " (Found at: {$match['location']})";
            }
            if (!empty($match['found_at'])) {
                $foundTime = date('M d, Y', strtotime($match['found_at']));
                $summary .= " on {$foundTime}";
            }
        }

        $summary .= "\n\nOnly general public details are shown. Click on items to see full information.";

        return $summary;
    }

    /**
     * Formats the structured search details into a readable string.
     *
     * @param array<string, mixed> $structuredData extracted search data
     * @return string
     */
    private function formatSearchDetails(array $structuredData): string
    {
        $details = [];

        if (!empty($structuredData['category'])) {
            $details[] = "a {$structuredData['category']}";
        }

        if (!empty($structuredData['color'])) {
            $details[] = $structuredData['color'];
        }

        if (!empty($structuredData['lost_time'])) {
            $details[] = "lost in the {$structuredData['lost_time']}";
        }

        if (!empty($structuredData['location'])) {
            $details[] = "at {$structuredData['location']}";
        }

        if (!empty($structuredData['brand'])) {
            $details[] = "{$structuredData['brand']} brand";
        }

        if (!empty($structuredData['attributes']) && is_array($structuredData['attributes']) && count($structuredData['attributes']) > 0) {
            $attrs = implode(', ', $structuredData['attributes']);
            $details[] = "with features: {$attrs}";
        }

        return implode(', ', $details) ?: 'your item';
    }

    /**
     * Searches for items by image similarity.
     *
     * @param Request $request the HTTP request with image file
     * @return JsonResponse
     */
    public function searchByImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|max:10240',
            'category' => 'required|string|max:255',
        ]);

        try {
            $category = $request->string('category')->value();

            // Get all items with images matching the category
            $items = Item::where('image_url', '!=', null)
                ->where('status', 'active')
                ->where('category', $category)
                ->with(['finder', 'owner'])
                ->get();

            if ($items->isEmpty()) {
                return response()->json([
                    'structured_data' => null,
                    'matches' => [],
                    'assistant_reply' => 'No items found in the database. Please describe your lost item instead.',
                    'conversation_messages' => [],
                ]);
            }

            // Extract dominant colors from uploaded image
            $uploadedImageMimeType = $request->file('image')->getMimeType();
            $uploadedImagePath = $request->file('image')->getRealPath();
            
            // Get colors from uploaded image (simplified approach)
            $uploadedColors = $this->extractDominantColors($uploadedImagePath);

            // Score each item based on color similarity
            $scoredItems = [];
            foreach ($items as $item) {
                // Extract colors from item image URL
                // For simplicity, we'll use a basic matching based on item attributes
                $score = $this->calculateImageSimilarity($uploadedColors, $item);
                
                if ($score >= 60) {
                    $scoredItems[] = [
                        'item' => $item,
                        'score' => $score,
                    ];
                }
            }

            // Sort by score descending
            usort($scoredItems, fn($a, $b) => $b['score'] <=> $a['score']);

            // Get top 5 matches
            $topMatches = array_slice($scoredItems, 0, 5);
            $matches = array_map(fn($match) => $match['item'], $topMatches);

            $matchCount = count($matches);
            $assistantReply = $matchCount > 0
                ? "Found {$matchCount} possible match" . ($matchCount !== 1 ? 'es' : '') . " based on image analysis."
                : 'No similar items found. Please describe your lost item in more detail.';

            return response()->json([
                'structured_data' => [
                    'item_type' => null,
                    'category' => null,
                    'color' => null,
                    'brand' => null,
                    'location' => null,
                    'lost_time' => null,
                    'keywords' => [],
                    'attributes' => [],
                    'skipped_fields' => [],
                    'last_requested_field' => null,
                    'needs_followup' => false,
                    'followup_question' => null,
                ],
                'matches' => $matches,
                'assistant_reply' => $assistantReply,
                'conversation_messages' => [],
            ]);
        } catch (\Exception $e) {
            \Log::error('Image search failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Image search failed.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Extracts dominant colors from an image.
     *
     * @param string $imagePath path to the image file
     * @return array<string> array of dominant color hex values
     */
    private function extractDominantColors(string $imagePath): array
    {
        // Simplified color extraction
        // In production, you might use a library like imagine or opencv
        $colors = [];
        
        if (function_exists('imagecreatefromjpeg') || function_exists('imagecreatefrompng')) {
            try {
                $image = $this->createImageFromFile($imagePath);
                if ($image) {
                    // Sample 10 random pixels to estimate color palette
                    $width = imagesx($image);
                    $height = imagesy($image);
                    
                    for ($i = 0; $i < 10; $i++) {
                        $x = rand(0, $width - 1);
                        $y = rand(0, $height - 1);
                        $rgb = imagecolorat($image, $x, $y);
                        $colors[] = $this->rgbToHex($rgb);
                    }
                    
                    imagedestroy($image);
                }
            } catch (\Exception) {
                // Fallback: return empty colors
            }
        }
        
        return $colors ?: ['#808080']; // Default gray if extraction fails
    }

    /**
     * Creates an image resource from file.
     *
     * @param string $filePath path to image file
     * @return \GdImage|false
     */
    private function createImageFromFile(string $filePath)
    {
        $mimeType = mime_content_type($filePath);
        
        if ($mimeType === 'image/jpeg') {
            return imagecreatefromjpeg($filePath);
        } elseif ($mimeType === 'image/png') {
            return imagecreatefrompng($filePath);
        } elseif ($mimeType === 'image/gif') {
            return imagecreatefromgif($filePath);
        } elseif ($mimeType === 'image/webp') {
            return imagecreatefromwebp($filePath);
        }
        
        return false;
    }

    /**
     * Converts RGB color value to hex string.
     *
     * @param int $rgb RGB color value
     * @return string hex color string
     */
    private function rgbToHex(int $rgb): string
    {
        return '#' . str_pad(dechex($rgb), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Calculates similarity score between uploaded image colors and an item.
     *
     * @param array<string> $uploadedColors dominant colors from uploaded image
     * @param Item $item the item to score
     * @return int similarity score (0-100)
     */
    private function calculateImageSimilarity(array $uploadedColors, Item $item): int
    {
        $score = 0;
        
        // Base score if item has image and is active
        if (!$item->image_url || $item->status !== 'active') {
            return 0; // No match if no image or not active
        }
        
        $score = 60; // Base score for matching category + active status + has image
        
        // Color similarity matching (40 points max for color)
        if (!empty($uploadedColors) && $uploadedColors[0] !== '#808080') {
            $itemColor = $item->color;
            
            if ($itemColor) {
                // Try to match with item's recorded color
                $colorScore = $this->getColorSimilarityScore($uploadedColors[0], $itemColor);
                $score += min($colorScore / 2, 40); // Max 40 points from color similarity
            }
        }
        
        return min($score, 100); // Cap at 100
    }

    /**
     * Gets similarity score for two colors (0-100, higher is more similar).
     *
     * @param string $hex1 hex color (e.g., "#FF0000")
     * @param string $colorName color name or description
     * @return int similarity score
     */
    private function getColorSimilarityScore(string $hex1, string $colorName): int
    {
        $colorName = strtolower(trim($colorName));
        $rgb1 = $this->hexToRgb($hex1);
        
        // Map common color names to RGB ranges
        $commonColors = [
            'black' => ['r' => 0, 'g' => 0, 'b' => 0],
            'white' => ['r' => 255, 'g' => 255, 'b' => 255],
            'red' => ['r' => 255, 'g' => 0, 'b' => 0],
            'green' => ['r' => 0, 'g' => 128, 'b' => 0],
            'blue' => ['r' => 0, 'g' => 0, 'b' => 255],
            'yellow' => ['r' => 255, 'g' => 255, 'b' => 0],
            'gray' => ['r' => 128, 'g' => 128, 'b' => 128],
            'grey' => ['r' => 128, 'g' => 128, 'b' => 128],
            'brown' => ['r' => 165, 'g' => 42, 'b' => 42],
            'orange' => ['r' => 255, 'g' => 165, 'b' => 0],
            'purple' => ['r' => 128, 'g' => 0, 'b' => 128],
            'pink' => ['r' => 255, 'g' => 192, 'b' => 203],
            'silver' => ['r' => 192, 'g' => 192, 'b' => 192],
            'gold' => ['r' => 255, 'g' => 215, 'b' => 0],
        ];
        
        // Check if color name contains any known color
        foreach ($commonColors as $colorKey => $colorRgb) {
            if (strpos($colorName, $colorKey) !== false) {
                return $this->calculateColorDistance($rgb1, $colorRgb);
            }
        }
        
        // If no color name match, return moderate score
        return 50;
    }

    /**
     * Calculates Euclidean distance between two RGB colors.
     *
     * @param array<string, int> $rgb1 RGB color 1
     * @param array<string, int> $rgb2 RGB color 2
     * @return int distance score (0-100, higher = more similar)
     */
    private function calculateColorDistance(array $rgb1, array $rgb2): int
    {
        $distance = sqrt(
            pow($rgb1['r'] - $rgb2['r'], 2) +
            pow($rgb1['g'] - $rgb2['g'], 2) +
            pow($rgb1['b'] - $rgb2['b'], 2)
        );
        
        // Max distance is sqrt(3 * 255^2) ≈ 441
        // Convert to 0-100 scale (higher = more similar)
        $similarity = max(0, 100 - ($distance / 441 * 100));
        
        return (int) $similarity;
    }

    /**
     * Converts hex color to RGB array.
     *
     * @param string $hex hex color string
     * @return array<string, int>
     */
    private function hexToRgb(string $hex): array
    {
        $hex = str_replace('#', '', $hex);
        
        return [
            'r' => (int) hexdec(substr($hex, 0, 2)),
            'g' => (int) hexdec(substr($hex, 2, 2)),
            'b' => (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}