@php
    $existingAttachments = ($requestItem->relationLoaded('files') ? $requestItem->files : collect());
@endphp
@if ($existingAttachments->isNotEmpty())
    <div>
        <p class="text-xs font-medium text-slate-600 dark:text-slate-400">Already attached</p>
        <ul class="mt-2 space-y-1 text-sm">
            @foreach ($existingAttachments as $file)
                <li>
                    <a href="{{ route('property.maintenance.requests.files.show', [$requestItem, $file]) }}" target="_blank" rel="noopener" class="text-blue-700 hover:underline">{{ $file->original_name }}</a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
