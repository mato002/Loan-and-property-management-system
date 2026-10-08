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
@endphp
<div>
    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Photos and videos</label>
    <input
        type="file"
        name="attachments[]"
        multiple
        accept="image/*,video/*"
        class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-700 dark:text-slate-300 dark:file:bg-slate-800 dark:file:text-slate-100"
    />
    <p class="mt-1 text-xs text-slate-500">The person this request is sent to can open every photo and video. Up to 1 GB in total.</p>
    @if ($serverUploadCap > 0 && $serverUploadCap < 1073741824)
        <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">This server currently accepts uploads up to {{ \Illuminate\Support\Number::fileSize($serverUploadCap) }}. Raise <span class="font-medium">upload_max_filesize</span> and <span class="font-medium">post_max_size</span> to 1024M before a 1 GB file will go through.</p>
    @endif
    @error('attachments')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    @error('attachments.*')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
</div>
