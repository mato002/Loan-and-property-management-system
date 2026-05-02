@if (session('status'))
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        {{ session('status') }}
    </div>
@endif

@if ($errors->any())
    @foreach (array_unique($errors->all()) as $message)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ $message }}
        </div>
    @endforeach
@endif
