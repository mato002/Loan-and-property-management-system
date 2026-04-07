<x-public-layout>
    <div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <h2 class="mt-6 text-center text-4xl font-black text-gray-900 tracking-tight">Create your account</h2>
            <p class="mt-2 text-center text-sm text-gray-600 font-medium">
                Or <a href="{{ route('login') }}" class="font-bold text-emerald-600 hover:text-emerald-500">sign in to your existing account</a>
            </p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-[32rem]">
            <div class="bg-white py-10 px-6 sm:px-12 rounded-[2rem] shadow-2xl border border-gray-100">
                <form class="space-y-6" action="{{ route('register') }}" method="POST">
                    @csrf
                    <!-- Hidden property system value -->
                    <input type="hidden" name="system" value="property">

                    <!-- Role Selection -->
                    <div class="mb-8">
                        <label class="text-sm font-bold text-gray-700 block mb-3">I am joining as a:</label>
                        <div class="grid grid-cols-2 gap-4">
                            <label class="cursor-pointer">
                                <input type="radio" name="role" value="tenant" class="peer sr-only" checked>
                                <div class="rounded-2xl border border-gray-200 p-4 text-center hover:bg-gray-50 peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:text-emerald-600 transition-all font-bold">
                                    <svg class="w-8 h-8 mx-auto mb-2 text-gray-400 peer-checked:text-emerald-600 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    Tenant
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="role" value="landlord" class="peer sr-only">
                                <div class="rounded-2xl border border-gray-200 p-4 text-center hover:bg-gray-50 peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:text-emerald-600 transition-all font-bold">
                                    <svg class="w-8 h-8 mx-auto mb-2 text-gray-400 peer-checked:text-emerald-600 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    Landlord
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Name -->
                    <div>
                        <label for="name" class="block text-sm font-bold text-gray-700">Full Name</label>
                        <div class="mt-2">
                            <input id="name" name="name" type="text" required class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 py-3.5 bg-gray-50 focus:bg-white outline-none sm:text-sm">
                        </div>
                    </div>

                    <!-- Email Address -->
                    <div>
                        <label for="email" class="block text-sm font-bold text-gray-700">Email address</label>
                        <div class="mt-2">
                            <input id="email" name="email" type="email" autocomplete="email" required class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 py-3.5 bg-gray-50 focus:bg-white outline-none sm:text-sm">
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-bold text-gray-700">Password</label>
                        <div class="mt-2">
                            <input id="password" name="password" type="password" required class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 py-3.5 bg-gray-50 focus:bg-white outline-none sm:text-sm">
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label for="password_confirmation" class="block text-sm font-bold text-gray-700">Confirm Password</label>
                        <div class="mt-2">
                            <input id="password_confirmation" name="password_confirmation" type="password" required class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 py-3.5 bg-gray-50 focus:bg-white outline-none sm:text-sm">
                        </div>
                    </div>

                    <div class="flex items-center pt-2">
                        <input id="terms" name="terms" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" required>
                        <label for="terms" class="ml-2 block text-sm font-medium text-gray-900">
                            I agree to the <a href="#" class="text-emerald-600 hover:text-emerald-500 font-bold">Terms and Conditions</a>
                        </label>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="w-full flex justify-center py-4 px-4 border border-transparent rounded-xl shadow-lg shadow-emerald-600/30 text-lg font-black text-white bg-emerald-600 hover:bg-emerald-700 transition-transform hover:-translate-y-1">
                            Create Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-public-layout>
