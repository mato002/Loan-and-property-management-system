<x-property-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('property.tenant.payments.history') }}" class="text-gray-400 hover:text-teal-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <h1 class="text-2xl font-semibold text-gray-900">Waiting for payment</h1>
            </div>
            <div class="flex items-center space-x-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                    <svg class="w-3 h-3 mr-1 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Pending
                </span>
            </div>
        </div>
    </x-slot>

    <x-property.page
        title="Complete payment on your phone"
        subtitle="We've sent an M-Pesa prompt. Approve it on your phone to finish checkout."
    >
        <script>
            (function () {
                const statusUrl = @json(route('property.tenant.payments.pending.status', $payment));
                const doneRedirect = @json(route('property.tenant.payments.history'));

                let tries = 0;
                const maxTries = 60; // ~3 minutes at 3s interval

                async function poll() {
                    tries++;
                    if (tries > maxTries) return;

                    try {
                        const res = await fetch(statusUrl, {
                            headers: {
                                'Accept': 'application/json',
                            },
                            credentials: 'same-origin',
                        });
                        if (!res.ok) return;
                        const data = await res.json();
                        const status = (data && data.status) ? String(data.status).toLowerCase() : '';
                        if (status && status !== 'pending') {
                            const url = (data && data.redirect_url) ? String(data.redirect_url) : doneRedirect;
                            window.location.href = url;
                        }
                    } catch (e) {
                        // ignore transient network errors
                    }
                }

                // initial delay then poll
                setTimeout(() => {
                    poll();
                    setInterval(poll, 3000);
                }, 1500);
            })();
        </script>

        <div class="max-w-2xl mx-auto">
            <!-- Main Payment Status Card -->
            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <!-- Animated Header -->
                <div class="bg-gradient-to-r from-teal-600 to-teal-700 px-6 py-5 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/5 rounded-full -mt-16 -mr-16"></div>
                    <div class="absolute bottom-0 left-0 w-24 h-24 bg-white/5 rounded-full -mb-12 -ml-12"></div>
                    <div class="relative">
                        <div class="flex items-center space-x-3">
                            <div class="relative">
                                <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center animate-pulse">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <div class="absolute -top-1 -right-1 w-3 h-3 bg-green-400 rounded-full animate-ping"></div>
                            </div>
                            <div>
                                <h3 class="text-white font-semibold text-lg">Payment in progress</h3>
                                <p class="text-teal-100 text-sm">Please check your phone to complete the transaction</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-6 space-y-6">
                    <!-- STK Prompt Status -->
                    <div class="flex items-start gap-4 p-4 rounded-xl bg-gradient-to-br from-teal-50 to-emerald-50 border border-teal-100">
                        <div class="h-12 w-12 rounded-full bg-teal-100 flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-teal-900">STK prompt sent to your phone</p>
                            <p class="text-sm text-teal-700 mt-1">
                                Open your phone, enter your M-Pesa PIN, and approve the payment.
                            </p>
                            <div class="mt-2 flex items-center space-x-2">
                                <div class="flex space-x-1">
                                    <div class="w-1.5 h-1.5 bg-teal-400 rounded-full animate-bounce" style="animation-delay: 0s"></div>
                                    <div class="w-1.5 h-1.5 bg-teal-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                                    <div class="w-1.5 h-1.5 bg-teal-400 rounded-full animate-bounce" style="animation-delay: 0.4s"></div>
                                </div>
                                <span class="text-xs text-teal-600">Waiting for confirmation...</span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Details Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="rounded-xl bg-gray-50 p-4 hover:shadow-sm transition-shadow">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-[11px] uppercase tracking-wide text-gray-500 font-medium">Amount</p>
                                <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <p class="text-2xl font-bold text-gray-900 tabular-nums">
                                KES {{ number_format((float) $payment->amount, 2) }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-4 hover:shadow-sm transition-shadow">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-[11px] uppercase tracking-wide text-gray-500 font-medium">Status</p>
                                <svg class="w-3 h-3 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="flex items-center space-x-2">
                                <div class="w-2 h-2 bg-amber-500 rounded-full animate-pulse"></div>
                                <p class="text-lg font-semibold text-gray-900">
                                    {{ ucfirst($payment->status) }}
                                </p>
                            </div>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-4 hover:shadow-sm transition-shadow">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-[11px] uppercase tracking-wide text-gray-500 font-medium">Phone number</p>
                                <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                </svg>
                            </div>
                            <p class="text-lg font-semibold text-gray-900 font-mono">
                                {{ data_get($payment->meta, 'phone') ?: '—' }}
                            </p>
                        </div>
                    </div>

                    <!-- Troubleshooting Section -->
                    <div class="rounded-xl bg-gradient-to-br from-amber-50 to-orange-50 border border-amber-200 p-5">
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0">
                                <div class="bg-amber-100 rounded-full p-2">
                                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-amber-900">If you don't see the prompt</p>
                                <ul class="mt-2 space-y-2">
                                    <li class="flex items-start space-x-2 text-xs text-amber-800">
                                        <svg class="w-3 h-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span>Make sure your phone has network coverage and you're using a Safaricom line</span>
                                    </li>
                                    <li class="flex items-start space-x-2 text-xs text-amber-800">
                                        <svg class="w-3 h-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span>Wait 10–30 seconds, then tap "Verify payment" below</span>
                                    </li>
                                    <li class="flex items-start space-x-2 text-xs text-amber-800">
                                        <svg class="w-3 h-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                        </svg>
                                        <span>Your callback URL must be public HTTPS and reachable by Safaricom</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-3 pt-2">
                        <form method="post" action="{{ route('property.tenant.payments.pending.verify', $payment) }}" data-turbo="false" class="flex-1">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-teal-600 to-teal-700 px-4 py-3 text-sm font-semibold text-white hover:from-teal-700 hover:to-teal-800 transition-all duration-200 shadow-sm hover:shadow-md">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Verify payment
                            </button>
                        </form>
                        <a href="{{ route('property.tenant.payments.history') }}" data-turbo="false" class="inline-flex items-center justify-center rounded-xl border-2 border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition-all duration-200 flex-1">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V7a2 2 0 00-2-2H7a2 2 0 00-2 2v2"></path>
                            </svg>
                            View payment history
                        </a>
                    </div>

                    <!-- Auto-refresh Status -->
                    <div class="text-center pt-2">
                        <div class="inline-flex items-center space-x-2 text-xs text-gray-500">
                            <svg class="w-3 h-3 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            <span>Auto-checking payment status every 3 seconds</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Help Card -->
            <div class="mt-4 rounded-xl bg-white border border-gray-200 p-4 shadow-sm">
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0">
                        <div class="bg-teal-50 rounded-lg p-2">
                            <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-xs font-medium text-gray-900">Need assistance?</p>
                        <p class="text-xs text-gray-600 mt-0.5">If your payment isn't going through after 5 minutes, contact support with your transaction reference.</p>
                    </div>
                    <button class="text-xs text-teal-600 hover:text-teal-700 font-medium">Contact support →</button>
                </div>
            </div>
        </div>
    </x-property.page>
</x-property-layout>