<x-marketplace.layouts.app :title="'Booking Confirmed - GLOUP'">
    <div class="min-h-screen">
        <main class="flex-1 py-12">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Success Header -->
                <div class="text-center mb-12">
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-green-100 mb-6">
                        <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Appointment Confirmed!</h1>
                    <p class="text-gray-600 text-lg">Your booking has been successfully created.</p>
                </div>

                <!-- Appointment Details Card -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-8">
                    <div class="bg-gradient-to-r from-primary-600 to-pink-600 px-6 py-4">
                        <h2 class="text-xl font-semibold text-white">Appointment Details</h2>
                    </div>
                    <div class="p-6 space-y-6">
                        <!-- Salon & Branch -->
                        <div class="flex items-start gap-4 p-4 bg-gray-50 rounded-xl">
                            <div class="w-14 h-14 bg-primary-100 rounded-xl flex items-center justify-center shrink-0">
                                <svg class="w-7 h-7 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900">{{ $appointment->tenant->name }}</h3>
                                <p class="text-gray-600 text-sm">{{ $appointment->branch->name }}, {{ $appointment->branch->city }}</p>
                                @if($appointment->branch->address_line1)
                                    <p class="text-gray-500 text-xs mt-1">{{ $appointment->branch->address_line1 }}</p>
                                @endif
                            </div>
                        </div>

                        <!-- Date & Time -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="p-4 bg-gray-50 rounded-xl">
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Date</p>
                                <p class="text-lg font-semibold text-gray-900 mt-1">{{ \Carbon\Carbon::parse($appointment->starts_at)->format('l, F j, Y') }}</p>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-xl">
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Time</p>
                                <p class="text-lg font-semibold text-gray-900 mt-1">{{ \Carbon\Carbon::parse($appointment->starts_at)->format('g:i A') }} - {{ \Carbon\Carbon::parse($appointment->ends_at)->format('g:i A') }}</p>
                            </div>
                        </div>

                        <!-- Staff & Services -->
                        <div class="border-t border-gray-200 pt-6">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                                <div class="p-4 bg-gray-50 rounded-xl">
                                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Staff</p>
                                    <p class="text-lg font-semibold text-gray-900 mt-1">{{ $appointment->staff->name }}</p>
                                </div>
                                <div class="p-4 bg-gray-50 rounded-xl">
                                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Status</p>
                                    <p class="text-lg font-semibold text-gray-900 mt-1">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            @if($appointment->status === 'confirmed') bg-green-100 text-green-800
                                            @elseif($appointment->status === 'pending') bg-yellow-100 text-yellow-800
                                            @elseif($appointment->status === 'completed') bg-blue-100 text-blue-800
                                            @elseif($appointment->status === 'cancelled') bg-red-100 text-red-800
                                            @elseif($appointment->status === 'no_show') bg-gray-100 text-gray-800
                                            @else bg-gray-100 text-gray-800 @endif">
                                            {{ ucfirst(str_replace('_', ' ', $appointment->status)) }}
                                        </span>
                                    </p>
                                </div>
                            </div>

                            <h4 class="font-medium text-gray-900 mb-3">Services</h4>
                            <div class="space-y-2">
                                @foreach($appointment->services as $apptService)
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div>
                                            <p class="font-medium text-gray-900">{{ $apptService->service->name }}</p>
                                            <p class="text-sm text-gray-500">{{ $apptService->duration_minutes + $apptService->buffer_minutes }} min • ₹{{ number_format($apptService->price_inr) }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Total & Payment -->
                        <div class="border-t border-gray-200 pt-6">
                            <div class="flex justify-between items-center mb-4">
                                <span class="text-lg font-semibold text-gray-900">Total</span>
                                <span class="text-2xl font-bold text-primary-600">₹{{ number_format($appointment->total_price_inr) }}</span>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-xl">
                                <div class="flex items-center gap-3">
                                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                                    <div>
                                        <p class="font-medium text-gray-900">Payment Method</p>
                                        <p class="text-sm text-gray-600">
                                            @if($appointment->payment_status === 'pay_at_salon')
                                                Pay at Salon
                                            @elseif($appointment->payment_status === 'paid')
                                                Paid Online
                                            @elseif($appointment->payment_status === 'pending')
                                                Payment Pending
                                            @else
                                                {{ ucfirst(str_replace('_', ' ', $appointment->payment_status)) }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Management Token for Guests -->
                        @if($appointment->management_token)
                            <div class="border-t border-gray-200 pt-6">
                                <h4 class="font-medium text-gray-900 mb-3">Manage Your Booking</h4>
                                <p class="text-sm text-gray-600 mb-3">Save this secure link to view, reschedule, or cancel your appointment:</p>
                                <div class="bg-gray-50 rounded-lg p-3 flex gap-2">
                                    <input type="text" 
                                           value="{{ route('marketplace.booking.confirmation', ['tenant' => $appointment->tenant->slug, 'appointment' => $appointment->id]) }}?token={{ $appointment->management_token }}" 
                                           readonly 
                                           class="flex-1 text-sm bg-white border border-gray-300 rounded px-3 py-2 font-mono"
                                           id="management-link">
                                    <button onclick="navigator.clipboard.writeText(document.getElementById('management-link').value)" 
                                            class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition">
                                        Copy
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Keep this link private. Anyone with this link can manage your appointment.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
                    <a href="{{ route('marketplace.booking.manage', ['tenant' => $appointment->tenant->slug, 'appointment' => $appointment->id]) }}" 
                       class="col-span-1 sm:col-span-2 bg-primary-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:bg-primary-700 transition">
                        Manage Booking (Reschedule/Cancel)
                    </a>
                    <a href="{{ route('marketplace.salon.show', $appointment->tenant->slug) }}" 
                       class="bg-gray-100 text-gray-700 py-3 px-6 rounded-xl font-semibold text-center hover:bg-gray-200 transition">
                        Back to Salon
                    </a>
                </div>

                <!-- Create Account CTA for Guests -->
                @guest
                    <div class="bg-primary-50 border border-primary-200 rounded-2xl p-6 sm:p-8 text-center">
                        <h3 class="text-xl font-semibold text-primary-900 mb-2">Want faster future bookings?</h3>
                        <p class="text-primary-700 mb-6">Create an account to save your details, view booking history, and manage all appointments in one place.</p>
                        <a href="{{ route('register.customer') }}" class="inline-block bg-primary-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-primary-700 transition">
                            Create Free Account
                        </a>
                    </div>
                @endguest
            </div>
        </main>
    </div>
</x-marketplace.layouts.app>