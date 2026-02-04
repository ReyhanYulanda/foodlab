<x-guest-layout>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: #3b82f6;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #2563eb;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-20px);
            }
        }

        .animate-float {
            animation: float 3s ease-in-out infinite;
        }
    </style>

    <main class="min-h-screen bg-slate-50">
        <!-- Navbar -->
        <nav class="fixed w-full bg-white/80 backdrop-blur-md z-50 border-b border-slate-100">
            <div class="container mx-auto px-4 py-4 flex justify-between items-center">
                <div class="text-2xl font-bold text-blue-600">Foodlab<span class="text-yellow-500">.</span></div>
                <a href="{{ route('login') }}"
                    class="bg-blue-600 text-white px-6 py-2 rounded-full font-medium hover:bg-blue-700 transition">
                    Login Access
                </a>
            </div>
        </nav>

        <!-- Hero Section -->
        <section class="pt-32 pb-20 bg-gradient-to-br from-blue-50 to-white overflow-hidden">
            <div class="container mx-auto px-4">
                <div class="flex flex-col lg:flex-row items-center gap-12">
                    <!-- Content Left -->
                    <div class="lg:w-1/2 space-y-6">
                        <span
                            class="inline-block px-4 py-1.5 bg-blue-100 text-blue-600 font-semibold rounded-full text-sm">
                            Solusi Perut Keroncongan di Kampus 🎓
                        </span>
                        <h1 class="text-5xl lg:text-6xl font-bold leading-tight text-slate-900">
                            Lapar di Tengah Kelas? <br />
                            <span class="text-blue-600">Foodlab-in Aja!</span>
                        </h1>
                        <p class="text-lg text-slate-600 max-w-lg">
                            Pesan makanan favoritmu dari kantin atau sekitar kampus, diantar langsung ke depan kelas,
                            perpustakaan, atau kosan.
                        </p>
                        <div class="flex flex-col sm:flex-row gap-4 pt-4">
                            <a href="#download"
                                class="bg-blue-600 text-white px-8 py-4 rounded-full font-semibold flex items-center justify-center gap-2 hover:bg-blue-700 transition shadow-lg shadow-blue-500/30">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z">
                                    </path>
                                </svg>
                                Download di Play Store
                            </a>
                            <a href="#how-it-works"
                                class="bg-white text-slate-700 border border-slate-200 px-8 py-4 rounded-full font-semibold hover:bg-slate-50 transition flex items-center justify-center">
                                Pelajari Cara Kerja
                            </a>
                        </div>
                        <div class="pt-8 flex items-center gap-8 text-sm text-slate-500 font-medium">
                            <div class="flex items-center gap-2">
                                <div class="bg-green-100 p-1 rounded-full">
                                    <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                                </div>
                                <span>10k+ Mahasiswa</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="bg-green-100 p-1 rounded-full">
                                    <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                                </div>
                                <span>50+ Merchant Favorit</span>
                            </div>
                        </div>
                    </div>

                    <!-- Visual Right -->
                    <div class="lg:w-1/2 relative">
                        <!-- Phone Mockup -->
                        <div
                            class="relative z-10 w-[300px] h-[600px] mx-auto bg-slate-900 rounded-[3rem] border-8 border-slate-800 shadow-2xl flex flex-col items-center justify-center overflow-hidden">
                            <div
                                class="absolute top-0 w-full h-full bg-gradient-to-br from-blue-600 to-blue-800 flex items-center justify-center">
                                <div class="text-center text-white p-8">
                                    <div class="text-4xl font-bold mb-2">FoodLab</div>
                                    <div class="text-sm opacity-80">PENS Smart Canteen</div>
                                </div>
                            </div>
                            <!-- Notch -->
                            <div class="absolute top-0 w-1/2 h-6 bg-slate-800 rounded-b-xl"></div>
                        </div>

                        <!-- Decorative Blobs -->
                        <div
                            class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-yellow-500/20 rounded-full blur-3xl -z-10">
                        </div>
                        <div
                            class="absolute top-1/2 right-10 -translate-y-1/2 w-[300px] h-[300px] bg-blue-600/20 rounded-full blur-3xl -z-10">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pain Points Section -->
        <section class="py-20 bg-white">
            <div class="container mx-auto px-4 text-center mb-16">
                <h2 class="text-3xl lg:text-4xl font-bold mb-4">Kenapa Sih Harus Foodlab?</h2>
                <p class="text-slate-600 max-w-2xl mx-auto">
                    Kami paham banget rasanya jadi mahasiswa sibuk yang perutnya gak bisa diajak kompromi.
                </p>
            </div>

            <div class="container mx-auto px-4 grid md:grid-cols-3 gap-8">
                <!-- Card 1 -->
                <div class="p-8 rounded-2xl bg-slate-50 hover:bg-blue-50 transition border border-slate-100 group">
                    <div
                        class="w-14 h-14 bg-red-100 text-red-600 rounded-xl flex items-center justify-center mb-6 text-2xl group-hover:scale-110 transition">
                        😓
                    </div>
                    <h3 class="text-xl font-bold mb-3">Mager Keluar Kelas</h3>
                    <p class="text-slate-600">
                        Lagi asik dengerin dosen (atau ngantuk), tapi perut bunyi? Gak perlu jalan jauh ke kantin.
                    </p>
                </div>

                <!-- Card 2 -->
                <div class="p-8 rounded-2xl bg-slate-50 hover:bg-blue-50 transition border border-slate-100 group">
                    <div
                        class="w-14 h-14 bg-orange-100 text-orange-600 rounded-xl flex items-center justify-center mb-6 text-2xl group-hover:scale-110 transition">
                        ⏳
                    </div>
                    <h3 class="text-xl font-bold mb-3">Antrean Panjang</h3>
                    <p class="text-slate-600">
                        Waktu istirahat cuma sebentar, eh antrean kantin kayak uler tangga. Foodlab-in aja biar cepet.
                    </p>
                </div>

                <!-- Card 3 -->
                <div class="p-8 rounded-2xl bg-slate-50 hover:bg-blue-50 transition border border-slate-100 group">
                    <div
                        class="w-14 h-14 bg-green-100 text-green-600 rounded-xl flex items-center justify-center mb-6 text-2xl group-hover:scale-110 transition">
                        💸
                    </div>
                    <h3 class="text-xl font-bold mb-3">Hemat Budget</h3>
                    <p class="text-slate-600">
                        Ongkir mahal? Tenang, tarif kita bersahabat banget sama kantong mahasiswa akhir bulan.
                    </p>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section id="how-it-works" class="py-20 bg-slate-50 relative overflow-hidden">
            <div class="container mx-auto px-4 text-center mb-16 relative z-10">
                <span class="text-blue-600 font-semibold tracking-wide uppercase text-sm">Gampang Banget</span>
                <h2 class="text-3xl lg:text-4xl font-bold mt-2">Cuma 4 Langkah Mudah</h2>
            </div>

            <div class="container mx-auto px-4 relative z-10">
                <div class="grid md:grid-cols-4 gap-8">
                    <!-- Step 1 -->
                    <div class="relative flex flex-col items-center text-center group">
                        <div
                            class="w-24 h-24 bg-white rounded-full border-4 border-white shadow-xl flex items-center justify-center text-4xl mb-6 group-hover:scale-110 group-hover:border-yellow-500 transition duration-300">
                            🍔
                        </div>
                        <h3 class="text-xl font-bold mb-2 text-slate-900">Pilih Menu</h3>
                        <p class="text-slate-600 text-sm px-4">Cari makanan dari vendor favorit di sekitar kampus.</p>
                    </div>

                    <!-- Step 2 -->
                    <div class="relative flex flex-col items-center text-center group">
                        <div
                            class="w-24 h-24 bg-white rounded-full border-4 border-white shadow-xl flex items-center justify-center text-4xl mb-6 group-hover:scale-110 group-hover:border-yellow-500 transition duration-300">
                            💳
                        </div>
                        <h3 class="text-xl font-bold mb-2 text-slate-900">Pesan & Bayar</h3>
                        <p class="text-slate-600 text-sm px-4">Bayar pakai E-wallet favoritmu, praktis & aman.</p>
                    </div>

                    <!-- Step 3 -->
                    <div class="relative flex flex-col items-center text-center group">
                        <div
                            class="w-24 h-24 bg-white rounded-full border-4 border-white shadow-xl flex items-center justify-center text-4xl mb-6 group-hover:scale-110 group-hover:border-yellow-500 transition duration-300">
                            🛵
                        </div>
                        <h3 class="text-xl font-bold mb-2 text-slate-900">Lacak Pesanan</h3>
                        <p class="text-slate-600 text-sm px-4">Pantau kurirmu real-time sampai ke titik jemput.</p>
                    </div>

                    <!-- Step 4 -->
                    <div class="relative flex flex-col items-center text-center group">
                        <div
                            class="w-24 h-24 bg-white rounded-full border-4 border-white shadow-xl flex items-center justify-center text-4xl mb-6 group-hover:scale-110 group-hover:border-yellow-500 transition duration-300">
                            😋
                        </div>
                        <h3 class="text-xl font-bold mb-2 text-slate-900">Nikmati</h3>
                        <p class="text-slate-600 text-sm px-4">Makan enak tanpa perlu beranjak dari tempatmu.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Key Features Section -->
        <section class="py-20 bg-white">
            <div class="container mx-auto px-4">
                <div class="text-center mb-16">
                    <h2 class="text-3xl lg:text-4xl font-bold mb-4">Kenapa Foodlab Lebih Oke?</h2>
                    <p class="text-slate-600">Dibanding aplikasi sebelah, kita lebih ngertiin mahasiswa.</p>
                </div>

                <div class="max-w-4xl mx-auto overflow-hidden rounded-2xl border border-slate-200 shadow-lg">
                    <div class="grid grid-cols-3 bg-slate-50 border-b border-slate-200 text-sm md:text-base">
                        <div class="p-4 font-bold text-slate-500">Fitur</div>
                        <div class="p-4 font-bold text-slate-500 text-center">Aplikasi Umum</div>
                        <div class="p-4 font-bold text-blue-600 bg-blue-50/50 text-center relative">
                            Foodlab
                            <div class="absolute top-0 left-0 w-full h-1 bg-blue-600"></div>
                        </div>
                    </div>

                    <!-- Row 1 -->
                    <div class="grid grid-cols-3 border-b border-slate-100 hover:bg-slate-50 transition">
                        <div class="p-4 font-medium text-slate-700 flex items-center">Ongkir Kampus</div>
                        <div
                            class="p-4 text-slate-500 text-center flex items-center justify-center border-l border-slate-100">
                            Mahal (>10rb)</div>
                        <div
                            class="p-4 font-bold text-center flex items-center justify-center border-l border-slate-100 text-green-600 bg-green-50/30">
                            <span class="mr-1">✅</span> Murah meriah (Mulai 2rb)
                        </div>
                    </div>

                    <!-- Row 2 -->
                    <div class="grid grid-cols-3 border-b border-slate-100 hover:bg-slate-50 transition">
                        <div class="p-4 font-medium text-slate-700 flex items-center">Titik Jemput</div>
                        <div
                            class="p-4 text-slate-500 text-center flex items-center justify-center border-l border-slate-100">
                            Gerbang Depan</div>
                        <div
                            class="p-4 font-bold text-center flex items-center justify-center border-l border-slate-100 text-green-600 bg-green-50/30">
                            <span class="mr-1">✅</span> Lobby / Depan Gedung
                        </div>
                    </div>

                    <!-- Row 3 -->
                    <div class="grid grid-cols-3 border-b border-slate-100 hover:bg-slate-50 transition">
                        <div class="p-4 font-medium text-slate-700 flex items-center">Voucher Mahasiswa</div>
                        <div
                            class="p-4 text-slate-500 text-center flex items-center justify-center border-l border-slate-100">
                            Jarang</div>
                        <div
                            class="p-4 font-bold text-center flex items-center justify-center border-l border-slate-100 text-blue-600">
                            Banyak (Exam Promo, dll)
                        </div>
                    </div>

                    <!-- Row 4 -->
                    <div class="grid grid-cols-3 hover:bg-slate-50 transition">
                        <div class="p-4 font-medium text-slate-700 flex items-center">Minimum Order</div>
                        <div
                            class="p-4 text-slate-500 text-center flex items-center justify-center border-l border-slate-100">
                            Tinggi</div>
                        <div
                            class="p-4 font-bold text-center flex items-center justify-center border-l border-slate-100 text-blue-600">
                            Tanpa Minimum Order
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Testimonials -->
        <section class="py-20 bg-slate-900 text-white">
            <div class="container mx-auto px-4 text-center mb-16">
                <h2 class="text-3xl lg:text-4xl font-bold mb-4">Kata Teman-Teman Kampus</h2>
            </div>
            <div class="container mx-auto px-4 grid md:grid-cols-2 gap-8 max-w-4xl">
                <div class="bg-slate-800 p-8 rounded-2xl relative">
                    <div class="text-6xl text-yellow-500 absolute top-4 left-4 opacity-20">"</div>
                    <p class="text-lg italic mb-6 relative z-10">
                        "Sangat membantu pas lagi ngerjain skripsi di perpus, gak perlu takut kehilangan tempat duduk
                        cuma buat cari makan."
                    </p>
                    <div class="flex items-center gap-4">
                        <div
                            class="w-12 h-12 bg-slate-700 rounded-full flex items-center justify-center font-bold text-xl">
                            A</div>
                        <div class="text-left">
                            <div class="font-bold">Andi</div>
                            <div class="text-sm text-slate-400">Mahasiswa Teknik</div>
                        </div>
                    </div>
                </div>
                <div class="bg-slate-800 p-8 rounded-2xl relative">
                    <div class="text-6xl text-yellow-500 absolute top-4 left-4 opacity-20">"</div>
                    <p class="text-lg italic mb-6 relative z-10">
                        "Tugas numpuk, laper, panas. Foodlab penyelamat banget sih. Titik jemputnya pas di depan lab!"
                    </p>
                    <div class="flex items-center gap-4">
                        <div
                            class="w-12 h-12 bg-slate-700 rounded-full flex items-center justify-center font-bold text-xl">
                            S</div>
                        <div class="text-left">
                            <div class="font-bold">Siti</div>
                            <div class="text-sm text-slate-400">Mahasiswa DKV</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- App Preview Section -->
        <section class="py-20 bg-blue-50 overflow-hidden">
            <div class="container mx-auto px-4 flex flex-col items-center">
                <h2 class="text-3xl lg:text-4xl font-bold mb-12 text-center">Tampilan Kece, Gampang Pakai</h2>
                <div class="flex gap-8 overflow-x-auto pb-10 w-full justify-center snap-x">
                    <div
                        class="snap-center shrink-0 w-[280px] h-[580px] bg-slate-900 rounded-[2.5rem] border-8 border-slate-800 shadow-2xl overflow-hidden relative group">
                        <div
                            class="absolute inset-0 bg-slate-800 flex items-center justify-center group-hover:bg-slate-700 transition">
                            <span class="text-slate-500 font-medium">Screen 1</span>
                        </div>
                        <div class="absolute top-0 w-1/2 h-6 bg-slate-800 rounded-b-xl left-1/2 -translate-x-1/2"></div>
                    </div>
                    <div
                        class="snap-center shrink-0 w-[280px] h-[580px] bg-slate-900 rounded-[2.5rem] border-8 border-slate-800 shadow-2xl overflow-hidden relative group">
                        <div
                            class="absolute inset-0 bg-slate-800 flex items-center justify-center group-hover:bg-slate-700 transition">
                            <span class="text-slate-500 font-medium">Screen 2</span>
                        </div>
                        <div class="absolute top-0 w-1/2 h-6 bg-slate-800 rounded-b-xl left-1/2 -translate-x-1/2"></div>
                    </div>
                    <div
                        class="snap-center shrink-0 w-[280px] h-[580px] bg-slate-900 rounded-[2.5rem] border-8 border-slate-800 shadow-2xl overflow-hidden relative group">
                        <div
                            class="absolute inset-0 bg-slate-800 flex items-center justify-center group-hover:bg-slate-700 transition">
                            <span class="text-slate-500 font-medium">Screen 3</span>
                        </div>
                        <div class="absolute top-0 w-1/2 h-6 bg-slate-800 rounded-b-xl left-1/2 -translate-x-1/2"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Download CTA Section -->
        <section id="download" class="py-20 bg-gradient-to-br from-blue-600 to-blue-800 text-white">
            <div class="container mx-auto px-4 text-center">
                <h2 class="text-4xl lg:text-5xl font-bold mb-6">Siap Pesan Sekarang?</h2>
                <p class="text-blue-100 text-lg mb-10 max-w-2xl mx-auto">
                    Download aplikasi Foodlab sekarang dan rasakan kemudahan pesan makanan di kampus!
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                    <a href="#"
                        class="bg-white text-blue-600 px-8 py-4 rounded-xl font-bold hover:bg-blue-50 transition flex items-center gap-3 shadow-lg">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M3,20.5V3.5C3,2.91 3.34,2.39 3.84,2.15L13.69,12L3.84,21.85C3.34,21.6 3,21.09 3,20.5M16.81,15.12L6.05,21.34L14.54,12.85L16.81,15.12M20.16,10.81C20.5,11.08 20.75,11.5 20.75,12C20.75,12.5 20.53,12.9 20.18,13.18L17.89,14.5L15.39,12L17.89,9.5L20.16,10.81M6.05,2.66L16.81,8.88L14.54,11.15L6.05,2.66Z" />
                        </svg>
                        <div class="text-left">
                            <div class="text-xs text-blue-400">Download di</div>
                            <div class="text-base font-bold">Google Play</div>
                        </div>
                    </a>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-white border-t border-slate-200 pt-16 pb-8">
            <div class="container mx-auto px-4">
                <div class="flex flex-col md:flex-row justify-between items-start gap-12 mb-12">
                    <div class="md:w-1/3">
                        <div class="text-2xl font-bold text-blue-600 mb-4">Foodlab<span class="text-yellow-500">.</span>
                        </div>
                        <p class="text-slate-500 mb-6">
                            Platform pesan antar makanan #1 khusus mahasiswa. Solusi perut lapar tanpa ribet, tanpa
                            antre.
                        </p>
                        <div class="flex gap-4">
                            <div
                                class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center hover:bg-blue-600 hover:text-white transition cursor-pointer">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                                </svg>
                            </div>
                            <div
                                class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center hover:bg-blue-600 hover:text-white transition cursor-pointer">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="md:w-2/3 grid grid-cols-2 md:grid-cols-3 gap-8">
                        <div>
                            <h4 class="font-bold mb-4">Perusahaan</h4>
                            <ul class="space-y-2 text-slate-500 text-sm">
                                <li><a href="#" class="hover:text-blue-600 transition">Tentang Kami</a></li>
                                <li><a href="#" class="hover:text-blue-600 transition">Blog</a></li>
                                <li><a href="#" class="hover:text-blue-600 transition">Karir</a></li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="font-bold mb-4">Bantuan</h4>
                            <ul class="space-y-2 text-slate-500 text-sm">
                                <li><a href="#" class="hover:text-blue-600 transition">Pusat Bantuan</a></li>
                                <li><a href="#" class="hover:text-blue-600 transition">Syarat & Ketentuan</a></li>
                                <li><a href="#" class="hover:text-blue-600 transition">Kebijakan Privasi</a></li>
                                <li><a href="#" class="hover:text-blue-600 transition">Daftar Mitra</a></li>
                            </ul>
                        </div>
                        <div class="col-span-2 md:col-span-1">
                            <h4 class="font-bold mb-4">Download</h4>
                            <a href="#"
                                class="bg-slate-900 text-white px-6 py-2 rounded-lg text-sm w-full mb-2 flex items-center justify-center gap-2 hover:bg-slate-800 transition">
                                Play Store
                            </a>
                            <a href="#"
                                class="bg-slate-900 text-white px-6 py-2 rounded-lg text-sm w-full flex items-center justify-center gap-2 hover:bg-slate-800 transition">
                                App Store
                            </a>
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-8 text-center text-slate-400 text-sm">
                    &copy; {{ date('Y') }} Foodlab Indonesia. All rights reserved.
                </div>
            </div>
        </footer>
    </main>
</x-guest-layout>