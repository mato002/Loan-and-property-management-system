@php
    /** @var \App\Models\PropertyUnit $unit */
@endphp
<div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-4">
    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Upload photos &amp; videos</h3>
    <form
        method="post"
        action="{{ route('property.listings.vacant.public.photos.store', $unit) }}"
        enctype="multipart/form-data"
        class="space-y-3"
    >
        @csrf
        <input
            type="file"
            name="photos[]"
            accept="{{ \App\Models\PropertyUnitPublicImage::uploadAcceptAttribute() }}"
            multiple
            required
            class="block w-full text-sm text-slate-600 dark:text-slate-300"
        />
        <p class="text-xs text-slate-500 dark:text-slate-400">
            Photos (JPEG/PNG/WEBP/GIF) and videos (MP4/WEBM/MOV). Max 1&nbsp;GB per file, up to {{ \App\Models\PropertyUnitPublicImage::MAX_FILES_PER_BATCH }} files per upload.
        </p>
        @if ($errors->has('photos') || $errors->has('photos.*'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300">
                @foreach ($errors->get('photos') as $msg)
                    <p>{{ $msg }}</p>
                @endforeach
                @foreach ($errors->get('photos.*') as $messages)
                    @foreach ((array) $messages as $msg)
                        <p>{{ $msg }}</p>
                    @endforeach
                @endforeach
            </div>
        @endif
        <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Upload</button>
    </form>

    <div class="border-t border-slate-200 dark:border-slate-600 pt-4 space-y-3">
        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gallery ({{ $unit->publicImages->count() }})</h4>
        <ul class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            @foreach ($unit->publicImages as $img)
                @php
                    $mediaUrl = $img->publicUrl();
                    $isVideo = $img->isVideo();
                @endphp
                <li class="relative group rounded-lg overflow-hidden border border-slate-200 dark:border-slate-600 aspect-[4/3] bg-slate-100 dark:bg-slate-900">
                    @if ($isVideo)
                        <video src="{{ $mediaUrl }}" class="w-full h-full object-cover" controls playsinline preload="metadata"></video>
                        <span class="absolute bottom-1 left-1 rounded-md bg-slate-900/80 text-white text-[10px] px-2 py-0.5 font-semibold">Video</span>
                    @else
                        <img
                            src="{{ $mediaUrl }}"
                            alt=""
                            class="w-full h-full object-cover"
                            loading="lazy"
                            onerror="this.classList.add('opacity-0'); this.nextElementSibling?.classList.remove('hidden');"
                        />
                        <div class="hidden absolute inset-0 flex items-center justify-center text-[11px] text-slate-500 px-2 text-center">Preview unavailable</div>
                    @endif
                    @if ($loop->first)
                        <span class="absolute top-1 left-1 rounded-md bg-emerald-600 text-white text-[10px] px-2 py-1 font-semibold">Main {{ $isVideo ? 'video' : 'image' }}</span>
                    @else
                        <form
                            method="post"
                            action="{{ route('property.listings.vacant.public.photos.main', [$unit, $img]) }}"
                            class="absolute top-1 left-1 opacity-0 group-hover:opacity-100 transition-opacity"
                        >
                            @csrf
                            <button type="submit" class="rounded-md bg-indigo-600 text-white text-xs px-2 py-1 font-medium hover:bg-indigo-700">Set main</button>
                        </form>
                    @endif
                    <form
                        method="post"
                        action="{{ route('property.listings.vacant.public.photos.destroy', [$unit, $img]) }}"
                        class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition-opacity"
                        data-swal-confirm="Remove this media?"
                    >
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-md bg-red-600 text-white text-xs px-2 py-1 font-medium hover:bg-red-700">Remove</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
</div>
