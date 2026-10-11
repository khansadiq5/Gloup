@extends('layouts.app', ['title' => $tenant->name . ' — Dashboard'])

@section('content')
<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <div class="md:flex md:items-center md:justify-between border-b border-gray-200 pb-6 mb-6">
        <div class="flex-1 min-w-0">
            <h1 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                {{ $tenant->name }}
            </h1>
            <div class="mt-1 flex flex-col sm:flex-row sm:flex-wrap sm:mt-0 sm:space-x-6">
                <div class="mt-2 flex items-center text-sm text-gray-500">
                    <span class="font-medium text-gray-900 mr-1">Public URL:</span>
                    <a href="{{ url('salon/' . $tenant->slug) }}" class="text-indigo-600 hover:underline">
                        /salon/{{ $tenant->slug }}
                    </a>
                </div>
                <div class="mt-2 flex items-center text-sm text-gray-500">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        {{ ucfirst($tenant->status->value) }}
                    </span>
                </div>
                <div class="mt-2 flex items-center text-sm text-gray-500">
                    <span class="text-gray-700 font-medium">Your Role:</span>&nbsp;{{ $membership?->role?->label() ?? 'Member' }}
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Branches</h2>
            <p class="text-3xl font-bold text-indigo-600">1</p>
            <p class="text-xs text-gray-500 mt-1">Free plan limit: 1 branch</p>
        </div>

        <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Upcoming Appointments</h2>
            <p class="text-3xl font-bold text-gray-900">0</p>
            <p class="text-xs text-gray-500 mt-1">Ready for bookings</p>
        </div>

        <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Salon Customers</h2>
            <p class="text-3xl font-bold text-gray-900">{{ $tenant->customerProfiles()->count() }}</p>
            <p class="text-xs text-gray-500 mt-1">Tenant isolated</p>
        </div>
    </div>
</div>
@endsection
