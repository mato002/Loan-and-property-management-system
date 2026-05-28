<x-public-layout
    :page-title="$publicPageTitle ?? null"
    :page-description="$publicPageDescription ?? null"
    :page-robots="$publicPageRobots ?? 'noindex,nofollow'"
>
    <div class="min-h-[60vh] flex flex-col items-center justify-center py-12 sm:py-20 public-container">
        <div class="w-16 h-16 sm:w-20 sm:h-20 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mb-6 shadow-inner">
            <svg class="w-8 h-8 sm:w-10 sm:h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h1 class="text-2xl sm:text-4xl font-black text-gray-900 tracking-tight mb-3 text-center">
            {{ ($thankYouType ?? 'application') === 'contact' ? 'Message received!' : 'Application submitted!' }}
        </h1>
        <p class="text-base text-gray-500 max-w-lg text-center mb-8 leading-relaxed">
            @if (($thankYouType ?? 'application') === 'contact')
                Thank you for reaching out. A property advisor will respond within 24 hours via phone, email, or WhatsApp.
            @else
                Your rental application has been securely submitted. Our team will review it and contact you shortly.
            @endif
        </p>
        <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
            <a href="{{ route('public.home') }}" class="public-btn public-btn-secondary">Return home</a>
            <a href="{{ route('public.properties') }}" class="public-btn public-btn-primary">Browse listings</a>
        </div>
    </div>
</x-public-layout>
