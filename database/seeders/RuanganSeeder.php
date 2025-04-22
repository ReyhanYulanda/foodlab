<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Gedung;
use App\Models\Ruangan;

class RuanganSeeder extends Seeder
{
    public function run()
    {
        $gedungD4 = Gedung::where('nama', 'D4')->first();
        $gedungD3 = Gedung::where('nama', 'D3')->first();
        $gedungPASCA = Gedung::where('nama', 'PASCA')->first();
        $gedungSAW = Gedung::where('nama', 'SAW')->first();

        if (!$gedungD4 || !$gedungD3 || !$gedungPASCA) {
            $this->command->error('Gedung D3 atau D4 tidak ditemukan. Pastikan seed Gedung dijalankan terlebih dahulu.');
            return;
        }

        $ruanganD4 = [
            "C 102-Lab.Pusat Data & Sistem Bisnis",
            "C 105-Lab.Informatika Kesehatan",
            "C 203-Lab.Human Centered Artificial Intellegence",
            "C 206-R. Lab.Agile Product Development",
            "C 302-R. KaLab & Pengajar",
            "C 303-Lab Rekayasa Data & Optimasi Proses",
            "C 306-R. KaLab & Pengajar",
            "C 307-Jaringan Komputer",
            "C.106-R. Lab.Health Informatic",
            "C.106 (2)-C.106 (2)",
            "D 103-Lab. Pengemudian Elektrik dan Kendaraan",
            "D 104-Kontrol Cerdas dan Robotika",
            "D 105-R. KaLab & Pengajar",
            "D 203-R. Lab. Kualitas Daya dan Pengontrolan",
            "D 204-R. Proyek Otomasi Industri",
            "D 205-Ka Lab.& Pengajar",
            "D 206-Lab. Elektronika Industri",
            "D 303-R. Lab Otomasi Industri dan Sistem Cerdas",
            "D 305-R. Ka.Lab.& Pengajar",
            "D 306-Lab.Jaringan Sensor Nirkabel",
            "D.102-R. Ka Lab & Pengajar",
            "E 107-R.Jaringan Bergerak & Komputasi Pervasif",
            "E 206-Lab. Pemrosesan Sinyal",
            "E 304-R. KaLab & Pengajar",
            "E 305-R. Komunikasi Nirkabel",
            "G 102-R.Lab.Elektronika Daya untuk Konversi Energi D4",
            "G 103-Lab. Distribusi dan Utilitas Tenaga Listrik D4",
            "G 104-Proyek Sistem Tenaga",
            "H 102-Lab.Kecerdasan Buatan dan Sistem Benam Pintar D4",
            "H 202-Lab. Perancangan Logika",
            "H 101-Ruang Kalab & Pengajar",
            "H 201-R. KaLab & Pengajar D4",
        ];

        $ruanganD3 = [
            "HI 205-R. Proyek Akhir D3 T. Komputer",
            "HI 102-Ruang Workshop",
            "HI 201-R.Tugas Akhir T.Komputer D3",
            "HI 202-Lab.Visi Komputer & Grafis",
            "HI 203-R. Robotika Industri",
            "HI 204-Lab. Pemrograman Real Time D3",
            "HI 301-R.Lab.Real Time Computer System D3",
            "HI 302-Ruang Persiapan Dosen T.Komputer",
            "HI 303-Lab. Sistem Analog D3",
            "HI 304-Lab. Sistem Digital D3",
            "HI 104-R. Persiapan Dosen T.Komputer",
            "IT 013-LAB WORKSHOP",
            "IT 201-LAB ARTIFICIAL INTELLIGENCE",
            "IT 206-LAB RPL DAN SISTEM OPERASI",
            "JJ 202-Lab Elka Dasar",
            "JJ 101-Lab.Perancangan Sistem Elektronika",
            "JJ 102-Lab. Kendaraan Robotika D3",
            "JJ 103-LAB DIGITAL",
            "JJ 105-RUANG/KAMAR GELAP (PCB)",
            "JJ 106-Lab.Penyimpanan Energi & Tegangan Tinggi D3",
            "JJ 109-R. Lab Teknologi Smart Grid",
            "JJ 110-Lab. Manufaktur",
            "JJ 112-Lab.Bengkel Manufaktur D3",
            "JJ 112A-Pembelajaran Berbasis Proyek",
            "JJ 201-Lab. Elektronika Terapan D3",
            "JJ 203-Lab. Elektronika Dasar D3",
            "JJ 204-Lab.Simulasi Sistem Tenaga",
            "JJ 207-Lab. Instrumentasi Elektronika & kompabilitas",
            "JJ 208-Lab.Sistem Benam & Jaringan",
            "JJ 209-Ruang Persiapan Dosen Mekatronika",
            "JJ 210-R. Lab Kontrol Mekatronika",
            "JJ 301-Lab.Komunikasi Optik D3",
            "JJ 302-Lab. Elektronika Komunikasi",
            "JJ 303-R. Lab Elektro Komunikasi",
            "JJ 304-Lab. Dasar Telekomunikasi D3",
            "JJ 305-Lab.Teknik Telekomunikasi D3",
            "JJ 306-Lab.Proyek Komunikasi Bergerak",
            "JJ 309-Lab. Komunikasi Data D3",
            "JJ 310-Lab. Media Digital D3",
            "JJ 311-Lab.Produksi Audiovisual D3",
            "JJ 312-Studio Photografi D3",
            "JJ 313-R. Lab.Studio Penyiaran D3",
            "JJ 303-R.Lab.Elektro Komunikasi D3",
            "JJ 102-Lab. Kendaraan Robotika",
        ];

        $ruanganPASCA = [
            "PS 03.14-Lab Mekanika & Robotika Lt3 Pascasarjana",
            "PS 03.16-Lab Pasca",
            "PS 01.02-R. Lab Energi Terbarukan S2",
            "PS 01.03-R. Mesin Adaptif",
            "PS 01.04-Lab. Green Electrification",
            "PS 01.05-Lab. Fusi Sensor",
            "PS 01.06-Ruang Mahasiswa Lt.1 Pasca sarjana",
            "PS 03.14-R. Mekanika & Robotika Lt.3 Pascasarjana",
            "PS 03.03-R.Lab Manufaktur dan Teknik Presisi S2",
            "PS 03.16-Ruang Dosen Lt.3 Pascasarjana",
            "PS 03.17-Lab Based Workshop",
            "PS 04.02-R.Piranti Sensor Cerdas & Sistem Terapan",
            "PS 04.03-R.Lab Transportasi Teknologi Hijau & Aplikasi",
            "PS 04.04-Ruang Dosen S2",
            "PS 04.05-R. Lab Biomedis dan instrumen medis",
            "PS 04.06-R.Proyek Perangkat & Sensor",
            "PS 04.07-R. Lab Biomedis dan Instrumentasi Medis",
            "PS 04.13-R. Lab.Teknologi Terapan Rekayasa Akuakultur",
            "PS 04.14-Lab. Komunikasi Nirkabel & Bergerak",
            "PS 04.15-R.Lab.Teknologi Terapan Rekayasa Akuakultur",
            "PS 04.16-R.. Lab Komunikasi Nirkabel & Bergerak",
            "PS 04.17-R. Teknologi Terapan Rekayasa Akuakultur",
            "PS 04.18-R. Lab Proyek Perangkat Nirkabel",
            "PS 05.06-R. Lab.Pervasive Computing",
            "PS 05.09-R. Lab. Mobile Sensing & Edge Computing",
            "PS 05.11-R.Lab.Mobile Sensing & Edge Computing Technology",
            "PS 05.13-R. Lab Proyek IoT",
            "PS 07.06-R. Lab Sistem Siber Fisik",
            "PS 07.07-R. Lab Sistem Siber Fisik",
            "PS 07.09-R. Lab Sistem Siber Fisik",
            "PS 08.02-R.Lab Database & Knowledge Engineering",
            "PS 08.05-Ruang Mahasiswa 5 Lt 8 S2",
            "PS 08.06-R. Lab. Kesehatan & Kesehatan Hayati",
            "PS 08.07-Lab. Komputasi",
            "PS 08.08-R. Signal Vision Grafis Lt.8 S2",
            "PS 09.02-R. Lab.Human Centric Multimedia",
            "PS 09.04-R. Lab Human Centric Multimedia",
            "PS 09.05-R. Lab.Pendidikan Jarak Jauh S2",
            "PS 09.08-R. Pembelajaran Berbantuan Komputer S2",
            "PS 10.02-R. Lab. Komunikasi Multimedia Lt.10 S2",
            "PS 10.04-R. Lab.Proyek Permainan Edukasi",
            "PS 10.06-R. Lab.Proyek Multimedia",
            "PS 10.07-R. Lab Tek.Multimedia Interaktif S2",
            "PS 11.01-R. Lab.Proyek Multimedia",
            "PS 11.03-R. Lab Digitalisasi Visual",
            "PS 11.06-R. Lab Studio TV Pertunjukan",
            "PS 11.08-R. Fitting Lt. 11 S2",
            "PS 11.10-R. Make UP Lt. 11 S2",
            "PS 11.11-R. Lab. Studio TV Pertunjukan",
            "PS 08.08-Pasca Sarjana Lnt 8 8",
            "PS 10.05-Pasca Sarjana Lnt 10 5",
            "PS 10.07-Lab. Teknik Multimedia Interaktif",
            "PS 11.05-R. Lab Digital Imaging Lt.11 S2",
        ];

        $ruanganSAW = [
            "SAW 00.03-Lab. Bengkel CNC",
            "SAW 00.07-Lab. Workshop Manufakture",
            "SAW 01.01-Lab. Konversi Energi Elektromekanis",
            "SAW 01.02-Lab. Rapid Prototyping",
            "SAW 01.06-Lab. Sistem Manufaktur Fleksibel",
            "SAW 01.07-Lab. Workshop Perakitan dan Pengujian",
            "SAW 03.07-Lab. Komunikasi Bergerak & IoT",
            "SAW 03.08-Lab. Keamanan Jaringan",
            "SAW 03.14-Lab. Mekatronika Cerdas & Robotika",
            "SAW 04.06-Lab. Technology Internet",
            "SAW 04.07-Lab. Cloud Computing",
            "SAW 07.01-Lab. Proyek Data",
            "SAW 07.06-Lab. Analisa dan Pemodelan Data",
            "SAW 07.07-Lab. Rekayasa Data",
            "SAW 08.01-Lab. Artistik Game",
            "SAW 08.06-Lab. Multimedia & Game Technology",
            "SAW 08.07-Lab. Game Art & Design",
            "SAW 09.06-Lab. Audio Visual & Animasi",
            "SAW 09.06-Lab. Audio Visual & Animation",
            "SAW 09.07-Lab. Immersive Technology",
            "SAW 09.07-Lab. Teknologi Imersif",
            "SAW 10.02-Lab. Medical & Bio Inspired System",
            "SAW 10.06-Lab. Sistem Otonom",
            "SAW 10.06-Lab. Food & Agricultural Technology",
            "SAW 10.07-Lab. Teknologi Energi",
            "SAW 11.01-Lab. Transportasi & Kota Pintar",
            "SAW 11.02-Lab. Radio & Satellite Communication",
            "SAW 11.03-Lab. Radio & Satellite Communication",
            "SAW 11.07-Lab. Radio & Satellite Communication",
        ];

        foreach ($ruanganD4 as $fullName) {
            [$kodeRuangan, $nama] = explode('-', $fullName, 2);
            Ruangan::create([
                'kode_ruangan' => trim($kodeRuangan),
                'nama' => trim($nama),
                'gedung_id' => $gedungD4->id,
            ]);
        }

        foreach ($ruanganD3 as $fullName) {
            [$kodeRuangan, $nama] = explode('-', $fullName, 2);
            Ruangan::create([
                'kode_ruangan' => trim($kodeRuangan),
                'nama' => trim($nama),
                'gedung_id' => $gedungD3->id,
            ]);
        }

        foreach ($ruanganPASCA as $fullName) {
            [$kodeRuangan, $nama] = explode('-', $fullName, 2);
            Ruangan::create([
                'kode_ruangan' => trim($kodeRuangan),
                'nama' => trim($nama),
                'gedung_id' => $gedungPASCA->id,
            ]);
        }
        
        foreach ($ruanganSAW as $fullName) {
            [$kodeRuangan, $nama] = explode('-', $fullName, 2);
            Ruangan::create([
                'kode_ruangan' => trim($kodeRuangan),
                'nama' => trim($nama),
                'gedung_id' => $gedungSAW->id,
            ]);
        }

        $this->command->info(count($ruanganD4) . " ruangan berhasil ditambahkan ke gedung D4.");
        $this->command->info(count($ruanganD3) . " ruangan berhasil ditambahkan ke gedung D3.");
        $this->command->info(count($ruanganPASCA) . " ruangan berhasil ditambahkan ke gedung PASCA.");
        $this->command->info(count($ruanganSAW) . " ruangan berhasil ditambahkan ke gedung SAW.");
    }
}
