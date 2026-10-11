<x-marketplace.layouts.app :title="'{{ $tenant->name }} - GLOUP'">
    <div x-data="salonPage()" class="min-h-screen">
        <!-- Salon Header -->
        <header class="bg-white shadow-sm sticky top-0 z-40">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <div class="flex items-center gap-4">
                        <a href="{{ route('marketplace.index') }}" class="text-gray-600 hover:text-primary-600" aria-label="Back to salons">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        </a>
                        <div>
                            <h1 class="text-xl font-bold text-gray-900">{{ $tenant->name }}</h1>
                            @if($primaryBranch)
                                <p class="text-sm text-gray-500">{{ $primaryBranch->city }}, {{ $primaryBranch->state }}</p>
                            @endif
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3">
                        @if(count($branches) > 1)
                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open" class="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    {{ $primaryBranch?->name ?? 'Select Branch' }}
                                    <svg class="w-4 h-4" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                                <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:leave="transition ease-in duration-75" x-transition:leave-end="transform opacity-0 scale-95" @click.away="open = false" class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                                    @foreach($branches as $branch)
                                        <a href="{{ route('marketplace.salon.show', ['tenant' => $tenant->slug, 'branch_id' => $branch->id]) }}" 
                                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 {{ $branch->id == $primaryBranch?->id ? 'bg-primary-50 text-primary-700' : '' }}">
                                            {{ $branch->name }} @if($branch->is_primary)<span class="ml-2 text-xs text-primary-600">(Main)</span>@endif
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        
                        <a href="{{ route('marketplace.booking.show', ['tenant' => $tenant->slug, 'branch_id' => $primaryBranch?->id]) }}" 
                           class="bg-primary-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-primary-700 transition whitespace-nowrap">
                            Book Appointment
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Left Column: Services & Staff -->
                    <div class="lg:col-span-2 space-y-8">
                        <!-- About Section -->
                        @if($tenant->description || $tenant->bio)
                            <section>
                                <h2 class="text-xl font-semibold text-gray-900 mb-4">About</h2>
                                <div class="prose prose-gray max-w-none">
                                    @if($tenant->description)
                                        <p class="text-gray-700">{{ $tenant->description }}</p>
                                    @endif
                                    @if($tenant->bio)
                                        <p class="text-gray-600 mt-2">{{ $tenant->bio }}</p>
                                    @endif
                                </div>
                            </section>
                        @endif

                        <!-- Services by Category -->
                        <section>
                            <h2 class="text-xl font-semibold text-gray-900 mb-6">Services</h2>
                            @foreach($servicesByCategory as $category)
                                @if($category->services->isNotEmpty())
                                    <div class="mb-8">
                                        <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center gap-2">
                                            <span class="px-2 py-1 text-xs font-medium bg-primary-50 text-primary-700 rounded-full">{{ $category->name }}</span>
                                        </h3>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            @foreach($category->services as $service)
                                                @php
                                                    $price = $primaryBranch ? $service->getPriceForBranch($primaryBranch) : $service->price_inr;
                                                    $duration = $primaryBranch ? $service->getDurationForBranch($primaryBranch) : $service->duration_minutes;
                                                    $buffer = $primaryBranch ? $service->getBufferForBranch($primaryBranch) : $service->buffer_minutes;
                                                @endphp
                                                <div class="border border-gray-200 rounded-lg p-4 hover:border-primary-200 transition-colors">
                                                    <div class="flex justify-between items-start mb-2">
                                                        <h4 class="font-medium text-gray-900">{{ $service->name }}</h4>
                                                        <span class="text-lg font-bold text-primary-600">₹{{ number_format($price) }}</span>
                                                    </div>
                                                    @if($service->description)
                                                        <p class="text-sm text-gray-600 mb-3 line-clamp-2">{{ $service->description }}</p>
                                                    @endif
                                                    <div class="flex items-center gap-4 text-sm text-gray-500">
                                                        <span class="flex items-center gap-1">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                            {{ $duration + $buffer }} min
                                                        </span>
                                                        @if($buffer > 0)
                                                            <span class="text-xs text-gray-400">(incl. {{ $buffer }} min prep)</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </section>

                        <!-- Staff -->
                        @if($staff->isNotEmpty())
                            <section>
                                <h2 class="text-xl font-semibold text-gray-900 mb-6">Our Team</h2>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                    @foreach($staff as $member)
                                        <div class="bg-white border border-gray-200 rounded-xl p-6 text-center">
                                            <div class="w-24 h-24 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                                                <svg class="w-12 h-12 text-gray-400" fill="currentColor" viewBox="0 0 24 24"><path d="M24 20.993V24H0v-3.007A12.005 12.005 0 0112 9c2.812 0 5.397.94 7.465 2.472A10.015 10.015 0 0124 20.993z"></path></svg>
                                            </div>
                                            <h4 class="font-semibold text-gray-900">{{ $member->name }}</h4>
                                            @if($member->bio)
                                                <p class="text-sm text-gray-600 mt-1 line-clamp-2">{{ $member->bio }}</p>
                                            @endif
                                            <div class="mt-3 flex flex-wrap justify-center gap-1">
                                                @foreach($member->services->take(3) as $service)
                                                    <span class="px-2 py-0.5 text-xs bg-primary-50 text-primary-700 rounded">{{ $service->name }}</span>
                                                @endforeach
                                                @if($member->services->count() > 3)
                                                    <span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-600 rounded">+{{ $member->services->count() - 3 }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    </div>

                    <!-- Right Column: Branch Info & Booking CTA -->
                    <div class="lg:col-span-1">
                        <div class="sticky top-24 space-y-6">
                            <!-- Selected Branch Card -->
                            <div class="bg-white border border-gray-200 rounded-xl p-6">
                                <h3 class="font-semibold text-gray-900 mb-4">{{ $primaryBranch?->name ?? 'Select a Branch' }}</h3>
                                
                                @if($primaryBranch)
                                    <div class="space-y-3 text-sm text-gray-600">
                                        @if($primaryBranch->address_line1)
                                            <p class="flex items-start gap-2">
                                                <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                                <span>{{ $primaryBranch->address_line1 }} @if($primaryBranch->address_line2), {{ $primaryBranch->address_line2 }}@endif</span>
                                            </p>
                                        @endif
                                        @if($primaryBranch->city || $primaryBranch->state)
                                            <p class="flex items-center gap-2">
                                                <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                                <span>{{ $primaryBranch->city }}{{ $primaryBranch->state ? ', ' . $primaryBranch->state : '' }}</span>
                                            </p>
                                        @endif
                                        @if($primaryBranch->phone)
                                            <p class="flex items-center gap-2">
                                                <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V3.28a2 2 0 01.684-1.948l.008-.002z"></path></svg>
                                                <a href="tel:{{ $primaryBranch->phone }}" class="hover:text-primary-600">{{ $primaryBranch->phone }}</a>
                                            </p>
                                        @endif
                                    </div>

                                    <!-- Hours -->
                                    @if($primaryBranch->branchHours->isNotEmpty())
                                        <div class="mt-6 pt-6 border-t border-gray-100">
                                            <h4 class="font-medium text-gray-900 mb-3">Hours</h4>
                                            <dl class="space-y-2 text-sm">
                                                @php
                                                    $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                                                @endphp
                                                @foreach($dayNames as $index => $dayName)
                                                    @php
                                                        $hours = $primaryBranch->branchHours->firstWhere('day_of_week', $index);
                                                    @endphp
                                                    <div class="flex justify-between">
                                                        <dt class="text-gray-600">{{ $dayName }}</dt>
                                                        <dd class="font-medium {{ $hours && !$hours->is_closed ? 'text-gray-900' : 'text-gray-400' }}">
                                                            @if($hours && !$hours->is_closed)
                                                                {{ \Carbon\Carbon::parse($hours->open_time)->format('g:i A') }} - {{ \Carbon\Carbon::parse($hours->close_time)->format('g:i A') }}
                                                            @else
                                                                Closed
                                                            @endif
                                                        </dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        </div>
                                    @endif
                                @endif
                            </div>

                            <!-- Quick Book CTA -->
                            <a href="{{ route('marketplace.booking.show', ['tenant' => $tenant->slug, 'branch_id' => $primaryBranch?->id]) }}" 
                               class="block w-full bg-primary-600 text-white py-4 rounded-xl text-center font-semibold text-lg hover:bg-primary-700 transition shadow-lg hover:shadow-primary-500/25">
                                Book Appointment at {{ $primaryBranch?->name ?? 'This Salon' }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function salonPage() {
            return {
                // Component logic here if needed
            }
        }
    </script>
</x-marketplace.layouts.app>