@php($title = 'Agent Subscriptions — Super Admin')
@extends('layouts.superadmin', ['title' => $title])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-black tracking-tight text-slate-900">Agent Subscriptions</h1>
        <p class="mt-1 text-sm text-slate-600">Manage agent subscriptions and payment records.</p>
    </div>

    @if (!$tablesReady)
        <div class="rounded-2xl border border-amber-200 bg-amber-50/50 p-6 shadow-sm">
            <div class="flex items-center gap-3 mb-4">
                <div class="bg-amber-100 p-2 rounded-lg">
                    <svg class="h-6 w-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-amber-900">Database Tables Not Ready</h3>
            </div>
            <p class="text-amber-700">The agent subscriptions database tables have not been created yet. Please run the database migrations first.</p>
        </div>
    @else
        {{-- Add Subscription Form --}}
        <div class="rounded-2xl bg-white p-6 shadow-sm mb-8">
            <h2 class="text-lg font-bold tracking-tight text-slate-900 mb-4">Add New Subscription</h2>
            <form action="{{ route('superadmin.console.subscriptions.store') }}" method="POST" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Agent</label>
                        <select name="user_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">Select Agent</option>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent->id }}">{{ $agent->name }} ({{ $agent->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Package</label>
                        <select name="subscription_package_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">Select Package</option>
                            @foreach ($packages as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <select name="status" required class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Start Date</label>
                        <input type="date" name="starts_at" required class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">End Date (optional)</label>
                        <input type="date" name="ends_at" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Price Paid (KSH)</label>
                        <input type="number" name="price_paid" min="0" step="0.01" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Payment Method</label>
                        <input type="text" name="payment_method" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Payment Reference</label>
                        <input type="text" name="payment_reference" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                    Add Subscription
                </button>
            </form>
        </div>

        {{-- Filters --}}
        <div class="rounded-2xl bg-white p-6 shadow-sm mb-8">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Search</label>
                    <input type="text" name="q" value="{{ $q }}" placeholder="Agent name or email..." class="rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                    <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">All Statuses</option>
                        <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                        <option value="suspended" {{ $status === 'suspended' ? 'selected' : '' }}>Suspended</option>
                        <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Package</label>
                    <select name="package" class="rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">All Packages</option>
                        @foreach ($packages as $id => $name)
                            <option value="{{ $id }}" {{ $package == $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 transition-colors">
                    Filter
                </button>
                <a href="{{ route('superadmin.console.subscriptions') }}" class="px-4 py-2 text-slate-600 hover:text-slate-900 transition-colors">
                    Clear
                </a>
            </form>
        </div>

        {{-- Subscriptions List --}}
        <div class="rounded-2xl bg-white shadow-sm">
            <div class="p-6 border-b border-slate-200 flex justify-between items-center">
                <h2 class="text-lg font-bold tracking-tight text-slate-900">Subscriptions ({{ $items->total() }})</h2>
                <div class="flex gap-2">
                    @foreach (['csv', 'xls', 'pdf'] as $format)
                        <a href="?{{ http_build_query(array_merge(request()->query(), ['export' => $format])) }}" 
                           class="px-3 py-1 text-sm bg-slate-100 text-slate-700 rounded hover:bg-slate-200 transition-colors">
                            Export {{ strtoupper($format) }}
                        </a>
                    @endforeach
                </div>
            </div>
            @if ($items->isEmpty())
                <div class="p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-slate-900">No subscriptions found</h3>
                    <p class="mt-1 text-sm text-slate-500">Get started by creating your first agent subscription.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Agent</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Package</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Period</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Payment</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @foreach ($items as $subscription)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-6 py-4">
                                        <div>
                                            <div class="text-sm font-medium text-slate-900">{{ $subscription->user->name }}</div>
                                            <div class="text-sm text-slate-500">{{ $subscription->user->email }}</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div>
                                            <div class="text-sm font-medium text-slate-900">{{ $subscription->subscriptionPackage->name }}</div>
                                            <div class="text-sm text-slate-500">{{ $subscription->subscriptionPackage->formatted_monthly_price }}</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        @switch($subscription->status)
                                            @case('active')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                                    Active
                                                </span>
                                                @break
                                            @case('inactive')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800">
                                                    Inactive
                                                </span>
                                                @break
                                            @case('suspended')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                                    Suspended
                                                </span>
                                                @break
                                            @case('cancelled')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800">
                                                    Cancelled
                                                </span>
                                                @break
                                        @endswitch
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-900">
                                        <div>{{ $subscription->starts_at?->format('M j, Y') }}</div>
                                        @if ($subscription->ends_at)
                                            <div class="text-slate-500">to {{ $subscription->ends_at->format('M j, Y') }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-900">
                                        @if ($subscription->price_paid)
                                            <div>{{ $subscription->formatted_price_paid }}</div>
                                            @if ($subscription->payment_method)
                                                <div class="text-slate-500">{{ $subscription->payment_method }}</div>
                                            @endif
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <form action="{{ route('superadmin.console.subscriptions.delete', $subscription) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this subscription?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-rose-600 hover:text-rose-900">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $items->links() }}
            @endif
        </div>
    @endif
@endsection
