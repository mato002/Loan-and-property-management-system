<x-property-layout>
    <x-slot name="header">Branding</x-slot>
    @php $isSuperAdmin = (bool) (auth()->user()->is_super_admin ?? false); @endphp

    <x-property.page
        title="Branding"
        subtitle="Set company identity, favicon, public website domain, and contact details."
    >
        @include('property.agent.settings.partials.subnav', ['active' => 'property.settings.branding'])

        @if ($isSuperAdmin && ! empty($brandingEditorAgentUserId ?? null))
            <p class="mb-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-100">
                {{ __('You are editing the branding used on the login page and agent workspace for this deployment.') }}
            </p>
        @endif
        <div class="grid gap-6 lg:grid-cols-2">
            <form method="post" action="{{ route('property.settings.branding.store') }}" enctype="multipart/form-data" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Company name</label>
                    <input type="text" name="company_name" value="{{ old('company_name', $companyName) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="e.g. Acme Properties Ltd" />
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Shown on the public website, login page, invoices, and portal.</p>
                    @error('company_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Portal color theme</label>
                    <select name="portal_color_theme" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="light" @selected(old('portal_color_theme', $portalColorTheme ?? 'light') === 'light')>Light (default)</option>
                        <option value="dark" @selected(old('portal_color_theme', $portalColorTheme ?? 'light') === 'dark')>Dark</option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Controls the property portal appearance for this workspace. This does not follow each user&apos;s browser theme.</p>
                    @error('portal_color_theme')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Public website domain</label>
                    <input type="text" name="public_website_domain" value="{{ old('public_website_domain', $publicWebsiteDomain ?? '') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="e.g. gaithoproperties.co.ke" />
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">When visitors open this domain, they see this workspace’s name, logo, favicon, and contact details. Leave blank if you use env mapping or a single-tenant deployment.</p>
                    @error('public_website_domain')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Upload logo</label>
                    <input type="file" name="company_logo" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml" class="mt-1 block w-full text-sm text-slate-600 dark:text-slate-300 file:mr-2 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 dark:file:bg-slate-800" />
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Recommended: square or wide PNG/JPG, max 100MB.</p>
                    @error('company_logo')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Logo URL (optional)</label>
                    <input type="text" name="company_logo_url" value="{{ old('company_logo_url', $companyLogoUrl) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="https://example.com/logo.png or /storage/property/branding/logo.png" />
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Used only if no new file is uploaded in this save.</p>
                    @error('company_logo_url')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <input type="checkbox" name="remove_logo" value="1" />
                    Remove current logo
                </label>

                <div class="pt-2 border-t border-slate-200 dark:border-slate-700">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Upload favicon</label>
                    <input type="file" name="site_favicon" accept="image/png,image/x-icon,image/vnd.microsoft.icon,image/svg+xml" class="mt-1 block w-full text-sm text-slate-600 dark:text-slate-300 file:mr-2 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 dark:file:bg-slate-800" />
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Best size: 32x32 or 48x48 icon. Max 100MB.</p>
                    @error('site_favicon')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Favicon URL (optional)</label>
                    <input type="text" name="site_favicon_url" value="{{ old('site_favicon_url', $siteFaviconUrl) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="https://example.com/favicon.ico or /storage/property/branding/favicon.png" />
                    @error('site_favicon_url')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <input type="checkbox" name="remove_favicon" value="1" />
                    Remove current favicon
                </label>

                <div class="pt-2 border-t border-slate-200 dark:border-slate-700 space-y-3">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Public contact details</h3>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Primary email</label>
                        <input type="email" name="contact_email_primary" value="{{ old('contact_email_primary', $contactEmailPrimary) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                        @error('contact_email_primary')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Support email</label>
                        <input type="email" name="contact_email_support" value="{{ old('contact_email_support', $contactEmailSupport) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                        @error('contact_email_support')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Phone</label>
                            <input type="text" name="contact_phone" value="{{ old('contact_phone', $contactPhone) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                            @error('contact_phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">WhatsApp number</label>
                            <input type="text" name="contact_whatsapp" value="{{ old('contact_whatsapp', $contactWhatsapp) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="e.g. +254712345678" />
                            @error('contact_whatsapp')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Address</label>
                        <textarea name="contact_address" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('contact_address', $contactAddress) }}</textarea>
                        @error('contact_address')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Registration number</label>
                        <input type="text" name="contact_reg_no" value="{{ old('contact_reg_no', $contactRegNo) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                        @error('contact_reg_no')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Map embed URL</label>
                        <input type="url" name="contact_map_embed_url" value="{{ old('contact_map_embed_url', $contactMapEmbedUrl) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                        @error('contact_map_embed_url')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save branding</button>
            </form>

            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Current preview</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">This is how your branding is loaded by payroll payslip templates.</p>

                <div class="mt-4 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $companyName !== '' ? $companyName : 'Company name not set' }}</p>
                    @if ($companyLogoUrl)
                        <img src="{{ $companyLogoUrl }}" alt="Company logo" class="mt-3 h-16 w-auto object-contain rounded border border-slate-200 dark:border-slate-700 bg-white p-1" />
                    @else
                        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">No logo configured yet.</p>
                    @endif
                    @if ($siteFaviconUrl)
                        <div class="mt-3 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                            <img src="{{ $siteFaviconUrl }}" alt="Favicon" class="h-5 w-5 rounded-sm border border-slate-200 dark:border-slate-700 bg-white p-0.5" />
                            <span>Favicon configured</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="mt-6">
            <a href="{{ route('property.settings.index') }}" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">← Back to settings</a>
        </div>
    </x-property.page>
</x-property-layout>

