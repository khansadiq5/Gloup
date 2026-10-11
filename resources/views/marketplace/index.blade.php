<x-marketplace.layouts.app :title="'Find Salons - GLOUP'">
    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-primary-50 to-pink-50 py-16 sm:py-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto">
                <h1 class="text-4xl sm:text-5xl font-bold text-gray-900 mb-6">
                    Find Your Perfect <span class="text-primary-600">Salon</span>
                </h1>
                <p class="text-xl text-gray-600 mb-8">
                    Discover top-rated salons near you. Book appointments instantly with verified professionals.
                </p>
                
                <!-- Search Form -->
                <form action="{{ route('marketplace.index') }}" method="GET" class="space-y-4 sm:space-y-0 sm:flex sm:space-x-4">
                    <div class="flex-1">
                        <label for="search" class="sr-only">Search salons, services, or location</label>
                        <input type="text" name="search" id="search" 
                               value="{{ request('search') }}"
                               placeholder="Search salons, services, or location..."
                               class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-lg">
                    </div>
                    <div class="flex-1">
                        <label for="category" class="sr-only">Service Category</label>
                        <select name="category" id="category" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-lg bg-white">
                            <option value="">All Categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1">
                        <label for="city" class="sr-only">City</label>
                        <select name="city" id="city" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-lg bg-white">
                            <option value="">All Cities</option>
                            @foreach($cities as $city)
                                <option value="{{ $city }}" {{ request('city') == $city ? 'selected' : '' }}>
                                    {{ $city }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="bg-primary-600 text-white px-8 py-3 rounded-lg font-semibold text-lg hover:bg-primary-700 transition whitespace-nowrap">
                        Search
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- Results Section -->
    <section class="py-12 -mt-8 relative z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-2xl font-bold text-gray-900">
                    {{ $salons->total() }} Salon{{ $salons->total() !== 1 ? 's' : '' }} Found
                </h2>
                @if(request()->hasAny(['search', 'category', 'city']))
                    <a href="{{ route('marketplace.index') }}" class="text-primary-600 hover:underline text-sm">
                        Clear filters
                    </a>
                @endif
            </div>

            @if($salons->isNotEmpty())
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($salons as $salon)
                        <article class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-300">
                            <div class="aspect-video bg-gray-100 relative">
                                @if($salon->primaryBranch)
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
                                    <div class="absolute bottom-0 left-0 right-0 p-4 text-white">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary-100 text-primary-800">
                                            {{ $salon->primaryBranch->city ?? 'City Center' }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                            
                            <div class="p-6">
                                <h3 class="text-xl font-semibold text-gray-900 mb-2">
                                    <a href="{{ route('marketplace.salon.show', $salon->slug) }}" class="hover:text-primary-600 transition">
                                        {{ $salon->name }}
                                    </a>
                                </h3>
                                
                                @if($salon->primaryBranch)
                                    <p class="text-gray-600 text-sm mb-4 flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                        {{ $salon->primaryBranch->city }}, {{ $salon->primaryBranch->state }}
                                    </p>
                                @endif
                                
                                <div class="flex items-center gap-2 mb-4">
                                    @foreach($salon->serviceCategories->take(3) as $category)
                                        <span class="px-2 py-1 text-xs font-medium bg-primary-50 text-primary-700 rounded-full">
                                            {{ $category->name }}
                                        </span>
                                    @endforeach
                                    @if($salon->serviceCategories->count() > 3)
                                        <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-600 rounded-full">
                                            +{{ $salon->serviceCategories->count() - 3 }} more
                                        </span>
                                    @endif
                                </div>
                                
                                <a href="{{ route('marketplace.salon.show', $salon->slug) }}" 
                                   class="block w-full text-center py-2 px-4 bg-primary-600 text-white rounded-lg font-medium hover:bg-primary-700 transition">
                                    View Salon & Book
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="mt-8">
                    {{ $salons->links() }}
                </div>
            @else
                <div class="text-center py-16">
                    <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h3 class="mt-4 text-lg font-medium text-gray-900">No salons found</h3>
                    <p class="mt-2 text-gray-600">Try adjusting your search or filters.</p>
                    <a href="{{ route('marketplace.index') }}" class="mt-4 inline-block text-primary-600 hover:underline">
                        Clear all filters
                    </a>
                </div>
            @endif
        </div>
    </section>

    <!-- CTA Section -->
    <section class="bg-gray-900 py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl font-bold text-white mb-4">Own a Salon?</h2>
            <p class="text-gray-300 text-lg mb-8 max-w-2xl mx-auto">
                Join thousands of salon owners managing their business with GLOUP. 
                Free to start, powerful features included.
            </p>
            <a href="{{ route('register.owner') }}" class="inline-block bg-primary-600 text-white px-8 py-3 rounded-lg font-semibold text-lg hover:bg-primary-700 transition">
                Register Your Salon Free
            </a>
        </div>
    </section>
</x-marketplace.layouts.app>