<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth" data-pwa-context="public">
<head>
    @php
        $companyName = \App\Models\PropertyPortalSetting::getValue('company_name', '') ?: 'Gaitho Properties';
        $companyLogoUrl = \App\Models\PropertyPortalSetting::getValue('company_logo_url', '');
        $siteFaviconUrl = \App\Models\PropertyPortalSetting::getValue('site_favicon_url', '');
        $contactEmailPrimary = \App\Models\PropertyPortalSetting::getValue('contact_email_primary', '') ?: 'info@gaithoproperties.co.ke';
        $contactPhone = \App\Models\PropertyPortalSetting::getValue('contact_phone', '') ?: '0717 018779';
        $contactWhatsapp = \App\Models\PropertyPortalSetting::getValue('contact_whatsapp', '') ?: '254717018779';
        $contactAddress = \App\Models\PropertyPortalSetting::getValue('contact_address', '') ?: "Nairobi, Kenya";
        $contactRegNo = \App\Models\PropertyPortalSetting::getValue('contact_reg_no', '');
        $contactMapEmbedUrl = \App\Models\PropertyPortalSetting::getValue('contact_map_embed_url', '');
        $whatsAppDigits = preg_replace('/\D+/', '', $contactWhatsapp) ?: '254717018779';
        $phoneHref = preg_replace('/[^0-9\+]/', '', $contactPhone) ?: '+254717018779';
        $faviconHref = $siteFaviconUrl !== '' ? $siteFaviconUrl : asset('favicon.ico');
        $faviconVersioned = $faviconHref.'?v='.rawurlencode(substr(md5($faviconHref), 0, 12));
        $currentUrl = url()->current();
        $resolvedPageTitle = isset($publicPageTitle) && trim((string) $publicPageTitle) !== ''
            ? trim((string) $publicPageTitle).' | '.$companyName
            : $companyName.' — Verified Rentals in Kenya';
        $resolvedDescription = isset($publicPageDescription) && trim((string) $publicPageDescription) !== ''
            ? trim((string) $publicPageDescription)
            : 'Discover verified rental properties across Kenya. Browse apartments, bedsitters and managed homes with professional property operations.';
        $resolvedOgImage = isset($publicPageImage) && trim((string) $publicPageImage) !== ''
            ? trim((string) $publicPageImage)
            : ($companyLogoUrl !== '' ? $companyLogoUrl : \App\Http\Controllers\PublicController::LISTING_PLACEHOLDER_IMAGE);
        $resolvedRobots = isset($publicPageRobots) && trim((string) $publicPageRobots) !== ''
            ? trim((string) $publicPageRobots)
            : 'index,follow';
        $resolvedLocale = str_replace('_', '-', app()->getLocale());
        $schemaGraph = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'RealEstateAgent',
                    'name' => $companyName,
                    'url' => url('/'),
                    'logo' => $resolvedOgImage,
                    'email' => $contactEmailPrimary,
                    'telephone' => $contactPhone,
                    'address' => [
                        '@type' => 'PostalAddress',
                        'addressLocality' => 'Nairobi',
                        'addressCountry' => 'KE',
                    ],
                ],
                [
                    '@type' => 'WebSite',
                    'name' => $companyName,
                    'url' => url('/'),
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => url('/properties').'?city={city}&unit_type={unit_type}',
                        'query-input' => 'required name=city',
                    ],
                ],
            ],
        ];
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $resolvedPageTitle }}</title>
    <meta name="description" content="{{ $resolvedDescription }}">
    <meta name="keywords" content="rentals Kenya, apartments Nairobi, property management, verified listings, bedsitter, houses for rent">
    <meta name="robots" content="{{ $resolvedRobots }}">
    <link rel="canonical" href="{{ $currentUrl }}">
    <link rel="alternate" hreflang="{{ $resolvedLocale }}" href="{{ $currentUrl }}">
    <link rel="alternate" hreflang="x-default" href="{{ $currentUrl }}">
    <meta property="og:title" content="{{ $resolvedPageTitle }}">
    <meta property="og:description" content="{{ $resolvedDescription }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $currentUrl }}">
    <meta property="og:image" content="{{ $resolvedOgImage }}">
    <meta property="og:locale" content="en_KE">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $resolvedPageTitle }}">
    <meta name="twitter:description" content="{{ $resolvedDescription }}">
    <meta name="twitter:image" content="{{ $resolvedOgImage }}">
    <link rel="icon" href="{{ $faviconVersioned }}" />
    <link rel="shortcut icon" href="{{ $faviconVersioned }}" />
    <link rel="apple-touch-icon" href="{{ $faviconVersioned }}" />
    <link rel="manifest" href="{{ route('pwa.manifest') }}" />
    <meta name="theme-color" content="#059669" />
    <meta name="mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-title" content="{{ $companyName }}" />
    <meta name="apple-mobile-web-app-status-bar-style" content="default" />
    <script type="application/ld+json">{!! json_encode($schemaGraph, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
    @stack('head')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/public-site.js'])
    <script src="{{ asset('js/pwa-install.js') }}?v=2" defer></script>
    <style>
        .footer-portal-login .footer-login-btn {
            display: flex; width: 100%; align-items: center; justify-content: center; gap: 0.5rem;
            border-radius: 0.5rem; padding: 0.625rem 0.75rem; font-size: 0.875rem; font-weight: 700;
            text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.2); transition: filter 0.15s, transform 0.15s;
        }
        .footer-portal-login .footer-login-btn:hover { filter: brightness(1.08); transform: translateY(-1px); }
        .footer-portal-login .footer-login-tenant { background: #059669; color: #fff !important; border: 1px solid #34d399; }
        .footer-portal-login .footer-login-landlord { background: #f59e0b; color: #111827 !important; border: 1px solid #fcd34d; }
        .footer-portal-login .footer-login-staff { background: #374151; color: #fff !important; border: 1px solid #4b5563; }
    </style>
</head>
<body class="font-sans antialiased text-gray-900 bg-white" x-data="{ mobileMenuOpen: false, scrolled: false }" @scroll.window="scrolled = (window.pageYOffset > 20)">
    <x-swal-flash />

    <x-public.header :company-name="$companyName" :company-logo-url="$companyLogoUrl" />

    <main class="min-h-screen pt-14 sm:pt-16 public-mobile-safe-bottom">
        {{ $slot }}
    </main>

    {{-- Floating WhatsApp + Call --}}
    <div class="fixed z-50 right-3 bottom-[4.5rem] md:bottom-4 flex flex-col gap-2.5">
        <a href="https://wa.me/{{ $whatsAppDigits }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-[#25D366] hover:bg-[#1ebe57] text-white shadow-lg shadow-[#25D366]/30 transition-transform hover:scale-105" aria-label="Chat on WhatsApp">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M20.52 3.48A11.86 11.86 0 0 0 12.06 0C5.5 0 .16 5.34.16 11.9c0 2.1.55 4.14 1.6 5.95L0 24l6.32-1.66a11.84 11.84 0 0 0 5.73 1.47h.01c6.56 0 11.9-5.34 11.9-11.9 0-3.18-1.24-6.16-3.44-8.43ZM12.06 21.8h-.01a9.8 9.8 0 0 1-4.99-1.37l-.36-.21-3.75.98 1-3.65-.24-.37a9.82 9.82 0 0 1-1.52-5.28c0-5.4 4.39-9.8 9.8-9.8 2.62 0 5.08 1.02 6.92 2.87a9.7 9.7 0 0 1 2.87 6.93c0 5.4-4.4 9.8-9.81 9.8Zm5.38-7.36c-.3-.15-1.77-.88-2.05-.98-.27-.1-.46-.15-.66.15-.2.3-.76.98-.93 1.18-.17.2-.35.23-.65.08-.3-.15-1.26-.46-2.4-1.47a9 9 0 0 1-1.67-2.07c-.18-.3-.02-.46.13-.6.13-.13.3-.35.45-.52.15-.18.2-.3.3-.5.1-.2.05-.38-.02-.53-.08-.15-.66-1.58-.9-2.17-.24-.57-.49-.5-.66-.5h-.57c-.2 0-.53.08-.8.38-.28.3-1.05 1.03-1.05 2.5 0 1.48 1.08 2.9 1.23 3.1.15.2 2.12 3.23 5.13 4.53.72.31 1.29.5 1.73.64.73.23 1.4.2 1.92.12.58-.09 1.77-.72 2.02-1.42.25-.7.25-1.3.17-1.42-.08-.13-.27-.2-.57-.35Z"/></svg>
        </a>
        <a href="tel:{{ $phoneHref }}" class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white shadow-lg shadow-emerald-600/30 transition-transform hover:scale-105" aria-label="Call us">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
        </a>
    </div>

    <x-public.mobile-bottom-nav />

    <x-public.footer
        :company-name="$companyName"
        :company-logo-url="$companyLogoUrl"
        :contact-email-primary="$contactEmailPrimary"
        :contact-phone="$contactPhone"
        :contact-address="$contactAddress"
        :contact-reg-no="$contactRegNo"
        :contact-map-embed-url="$contactMapEmbedUrl"
    />

    <x-public.pwa-install-prompt />
</body>
</html>
