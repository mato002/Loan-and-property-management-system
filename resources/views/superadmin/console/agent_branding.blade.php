@extends('layouts.superadmin', ['title' => 'Branding — '.$agent->name.' — Super Admin'])

@section('content')
    <div class="mb-6">
        <a href="{{ route('superadmin.agent_branding') }}" class="text-sm font-semibold text-[#2f4f4f] hover:underline">&larr; All agent branding</a>
        <div class="mt-4 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-900">Workspace branding</h1>
                <p class="mt-1 text-sm text-slate-600">Editing branding for <span class="font-semibold text-slate-900">{{ $agent->name }}</span> ({{ $agent->email }}). You stay logged in as Super Admin — no impersonation.</p>
            </div>
            <a href="{{ route('superadmin.agent_workspaces.show', $agent) }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Workspace details</a>
        </div>
    </div>

    <div class="mb-6 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
        Switch agent below to load another workspace’s branding without leaving this screen. Saves apply only to the selected agent.
    </div>

    <form method="get" action="{{ route('superadmin.agent_branding') }}" class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label for="switch-agent" class="block text-xs font-semibold text-slate-600">Agent</label>
                <select
                    id="switch-agent"
                    name="agent"
                    class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    onchange="if (this.value) { window.location = '{{ url('/superadmin/agent-workspaces') }}/' + this.value + '/branding'; }"
                >
                    @foreach ($agents as $row)
                        <option value="{{ $row->id }}" @selected((int) $row->id === (int) $agent->id)>{{ $row->name }} ({{ $row->email }})</option>
                    @endforeach
                </select>
            </div>
            <noscript>
                <button type="submit" class="rounded-xl bg-[#2f4f4f] px-4 py-2.5 text-sm font-bold text-white">Switch</button>
            </noscript>
        </div>
    </form>

    <div class="grid gap-6 lg:grid-cols-2">
        <form
            method="post"
            action="{{ route('superadmin.agent_workspaces.branding.store', $agent) }}"
            enctype="multipart/form-data"
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4"
        >
            @csrf

            <div>
                <label class="block text-xs font-semibold text-slate-600">Company name</label>
                <input type="text" name="company_name" value="{{ old('company_name', $companyName) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g. Acme Properties Ltd" />
                <p class="mt-1 text-xs text-slate-500">Shown on invoices, receipts, prints, and this agent’s portal.</p>
                @error('company_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            @include('property.agent.settings.partials.brand_palette_field')

            <div>
                <label class="block text-xs font-semibold text-slate-600">Portal color theme</label>
                <select name="portal_color_theme" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="light" @selected(old('portal_color_theme', $portalColorTheme ?? 'light') === 'light')>Light (default)</option>
                    <option value="dark" @selected(old('portal_color_theme', $portalColorTheme ?? 'light') === 'dark')>Dark</option>
                </select>
                @error('portal_color_theme')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600">Public website domain</label>
                <input type="text" name="public_website_domain" value="{{ old('public_website_domain', $publicWebsiteDomain ?? '') }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g. gaithoproperties.co.ke" />
                @error('public_website_domain')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600">Upload logo</label>
                <input type="file" name="company_logo" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml" class="mt-1 block w-full text-sm text-slate-600 file:mr-2 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2" />
                @error('company_logo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600">Logo URL (optional)</label>
                <input type="text" name="company_logo_url" value="{{ old('company_logo_url', $companyLogoUrl) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="https://… or /storage/property/branding/…" />
                @error('company_logo_url')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                Remove current logo
            </label>

            <div class="border-t border-slate-200 pt-4">
                <label class="block text-xs font-semibold text-slate-600">Upload favicon</label>
                <input type="file" name="site_favicon" accept="image/png,image/x-icon,image/vnd.microsoft.icon,image/svg+xml" class="mt-1 block w-full text-sm text-slate-600 file:mr-2 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2" />
                @error('site_favicon')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600">Favicon URL (optional)</label>
                <input type="text" name="site_favicon_url" value="{{ old('site_favicon_url', $siteFaviconUrl) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                @error('site_favicon_url')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="remove_favicon" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                Remove current favicon
            </label>

            <div class="space-y-3 border-t border-slate-200 pt-4">
                <h3 class="text-sm font-bold text-slate-900">Contact details</h3>
                <div>
                    <label class="block text-xs font-semibold text-slate-600">Primary email</label>
                    <input type="email" name="contact_email_primary" value="{{ old('contact_email_primary', $contactEmailPrimary) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    @error('contact_email_primary')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600">Support email</label>
                    <input type="email" name="contact_email_support" value="{{ old('contact_email_support', $contactEmailSupport) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    @error('contact_email_support')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600">Phone</label>
                        <input type="text" name="contact_phone" value="{{ old('contact_phone', $contactPhone) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('contact_phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600">WhatsApp</label>
                        <input type="text" name="contact_whatsapp" value="{{ old('contact_whatsapp', $contactWhatsapp) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="+254…" />
                        @error('contact_whatsapp')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600">Address</label>
                    <textarea name="contact_address" rows="2" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('contact_address', $contactAddress) }}</textarea>
                    @error('contact_address')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600">Registration number</label>
                    <input type="text" name="contact_reg_no" value="{{ old('contact_reg_no', $contactRegNo) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    @error('contact_reg_no')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600">Map embed URL</label>
                    <input type="url" name="contact_map_embed_url" value="{{ old('contact_map_embed_url', $contactMapEmbedUrl) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    @error('contact_map_embed_url')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <button type="submit" class="rounded-xl bg-[#2f4f4f] px-5 py-2.5 text-sm font-bold text-white hover:bg-[#264040]">
                Save branding for {{ $agent->name }}
            </button>
        </form>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900">Current preview</h2>
            <p class="mt-1 text-xs text-slate-500">What this agent’s documents will show after save.</p>
            <div class="mt-4 rounded-xl border border-slate-200 p-4">
                <p class="text-sm font-semibold text-slate-900">{{ $companyName !== '' ? $companyName : 'Company name not set' }}</p>
                @if ($companyLogoUrl)
                    <img src="{{ $companyLogoUrl }}" alt="Company logo" class="mt-3 h-16 w-auto rounded border border-slate-200 bg-white object-contain p-1" />
                @else
                    <p class="mt-3 text-xs text-slate-500">No logo configured yet.</p>
                @endif
                @if ($siteFaviconUrl)
                    <div class="mt-3 flex items-center gap-2 text-xs text-slate-500">
                        <img src="{{ $siteFaviconUrl }}" alt="Favicon" class="h-5 w-5 rounded-sm border border-slate-200 bg-white p-0.5" />
                        <span>Favicon configured</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
