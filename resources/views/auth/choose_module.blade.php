@extends('layouts.guest')

@section('content')
    @php
        $badgeClass = 'inline-flex items-center rounded-full bg-[#6a9f97]/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-[#386f66] ring-1 ring-[#6a9f97]/35';
    @endphp

    <div class="text-center mb-7">
        <p class="{{ $badgeClass }}">{{ __('Operations') }}</p>
        <h1 class="mt-4 text-2xl font-bold tracking-tight text-slate-900">{{ __('Choose module') }}</h1>
        <p class="mt-2 text-sm leading-relaxed text-slate-500">
            {{ __('Pick Property or Loan to open. Switch modules anytime from the header after sign-in.') }}
        </p>
    </div>

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 text-left" role="alert">
            {{ $errors->first('module') }}
        </div>
    @endif

    @if ($propertyApproved)
        <link rel="prefetch" href="{{ route('module.switch', ['module' => 'property']) }}" as="document">
    @endif
    @if ($loanApproved)
        <link rel="prefetch" href="{{ route('module.switch', ['module' => 'loan']) }}" as="document">
    @endif

    <div class="space-y-3">
        @if ($propertyApproved)
            <a
                href="{{ route('module.switch', ['module' => 'property']) }}"
                data-choose-module-enter="property"
                data-turbo-frame="_top"
                class="flex w-full flex-col rounded-xl border border-[#6a9f97]/35 bg-[#6a9f97]/10 px-5 py-4 text-left transition hover:bg-[#6a9f97]/20"
            >
                <span class="text-xs font-semibold uppercase tracking-wide text-[#386f66]">{{ __('Property module') }}</span>
                <span class="mt-1 text-lg font-bold text-[#2f4f4f]">{{ __('Property Management') }}</span>
            </a>
        @else
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-5 py-4 text-slate-400">
                <span class="text-xs font-semibold uppercase tracking-wide">{{ __('Property module') }}</span>
                <span class="mt-1 block text-lg font-bold">{{ __('Not approved') }}</span>
            </div>
        @endif

        @if ($loanApproved)
            <a
                href="{{ route('module.switch', ['module' => 'loan']) }}"
                data-choose-module-enter="loan"
                data-turbo-frame="_top"
                class="flex w-full flex-col rounded-xl border border-[#4d8d82]/35 bg-[#4d8d82]/10 px-5 py-4 text-left transition hover:bg-[#4d8d82]/20"
            >
                <span class="text-xs font-semibold uppercase tracking-wide text-[#386f66]">{{ __('Loan module') }}</span>
                <span class="mt-1 text-lg font-bold text-[#2f4f4f]">{{ __('Loan Management') }}</span>
            </a>
        @else
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-5 py-4 text-slate-400">
                <span class="text-xs font-semibold uppercase tracking-wide">{{ __('Loan module') }}</span>
                <span class="mt-1 block text-lg font-bold">{{ __('Not approved') }}</span>
            </div>
        @endif
    </div>

    <div class="mt-8 text-center text-xs text-slate-500">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="font-semibold text-[#4d8d82] hover:text-[#3f7a70] underline underline-offset-2">
                {{ __('Sign out') }}
            </button>
        </form>
    </div>
@endsection
