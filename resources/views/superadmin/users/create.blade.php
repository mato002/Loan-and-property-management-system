@php($title = 'Add user — Super Admin')
@extends('layouts.superadmin', ['title' => $title])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-black tracking-tight text-slate-900">Add user</h1>
        <p class="mt-1 text-sm text-slate-600">Create a staff account. You can approve module access and assign roles after creating.</p>
    </div>

    <form method="post" action="{{ route('superadmin.users.store') }}" class="max-w-2xl space-y-6">
        @csrf

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Name</label>
                <input name="name" value="{{ old('name') }}" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required />
                @error('name')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required />
                @error('email')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Password</label>
                <input type="password" name="password" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required />
                @error('password')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Property portal role (optional)</label>
                    <select name="property_portal_role" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">None</option>
                        @foreach (['agent' => 'Agent', 'landlord' => 'Landlord', 'tenant' => 'Tenant'] as $k => $lbl)
                            <option value="{{ $k }}" @selected(old('property_portal_role') === $k)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                    @error('property_portal_role')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2 text-sm font-bold text-slate-700">
                        <input type="checkbox" name="is_super_admin" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                        Super admin (full access)
                    </label>
                </div>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row items-center gap-3">
            <button class="w-full sm:w-auto rounded-xl bg-indigo-600 px-6 py-3 text-sm font-bold text-white hover:bg-indigo-700">Create user</button>
            <a href="{{ route('superadmin.users.index') }}" class="w-full sm:w-auto text-center rounded-xl border border-slate-300 bg-white px-6 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50">Cancel</a>
        </div>
    </form>
@endsection

