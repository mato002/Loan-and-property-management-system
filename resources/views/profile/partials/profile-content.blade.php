<div class="py-8">
    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-5 sm:p-8 bg-white shadow-[0_12px_28px_rgba(47,79,79,0.10)] ring-1 ring-[#dbe8e4] rounded-2xl">
            <div class="max-w-2xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="p-5 sm:p-8 bg-white shadow-[0_12px_28px_rgba(47,79,79,0.10)] ring-1 ring-[#dbe8e4] rounded-2xl">
            <div class="max-w-2xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="p-5 sm:p-8 bg-white shadow-[0_12px_28px_rgba(47,79,79,0.10)] ring-1 ring-[#f2d4d4] rounded-2xl">
            <div class="max-w-2xl">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</div>
