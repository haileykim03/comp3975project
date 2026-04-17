<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Item Management') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                
                <h1 class="text-2xl font-bold mb-6 text-gray-800">All Items</h1>

                <form method="GET" action="{{ route('admin.items.index') }}" class="mb-6 flex gap-3 flex-wrap items-center">

                    <div>
                        <label class="sr-only" for="student_id">Search by Student ID</label>
                        <input
                            type="text"
                            id="student_id"
                            name="student_id"
                            value="{{ $selectedStudentId }}"
                            placeholder="Search by Student ID"
                            class="border rounded-lg px-3 py-2 text-sm"
                        >
                    </div>

                    <div>
                        <label class="sr-only" for="status">Filter by Status</label>
                        <select
                            id="status"
                            name="status"
                            class="border rounded-lg px-3 py-2 text-sm"
                        >
                            <option value="">All Statuses</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" {{ $selectedStatus == $status ? 'selected' : '' }}>
                                    {{ ucfirst($status) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button
                        type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm"
                    >
                        Filter
                    </button>

                    <a
                        href="{{ route('admin.items.index') }}"
                        class="bg-gray-300 hover:bg-gray-400 px-4 py-2 rounded-lg text-sm"
                    >
                        Reset
                    </a>
                </form>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="py-2 px-4 border-b text-left text-sm font-semibold text-gray-600">Category</th>
                                <th class="py-2 px-4 border-b text-left text-sm font-semibold text-gray-600">Location</th>
                                <th class="py-2 px-4 border-b text-left text-sm font-semibold text-gray-600">Finder ID</th>
                                <th class="py-2 px-4 border-b text-left text-sm font-semibold text-gray-600">Owner ID</th>
                                <th class="py-2 px-4 border-b text-left text-sm font-semibold text-gray-600">Status</th>
                                <th class="py-2 px-4 border-b text-center text-sm font-semibold text-gray-600">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-4 border-b text-sm text-gray-800">{{ $item->category }}</td>
                                <td class="py-3 px-4 border-b text-sm text-gray-600">{{ $item->location }}</td>
                                <td class="py-3 px-4 border-b text-sm text-gray-600">{{ $item->finder?->student_id ?? 'N/A' }}</td>
                                <td class="py-3 px-4 border-b text-sm text-gray-600">{{ $item->owner?->student_id ?? 'N/A' }}</td>
                                <td class="py-3 px-4 border-b text-sm font-bold">
                                    {{ ucfirst($item->status) }} </td>
                                <td class="py-3 px-4 border-b text-sm text-center">
                                    <button onclick="openModal({{ $item->id }})" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs transition">
                                        Detail
                                    </button>
                                </td>
                            </tr>

                            <div id="modal-{{ $item->id }}" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
                                <div class="bg-white rounded-lg shadow-xl p-6" style="width: 600px;">
                                    <h2 class="text-xl font-bold mb-4 border-b pb-2">Item Details (#{{ $item->id }})</h2>
                                    
                                    <div class="mb-6 space-y-2 text-sm text-gray-700">
                                        <p><strong>Name:</strong> {{ $item->name }}</p>
                                        <p><strong>Category:</strong> {{ $item->category }}</p>
                                        <p><strong>Location:</strong> {{ $item->location }}</p>
                                        <p><strong>Color:</strong> {{ $item->color ?? 'N/A' }}</p>
                                        <p><strong>Brand:</strong> {{ $item->brand ?? 'N/A' }}</p>
                                        <p><strong>Finder ID:</strong> {{ $item->finder?->student_id ?? 'N/A' }}</p>
                                        <p><strong>Owner ID:</strong> {{ $item->owner?->student_id ?? 'N/A' }}</p>
                                        <p><strong>Found At:</strong> {{ $item->found_at ? $item->found_at->format('Y-m-d H:i') : 'N/A' }}</p>
                                        <p><strong>Description:</strong> {{ $item->description ?? 'N/A' }}</p>
                                        <p><strong>Current Status:</strong> <span class="font-bold text-blue-600">{{ ucfirst($item->status) }}</span></p>
                                    </div>

                                    @if($item->image_url)
                                    <div class="mb-6 border-b pb-4">
                                        <p class="font-bold text-sm text-gray-700 mb-3">Item Image</p>
                                        <img 
                                            src="{{ $item->image_url }}" 
                                            alt="{{ $item->name }}" 
                                            class="max-h-64 rounded-lg border border-gray-300 object-cover"
                                        />
                                    </div>
                                    @endif

                                    @if(in_array(strtolower($item->status), ['pending', 'claim pending']))
                                    <form action="{{ route('admin.items.updateStatus', $item->id) }}" method="POST" class="mb-4">
                                        @csrf
                                        @method('PATCH')
                                        <div class="bg-gray-50 p-3 rounded-md border flex items-end space-x-2">
                                            <div class="flex-1">
                                                <label class="block text-xs font-medium text-gray-700 mb-1">Update Status</label>
                                                <select name="status" class="block w-full border-gray-300 rounded-md shadow-sm text-sm py-1">
                                                    
                                                    @if(strtolower($item->status) === 'pending')
                                                        <option value="active">Active</option>
                                                    
                                                    @elseif(strtolower($item->status) === 'claim pending')
                                                        <option value="active">Active</option>
                                                        <option value="returned">Returned</option>
                                                    @endif
                                                    
                                                </select>
                                            </div>
                                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded text-xs transition h-[34px]">
                                                Save
                                            </button>
                                        </div>
                                    </form>
                                    @endif

                                    <div class="text-right border-t pt-4">
                                        <button onclick="closeModal({{ $item->id }})" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded text-sm transition">
                                            Close
                                        </button>
                                    </div>
                                </div>
                            </div>
                            @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-gray-500">No items found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById('modal-' + id).classList.remove('hidden'); }
        function closeModal(id) { document.getElementById('modal-' + id).classList.add('hidden'); }
    </script>
</x-app-layout>