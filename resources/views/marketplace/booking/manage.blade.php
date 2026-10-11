<x-marketplace.layouts.app :title="'Manage Booking - GLOUP'">
    <div x-data="manageBooking()" class="min-h-screen">
        <main class="flex-1 py-8">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-8">
                    <a href="{{ route('marketplace.booking.confirmation', ['tenant' => $tenant->slug, 'appointment' => $appointment->id]) }}" class="text-primary-600 hover:underline text-sm flex items-center gap-1 mb-4">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        Back to Confirmation
                    </a>
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Manage Appointment</h1>
                            <p class="text-gray-600">{{ $appointment->tenant->name }} • {{ $appointment->branch->name }}</p>
                        </div>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                            @if($appointment->status === 'confirmed') bg-green-100 text-green-800
                            @elseif($appointment->status === 'pending') bg-yellow-100 text-yellow-800
                            @elseif($appointment->status === 'completed') bg-blue-100 text-blue-800
                            @elseif($appointment->status === 'cancelled') bg-red-100 text-red-800
                            @elseif($appointment->status === 'no_show') bg-gray-100 text-gray-800
                            @else bg-gray-100 text-gray-800 @endif">
                            {{ ucfirst(str_replace('_', ' ', $appointment->status)) }}
                        </span>
                    </div>
                </div>

                @if(session('error'))
                    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6" role="alert">
                        {{ session('error') }}
                    </div>
                @endif
                @if(session('success'))
                    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6" role="alert">
                        {{ session('success') }}
                    </div>
                @endif>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Appointment Summary -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Current Appointment Details -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-6">Current Appointment</h2>
                            
                            <div class="space-y-4">
                                <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl">
                                    <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center">
                                        <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Date</p>
                                        <p class="font-semibold text-gray-900">{{ \Carbon\Carbon::parse($appointment->starts_at)->format('l, F j, Y') }}</p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl">
                                    <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center">
                                        <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Time</p>
                                        <p class="font-semibold text-gray-900">{{ \Carbon\Carbon::parse($appointment->starts_at)->format('g:i A') }} - {{ \Carbon\Carbon::parse($appointment->ends_at)->format('g:i A') }}</p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl">
                                    <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center">
                                        <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Location</p>
                                        <p class="font-semibold text-gray-900">{{ $appointment->branch->name }}</p>
                                        <p class="text-sm text-gray-500">{{ $appointment->branch->city }}, {{ $appointment->branch->state }}</p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl">
                                    <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center">
                                        <svg class="w-6 h-6 text-primary-600" fill="currentColor" viewBox="0 0 24 24"><path d="M24 20.993V24H0v-3.007A12.005 12.005 0 0112 9c2.812 0 5.397.94 7.465 2.472A10.015 10.015 0 0124 20.993z"></path></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Staff</p>
                                        <p class="font-semibold text-gray-900">{{ $appointment->staff->name }}</p>
                                    </div>
                                </div>

                                <div class="border-t border-gray-200 pt-4">
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

                                <div class="border-t border-gray-200 pt-4 flex justify-between items-center">
                                    <span class="text-lg font-semibold text-gray-900">Total</span>
                                    <span class="text-2xl font-bold text-primary-600">₹{{ number_format($appointment->total_price_inr) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Cancel Appointment -->
                        @if(in_array($appointment->status, ['pending', 'confirmed']))
                            <div class="bg-red-50 border border-red-200 rounded-2xl p-6">
                                <h3 class="text-lg font-semibold text-red-800 mb-4">Cancel Appointment</h3>
                                <p class="text-red-700 mb-4">Cancelling will release your time slot. This action cannot be undone.</p>
                                
                                <form method="POST" action="{{ route('marketplace.booking.cancel', ['tenant' => $tenant->slug, 'appointment' => $appointment->id]) }}" @submit.prevent="cancelAppointment($event)">
                                    @csrf
                                    <div class="mb-4">
                                        <label for="cancel_reason" class="block text-sm font-medium text-red-700 mb-1">Reason for Cancellation <span class="text-red-500">*</span></label>
                                        <textarea id="cancel_reason" name="reason" rows="3" required
                                                  class="w-full px-4 py-3 border border-red-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                                  placeholder="Please let us know why you're cancelling..."></textarea>
                                        @error('reason')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <button type="submit" 
                                            :disabled="cancelling"
                                            class="w-full bg-red-600 text-white py-3 px-6 rounded-xl font-semibold hover:bg-red-700 transition disabled:opacity-50">
                                        <span x-show="!cancelling">Cancel Appointment</span>
                                        <span x-show="cancelling" class="flex items-center justify-center gap-2">
                                            <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            Cancelling...
                                        </span>
                                    </button>
                                </form>
                            </div>
                        @endif

                        <!-- Reschedule Appointment -->
                        @if(in_array($appointment->status, ['pending', 'confirmed']))
                            <div class="bg-blue-50 border border-blue-200 rounded-2xl p-6">
                                <h3 class="text-lg font-semibold text-blue-800 mb-4">Reschedule Appointment</h3>
                                <p class="text-blue-700 mb-6">Choose a new date and time for your appointment.</p>

                                <form method="POST" action="{{ route('marketplace.booking.reschedule', ['tenant' => $tenant->slug, 'appointment' => $appointment->id]) }}" @submit.prevent="rescheduleAppointment($event)">
                                    @csrf
                                    
                                    <!-- Staff Selection -->
                                    <div class="mb-6">
                                        <label class="block text-sm font-medium text-blue-800 mb-3">Staff Member</label>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" role="radiogroup">
                                            @foreach($staff as $member)
                                                <label class="relative">
                                                    <input type="radio" name="staff_selection" 
                                                           :value="member.id" 
                                                           x-model="rescheduleStaff"
                                                           class="sr-only peer"
                                                           @if($member->id == $appointment->staff_id)checked @endif
                                                           required>
                                                    <div class="relative p-4 border-2 rounded-xl cursor-pointer transition-all
                                                        peer-checked:border-primary-500 peer-checked:bg-primary-50 peer-checked:ring-2 peer-checked:ring-primary-500
                                                        hover:border-gray-300">
                                                        <div class="flex items-center gap-3">
                                                            <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                                                                <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 24 24"><path d="M24 20.993V24H0v-3.007A12.005 12.005 0 0112 9c2.812 0 5.397.94 7.465 2.472A10.015 10.015 0 0124 20.993z"></path></svg>
                                                            </div>
                                                            <div>
                                                                <h4 class="font-medium text-gray-900">{{ $member->name }}</h4>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>

                                    <!-- Date Selection -->
                                    <div class="mb-6">
                                        <label class="block text-sm font-medium text-blue-800 mb-3">Select New Date</label>
                                        <div class="overflow-x-auto pb-4 -mx-4 px-4" role="radiogroup">
                                            <div class="flex gap-3 min-w-max">
                                                @foreach($availableDates as $date)
                                                    <label class="relative flex-shrink-0">
                                                        <input type="radio" name="date_selection" 
                                                               :value="date.date" 
                                                               x-model="rescheduleDate"
                                                               class="sr-only peer"
                                                               @if($loop->first)checked @endif
                                                               required>
                                                        <div class="relative w-24 text-center p-3 rounded-xl cursor-pointer transition-all
                                                            peer-checked:border-primary-500 peer-checked:bg-primary-50 peer-checked:ring-2 peer-checked:ring-primary-500
                                                            hover:border-gray-300 hover:bg-gray-50
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
                                    <div class="mb-6">
                                        <label class="block text-sm font-medium text-blue-800 mb-3">Select New Time</label>
                                        <div x-show="rescheduleSlots.length > 0" class="flex flex-wrap gap-2" role="radiogroup">
                                            <template x-for="slot in rescheduleSlots" :key="slot.start_iso">
                                                <label class="relative">
                                                    <input type="radio" name="time_selection" 
                                                           :value="slot.start_iso + '|' + slot.end_iso" 
                                                           x-model="rescheduleTimeSlot"
                                                           class="sr-only peer">
                                                    <div class="relative px-4 py-2 rounded-lg cursor-pointer transition-all
                                                        peer-checked:border-primary-500 peer-checked:bg-primary-50 peer-checked:ring-2 peer-checked:ring-primary-500
                                                        hover:border-gray-300 hover:bg-gray-50
                                                        border border-gray-200 bg-white text-sm font-medium text-gray-700">
                                                        <span x-text="slot.start + ' - ' + slot.end"></span>
                                                    </div>
                                                </label>
                                            </template>
                                        </div>
                                        <div x-show="rescheduleSlots.length === 0 && rescheduleDate && rescheduleStaff" class="text-center py-8 text-gray-500">
                                            <p>No available slots for this date</p>
                                            <p class="text-sm">Try another date or staff member</p>
                                        </div>
                                        <div x-show="!rescheduleDate || !rescheduleStaff" class="text-center py-8 text-gray-500">
                                            <p>Select a date and staff member to see available times</p>
                                        </div>
                                    </div>

                                    <input type="hidden" name="reschedule_session_token" :value="rescheduleReservationToken">

                                    <button type="submit" 
                                            :disabled="rescheduling || !rescheduleReservationToken"
                                            class="w-full bg-blue-600 text-white py-3 px-6 rounded-xl font-semibold hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                                        <span x-show="!rescheduling">Reschedule Appointment</span>
                                        <span x-show="rescheduling" class="flex items-center justify-center gap-2">
                                            <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            Rescheduling...
                                        </span>
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>

                    <!-- Sidebar: Quick Actions & Info -->
                    <div class="lg:col-span-1 space-y-6">
                        <!-- Quick Info -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 sticky top-24">
                            <h3 class="font-semibold text-gray-900 mb-4">Quick Actions</h3>
                            
                            <div class="space-y-3">
                                <a href="{{ route('marketplace.booking.confirmation', ['tenant' => $tenant->slug, 'appointment' => $appointment->id]) }}" 
                                   class="block w-full text-left p-4 bg-primary-50 border border-primary-200 rounded-lg hover:bg-primary-100 transition">
                                    <p class="font-medium text-primary-800">View Confirmation</p>
                                    <p class="text-sm text-primary-700">See full appointment details</p>
                                </a>

                                @if($appointment->management_token)
                                    <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg">
                                        <p class="text-sm font-medium text-gray-700 mb-2">Secure Management Link</p>
                                        <div class="flex gap-2">
                                            <input type="text" 
                                                   value="{{ route('marketplace.booking.confirmation', ['tenant' => $appointment->tenant->slug, 'appointment' => $appointment->id]) }}?token={{ $appointment->management_token }}" 
                                                   readonly 
                                                   class="flex-1 text-xs bg-white border border-gray-300 rounded px-2 py-1 font-mono"
                                                   id="sidebar-link">
                                            <button onclick="navigator.clipboard.writeText(document.getElementById('sidebar-link').value)" 
                                                    class="px-3 py-1 bg-primary-600 text-white rounded text-xs font-medium hover:bg-primary-700 transition">
                                                Copy
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="mt-6 pt-6 border-t border-gray-200">
                                <h4 class="font-medium text-gray-900 mb-3">Policy Reminders</h4>
                                <ul class="space-y-2 text-sm text-gray-600">
                                    <li class="flex items-start gap-2">
                                        <svg class="w-5 h-5 text-primary-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span>Reschedule up to 2 hours before</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <svg class="w-5 h-5 text-primary-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span>Cancel free up to 24 hours before</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <svg class="w-5 h-5 text-primary-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span>Late cancellations may incur fees</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <svg class="w-5 h-5 text-primary-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span>No-shows affect future booking priority</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function manageBooking() {
            return {
                cancelling: false,
                rescheduling: false,
                rescheduleStaff: '{{ $appointment->staff_id }}',
                rescheduleDate: '{{ $availableDates[0]['date'] ?? '' }}',
                rescheduleTimeSlot: '',
                rescheduleSlots: [],
                rescheduleReservationToken: '',

                async loadRescheduleSlots() {
                    if (!this.rescheduleDate || !this.rescheduleStaff) {
                        this.rescheduleSlots = [];
                        return;
                    }

                    try {
                        const response = await fetch(`{{ route('marketplace.booking.reschedule.slots', ['tenant' => $tenant->slug, 'appointment' => $appointment->id]) }}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                branch_id: '{{ $appointment->branch_id }}',
                                staff_id: this.rescheduleStaff,
                                date: this.rescheduleDate,
                            })
                        });

                        const data = await response.json();
                        this.rescheduleSlots = data.slots || [];
                        this.rescheduleTimeSlot = '';
                        this.rescheduleReservationToken = '';
                    } catch (error) {
                        console.error('Failed to load slots:', error);
                        this.rescheduleSlots = [];
                    }
                },

                async createRescheduleReservation() {
                    if (!this.rescheduleTimeSlot) return;

                    const [start, end] = this.rescheduleTimeSlot.split('|');

                    try {
                        const response = await fetch(`{{ route('marketplace.booking.reschedule.reserve', ['tenant' => $tenant->slug, 'appointment' => $appointment->id]) }}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                branch_id: '{{ $appointment->branch_id }}',
                                staff_id: this.rescheduleStaff,
                                start_time: start,
                                end_time: end,
                                service_ids: @json($appointment->services->pluck('service_id')),
                            })
                        });

                        const data = await response.json();
                        if (data.success) {
                            this.rescheduleReservationToken = data.session_token;
                        } else {
                            alert('Failed to reserve slot: ' + data.message);
                        }
                    } catch (error) {
                        console.error('Failed to create reservation:', error);
                    }
                },

                async cancelAppointment(event) {
                    event.preventDefault();
                    
                    if (!confirm('Are you sure you want to cancel this appointment? This cannot be undone.')) {
                        return;
                    }

                    this.cancelling = true;
                    const form = event.target;
                    const formData = new FormData(form);
                    
                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                        });

                        const data = await response.json();
                        if (data.success) {
                            window.location.href = `{{ route('marketplace.booking.confirmation', ['tenant' => $tenant->slug, 'appointment' => $appointment->id]) }}`;
                        } else {
                            alert(data.message || 'Failed to cancel appointment');
                        }
                    } catch (error) {
                        alert('Failed to cancel appointment');
                    } finally {
                        this.cancelling = false;
                    }
                },

                async rescheduleAppointment(event) {
                    event.preventDefault();

                    if (!this.rescheduleReservationToken) {
                        await this.createRescheduleReservation();
                        if (!this.rescheduleReservationToken) return;
                    }

                    this.rescheduling = true;
                    const form = event.target;
                    const formData = new FormData(form);
                    formData.append('reschedule_session_token', this.rescheduleReservationToken);
                    
                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                        });

                        const data = await response.json();
                        if (data.success) {
                            window.location.href = `{{ route('marketplace.booking.confirmation', ['tenant' => $tenant->slug, 'appointment' => $appointment->id]) }}`;
                        } else {
                            alert(data.message || 'Failed to reschedule appointment');
                        }
                    } catch (error) {
                        alert('Failed to reschedule appointment');
                    } finally {
                        this.rescheduling = false;
                    }
                },

                async createRescheduleReservation() {
                    if (!this.rescheduleTimeSlot) return;

                    const [start, end] = this.rescheduleTimeSlot.split('|');

                    try {
                        const response = await fetch(`{{ route('marketplace.booking.reschedule.reserve', ['tenant' => $tenant->slug, 'appointment' => $appointment->id]) }}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                branch_id: '{{ $appointment->branch_id }}',
                                staff_id: this.rescheduleStaff,
                                start_time: start,
                                end_time: end,
                                service_ids: @json($appointment->services->pluck('service_id')),
                            })
                        });

                        const data = await response.json();
                        if (data.success) {
                            this.rescheduleReservationToken = data.session_token;
                        } else {
                            alert('Failed to reserve slot: ' + data.message);
                        }
                    } catch (error) {
                        console.error('Failed to create reservation:', error);
                    }
                },

                init() {
                    this.$watch('rescheduleDate', () => {
                        this.rescheduleTimeSlot = '';
                        this.rescheduleReservationToken = '';
                        this.loadRescheduleSlots();
                    });

                    this.$watch('rescheduleStaff', () => {
                        this.rescheduleTimeSlot = '';
                        this.rescheduleReservationToken = '';
                        this.loadRescheduleSlots();
                    });
                }
            }
        }
    </script>
</x-marketplace.layouts.app>