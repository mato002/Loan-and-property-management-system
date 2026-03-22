<x-loan-layout>
    <x-loan.page
        title="Edit group"
        subtitle="{{ $default_client_group->name }}"
    >
        <x-slot name="actions">
            <a href="{{ route('loan.clients.default_groups.show', $default_client_group) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back to members
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 sm:p-8 max-w-2xl">
            <form method="post" action="{{ route('loan.clients.default_groups.update', $default_client_group) }}" class="space-y-5">
                @csrf
                @method('patch')
                <div>
                    <x-input-label for="name" value="Group name" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $default_client_group->name)" required />
                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                </div>
                <div>
                    <x-input-label for="description" value="Description" />
                    <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $default_client_group->description) }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('description')" />
                </div>
                <x-primary-button type="submit">{{ __('Save changes') }}</x-primary-button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
