@props([
    'title' => null,
    'subtitle' => null,
    'meta' => null,
    'branding' => null,
    'variant' => 'screen', // screen | pdf
    'showAccentBar' => true,
])

@php
    $doc = is_array($branding) && $branding !== []
        ? $branding
        : \App\Support\Property\PropertyWorkspaceBranding::documentSnapshot();
    $company = (string) ($doc['company_name'] ?? 'Property Manager');
    $logoSrc = (string) (($doc['logo_src'] ?? '') ?: ($doc['logo_url'] ?? $doc['company_logo_url'] ?? ''));
    $contactLine = (string) ($doc['contact_line'] ?? '');
    $colour = (string) ($doc['colour'] ?? '#0f766e');
    $isPdf = $variant === 'pdf';
@endphp

@if ($isPdf)
    <div class="doc-letterhead" style="border-bottom:3px solid {{ $colour }}; padding-bottom:10px; margin-bottom:14px;">
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="vertical-align:middle; width:72px;">
                    @if ($logoSrc !== '')
                        <img src="{{ $logoSrc }}" alt="{{ $company }} logo" style="max-height:52px; max-width:64px; object-fit:contain;">
                    @endif
                </td>
                <td style="vertical-align:middle; padding-left:10px;">
                    <div style="font-size:15pt; font-weight:bold; color:{{ $colour }};">{{ $company }}</div>
                    @if ($contactLine !== '')
                        <div style="margin-top:3px; font-size:8pt; color:#475569; line-height:1.4;">{{ $contactLine }}</div>
                    @endif
                    @if (filled($title))
                        <div style="margin-top:6px; font-size:11pt; font-weight:bold; color:#0f172a;">{{ $title }}</div>
                    @endif
                    @if (filled($subtitle))
                        <div style="margin-top:2px; font-size:8.5pt; color:#475569;">{{ $subtitle }}</div>
                    @endif
                    @if (filled($meta))
                        <div style="margin-top:4px; font-size:8pt; color:#64748b;">{{ $meta }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>
@else
    <div class="property-doc-letterhead" @if ($showAccentBar) style="border-bottom:3px solid {{ $colour }};" @endif>
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0 flex items-start gap-3">
                @if ($logoSrc !== '')
                    <img src="{{ $logoSrc }}" alt="{{ $company }} logo" class="h-12 w-auto max-w-[5.5rem] object-contain shrink-0" />
                @endif
                <div class="min-w-0">
                    <div class="text-lg font-semibold" style="color: {{ $colour }};">{{ $company }}</div>
                    @if ($contactLine !== '')
                        <div class="mt-1 text-xs text-slate-600">{{ $contactLine }}</div>
                    @endif
                    @if (filled($title))
                        <div class="mt-2 text-base font-semibold text-slate-900">{{ $title }}</div>
                    @endif
                    @if (filled($subtitle))
                        <div class="mt-0.5 text-sm text-slate-700">{{ $subtitle }}</div>
                    @endif
                    @if (filled($meta))
                        <div class="mt-1 text-xs text-slate-600">{{ $meta }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
