<x-guest-layout>
    <x-auth-card>
        <x-slot name="logo">
            <a href="/">
                <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
            </a>
        </x-slot>

        <!-- Success Message -->
        @if (session('status'))
            <div class="mb-4 font-medium text-sm text-green-600">
                {{ session('status') }}
            </div>
        @else
            <!-- Validation Errors -->
            <x-auth-validation-errors class="mb-4" :errors="$errors" />

            <form method="POST" action="{{ route('password.update') }}">
                @csrf

                <!-- Password Reset Token -->
                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <!-- Email Address -->
                <div>
                    <x-label for="email" :value="__('Email')" />
                    <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $request->email)"
                        required autofocus />
                </div>

                <!-- Password -->
                <div class="mt-4 relative">
                    <x-label for="password" :value="__('Password')" />
                    <x-input id="password" class="block mt-1 w-full pr-10" type="password" name="password" required />
                    <button type="button" onclick="togglePassword('password', this)"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500">
                        <svg xmlns="http://www.w3.org/2000/svg" id="eye-open" class="h-5 w-5 block" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065
                                  7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg xmlns="http://www.w3.org/2000/svg" id="eye-closed" class="h-5 w-5 hidden" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.956 9.956
                                  0 012.413-4.568m3.999-2.318A9.956 9.956 0 0112 5c4.477 0 8.268
                                  2.943 9.542 7a9.956 9.956 0 01-4.043 5.197M15 12a3 3 0 11-6
                                  0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18" />
                        </svg>
                    </button>
                </div>

                <!-- Confirm Password -->
                <div class="mt-4 relative">
                    <x-label for="password_confirmation" :value="__('Confirm Password')" />
                    <x-input id="password_confirmation" class="block mt-1 w-full pr-10" type="password"
                        name="password_confirmation" required />
                    <button type="button" onclick="togglePassword('password_confirmation', this)"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500">
                        <svg xmlns="http://www.w3.org/2000/svg" id="eye-open" class="h-5 w-5 block" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065
                                  7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg xmlns="http://www.w3.org/2000/svg" id="eye-closed" class="h-5 w-5 hidden" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.956 9.956
                                  0 012.413-4.568m3.999-2.318A9.956 9.956 0 0112 5c4.477 0 8.268
                                  2.943 9.542 7a9.956 9.956 0 01-4.043 5.197M15 12a3 3 0 11-6
                                  0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18" />
                        </svg>
                    </button>
                </div>

                <div class="flex items-center justify-end mt-4">
                    <x-button>
                        {{ __('Reset Password') }}
                    </x-button>
                </div>
            </form>
        @endif
    </x-auth-card>
</x-guest-layout>

<script>
    function togglePassword(id, button) {
        const input = document.getElementById(id);
        const eyeOpen = button.querySelector('#eye-open');
        const eyeClosed = button.querySelector('#eye-closed');
        if (input.type === "password") {
            input.type = "text";
            eyeOpen.classList.add('hidden');
            eyeClosed.classList.remove('hidden');
        } else {
            input.type = "password";
            eyeClosed.classList.add('hidden');
            eyeOpen.classList.remove('hidden');
        }
    }
</script>
