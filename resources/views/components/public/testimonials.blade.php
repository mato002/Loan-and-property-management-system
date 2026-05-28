<section {{ $attributes->merge(['class' => 'py-12 sm:py-16 bg-slate-50']) }}>
    <div class="public-container">
        <div class="text-center mb-8 sm:mb-10 public-animate-in">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-600 mb-2">Testimonials</p>
            <h2 class="public-section-title">Trusted by renters & landlords</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6">
            @foreach ([
                ['quote' => 'Found our apartment in Westlands within a week. Photos matched reality and the viewing was well organized.', 'name' => 'Sarah M.', 'role' => 'Tenant, Nairobi'],
                ['quote' => 'As a landlord, I finally get clear monthly reports without chasing my agent. Payouts are on time.', 'name' => 'James K.', 'role' => 'Property owner'],
                ['quote' => 'Maintenance requests get handled quickly. Much better than our previous property manager.', 'name' => 'Amina O.', 'role' => 'Tenant, Mombasa'],
            ] as $t)
                <figure class="public-testimonial-card public-animate-in">
                    <div class="flex gap-0.5 text-amber-400 mb-3" aria-hidden="true">
                        @for ($i = 0; $i < 5; $i++)
                            <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        @endfor
                    </div>
                    <blockquote class="text-sm text-gray-700 leading-relaxed mb-4">"{{ $t['quote'] }}"</blockquote>
                    <figcaption>
                        <p class="font-black text-gray-900 text-sm">{{ $t['name'] }}</p>
                        <p class="text-xs text-gray-500">{{ $t['role'] }}</p>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>
