<x-guest-layout>
    <div class="mb-6 text-center">
        <h1 class="text-6xl font-bold text-red-500">404</h1>
        <h2 class="text-2xl font-semibold text-gray-700 mt-4">Page Not Found</h2>
        <p class="mt-2 text-lg text-gray-500">
            The page you're looking for doesn't exist in the BCIT Lost & Found Admin Portal.
        </p>
        <p class="mt-1 text-sm text-gray-400">Staff access only</p>
        <div class="mt-6">
            <a href="{{ route('login') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Go to Login
            </a>
        </div>
    </div>
</x-guest-layout>