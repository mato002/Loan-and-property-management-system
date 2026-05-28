@props([
    'companyName' => '',
    'companyLogoUrl' => '',
    'contactEmailPrimary' => '',
    'contactPhone' => '',
    'contactAddress' => '',
    'contactRegNo' => '',
    'contactMapEmbedUrl' => '',
])

<footer class="bg-gray-950 text-gray-300 pt-12 sm:pt-16 pb-28 md:pb-10 border-t border-gray-800">
    <div class="public-container">
        {{-- Newsletter --}}
        <div class="public-newsletter mb-10 sm:mb-12 public-animate-in">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-center">
                <div>
                    <p class="text-emerald-300 text-xs font-bold uppercase tracking-wider mb-2">Stay updated</p>
                    <h3 class="text-xl sm:text-2xl font-black text-white mb-2">Get new listings in your inbox</h3>
                    <p class="text-sm text-emerald-100/80">Be first to know when verified homes match your area and budget.</p>
                </div>
                <form action="{{ route('public.contact.store') }}" method="POST" class="flex flex-col sm:flex-row gap-2">
                    @csrf
                    <input type="hidden" name="intent" value="newsletter">
                    <input type="email" name="email" required placeholder="Your email address" class="flex-1 min-h-[2.75rem] rounded-xl border-0 px-4 text-sm text-gray-900 outline-none focus:ring-2 focus:ring-emerald-300">
                    <button type="submit" class="public-btn public-btn-primary !rounded-xl whitespace-nowrap">Subscribe</button>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-8 lg:gap-6">
            <div class="col-span-2 lg:col-span-1">
                <a href="{{ route('public.home') }}" class="inline-flex items-center gap-2 mb-4">
                    @if ($companyLogoUrl)
                        <img src="{{ $companyLogoUrl }}" alt="{{ $companyName }}" class="h-10 w-auto max-w-[9rem] object-contain bg-white rounded-lg px-1">
                    @endif
                    <span class="text-lg font-black text-white">{{ $companyName }}</span>
                </a>
                <p class="text-xs sm:text-sm text-gray-400 leading-relaxed max-w-xs">
                    Kenya's modern property marketplace — discover verified rentals backed by professional management operations.
                </p>
            </div>

            <div>
                <h4 class="text-xs font-black uppercase tracking-wider text-white mb-3">Discover</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('public.properties') }}" class="hover:text-emerald-400 transition-colors">All properties</a></li>
                    <li><a href="{{ route('public.properties', ['unit_type' => 'apartment', 'sort' => 'featured']) }}" class="hover:text-emerald-400 transition-colors">Apartments</a></li>
                    <li><a href="{{ route('public.properties', ['unit_type' => 'bedsitter', 'sort' => 'featured']) }}" class="hover:text-emerald-400 transition-colors">Bedsitters</a></li>
                    <li><a href="{{ route('public.properties', ['sort' => 'featured']) }}" class="hover:text-emerald-400 transition-colors">Featured listings</a></li>
                </ul>
            </div>

            <div>
                <h4 class="text-xs font-black uppercase tracking-wider text-white mb-3">Company</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('public.about') }}" class="hover:text-emerald-400 transition-colors">About us</a></li>
                    <li><a href="{{ route('public.contact', ['intent' => 'viewing']) }}" class="hover:text-emerald-400 transition-colors">Schedule viewing</a></li>
                    <li><a href="{{ route('public.apply') }}" class="hover:text-emerald-400 transition-colors">Rental application</a></li>
                    <li><a href="{{ route('public.contact') }}" class="hover:text-emerald-400 transition-colors">Contact</a></li>
                </ul>
            </div>

            <div class="footer-portal-login">
                <h4 class="text-xs font-black uppercase tracking-wider text-white mb-3">Portal access</h4>
                <ul class="space-y-2">
                    <li><a href="{{ route('property.tenant.login') }}" class="footer-login-btn footer-login-tenant !text-xs">Tenant login</a></li>
                    <li><a href="{{ route('property.landlord.login') }}" class="footer-login-btn footer-login-landlord !text-xs">Landlord login</a></li>
                    <li><a href="{{ route('login') }}" class="footer-login-btn footer-login-staff !text-xs">Staff login</a></li>
                </ul>
            </div>

            <div class="col-span-2 lg:col-span-1">
                <h4 class="text-xs font-black uppercase tracking-wider text-white mb-3">Contact</h4>
                <ul class="space-y-2.5 text-sm">
                    <li class="flex gap-2"><svg class="w-4 h-4 text-emerald-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg><span class="break-words">{!! nl2br(e($contactAddress)) !!}</span></li>
                    <li><a href="mailto:{{ $contactEmailPrimary }}" class="hover:text-emerald-400">{{ $contactEmailPrimary }}</a></li>
                    <li>{{ $contactPhone }}</li>
                    @if ($contactRegNo)
                        <li class="text-xs text-gray-500">Reg: {{ $contactRegNo }}</li>
                    @endif
                </ul>
            </div>
        </div>

        @if ($contactMapEmbedUrl)
            <div class="mt-10 rounded-2xl overflow-hidden border border-gray-800 h-48 sm:h-56">
                <iframe title="{{ $companyName }} office location" src="{{ $contactMapEmbedUrl }}" class="w-full h-full" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        @endif

        <div class="mt-8 pt-6 border-t border-gray-800 flex flex-col sm:flex-row justify-between items-center gap-3 text-xs text-gray-500">
            <p>&copy; {{ date('Y') }} {{ $companyName }}. All rights reserved.</p>
            <div class="flex gap-4">
                <a href="{{ route('public.privacy') }}" class="hover:text-white transition-colors">Privacy</a>
                <a href="{{ route('public.terms') }}" class="hover:text-white transition-colors">Terms</a>
            </div>
        </div>
    </div>
</footer>
