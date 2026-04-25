<x-guest-layout>
    <x-auth-card>
        <x-slot name="logo">
            <a href="/">
                <h1 class="mb-12 text-center text-4xl font-thin">
                    MasBro Canteen cek git actions
                </h1>
                {{-- <x-application-logo class="w-20 h-20 fill-current text-gray-500" /> --}}
            </a>
        </x-slot>

        <!-- Session Status -->
        <x-auth-session-status class="mb-4" :status="session('status')" />

        <!-- Validation Errors -->
        <x-auth-validation-errors class="mb-4" :errors="$errors" />

        <div class="overflow-hidden rounded-lg bg-white shadow-2xl">

        </div>
        <form method="POST" action="{{ route('login') }}">
            @csrf

            <!-- Email Address -->
            <div>
                <x-label for="email" :value="__('Email')" />

                <x-input id="email"
                    class="block w-full rounded border border-transparent bg-gray-200 p-3 focus:outline-none"
                    type="email" name="email" :value="old('email')" required autofocus />
                {{-- <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')"
                    required autofocus /> --}}
            </div>

            <!-- Password -->
            <div class="mt-4">
                <x-label for="password" :value="__('Password')" />

                <x-input id="password"
                    class="block w-full rounded border border-transparent bg-gray-200 p-3 focus:outline-none"
                    type="password" name="password" required autocomplete="current-password" />
            </div>

            <!-- Remember Me -->
            <div class="block mt-4">
                <label for="remember_me" class="inline-flex items-center">
                    <input id="remember_me" type="checkbox"
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        name="remember">
                    <span class="ml-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-4">
                {{-- @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
                @endif --}}

                <x-button class="mt-2 text-center w-full rounded bg-indigo-600 p-3 text-white shadow">
                    {{ __('Log in') }}
                </x-button>

            </div>
        </form>
        <div class="mt-5 flex justify-between p-4 bg-gray-100 text-sm">
            <a href="{{ route('register') }}" class="font-medium text-indigo-500">Create Account</a>

            <a class="text-gray-600 hover:text-gray-900" href="{{ route('password.request') }}">
                {{ __('Forgot your password?') }}
            </a>
            {{-- <a href="#" class="text-gray-600">
                Forgot password?
            </a> --}}
        </div>
    </x-auth-card>
</x-guest-layout>