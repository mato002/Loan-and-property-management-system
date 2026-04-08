<x-property-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('property.tenant.payments.index') }}" class="text-gray-400 hover:text-teal-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <h1 class="text-2xl font-semibold text-gray-900">Pay bills</h1>
            </div>
            <div class="flex items-center space-x-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Due soon
                </span>
            </div>
        </div>
    </x-slot>

    <x-property.page
        title="Pay bills"
        subtitle="Pay rent and water with Equity STK Push (prompt to phone)."
    >
        <div class="max-w-2xl mx-auto">
            <!-- Main Payment Card -->
            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <!-- Header with gradient -->
                <div class="bg-gradient-to-r from-teal-600 to-teal-700 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-teal-100 text-xs font-medium uppercase tracking-wider">Payment Summary</p>
                            <h3 class="text-white text-xl font-bold mt-1">Tenant Billing Payment</h3>
                        </div>
                        <div class="bg-white/10 rounded-full p-2">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="p-6 space-y-6">
                    <!-- Amount Due Card -->
                    <div class="rounded-xl bg-gradient-to-br from-gray-50 to-gray-100 border border-gray-200 p-5 text-center">
                        <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Amount due now</p>
                        <p class="text-3xl sm:text-4xl font-bold text-gray-900 mt-2 break-words">{{ $amountDue ?? 'KES 0.00' }}</p>
                        <div class="flex items-center justify-center mt-2 space-x-1">
                            <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-xs text-gray-500">Includes rent and water invoices currently due</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="rounded-xl border border-gray-200 bg-white p-3">
                            <p class="text-xs text-gray-500">Rent due</p>
                            <p class="text-sm font-semibold text-gray-900">{{ \App\Services\Property\PropertyMoney::kes((float) ($rentDue ?? 0)) }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-white p-3">
                            <p class="text-xs text-gray-500">Water due</p>
                            <p class="text-sm font-semibold text-gray-900">{{ \App\Services\Property\PropertyMoney::kes((float) ($waterDue ?? 0)) }}</p>
                        </div>
                    </div>

                    <form
                        method="post"
                        action="{{ route('property.tenant.payments.store') }}"
                        class="space-y-5"
                        x-data="{ method: 'mpesa_stk', submitting: false, scope: '{{ old('bill_scope', 'all') }}' }"
                        @submit="submitting = true"
                        data-turbo="false"
                        data-swal-confirm="Send STK prompt to your phone and start payment?"
                    >
                        @csrf
                        <input type="hidden" name="payment_method" value="mpesa_stk" />

                        <!-- What are you paying for? -->
                        <div class="rounded-xl border border-gray-200 bg-white p-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">What are you paying for?</label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <label class="flex items-center gap-2 rounded-lg border border-gray-200 p-3 cursor-pointer hover:border-teal-300">
                                    <input type="radio" name="bill_scope" value="all" class="text-teal-600" x-model="scope" @checked(old('bill_scope','all')==='all') />
                                    <span class="text-sm font-medium text-gray-800">All bills</span>
                                </label>
                                <label class="flex items-center gap-2 rounded-lg border border-gray-200 p-3 cursor-pointer hover:border-teal-300">
                                    <input type="radio" name="bill_scope" value="rent" class="text-teal-600" x-model="scope" @checked(old('bill_scope')==='rent') />
                                    <span class="text-sm font-medium text-gray-800">Rent only</span>
                                </label>
                                <label class="flex items-center gap-2 rounded-lg border border-gray-200 p-3 cursor-pointer hover:border-teal-300">
                                    <input type="radio" name="bill_scope" value="water" class="text-teal-600" x-model="scope" @checked(old('bill_scope')==='water') />
                                    <span class="text-sm font-medium text-gray-800">Water only</span>
                                </label>
                            </div>
                            @error('bill_scope')<p class="text-xs text-red-600 mt-2">{{ $message }}</p>@enderror
                            <p class="text-xs text-gray-500 mt-2">Payments are allocated to the oldest due invoices within your selected category.</p>
                        </div>
                        
                        <div class="rounded-xl border border-gray-200 bg-white p-4">
                            <p class="text-sm font-semibold text-gray-700">Payment method</p>
                            <p class="mt-1 text-sm text-gray-900">Equity STK Push</p>
                        </div>

                        <!-- M-Pesa STK Section -->
                        <div x-show="method === 'mpesa_stk'" x-cloak class="rounded-xl bg-gradient-to-br from-teal-50 to-emerald-50 border border-teal-200 p-5">
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="bg-teal-600 rounded-full p-2">
                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-semibold text-teal-900">Equity STK Push</p>
                                    <p class="text-xs text-teal-700 mt-1">
                                        We'll send a payment prompt directly to your phone. No reference number required.
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="block text-sm font-medium text-teal-900 mb-1">Phone number</label>
                                <input
                                    type="tel"
                                    name="payer_phone"
                                    value="{{ old('payer_phone') }}"
                                    autocomplete="tel"
                                    class="w-full rounded-xl border border-teal-300 bg-white text-sm px-4 py-3 focus:ring-2 focus:ring-teal-500 focus:border-transparent transition-all"
                                    placeholder="0712 345 678 or 0111 296 234"
                                />
                                @error('payer_phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                <p class="text-xs text-teal-600 mt-2 flex items-center">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Ensure your line is active and has sufficient balance
                                </p>
                            </div>
                        </div>

                        <!-- Custom Amount -->
                        <div class="rounded-xl border border-gray-200 bg-gray-50/30 p-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Amount <span class="text-xs text-gray-400 font-normal">(optional)</span></label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-500 font-medium">KES</span>
                                <input 
                                    type="text" 
                                    name="custom_amount" 
                                    value="{{ old('custom_amount') }}" 
                                    inputmode="decimal" 
                                    class="w-full rounded-xl border border-gray-200 bg-white text-sm pl-12 pr-4 py-3 focus:ring-2 focus:ring-teal-500 focus:border-transparent transition-all" 
                                    placeholder="0.00"
                                />
                            </div>
                            <p class="text-xs text-gray-500 mt-2 flex items-center">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Leave blank to pay the full amount due for the selected bill type
                            </p>
                            @error('custom_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            class="w-full rounded-xl bg-gradient-to-r from-teal-600 to-teal-700 py-4 text-sm font-semibold text-white hover:from-teal-700 hover:to-teal-800 disabled:opacity-60 disabled:cursor-not-allowed inline-flex items-center justify-center gap-2 transition-all duration-200 shadow-sm hover:shadow-md"
                            :disabled="submitting"
                        >
                            <svg x-show="submitting" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            <span x-show="!submitting">
                                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                                Send STK prompt
                            </span>
                            <span x-show="submitting">Processing payment...</span>
                        </button>
                        
                        <p class="text-xs text-center text-gray-500 flex items-center justify-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6-4h12a2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2v-6a2 2 0 012-2zm10-10V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2h8z"></path>
                            </svg>
                            STK payments are saved as pending until provider confirmation is received
                        </p>
                    </form>
                </div>
            </div>

        </div>
    </x-property.page>
</x-property-layout>