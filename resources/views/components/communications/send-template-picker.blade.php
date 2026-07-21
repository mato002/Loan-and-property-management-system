@props(['fieldName' => 'message_template_id'])

<div>
    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Template (optional)</label>
    <select
        name="{{ $fieldName }}"
        x-model="templateId"
        @change="applyTemplate()"
        class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
    >
        <option value="">— Custom message —</option>
        <template x-for="t in templatesForChannel()" :key="t.id">
            <option :value="t.id" x-text="t.name"></option>
        </template>
    </select>
    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Pick a saved template to prefill the message. You can edit the text before sending.</p>
</div>
