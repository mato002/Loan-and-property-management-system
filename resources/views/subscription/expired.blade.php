@extends('layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div class="text-center">
            <div class="mx-auto h-12 w-12 rounded-full bg-red-100 flex items-center justify-center">
                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h2 class="mt-6 text-3xl font-extrabold text-gray-900">Subscription Expired</h2>
            <p class="mt-2 text-sm text-gray-600">
                Your subscription has expired. Please renew to continue using our services.
            </p>
        </div>

        <div class="bg-white shadow rounded-lg p-6">
            @if (session('error'))
                <div class="mb-4 rounded-md bg-red-50 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">Access Denied</h3>
                            <div class="mt-2 text-sm text-red-700">
                                <p>{{ session('error') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if (session('expired_subscription'))
                <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-amber-800">Previous Subscription Details</h3>
                            <div class="mt-2 text-sm text-amber-700">
                                <p><strong>Package:</strong> {{ session('expired_subscription')->subscriptionPackage->name }}</p>
                                <p><strong>Expired:</strong> {{ session('expired_subscription')->ends_at->format('F j, Y') }}</p>
                                <p><strong>Days Expired:</strong> {{ now()->diffInDays(session('expired_subscription')->ends_at) }} days ago</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="space-y-4">
                <h3 class="text-lg font-medium text-gray-900">Renew Your Subscription</h3>
                
                <div class="space-y-3">
                    <div class="border border-gray-200 rounded-lg p-4 hover:border-indigo-500 transition-colors">
                        <div class="flex justify-between items-start">
                            <div>
                                <h4 class="font-medium text-gray-900">Monthly Renewal</h4>
                                <p class="text-sm text-gray-500">Continue with monthly billing</p>
                            </div>
                            <div class="text-right">
                                <p class="text-2xl font-bold text-gray-900">KSH 2,500</p>
                                <p class="text-sm text-gray-500">per month</p>
                            </div>
                        </div>
                    </div>

                    <div class="border border-indigo-500 bg-indigo-50 rounded-lg p-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <h4 class="font-medium text-indigo-900">Annual Savings</h4>
                                <p class="text-sm text-indigo-700">Save 10% with annual billing</p>
                            </div>
                            <div class="text-right">
                                <p class="text-2xl font-bold text-indigo-900">KSH 27,000</p>
                                <p class="text-sm text-indigo-700">per year (2 months free!)</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 space-y-3">
                    <a href="{{ route('contact') }}" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Renew Subscription
                    </a>
                    
                    <a href="{{ route('contact') }}" class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Contact Support
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
