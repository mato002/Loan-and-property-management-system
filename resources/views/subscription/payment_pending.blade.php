@extends('layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div class="text-center">
            <div class="mx-auto h-12 w-12 rounded-full bg-amber-100 flex items-center justify-center">
                <svg class="h-6 w-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                </svg>
            </div>
            <h2 class="mt-6 text-3xl font-extrabold text-gray-900">Payment Required</h2>
            <p class="mt-2 text-sm text-gray-600">
                Please complete payment to activate your subscription.
            </p>
        </div>

        <div class="bg-white shadow rounded-lg p-6">
            @if (session('error'))
                <div class="mb-4 rounded-md bg-amber-50 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-amber-800">Payment Pending</h3>
                            <div class="mt-2 text-sm text-amber-700">
                                <p>{{ session('error') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if (session('pending_payment_subscription'))
                <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800">Subscription Details</h3>
                            <div class="mt-2 text-sm text-blue-700">
                                <p><strong>Package:</strong> {{ session('pending_payment_subscription')->subscriptionPackage->name }}</p>
                                <p><strong>Price:</strong> {{ session('pending_payment_subscription')->subscriptionPackage->formatted_monthly_price }}</p>
                                <p><strong>Start Date:</strong> {{ session('pending_payment_subscription')->starts_at?->format('F j, Y') ?? 'Not set' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="space-y-4">
                <h3 class="text-lg font-medium text-gray-900">Complete Payment</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="radio" name="payment_method" value="mpesa" class="mr-2" checked>
                                <span class="text-sm text-gray-700">M-PESA</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="payment_method" value="bank" class="mr-2">
                                <span class="text-sm text-gray-700">Bank Transfer</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="payment_method" value="card" class="mr-2">
                                <span class="text-sm text-gray-700">Credit/Debit Card</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Reference (Optional)</label>
                        <input type="text" name="payment_reference" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" placeholder="Transaction ID, Reference Number, etc.">
                    </div>
                </div>

                <div class="mt-6 space-y-3">
                    <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Process Payment
                    </button>
                    
                    <a href="{{ route('contact') }}" class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Contact Support
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
