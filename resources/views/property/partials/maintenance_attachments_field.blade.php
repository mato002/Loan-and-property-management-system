@php
    $uploadBytes = static function (string $value): int {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return (int) match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => (int) $value,
        };
    };
    $serverUploadCap = min(
        $uploadBytes((string) ini_get('upload_max_filesize')),
        $uploadBytes((string) ini_get('post_max_size')),
    );
    $attachmentName = $attachmentFieldName ?? 'attachments[]';
    $mediaId = 'mm-'.uniqid();
@endphp
<div data-maintenance-media class="space-y-2">
    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Photos and videos <span class="font-normal text-slate-400">(optional)</span></label>

    <input
        id="{{ $mediaId }}-library"
        type="file"
        name="{{ $attachmentName }}"
        multiple
        accept="image/*,video/*"
        data-media-library
        class="sr-only"
        tabindex="-1"
    />
    <input id="{{ $mediaId }}-photo" type="file" accept="image/*" capture="environment" data-media-capture="photo" class="sr-only" tabindex="-1" />
    <input id="{{ $mediaId }}-video" type="file" accept="video/*" capture="environment" data-media-capture="video" class="sr-only" tabindex="-1" />

    <div class="flex flex-wrap gap-2">
        <label for="{{ $mediaId }}-library" class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
            Choose files
        </label>
        <label for="{{ $mediaId }}-photo" class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800 hover:bg-emerald-100 dark:border-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-100">
            Take photo
        </label>
        <label for="{{ $mediaId }}-video" class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-800 hover:bg-indigo-100 dark:border-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-100">
            Record video
        </label>
        <button type="button" data-media-open="photo" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
            Live camera
        </button>
    </div>

    <div data-media-preview class="flex flex-col gap-1.5"></div>

    <div data-media-live-panel class="hidden fixed inset-0 z-[80] flex items-end sm:items-center justify-center bg-slate-900/70 p-3">
        <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-slate-900">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2 dark:border-slate-700">
                <p class="text-sm font-semibold text-slate-900 dark:text-white">Live camera</p>
                <button type="button" data-media-close class="rounded-lg px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-100">Close</button>
            </div>
            <video data-media-live-video autoplay playsinline muted class="aspect-[3/4] w-full bg-black object-cover"></video>
            <div class="flex flex-wrap gap-2 p-3">
                <button type="button" data-media-shot class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Capture photo</button>
                <button type="button" data-media-rec class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-700"><span data-media-rec-label>Start recording</span></button>
            </div>
        </div>
    </div>

    <p class="text-xs text-slate-500">On a phone, Take photo and Record video open the camera immediately. Live camera records inside this page. Choose files still picks from the gallery. Up to 1 GB in total.</p>
    <script src="{{ asset('js/maintenance-media.js') }}?v=1" defer></script>
    @if ($serverUploadCap > 0 && $serverUploadCap < 1073741824)
        <p class="text-xs text-amber-700 dark:text-amber-300">This server currently accepts uploads up to {{ \Illuminate\Support\Number::fileSize($serverUploadCap) }}. Raise <span class="font-medium">upload_max_filesize</span> and <span class="font-medium">post_max_size</span> to 1024M before a 1 GB file will go through.</p>
    @endif
    @error('attachments')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    @error('attachments.*')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
</div>
