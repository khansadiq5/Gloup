@extends('layouts.app', ['title' => 'Register Your Salon — GLOUP'])

@section('content')
<div class="max-w-md mx-auto my-12 bg-white p-8 border border-gray-200 rounded-lg shadow-sm">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">Register Your Salon</h1>
    <p class="text-sm text-gray-600 mb-6">Create your business account and first salon profile on GLOUP.</p>

    @if ($errors->any())
        <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded">
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('register.owner.submit') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700">Salon / Business Name</label>
            <input type="text" name="salon_name" value="{{ old('salon_name') }}" required
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Salon URL Slug (optional)</label>
            <div class="mt-1 flex rounded-md shadow-sm">
                <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">salon/</span>
                <input type="text" name="salon_slug" value="{{ old('salon_slug') }}" placeholder="my-salon"
                       class="flex-1 block w-full px-3 py-2 border border-gray-300 rounded-none rounded-r-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>
        </div>

        <div class="border-t border-gray-200 pt-4">
            <label class="block text-sm font-medium text-gray-700">Owner Name</label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Email Address</label>
            <input type="email" name="email" value="{{ old('email') }}" required
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Phone Number (optional)</label>
            <input type="text" name="phone" value="{{ old('phone') }}"
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Password</label>
            <input type="password" name="password" required
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Confirm Password</label>
            <input type="password" name="password_confirmation" required
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
        </div>

        <button type="submit"
                class="w-full mt-2 flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            Create Salon & Account
        </button>
    </form>
</div>
@endsection
