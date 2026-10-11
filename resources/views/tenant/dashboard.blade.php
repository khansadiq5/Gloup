@extends('layouts.app')

@section('title', $tenant->name . ' - Dashboard')

@section('content')
<div class="min-h-screen bg-gray-50">
    <!-- Header with Branch Switcher -->
    <header class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-4">
                    <!-- Branch Switcher -->
                    <div x-data="branchSwitcher()" class="relative">
                        <button @click="open = !open" 
                                class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                aria-expanded="false" aria-haspopup="true">
                            <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <span>{{ $currentBranch?->name ?? 'Select Branch' }}</span>
                            <svg class="w-4 h-4" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        
                        <div x-show="open" x-transition:enter="transition ease-out duration-100" 
                             x-transition:enter-start="transform opacity-0 scale-95" 
                             x-transition:leave="transition ease-in duration-75" 
                             x-transition:leave-end="transform opacity-0 scale-95" 
                             @click.away="open = false" 
                             class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                            @foreach($branches as $branch)
                                <button wire:click="switchBranch({{ $branch->id }})" 
                                        class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 {{ $branch->id == $currentBranch?->id ? 'bg-primary-50 text-primary-700' : '' }}">
                                    <div class="flex items-center justify-between">
                                        <span>{{ $branch->name }}</span>
                                        @if($branch->is_primary)
                                            <span class="text-xs text-primary-600 font-medium">Primary</span>
                                        @endif
                                    </div>
                                    @if($branch->city)
                                        <div class="text-xs text-gray-500">{{ $branch->city }}, {{ $branch->state }}</div>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="hidden sm:block">
                        <h1 class="text-xl font-bold text-gray-900">{{ $tenant->name }}</h1>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="flex items-center gap-3">
                    <a href="{{ route('tenant.calendar.index', $tenant->slug) }}" 
                       class="px-4 py-2 bg-primary-600 text-white rounded-lg font-medium hover:bg-primary-700 transition">
                        View Calendar
                    </a>
                    <button @click="openWalkInModal = true" 
                            class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 transition">
                        + Walk-in
                    </button>
                </div>
            </div>
        </div>
    </header>

    <main class="flex-1">
        <div class="p-4 sm:p-6 lg:p-8">
            <!-- Stats Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Today's Appointments</p>
                            <p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['today_appointments'] }}</p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Pending</p>
                            <p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['pending_appointments'] }}</p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Customers</p>
                            <p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['total_customers'] }}</p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Active Staff</p>
                            <p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['active_staff'] }}</p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Today's Revenue</p>
                            <p class="text-3xl font-bold text-gray-900 mt-1">₹{{ number_format($stats['today_revenue']) }}</p>
                        </div>
                        <div class="w-12 h-12 bg-indigo-100 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Today's Appointments -->
                <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">Today's Appointments</h2>
                        <a href="{{ route('tenant.calendar.index', $tenant->slug) }}" class="text-sm text-primary-600 hover:underline">
                            View All
                        </a>
                    </div>
                    
                    <div class="divide-y divide-gray-200">
                        @if(empty($todayAppointments))
                            <div class="p-8 text-center text-gray-500">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <p class="mt-2 text-sm">No appointments today</p>
                            </div>
                        @else
                            @foreach($todayAppointments as $appt)
                                <div class="p-4 hover:bg-gray-50 flex items-center gap-4">
                                    <div class="w-16 text-center text-sm text-gray-500 font-medium">
                                        {{ $appt['time'] }} - {{ $appt['end_time'] }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-gray-900 truncate">{{ $appt['customer'] }}</p>
                                        <p class="text-sm text-gray-500 truncate">{{ $appt['services'] }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @if($appt['staff'])
                                            <span class="px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded">{{ $appt['staff'] }}</span>
                                        @endif
                                        <span class="px-2 py-1 text-xs rounded-full
                                            @if($appt['status'] === 'confirmed') bg-blue-100 text-blue-800
                                            @elseif($appt['status'] === 'pending') bg-yellow-100 text-yellow-800
                                            @elseif($appt['status'] === 'completed') bg-green-100 text-green-800
                                            @elseif($appt['status'] === 'cancelled') bg-red-100 text-red-800
                                            @elseif($appt['status'] === 'no_show') bg-gray-100 text-gray-800
                                            @else bg-gray-100 text-gray-800 @endif">
                                            {{ ucfirst(str_replace('_', ' ', $appt['status'])) }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>

                <!-- Upcoming & Staff On Duty -->
                <div class="space-y-6">
                    <!-- Upcoming Appointments -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-900">Upcoming (7 Days)</h2>
                            <a href="{{ route('tenant.calendar.list', $tenant->slug) }}" class="text-sm text-primary-600 hover:underline">
                                View All
                            </a>
                        </div>
                        
                        <div class="divide-y divide-gray-200">
                            @if(empty($upcomingAppointments))
                                <div class="p-6 text-center text-gray-500 text-sm">No upcoming appointments</div>
                            @else
                                @foreach($upcomingAppointments->take(10) as $appt)
                                    <div class="p-4 hover:bg-gray-50">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="font-medium text-gray-900">{{ $appt['customer'] }}</p>
                                                <p class="text-sm text-gray-500">{{ $appt['date'] }} at {{ $appt['time'] }}</p>
                                            </div>
                                            <span class="px-2 py-1 text-xs rounded-full
                                                @if($appt['status'] === 'confirmed') bg-blue-100 text-blue-800
                                                @elseif($appt['status'] === 'pending') bg-yellow-100 text-yellow-800
                                                @else bg-gray-100 text-gray-800 @endif">
                                                {{ ucfirst($appt['status']) }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>

                    <!-- Staff On Duty -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900">Staff On Duty Today</h2>
                        </div>
                        
                        <div class="divide-y divide-gray-200">
                            @if(empty($staffOnDuty))
                                <div class="p-6 text-center text-gray-500 text-sm">No staff scheduled today</div>
                            @else
                                @foreach($staffOnDuty as $staff)
                                    <div class="p-4 flex items-center justify-between">
                                        <div>
                                            <p class="font-medium text-gray-900">{{ $staff['name'] }}</p>
                                            <p class="text-sm text-gray-500">
                                                {{ $staff['shift_start'] }} - {{ $staff['shift_end'] }}
                                            </p>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Walk-in Modal -->
    <div x-data="{ openWalkInModal: false }" x-show="openWalkInModal" x-transition:enter="transition ease-out duration-100" 
         x-transition:enter-start="opacity-0" x-transition:leave="transition ease-in duration-75" 
         x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div @click="openWalkInModal = false" class="fixed inset-0 bg-black/50" aria-hidden="true"></div>
        <div class="relative w-full max-w-2xl bg-white rounded-xl shadow-xl max-h-[90vh] overflow-y-auto m-4">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">New Walk-in</h3>
                <button @click="openWalkInModal = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form @submit.prevent="submitWalkIn" class="p-6 space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Staff Member <span class="text-red-500">*</span></label>
                        <select x-model="form.staff_id" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <option value="">Select staff</option>
                            <template x-for="staff in staffList" :key="staff.id">
                                <option :value="staff.id" x-text="staff.name"></option>
                            </template>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                        <input type="datetime-local" x-model="form.starts_at" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Services <span class="text-red-500">*</span></label>
                    <div class="space-y-2" x-data="{ services: [{ id: '' }] }">
                        <template x-for="(service, index) in services" :key="index">
                            <div class="flex items-center gap-2">
                                <select x-model="service.id" required
                                        class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                    <option value="">Select service</option>
                                    <template x-for="svc in services" :key="svc.id">
                                        <option :value="svc.id" x-text="svc.name + ' - ₹' + svc.price + ' (' + (svc.duration + svc.buffer) + ' min)'"></option>
                                    </template>
                                </select>
                                <button type="button" @click="services.splice(index, 1)" 
                                        class="text-red-500 hover:text-red-700">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </template>
                        <button type="button" @click="services.push({ id: '' })" class="text-primary-600 hover:underline text-sm">
                            + Add Service
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Customer Name <span class="text-red-500">*</span></label>
                        <input type="text" x-model="form.customer_name" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone <span class="text-red-500">*</span></label>
                        <input type="tel" x-model="form.customer_phone" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                               placeholder="+91 98765 43210">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" x-model="form.customer_email"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea x-model="form.notes" rows="3"
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                    <button type="button" @click="openWalkInModal = false" 
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                        Cancel
                    </button>
                    <button type="submit" :disabled="submitting"
                            class="px-6 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 transition disabled:opacity-50">
                        <span x-show="!submitting">Create Walk-in</span>
                        <span x-show="submitting">Creating...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function branchSwitcher() {
            return {
                open: false,
                async switchBranch(branchId) {
                    try {
                        const response = await fetch(`{{ route('tenant.branch.switch', $tenant->slug) }}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({ branch_id: branchId }),
                        });
                        const data = await response.json();
                        if (data.success || data.branch) {
                            window.location.reload();
                        }
                    } catch (error) {
                        console.error('Failed to switch branch:', error);
                    }
                    this.open = false;
                }
            }
        }
    </script>
@endsection