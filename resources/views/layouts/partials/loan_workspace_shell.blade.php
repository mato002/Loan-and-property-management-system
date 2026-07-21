@php
    use App\Support\LoanWorkspaceTabs;

    $shellRouteName = Route::currentRouteName();
    $resolvedWorkspaceKey = LoanWorkspaceTabs::resolveWorkspaceKey($shellRouteName);
    $renderWorkspaceTabs = $resolvedWorkspaceKey
        && LoanWorkspaceTabs::shouldShow($shellRouteName);
@endphp

@if ($renderWorkspaceTabs)
    <div class="loan-workspace-shell print-hide mb-4 max-w-[1600px] mx-auto w-full">
        <x-loan.workspace-tabs :workspace="$resolvedWorkspaceKey" />
    </div>
@endif
