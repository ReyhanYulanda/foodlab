<x-guest-layout>
    <x-auth-card>
        <x-slot name="logo">
            <a href="/">
                <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
            </a>
        </x-slot>

        <div class="mb-4 text-sm text-gray-600 text-center">
            <strong>Email berhasil diverifikasi, sekarang kembali ke aplikasi FoodLAB!</strong>
        </div>

        <div class="flex items-center justify-center mt-4">
            {{-- <a href="{{ route('login') }}"
                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:outline-none focus:border-blue-700 focus:ring focus:ring-blue-200 active:bg-blue-600 disabled:opacity-25 transition">
                Login Sekarang
            </a> --}}
        </div>
    </x-auth-card>
</x-guest-layout>
