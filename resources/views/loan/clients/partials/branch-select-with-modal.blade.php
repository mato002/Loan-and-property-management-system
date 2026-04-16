@props([
    'fieldId' => 'branch',
    'selectedValue' => '',
    'branchOptions' => [],
    'storeUrl',
])

<div x-data="loanBranchPicker('{{ $fieldId }}', '{{ $storeUrl }}')">
    <x-input-label :for="$fieldId" value="Branch" />
    <div class="mt-1 flex gap-2">
        <select id="{{ $fieldId }}" name="branch" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Select branch...</option>
            @foreach ($branchOptions as $branchName)
                <option value="{{ $branchName }}" @selected($selectedValue === $branchName)>{{ $branchName }}</option>
            @endforeach
        </select>
        <button type="button" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white text-lg font-bold text-slate-700 hover:bg-slate-50" title="Create branch" @click="openBranchModal">+</button>
    </div>
    <x-input-error class="mt-2" :messages="$errors->get('branch')" />

    <div
        x-show="showBranchModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
        @keydown.escape.window="closeBranchModal"
        @click.self="closeBranchModal"
    >
        <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
            <h3 class="text-base font-semibold text-slate-800">Create branch</h3>
            <p class="mt-1 text-xs text-slate-500">Add a branch and use it immediately in this form.</p>

            <div class="mt-4">
                <x-input-label :for="$fieldId.'_new'" value="Branch name" />
                <x-text-input :id="$fieldId.'_new'" x-model.trim="newBranchName" type="text" class="mt-1 block w-full" placeholder="e.g. Nairobi CBD" />
            </div>

            <p x-show="branchModalError" x-text="branchModalError" class="mt-3 text-xs text-red-600"></p>

            <div class="mt-4 flex items-center justify-end gap-2">
                <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="closeBranchModal">Cancel</button>
                <button type="button" class="rounded-lg bg-[#2f4f4f] px-3 py-2 text-sm font-semibold text-white hover:bg-[#264040] disabled:cursor-not-allowed disabled:opacity-70" @click="saveBranch" :disabled="isSavingBranch">
                    <span x-show="!isSavingBranch">Save branch</span>
                    <span x-show="isSavingBranch">Saving...</span>
                </button>
            </div>
        </div>
    </div>
</div>

@once
    <script>
        function loanBranchPicker(selectId, branchStoreUrl) {
            return {
                selectId,
                branchStoreUrl,
                showBranchModal: false,
                newBranchName: '',
                branchModalError: '',
                isSavingBranch: false,
                openBranchModal() {
                    this.branchModalError = '';
                    this.showBranchModal = true;
                },
                closeBranchModal() {
                    if (this.isSavingBranch) return;
                    this.showBranchModal = false;
                    this.newBranchName = '';
                    this.branchModalError = '';
                },
                async saveBranch() {
                    this.branchModalError = '';
                    if (!this.newBranchName) {
                        this.branchModalError = 'Branch name is required.';
                        return;
                    }

                    this.isSavingBranch = true;
                    try {
                        const response = await fetch(this.branchStoreUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                            },
                            body: JSON.stringify({ name: this.newBranchName }),
                        });

                        const data = await response.json();
                        if (!response.ok || !data?.ok || !data?.branch?.name) {
                            this.branchModalError = data?.message ?? 'Unable to save branch.';
                            return;
                        }

                        const branchSelect = document.getElementById(this.selectId);
                        if (!branchSelect) {
                            this.branchModalError = 'Unable to locate branch field.';
                            return;
                        }

                        const existingOption = Array.from(branchSelect.options).find((option) => option.value === data.branch.name);
                        if (!existingOption) {
                            const option = new Option(data.branch.name, data.branch.name, true, true);
                            branchSelect.add(option);
                        } else {
                            existingOption.selected = true;
                        }
                        branchSelect.dispatchEvent(new Event('change'));
                        this.closeBranchModal();
                    } catch (error) {
                        this.branchModalError = 'Unable to save branch. Please try again.';
                    } finally {
                        this.isSavingBranch = false;
                    }
                },
            };
        }
    </script>
@endonce
