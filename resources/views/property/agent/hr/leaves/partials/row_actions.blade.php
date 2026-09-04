@if (auth()->check() && auth()->user()?->hasPmPermission('properties.manage'))
    <div class="flex flex-wrap gap-1">
        @foreach (['approved' => 'Approve', 'rejected' => 'Reject', 'pending' => 'Pending'] as $status => $label)
            <form method="post" action="{{ route('property.hr.leaves.status', $leave, false) }}" class="inline">
                @csrf
                <input type="hidden" name="status" value="{{ $status }}" />
                <button type="submit" class="text-xs font-medium @if($status === 'approved') text-emerald-600 @elseif($status === 'rejected') text-red-600 @else text-amber-600 @endif hover:underline">{{ $label }}</button>
            </form>
        @endforeach
    </div>
@endif
