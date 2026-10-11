<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between h-16 px-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-4">
                <a href="{{ route('tenant.dashboard', $tenant->slug) }}" class="text-gray-600 hover:text-primary-600" aria-label="Back to dashboard">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </a>
                <div>
                    <h1 class="text-xl font-bold text-gray-900">Calendar</h1>
                    <p class="text-sm text-gray-500">{{ $currentBranch?->name ?? 'Select Branch' }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.dashboard', $tenant->slug) }}" 
                   class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200 transition">
                    Back to Dashboard
                </a>
                <button wire:click="openWalkInModal" 
                        class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 transition">
                    + Walk-in
                </button>
            </div>
        </div>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8">
        <div x-data="calendarView()" class="space-y-6">
            <!-- Filters & View Controls -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
                    <div class="flex flex-col sm:flex-row gap-3 flex-1">
                        <!-- Date Range -->
                        <div class="flex items-center gap-2">
                            <label class="text-sm font-medium text-gray-700 whitespace-nowrap">From</label>
                            <input type="date" x-model="filters.date_from" 
                                   class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm">
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-sm font-medium text-gray-700 whitespace-nowrap">To</label>
                            <input type="date" x-model="filters.date_to" 
                                   class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm">
                        </div>

                        <!-- Staff Filter -->
                        <div class="flex items-center gap-2">
                            <label class="text-sm font-medium text-gray-700 whitespace-nowrap">Staff</label>
                            <select x-model="filters.staff_id" 
                                    class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm">
                                <option value="">All Staff</option>
                                <template x-for="staff in staffList" :key="staff.id">
                                    <option :value="staff.id" x-text="staff.name"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <div class="flex items-center gap-2">
                            <label class="text-sm font-medium text-gray-700 whitespace-nowrap">Status</label>
                            <select x-model="filters.status" 
                                    class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="no_show">No Show</option>
                            </select>
                        </div>
                    </div>

                    <!-- View Toggle -->
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-medium text-gray-700">View:</label>
                        <div class="flex border border-gray-300 rounded-lg overflow-hidden">
                            <template x-for="view in ['day', 'week', 'month', 'list']" :key="view">
                                <button type="button" 
                                        @click="view = view"
                                        :class="view === currentView ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                                        class="px-3 py-1.5 text-sm font-medium transition border-r last:border-r-0">
                                    <span x-text="view.charAt(0).toUpperCase() + view.slice(1)"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Calendar / List View -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <!-- Week/Day/Month View -->
                <div x-show="currentView !== 'list'" x-transition>
                    <div class="p-4 border-b border-gray-200">
                        <!-- Week Navigation -->
                        <div class="flex items-center justify-between mb-4">
                            <button @click="navigateWeek(-1)" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                            </button>
                            <h3 class="text-lg font-semibold text-gray-900" x-text="weekLabel"></h3>
                            <button @click="navigateWeek(1)" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                            </button>
                        </div>

                        <!-- Calendar Grid -->
                        <div class="grid grid-cols-7 gap-0.5" x-show="currentView === 'week'">
                            <template x-for="(day, index) in weekDays" :key="index">
                                <div class="border-r border-b border-gray-200 min-h-[600px] relative" :class="{ 'bg-gray-50': day.isToday }">
                                    <div class="p-2 text-center text-sm font-medium" :class="{ 'text-primary-600': day.isToday, 'text-gray-500': !day.isCurrentMonth }">
                                        <div x-text="day.date.format('ddd')"></div>
                                        <div class="text-lg" x-text="day.date.format('D')"></div>
                                    </div>
                                    <div class="p-1 space-y-1 overflow-y-auto h-[550px]">
                                        <template x-for="appt in getAppointmentsForDay(day.date)" :key="appt.id">
                                            <div class="px-2 py-1.5 rounded text-xs cursor-pointer hover:opacity-90 transition"
                                                 :style="{ backgroundColor: appt.color, color: '#fff' }"
                                                 @click="openAppointment(appt)">
                                                <div class="font-medium truncate" x-text="appt.time + ' ' + appt.title"></div>
                                                <div class="truncate text-opacity-90" x-text="appt.staff_name"></div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- List View -->
                <div x-show="currentView === 'list'" x-transition class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Services</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <template x-for="appt in appointments" :key="appt.id">
                                <tr class="hover:bg-gray-50" @click="openAppointment(appt)">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                        <div x-text="appt.start"></div>
                                        <div class="text-xs text-gray-500" x-text="appt.end"></div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900" x-text="appt.title"></td>
                                    <td class="px-4 py-3 text-sm text-gray-500" x-text="appt.staff_name || 'Unassigned'"></td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        <template x-for="svc in appt.services" :key="svc">
                                            <span class="inline-block px-2 py-0.5 text-xs bg-gray-100 text-gray-700 rounded mr-1 mb-1" x-text="svc"></span>
                                        </template>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 text-xs rounded-full"
                                            :class="{
                                                'bg-blue-100 text-blue-800': appt.status === 'confirmed',
                                                'bg-yellow-100 text-yellow-800': appt.status === 'pending',
                                                'bg-green-100 text-green-800': appt.status === 'completed',
                                                'bg-red-100 text-red-800': appt.status === 'cancelled',
                                                'bg-gray-100 text-gray-800': appt.status === 'no_show',
                                            }"
                                            x-text="appt.status.charAt(0).toUpperCase() + appt.status.slice(1).replace('_', ' ')"></span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <button @click.stop="openAppointment(appt)" 
                                                class="text-primary-600 hover:underline text-sm">
                                            View
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Appointment Detail Modal -->
        <div x-show="selectedAppointment" x-transition:enter="transition ease-out duration-100" 
             x-transition:enter-start="opacity-0" x-transition:leave="transition ease-in duration-75" 
             x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="flex min-h-full items-center justify-center p-4">
                <div @click="selectedAppointment = null" class="fixed inset-0 bg-black/50" aria-hidden="true"></div>
                <div class="relative w-full max-w-2xl bg-white rounded-xl shadow-xl max-h-[90vh] overflow-y-auto">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900" x-text="selectedAppointment.title"></h3>
                        <button @click="selectedAppointment = null" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    
                    <div class="p-6 space-y-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Date</p>
                                <p class="text-gray-900" x-text="formatDate(selectedAppointment.start)"></p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Time</p>
                                <p class="text-gray-900" x-text="formatTime(selectedAppointment.start) + ' - ' + formatTime(selectedAppointment.end)"></p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Staff</p>
                                <p class="text-gray-900" x-text="selectedAppointment.staff_name || 'Unassigned'"></p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Status</p>
                                <p class="text-gray-900">
                                    <span class="px-2 py-1 text-xs rounded-full"
                                        :class="{
                                            'bg-blue-100 text-blue-800': selectedAppointment.status === 'confirmed',
                                            'bg-yellow-100 text-yellow-800': selectedAppointment.status === 'pending',
                                            'bg-green-100 text-green-800': selectedAppointment.status === 'completed',
                                            'bg-red-100 text-red-800': selectedAppointment.status === 'cancelled',
                                            'bg-gray-100 text-gray-800': selectedAppointment.status === 'no_show',
                                        }"
                                        x-text="selectedAppointment.status.charAt(0).toUpperCase() + selectedAppointment.status.slice(1).replace('_', ' ')"></span>
                                </p>
                            </div>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-500 mb-2">Services</p>
                            <div class="space-y-2">
                                <template x-for="svc in selectedAppointment.services" :key="svc">
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <span x-text="svc"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-gray-200">
                            <p class="text-sm font-medium text-gray-500 mb-2">Actions</p>
                            <div class="flex flex-wrap gap-3">
                                <template x-if="selectedAppointment.status === 'confirmed' || selectedAppointment.status === 'pending'">
                                    <button @click="updateStatus('completed')" 
                                            class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 transition">
                                        Mark Complete
                                    </button>
                                    <button @click="updateStatus('cancelled')" 
                                            class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 transition">
                                        Cancel
                                    </button>
                                    <button @click="updateStatus('no_show')" 
                                            class="px-4 py-2 bg-gray-600 text-white rounded-lg text-sm hover:bg-gray-700 transition">
                                        No Show
                                    </button>
                                </template>
                                <template x-if="selectedAppointment.status === 'cancelled' || selectedAppointment.status === 'no_show'">
                                    <button @click="updateStatus('confirmed')" 
                                            class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition">
                                        Re-open
                                    </button>
                                </template>
                                <button @click="selectedAppointment = null" 
                                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function calendarView() {
            return {
                currentView: 'week',
                filters: {
                    date_from: '',
                    date_to: '',
                    staff_id: '',
                    status: '',
                },
                staffList: @json($staff ?? []),
                appointments: [],
                weekDays: [],
                selectedAppointment: null,
                loading: false,

                async init() {
                    this.weekDays = this.generateWeekDays();
                    await this.loadAppointments();
                },

                generateWeekDays() {
                    const startOfWeek = new Date();
                    startOfWeek.setDate(startOfWeek.getDate() - startOfWeek.getDay()); // Sunday
                    const days = [];
                    for (let i = 0; i < 7; i++) {
                        const date = new Date(startOfWeek);
                        date.setDate(startOfWeek.getDate() + i);
                        days.push({
                            date: date,
                            isToday: this.isSameDay(date, new Date()),
                            isCurrentMonth: true,
                        });
                    }
                    return days;
                },

                isSameDay(d1, d2) {
                    return d1.getFullYear() === d2.getFullYear() &&
                           d1.getMonth() === d2.getMonth() &&
                           d1.getDate() === d2.getDate();
                },

                get weekLabel() {
                    if (this.weekDays.length === 0) return '';
                    const start = this.weekDays[0].date;
                    const end = this.weekDays[6].date;
                    return start.toLocaleDateString('en-US', { month: 'long', day: 'numeric' }) + ' - ' + 
                           end.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                },

                navigateWeek(direction) {
                    this.weekDays.forEach(day => {
                        day.date.setDate(day.date.getDate() + (direction * 7));
                        day.isToday = this.isSameDay(day.date, new Date());
                    });
                    this.loadAppointments();
                },

                getAppointmentsForDay(date) {
                    const dayStr = date.toISOString().split('T')[0];
                    return this.appointments.filter(appt => {
                        const apptDate = new Date(appt.start).toISOString().split('T')[0];
                        return apptDate === dayStr;
                    });
                },

                async loadAppointments() {
                    this.loading = true;
                    try {
                        const params = new URLSearchParams();
                        if (this.filters.date_from) params.append('date_from', this.filters.date_from);
                        if (this.filters.date_to) params.append('date_to', this.filters.date_to);
                        if (this.filters.staff_id) params.append('staff_id', this.filters.staff_id);
                        if (this.filters.status) params.append('status', this.filters.status);
                        params.append('view', this.currentView);

                        const response = await fetch(`{{ route('tenant.calendar.index', $tenant->slug) }}?${params.toString()}`);
                        const data = await response.json();
                        this.appointments = data.appointments || [];
                        this.staffList = data.staff || [];
                    } catch (error) {
                        console.error('Failed to load appointments:', error);
                    } finally {
                        this.loading = false;
                    }
                },

                openAppointment(appt) {
                    this.selectedAppointment = appt;
                },

                formatDate(dateStr) {
                    return new Date(dateStr).toLocaleDateString('en-US', { 
                        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
                    });
                },

                formatTime(dateStr) {
                    return new Date(dateStr).toLocaleTimeString('en-US', { 
                        hour: 'numeric', minute: '2-digit', hour12: true 
                    });
                },

                async updateStatus(newStatus) {
                    if (!this.selectedAppointment) return;
                    
                    try {
                        const response = await fetch(`{{ route('tenant.calendar.appointments.status', ['tenant' => $tenant->slug, 'appointment' => ':id']) }.replace(':id', this.selectedAppointment.id)`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({ status: newStatus }),
                        });

                        const data = await response.json();
                        if (data.appointment) {
                            this.selectedAppointment = data.appointment;
                            this.loadAppointments();
                        } else {
                            alert(data.message || 'Failed to update status');
                        }
                    } catch (error) {
                        alert('Failed to update status');
                    }
                },
            }
        }
    </script>
</x-app-layout>