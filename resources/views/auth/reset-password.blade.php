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
                    <button type="button" onclick="togglePassword('password')"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500">
                        👁️
                    </button>
                </div>

                <!-- Confirm Password -->
                <div class="mt-4 relative">
                    <x-label for="password_confirmation" :value="__('Confirm Password')" />
                    <x-input id="password_confirmation" class="block mt-1 w-full pr-10" type="password"
                        name="password_confirmation" required />
                    <button type="button" onclick="togglePassword('password_confirmation')"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500">
                        👁️
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
    function togglePassword(id) {
        const input = document.getElementById(id);
        if (input.type === "password") {
            input.type = "text";
        } else {
            input.type = "password";
        }
    }
</script>
