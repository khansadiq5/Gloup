<x-marketplace.layouts.app :title="'Book Appointment - GLOUP'">
    <div x-data="bookingPage()" class="min-h-screen">
        <!-- Progress Indicator -->
        <nav class="bg-white border-b border-gray-200" aria-label="Booking progress">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <ol class="flex items-center justify-between h-12" role="list">
                    <li class="flex items-center">
                        <div class="flex items-center">
                            <span class="flex items-center justify-center w-8 h-8 rounded-full bg-primary-600 text-white text-sm font-medium" aria-current="step">1</span>
                            <span class="ml-3 font-medium text-primary-600 hidden sm:block">Services</span>
                        </div>
                    </li>
                    <li class="flex items-center">
                        <div class="flex items-center">
                            <span class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-200 text-gray-600 text-sm font-medium" aria-current="step">2</span>
                            <span class="ml-3 font-medium text-gray-400 hidden sm:block">Details</span>
                        </div>
                    </li>
                    <li class="flex items-center">
                        <div class="flex items-center">
                            <span class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-200 text-gray-600 text-sm font-medium" aria-current="step">3</span>
                            <span class="ml-3 font-medium text-gray-400 hidden sm:block">Confirm</span>
                        </div>
                    </li>
                </ol>
                <!-- Progress bar -->
                <div class="absolute bottom-0 left-0 right-0 h-1 bg-gray-200">
                    <div class="h-full bg-primary-600" style="width: 33.33%"></div>
                </div>
            </div>
        </nav>

        <main class="flex-1 py-8">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mb-8">
                    <a href="{{ route('marketplace.salon.show', $tenant->slug) }}" class="text-primary-600 hover:underline text-sm flex items-center gap-1 mb-4">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        Back to {{ $tenant->name }}
                    </a>
                    <h1 class="text-2xl font-bold text-gray-900">Book Appointment</h1>
                    <p class="text-gray-600 mt-1">{{ $branch->name }}</p>
                </div>

                @if($errors->any())
                    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6" role="alert">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('marketplace.booking.store', $tenant->slug) }}" x-data="bookingForm()" @submit.prevent="submitForm">
                    @csrf
                    
                    <input type="hidden" name="branch_id" :value="selectedBranch">
                    <input type="hidden" name="staff_id" :value="selectedStaff">
                    <input type="hidden" name="service_ids" :value="JSON.stringify(selectedServices)">
                    <input type="hidden" name="starts_at" :value="selectedStartTime">
                    <input type="hidden" name="ends_at" :value="selectedEndTime">
                    <input type="hidden" name="session_token" :value="reservationToken">

                    <!-- Step 1: Services (already selected, show summary) -->
                    <div class="space-y-6">
                        <section aria-labelledby="services-heading">
                            <h2 id="services-heading" class="text-lg font-semibold text-gray-900 mb-4">Selected Services</h2>
                            <div class="bg-gray-50 rounded-xl p-6">
                                <div class="space-y-3">
                                    @foreach($services as $service)
                                        @php
                                            $price = $service->getPriceForBranch($branch);
                                            $duration = $service->getDurationForBranch($branch);
                                            $buffer = $service->getBufferForBranch($branch);
                                        @endphp
                                        <div class="flex items-center justify-between bg-white p-4 rounded-lg border border-gray-200">
                                            <div>
                                                <h3 class="font-medium text-gray-900">{{ $service->name }}</h3>
                                                <p class="text-sm text-gray-500">{{ $duration + $buffer }} min @if($buffer > 0)(incl. {{ $buffer }} min prep)@endif</p>
                                            </div>
                                            <span class="text-lg font-bold text-primary-600">₹{{ number_format($price) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="mt-4 pt-4 border-t border-gray-200 flex justify-between items-center">
                                    <span class="font-medium text-gray-900">Total</span>
                                    <div class="text-right">
                                        <p class="text-2xl font-bold text-primary-600">₹{{ number_format($totalPrice) }}</p>
                                        <p class="text-sm text-gray-500">{{ $totalDuration }} min total</p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Step 2: Staff, Date & Time Selection -->
                        <section aria-labelledby="details-heading" class="border-t border-gray-200 pt-8">
                            <h2 id="details-heading" class="text-lg font-semibold text-gray-900 mb-6">Choose Staff, Date & Time</h2>

                            <!-- Staff Selection -->
                            <div class="mb-8">
                                <label class="block text-sm font-medium text-gray-700 mb-3">Select Staff Member</label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" role="radiogroup" aria-label="Staff members">
                                    @foreach($staff as $member)
                                        <label class="relative">
                                            <input type="radio" name="staff_selection" 
                                                   :value="member.id" 
                                                   x-model="selectedStaff"
                                                   class="sr-only peer"
                                                   @if($loop->first)checked @endif
                                                   required>
                                            <div class="relative p-4 border-2 rounded-xl cursor-pointer transition-all
                                                peer-checked:border-primary-500 peer-checked:bg-primary-50 peer-checked:ring-2 peer-checked:ring-primary-500
                                                hover:border-gray-300
                                                peer-focus:ring-2 peer-focus:ring-primary-500 peer-focus:ring-offset-2">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center">
                                                        <svg class="w-7 h-7 text-gray-400" fill="currentColor" viewBox="0 0 24 24"><path d="M24 20.993V24H0v-3.007A12.005 12.005 0 0112 9c2.812 0 5.397.94 7.465 2.472A10.015 10.015 0 0124 20.993z"></path></svg>
                                                    </div>
                                                    <div>
                                                        <h4 class="font-medium text-gray-900">{{ $member->name }}</h4>
                                                        <div class="flex flex-wrap gap-1 mt-1">
                                                            @foreach($member->services->take(3) as $service)
                                                                <span class="px-2 py-0.5 text-xs bg-primary-50 text-primary-700 rounded">{{ $service->name }}</span>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="absolute inset-0" aria-hidden="true">
                                                    <div class="w-full h-full bg-primary-500 opacity-0 peer-checked:opacity-100 transition-opacity">
                                                        <svg class="w-6 h-6 text-white mx-auto mt-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                                    </div>
                                                </div>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Date Selection -->
                            <div class="mb-8">
                                <label class="block text-sm font-medium text-gray-700 mb-3">Select Date</label>
                                <div class="overflow-x-auto pb-4 -mx-4 px-4" role="radiogroup" aria-label="Available dates">
                                    <div class="flex gap-3 min-w-max" style="min-width: max-content;">
                                        @foreach($availableDates as $date)
                                            <label class="relative flex-shrink-0">
                                                <input type="radio" name="date_selection" 
                                                       :value="date.date" 
                                                       x-model="selectedDate"
                                                       class="sr-only peer"
                                                       @if($loop->first)checked @endif
                                                       required>
                                                <div class="relative w-24 text-center p-3 rounded-xl cursor-pointer transition-all
                                                    peer-checked:border-primary-500 peer-checked:bg-primary-50 peer-checked:ring-2 peer-checked:ring-primary-500
                                                    hover:border-gray-300 hover:bg-gray-50
                                                    peer-focus:ring-2 peer-focus:ring-primary-500 peer-focus:ring-offset-2
                                                    border border-gray-200 bg-white">
                                                    <div class="text-xs font-medium text-gray-500 uppercase">{{ $date->day_name }}</div>
                                                    <div class="text-2xl font-bold text-gray-900 mt-1">{{ \Carbon\Carbon::parse($date->date)->format('j') }}</div>
                                                    <div class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($date->date)->format('M') }}</div>
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <!-- Time Slots -->
                            <div class="mb-8">
                                <label class="block text-sm font-medium text-gray-700 mb-3">Select Time</label>
                                <div x-show="timeSlots.length > 0" class="flex flex-wrap gap-2" role="radiogroup" aria-label="Available time slots">
                                    <template x-for="slot in timeSlots" :key="slot.start_iso">
                                        <label class="relative">
                                            <input type="radio" name="time_selection" 
                                                   :value="slot.start_iso + '|' + slot.end_iso" 
                                                   x-model="selectedTimeSlot"
                                                   class="sr-only peer">
                                            <div class="relative px-4 py-2 rounded-lg cursor-pointer transition-all
                                                peer-checked:border-primary-500 peer-checked:bg-primary-50 peer-checked:ring-2 peer-checked:ring-primary-500
                                                hover:border-gray-300 hover:bg-gray-50
                                                peer-focus:ring-2 peer-focus:ring-primary-500 peer-focus:ring-offset-2
                                                border border-gray-200 bg-white text-sm font-medium text-gray-700">
                                                <span x-text="slot.start + ' - ' + slot.end"></span>
                                            </div>
                                        </label>
                                    </template>
                                </div>
                                <div x-show="timeSlots.length === 0 && selectedDate && selectedStaff" class="text-center py-8 text-gray-500">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <p class="mt-2">No available slots for this date</p>
                                    <p class="text-sm">Try another date or staff member</p>
                                </div>
                                <div x-show="!selectedDate || !selectedStaff" class="text-center py-8 text-gray-500">
                                    <p>Select a date and staff member to see available times</p>
                                </div>
                            </div>
                        </section>

                        <!-- Step 3: Customer Details -->
                        <section aria-labelledby="customer-heading" class="border-t border-gray-200 pt-8">
                            <h2 id="customer-heading" class="text-lg font-semibold text-gray-900 mb-6">Your Details</h2>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                                    <input type="text" id="customer_name" name="customer_name" required
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                           :value="user?.name ?? ''"
                                           autocomplete="name">
                                    @error('customer_name')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="customer_email" class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                                    <input type="email" id="customer_email" name="customer_email" required
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                           :value="user?.email ?? ''"
                                           autocomplete="email">
                                    @error('customer_email')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="mb-6">
                                <label for="customer_phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number <span class="text-red-500">*</span></label>
                                <input type="tel" id="customer_phone" name="customer_phone" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                       :value="user?.phone ?? ''"
                                       autocomplete="tel"
                                       placeholder="+91 98765 43210">
                                @error('customer_phone')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mb-6">
                                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                                <textarea id="notes" name="notes" rows="3"
                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                          placeholder="Any special requests, allergies, or preferences..."></textarea>
                            </div>

                            @guest
                                <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                    <div class="flex items-start gap-3">
                                        <svg class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        <div>
                                            <p class="text-sm font-medium text-blue-800">Booking as Guest</p>
                                            <p class="text-sm text-blue-700 mt-1">You'll receive a secure link to manage this booking. Create an account after booking to save your details for future visits.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-6">
                                    <label class="flex items-center gap-3 cursor-pointer">
                                        <input type="checkbox" name="create_account" x-model="createAccount" class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500">
                                        <span class="text-sm text-gray-700">Create an account for faster future bookings</span>
                                    </label>
                                </div>

                                <div x-show="createAccount" class="mb-6 space-y-4" x-transition>
                                    <div>
                                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
                                        <input type="password" id="password" name="password" x-model="password"
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                               autocomplete="new-password" minlength="8" required>
                                        @error('password')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password <span class="text-red-500">*</span></label>
                                        <input type="password" id="password_confirmation" name="password_confirmation"
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                               autocomplete="new-password" minlength="8" required>
                                    </div>
                                </div>
                            @endguest

                            <button type="submit" 
                                    :disabled="submitting"
                                    class="w-full bg-primary-600 text-white py-4 px-6 rounded-xl font-semibold text-lg hover:bg-primary-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                                <span x-show="!submitting">Confirm & Book Appointment</span>
                                <span x-show="submitting" class="flex items-center gap-2">
                                    <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    Booking...
                                </span>
                            </button>
                        </section>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        function bookingPage() {
            return {
                selectedBranch: '{{ $branch->id }}',
                selectedStaff: '{{ $staff->first()->id ?? '' }}',
                selectedServices: @json($services->pluck('id')),
                totalPrice: {{ $totalPrice }},
                totalDuration: {{ $totalDuration }},
                availableDates: @json($availableDates),
                selectedDate: '{{ $availableDates[0]['date'] ?? '' }}',
                selectedTimeSlot: '',
                timeSlots: [],
                reservationToken: '',
                createAccount: false,
                password: '',
                password_confirmation: '',
                user: @json(auth()->user() ?? null),
                submitting: false,

                async loadTimeSlots() {
                    if (!this.selectedDate || !this.selectedStaff) {
                        this.timeSlots = [];
                        return;
                    }
                    
                    try {
                        const response = await fetch(`{{ route('marketplace.slots', $tenant->slug) }}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                branch_id: this.selectedBranch,
                                staff_id: this.selectedStaff,
                                date: this.selectedDate,
                                service_ids: this.selectedServices,
                            })
                        });
                        
                        const data = await response.json();
                        this.timeSlots = data.slots || [];
                        this.selectedTimeSlot = '';
                    } catch (error) {
                        console.error('Failed to load slots:', error);
                        this.timeSlots = [];
                    }
                },

                async createReservation() {
                    if (!this.selectedTimeSlot) return;
                    
                    const [start, end] = this.selectedTimeSlot.split('|');
                    
                    try {
                        const response = await fetch(`{{ route('marketplace.reserve', $tenant->slug) }}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                branch_id: this.selectedBranch,
                                staff_id: this.selectedStaff,
                                start_time: start,
                                end_time: end,
                                service_ids: this.selectedServices,
                            })
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            this.reservationToken = data.session_token;
                        } else {
                            alert('Failed to reserve slot: ' + data.message);
                        }
                    } catch (error) {
                        console.error('Failed to create reservation:', error);
                    }
                },

                async submitForm() {
                    if (!this.reservationToken) {
                        await this.createReservation();
                        if (!this.reservationToken) return;
                    }
                    
                    this.submitting = true;
                    
                    // Form will submit normally via action attribute
                    this.$el.submit();
                },

                // Watch for changes
                init() {
                    this.$watch('selectedDate', () => {
                        this.selectedTimeSlot = '';
                        this.reservationToken = '';
                        this.loadTimeSlots();
                    });
                    
                    this.$watch('selectedStaff', () => {
                        this.selectedTimeSlot = '';
                        this.reservationToken = '';
                        this.loadTimeSlots();
                    });
                }
            }
        }
    </script>
</x-marketplace.layouts.app>