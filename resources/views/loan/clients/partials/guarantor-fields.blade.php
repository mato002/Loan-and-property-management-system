{{--
    @param \App\Models\LoanClient|null $client  Existing record for edit; null for create.
--}}
@php
    $g = function (string $attr) use ($client) {
        return old($attr, $client?->{$attr} ?? '');
    };
@endphp

<div class="sm:col-span-2 border-t border-slate-200 pt-5 mt-1">
    <h3 class="text-sm font-semibold text-slate-800 mb-3">Guarantors <span class="font-normal text-slate-500">(optional)</span></h3>
    <p class="text-xs text-slate-500 mb-4">Capture up to two guarantors as collected during onboarding.</p>
</div>

<div class="sm:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="rounded-lg border border-slate-200 bg-slate-50/80 p-4 space-y-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Guarantor 1</p>
        <div>
            <x-input-label for="guarantor_1_full_name" value="Full name" />
            <x-text-input id="guarantor_1_full_name" name="guarantor_1_full_name" type="text" class="mt-1 block w-full" :value="$g('guarantor_1_full_name')" autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('guarantor_1_full_name')" />
        </div>
        <div>
            <x-input-label for="guarantor_1_phone" value="Phone" />
            <x-text-input id="guarantor_1_phone" name="guarantor_1_phone" type="text" class="mt-1 block w-full" :value="$g('guarantor_1_phone')" autocomplete="tel" />
            <x-input-error class="mt-2" :messages="$errors->get('guarantor_1_phone')" />
        </div>
        <div>
            <x-input-label for="guarantor_1_id_number" value="ID / registration" />
            <x-text-input id="guarantor_1_id_number" name="guarantor_1_id_number" type="text" class="mt-1 block w-full" :value="$g('guarantor_1_id_number')" />
            <x-input-error class="mt-2" :messages="$errors->get('guarantor_1_id_number')" />
        </div>
        <div>
            <x-input-label for="guarantor_1_relationship" value="Relationship to client" />
            <x-text-input id="guarantor_1_relationship" name="guarantor_1_relationship" type="text" class="mt-1 block w-full" :value="$g('guarantor_1_relationship')" placeholder="e.g. spouse, sibling" />
            <x-input-error class="mt-2" :messages="$errors->get('guarantor_1_relationship')" />
        </div>
        <div>
            <x-input-label for="guarantor_1_address" value="Address" />
            <textarea id="guarantor_1_address" name="guarantor_1_address" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ $g('guarantor_1_address') }}</textarea>
            <x-input-error class="mt-2" :messages="$errors->get('guarantor_1_address')" />
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-slate-50/80 p-4 space-y-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Guarantor 2</p>
        <div>
            <x-input-label for="guarantor_2_full_name" value="Full name" />
            <x-text-input id="guarantor_2_full_name" name="guarantor_2_full_name" type="text" class="mt-1 block w-full" :value="$g('guarantor_2_full_name')" autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('guarantor_2_full_name')" />
        </div>
        <div>
            <x-input-label for="guarantor_2_phone" value="Phone" />
            <x-text-input id="guarantor_2_phone" name="guarantor_2_phone" type="text" class="mt-1 block w-full" :value="$g('guarantor_2_phone')" autocomplete="tel" />
            <x-input-error class="mt-2" :messages="$errors->get('guarantor_2_phone')" />
        </div>
        <div>
            <x-input-label for="guarantor_2_id_number" value="ID / registration" />
            <x-text-input id="guarantor_2_id_number" name="guarantor_2_id_number" type="text" class="mt-1 block w-full" :value="$g('guarantor_2_id_number')" />
            <x-input-error class="mt-2" :messages="$errors->get('guarantor_2_id_number')" />
        </div>
        <div>
            <x-input-label for="guarantor_2_relationship" value="Relationship to client" />
            <x-text-input id="guarantor_2_relationship" name="guarantor_2_relationship" type="text" class="mt-1 block w-full" :value="$g('guarantor_2_relationship')" placeholder="e.g. colleague, parent" />
            <x-input-error class="mt-2" :messages="$errors->get('guarantor_2_relationship')" />
        </div>
        <div>
            <x-input-label for="guarantor_2_address" value="Address" />
            <textarea id="guarantor_2_address" name="guarantor_2_address" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ $g('guarantor_2_address') }}</textarea>
            <x-input-error class="mt-2" :messages="$errors->get('guarantor_2_address')" />
        </div>
    </div>
</div>
