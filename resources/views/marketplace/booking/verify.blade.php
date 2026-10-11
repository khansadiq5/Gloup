<x-marketplace.layouts.app :title="'Verify Booking - GLOUP'">
    <div class="min-h-screen flex items-center justify-center py-12">
        <div class="max-w-md w-full px-4">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary-100 mb-4">
                        <svg class="w-8 h-8 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900">Verify Your Booking</h1>
                    <p class="text-gray-600 mt-2">For security, please verify your identity to access this booking.</p>
                </div>

                @if(session('error'))
                    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6" role="alert">
                        {{ session('error') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('marketplace.booking.verify.access', ['tenant' => $tenant->slug, 'appointment' => $appointment->id]) }}" x-data="verifyForm()">
                    @csrf
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Verification Method</label>
                            <div class="flex gap-3" role="radiogroup" aria-label="Verification method">
                                <label class="flex-1 relative">
                                    <input type="radio" name="verification_method" value="email" x-model="method" class="sr-only peer" checked required>
                                    <div class="relative p-4 border-2 rounded-xl cursor-pointer transition-all
                                        peer-checked:border-primary-500 peer-checked:bg-primary-50 peer-checked:ring-2 peer-checked:ring-primary-500
                                        hover:border-gray-300
                                        text-center">
                                        <svg class="w-6 h-6 mx-auto text-gray-400 peer-checked:text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                        <p class="text-sm font-medium text-gray-900 mt-2">Email</p>
                                    </div>
                                </label>
                                <label class="flex-1 relative">
                                    <input type="radio" name="verification_method" value="phone" x-model="method" class="sr-only peer" required>
                                    <div class="relative p-4 border-2 rounded-xl cursor-pointer transition-all
                                        peer-checked:border-primary-500 peer-checked:bg-primary-50 peer-checked:ring-2 peer-checked:ring-primary-500
                                        hover:border-gray-300
                                        text-center">
                                        <svg class="w-6 h-6 mx-auto text-gray-400 peer-checked:text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V3.28a2 2 0 01.684-1.948l.008-.002z"></path></svg>
                                        <p class="text-sm font-medium text-gray-900 mt-2">Phone</p>
                                    </div>
                                </label>
                                <label class="flex-1 relative">
                                    <input type="radio" name="verification_method" value="token" x-model="method" class="sr-only peer" required>
                                    <div class="relative p-4 border-2 rounded-xl cursor-pointer transition-all
                                        peer-checked:border-primary-500 peer-checked:bg-primary-50 peer-checked:ring-2 peer-checked:ring-primary-500
                                        hover:border-gray-300
                                        text-center">
                                        <svg class="w-6 h-6 mx-auto text-gray-400 peer-checked:text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                        <p class="text-sm font-medium text-gray-900 mt-2">Secure Link</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Email Input -->
                        <div x-show="method === 'email'" x-transition>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                            <input type="email" id="email" name="email" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                   placeholder="your@email.com"
                                   autocomplete="email">
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Phone Input -->
                        <div x-show="method === 'phone'" x-transition>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                            <input type="tel" id="phone" name="phone" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                   placeholder="+91 98765 43210"
                                   autocomplete="tel">
                            @error('phone')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Token Input -->
                        <div x-show="method === 'token'" x-transition>
                            <label for="token" class="block text-sm font-medium text-gray-700 mb-1">Management Token</label>
                            <input type="text" id="token" name="token" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent font-mono text-sm"
                                   placeholder="Paste your secure management link or token">
                            <p class="text-xs text-gray-500 mt-1">Found in your booking confirmation email or SMS</p>
                            @error('token')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" 
                                class="w-full bg-primary-600 text-white py-3 px-6 rounded-xl font-semibold hover:bg-primary-700 transition"
                                :disabled="submitting">
                            <span x-show="!submitting">Verify & Access Booking</span>
                            <span x-show="submitting" class="flex items-center justify-center gap-2">
                                <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Verifying...
                            </span>
                        </button>
                    </div>
                </form>

                <p class="mt-6 text-center text-sm text-gray-500">
                    Need help? <a href="#" class="text-primary-600 hover:underline">Contact support</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        function verifyForm() {
            return {
                method: 'email',
                submitting: false,
            }
        }
    </script>
</x-marketplace.layouts.app>