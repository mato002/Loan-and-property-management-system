@php
    use App\Support\Property\PropertyWorkspaceBranding;

    $publicBrand = PropertyWorkspaceBranding::publicSiteSnapshot();
    $companyName = $publicBrand['company_name'];
    $contactEmail = $publicBrand['contact_email_primary'];
@endphp

<x-public-layout :page-title="__('Privacy Policy')">
    <div class="w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20 py-8 sm:py-16">
        <h1 class="text-2xl sm:text-4xl font-black text-gray-900 tracking-tight mb-4 sm:mb-6">Privacy Policy</h1>
        <div class="max-w-4xl prose prose-sm sm:prose-lg text-gray-600">
            <p>{{ $companyName }} respects your privacy. We collect only the information needed to respond to inquiries, process applications, and provide property management services.</p>
            <p>Your information is never sold to third parties. We may share data with trusted service providers only when required to deliver requested services or comply with legal obligations.</p>
            @if ($contactEmail !== '')
                <p>By using this site, you consent to this policy. For data requests, contact <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>.</p>
            @else
                <p>By using this site, you consent to this policy. Contact us through the details on our contact page for data requests.</p>
            @endif
        </div>
    </div>
</x-public-layout>
