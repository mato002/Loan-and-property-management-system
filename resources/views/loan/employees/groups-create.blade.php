<x-loan-layout>
    <x-loan.page
        title="Create staff group"
        subtitle="Name the group, then add people from the manage screen."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.employees.groups') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back to groups
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 sm:p-8 max-w-xl">
            <form method="post" action="{{ route('loan.employees.groups.store') }}" class="space-y-5">
                @csrf
                <div>
                    <x-input-label for="name" value="Group name" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required />
                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                </div>
                <div>
                    <x-input-label for="description" value="Description" />
                    <textarea id="description" name="description" rows="4" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('description') }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('description')" />
                </div>
                <x-primary-button>{{ __('Create group') }}</x-primary-button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
