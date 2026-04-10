@extends('layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div class="text-center">
            <div class="mx-auto h-12 w-12 rounded-full bg-amber-100 flex items-center justify-center">
                <svg class="h-6 w-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <h2 class="mt-6 text-3xl font-extrabold text-gray-900">Subscription Required</h2>
            <p class="mt-2 text-sm text-gray-600">
                You need an active subscription to access the property management features.
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

            <div class="space-y-4">
                <h3 class="text-lg font-medium text-gray-900">Choose Your Plan</h3>
                
                <div class="space-y-3">
                    <div class="border border-gray-200 rounded-lg p-4 hover:border-indigo-500 transition-colors">
                        <div class="flex justify-between items-start">
                            <div>
                                <h4 class="font-medium text-gray-900">Starter</h4>
                                <p class="text-sm text-gray-500">1-50 units</p>
                            </div>
                            <div class="text-right">
                                <p class="text-2xl font-bold text-gray-900">KSH 2,500</p>
                                <p class="text-sm text-gray-500">per month</p>
                            </div>
                        </div>
                    </div>

                    <div class="border border-gray-200 rounded-lg p-4 hover:border-indigo-500 transition-colors">
                        <div class="flex justify-between items-start">
                            <div>
                                <h4 class="font-medium text-gray-900">Professional</h4>
                                <p class="text-sm text-gray-500">51-150 units</p>
                            </div>
                            <div class="text-right">
                                <p class="text-2xl font-bold text-gray-900">KSH 6,500</p>
                                <p class="text-sm text-gray-500">per month</p>
                            </div>
                        </div>
                    </div>

                    <div class="border border-gray-200 rounded-lg p-4 hover:border-indigo-500 transition-colors">
                        <div class="flex justify-between items-start">
                            <div>
                                <h4 class="font-medium text-gray-900">Business</h4>
                                <p class="text-sm text-gray-500">151-300 units</p>
                            </div>
                            <div class="text-right">
                                <p class="text-2xl font-bold text-gray-900">KSH 12,000</p>
                                <p class="text-sm text-gray-500">per month</p>
                            </div>
                        </div>
                    </div>

                    <div class="border border-gray-200 rounded-lg p-4 hover:border-indigo-500 transition-colors">
                        <div class="flex justify-between items-start">
                            <div>
                                <h4 class="font-medium text-gray-900">Enterprise</h4>
                                <p class="text-sm text-gray-500">301+ units</p>
                            </div>
                            <div class="text-right">
                                <p class="text-2xl font-bold text-gray-900">KSH 20,000</p>
                                <p class="text-sm text-gray-500">per month</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <a href="{{ route('contact') }}" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Contact Sales to Subscribe
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
