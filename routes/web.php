<?php

use App\Http\Controllers\Auth\ChooseModuleController;
use App\Support\Auth\StaffModuleRedirect;
use App\Http\Controllers\Integrations\MpesaDarajaWebhookController;
use App\Http\Controllers\Loan\LoanPaymentWebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Property\PropertyCommunicationWebhookController;
use App\Http\Controllers\Property\PropertyPaymentWebhookController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\PublicListingMediaController;
use App\Http\Middleware\EnsureLoanAccessPolicy;
use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

// Public, no-auth invoice share / download. Token is a random 40-char
// opaque string stored on pm_invoices.share_token. The controller resolves
// outside of agent global scopes so tenants without portal accounts can
// still view / download their bill via SMS or email link.
Route::get('/invoices/p/{token}', [\App\Http\Controllers\Property\Agent\PmInvoiceController::class, 'publicShow'])
    ->where('token', '[A-Za-z0-9]{20,80}')
    ->name('property.invoices.public.show');
Route::get('/invoices/p/{token}/pdf', [\App\Http\Controllers\Property\Agent\PmInvoiceController::class, 'publicPdf'])
    ->where('token', '[A-Za-z0-9]{20,80}')
    ->name('property.invoices.public.pdf');

Route::get('/manifest.webmanifest', [\App\Http\Controllers\PwaManifestController::class, 'public'])->name('pwa.manifest');
Route::get('/property/manifest.webmanifest', [\App\Http\Controllers\PwaManifestController::class, 'portal'])->name('pwa.manifest.portal');

Route::get('/', [PublicController::class, 'home'])->name('public.home');
Route::get('/media/unit-listings/{path}', [PublicListingMediaController::class, 'show'])
    ->where('path', '.*')
    ->name('public.unit_listing_media');
Route::get('/properties', [PublicController::class, 'properties'])->name('public.properties');
Route::get('/properties/{id}', [PublicController::class, 'propertyDetails'])->name('public.property_details');
Route::get('/about', [PublicController::class, 'about'])->name('public.about');
Route::get('/contact', [PublicController::class, 'contact'])->name('public.contact');
Route::post('/contact', [PublicController::class, 'contactStore'])->name('public.contact.store');
Route::get('/apply', [PublicController::class, 'apply'])->name('public.apply');
Route::post('/apply', [PublicController::class, 'applyStore'])->name('public.apply.store');
Route::get('/thank-you', [PublicController::class, 'thankYou'])->name('public.thank_you');
Route::view('/privacy-policy', 'public.privacy')->name('public.privacy');
Route::view('/terms-of-service', 'public.terms')->name('public.terms');
Route::get('/seo/health', function () {
    $sitemapUrl = url('/sitemap.xml');
    $robotsUrl = url('/robots.txt');
    $health = [
        'sitemap_url' => $sitemapUrl,
        'robots_url' => $robotsUrl,
        'sitemap_route_exists' => Route::has('seo.sitemap'),
        'robots_route_exists' => Route::has('seo.robots'),
        'public_routes' => [
            'home' => route('public.home'),
            'properties' => route('public.properties'),
            'about' => route('public.about'),
            'contact' => route('public.contact'),
        ],
    ];

    return view('seo.health', ['health' => $health]);
})->name('seo.health');

Route::get('/robots.txt', function () {
    $lines = [
        'User-agent: *',
        'Allow: /',
        'Disallow: /dashboard',
        'Disallow: /loan',
        'Disallow: /property',
        'Disallow: /superadmin',
        'Sitemap: '.url('/sitemap.xml'),
    ];

    return response(implode("\n", $lines)."\n", 200)
        ->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('seo.robots');

Route::get('/sitemap.xml', function () {
    $viewLastMod = function (string $viewPath, string $fallback): string {
        $full = resource_path('views/'.str_replace('.', '/', $viewPath).'.blade.php');
        if (is_file($full)) {
            $mtime = @filemtime($full);
            if (is_int($mtime) && $mtime > 0) {
                return Carbon::createFromTimestamp($mtime)->toAtomString();
            }
        }

        return $fallback;
    };
    $now = now()->toAtomString();

    $urls = [
        ['loc' => url('/'), 'lastmod' => $viewLastMod('public.home', $now), 'changefreq' => 'daily', 'priority' => '1.0'],
        ['loc' => url('/properties'), 'lastmod' => $viewLastMod('public.properties', $now), 'changefreq' => 'daily', 'priority' => '0.9'],
        ['loc' => url('/about'), 'lastmod' => $viewLastMod('public.about', $now), 'changefreq' => 'monthly', 'priority' => '0.7'],
        ['loc' => url('/contact'), 'lastmod' => $viewLastMod('public.contact', $now), 'changefreq' => 'monthly', 'priority' => '0.7'],
        ['loc' => url('/privacy-policy'), 'lastmod' => $viewLastMod('public.privacy', $now), 'changefreq' => 'yearly', 'priority' => '0.4'],
        ['loc' => url('/terms-of-service'), 'lastmod' => $viewLastMod('public.terms', $now), 'changefreq' => 'yearly', 'priority' => '0.4'],
    ];

    if (class_exists(Property::class) && Schema::hasTable('property_units')) {
        try {
            \App\Models\PropertyUnit::query()
                ->publiclyListed()
                ->whereHas('property')
                ->select(['id', 'updated_at'])
                ->orderByDesc('updated_at')
                ->limit(500)
                ->get()
                ->each(function ($unit) use (&$urls) {
                    $urls[] = [
                        'loc' => route('public.property_details', ['id' => $unit->id]),
                        'lastmod' => optional($unit->updated_at)->toAtomString() ?: now()->toAtomString(),
                        'changefreq' => 'weekly',
                        'priority' => '0.8',
                    ];
                });
        } catch (Throwable) {
            // Keep sitemap generation resilient even if schema differs.
        }
    }

    $xml = view('seo.sitemap', ['urls' => $urls])->render();

    return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('seo.sitemap');
Route::post('/webhooks/property/payments/stk-callback', [PropertyPaymentWebhookController::class, 'stkCallback'])
    ->withoutMiddleware([PreventRequestForgery::class])
    ->name('webhooks.property.payments.stk_callback');
Route::post('/webhooks/property/payments/sms-ingest', [PropertyPaymentWebhookController::class, 'smsIngest'])
    ->withoutMiddleware([PreventRequestForgery::class])
    ->name('webhooks.property.payments.sms_ingest');
Route::post('/webhooks/property/communications/sms-delivery', [PropertyCommunicationWebhookController::class, 'smsDelivery'])
    ->withoutMiddleware([PreventRequestForgery::class])
    ->name('webhooks.property.communications.sms_delivery');
Route::post('/webhooks/property/communications/sms-inbound', [PropertyCommunicationWebhookController::class, 'smsInbound'])
    ->withoutMiddleware([PreventRequestForgery::class])
    ->name('webhooks.property.communications.sms_inbound');
Route::post('/webhooks/property/communications/pradytec', [PropertyCommunicationWebhookController::class, 'pradytec'])
    ->withoutMiddleware([PreventRequestForgery::class])
    ->name('webhooks.property.communications.pradytec');
Route::post('/webhooks/loan/payments/sms-ingest', [LoanPaymentWebhookController::class, 'smsIngest'])
    ->withoutMiddleware([PreventRequestForgery::class])
    ->name('webhooks.loan.payments.sms_ingest');

// Safaricom Daraja STK callback (raw Daraja format)
Route::post('/webhooks/mpesa/stk-callback', [MpesaDarajaWebhookController::class, 'stkCallback'])
    ->withoutMiddleware([PreventRequestForgery::class])
    ->name('webhooks.mpesa.stk_callback');

// Safaricom Daraja B2C Result URL callback
Route::post('/webhooks/mpesa/b2c-result', [MpesaDarajaWebhookController::class, 'b2cResultCallback'])
    ->withoutMiddleware([PreventRequestForgery::class])
    ->name('webhooks.mpesa.b2c_result');
Route::post('/webhooks/property/payments/bank/{provider}', [PropertyPaymentWebhookController::class, 'bankCallback'])
    ->whereIn('provider', ['kcb', 'equity', 'coop'])
    ->withoutMiddleware([PreventRequestForgery::class])
    ->name('webhooks.property.payments.bank_callback');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        $user = auth()->user();
        $system = request()->session()->get('active_system');

        if (! in_array($system, ['property', 'loan'], true)) {
            $preferred = StaffModuleRedirect::preferredModule(request());
            if ($preferred !== null && $user && StaffModuleRedirect::isModuleAllowed($user, $preferred)) {
                StaffModuleRedirect::rememberModule(request(), $preferred);
                $system = $preferred;
            }
        }

        if ($system === 'property' && $user) {
            return redirect()->route(StaffModuleRedirect::destinationRouteName($user, 'property'));
        }

        if ($system === 'loan' && $user) {
            return redirect()->route(StaffModuleRedirect::destinationRouteName($user, 'loan'));
        }

        return redirect()->route('choose_module');
    })->name('dashboard');

    Route::get('/choose-module', [ChooseModuleController::class, 'show'])->name('choose_module');
    Route::match(['get', 'post'], '/choose-module/activate/{module}', [ChooseModuleController::class, 'activate'])
        ->where('module', 'property|loan')
        ->name('choose_module.activate');

    Route::get('/switch-module/{module}', [ChooseModuleController::class, 'activate'])
        ->where('module', 'property|loan')
        ->name('module.switch');

    Route::get('/subscription/required', function () {
        return view('subscription.none');
    })->name('subscription.required');

    Route::get('/subscription/expired', function () {
        return view('subscription.expired');
    })->name('subscription.expired');

    Route::get('/subscription/payment', function () {
        return view('subscription.payment_pending');
    })->name('subscription.payment');

    Route::get('/subscription/renewal', function () {
        return view('subscription.renewal');
    })->name('subscription.renewal');

    require __DIR__.'/groups/superadmin.php';

    Route::middleware(['module.access:loan', EnsureLoanAccessPolicy::class])->group(function () {
        require __DIR__.'/groups/loan.php';
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile/devices/others', [ProfileController::class, 'removeOtherDevices'])->name('profile.devices.others.destroy');
    Route::delete('/profile/devices/{sessionId}', [ProfileController::class, 'removeDevice'])->name('profile.devices.destroy');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
