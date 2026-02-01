import { Instagram, Twitter } from "lucide-react";
import Link from "next/link";

export default function Footer() {
  return (
    <footer className="bg-white border-t border-slate-200 pt-16 pb-8">
      <div className="container mx-auto px-4">
        <div className="flex flex-col md:flex-row justify-between items-start gap-12 mb-12">
          <div className="md:w-1/3">
            <div className="text-2xl font-bold text-primary-blue mb-4">
              Foodlab<span className="text-primary-yellow">.</span>
            </div>
            <p className="text-slate-500 mb-6">
              Platform pesan antar makanan #1 khusus mahasiswa. Solusi perut lapar
              tanpa ribet, tanpa antre.
            </p>
            <div className="flex gap-4">
              <div className="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center hover:bg-primary-blue hover:text-white transition cursor-pointer">
                <Instagram size={20} />
              </div>
              <div className="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center hover:bg-primary-blue hover:text-white transition cursor-pointer">
                <Twitter size={20} />
              </div>
            </div>
          </div>

          <div className="md:w-2/3 grid grid-cols-2 md:grid-cols-3 gap-8">
            <div>
              <h4 className="font-bold mb-4">Perusahaan</h4>
              <ul className="space-y-2 text-slate-500 text-sm">
                <li><Link href="#" className="hover:text-primary-blue transition">Tentang Kami</Link></li>
                <li><Link href="#" className="hover:text-primary-blue transition">Blog</Link></li>
                <li><Link href="#" className="hover:text-primary-blue transition">Karir</Link></li>
              </ul>
            </div>
            <div>
              <h4 className="font-bold mb-4">Bantuan</h4>
              <ul className="space-y-2 text-slate-500 text-sm">
                <li><Link href="#" className="hover:text-primary-blue transition">Pusat Bantuan</Link></li>
                <li><Link href="#" className="hover:text-primary-blue transition">Syarat & Ketentuan</Link></li>
                <li><Link href="#" className="hover:text-primary-blue transition">Kebijakan Privasi</Link></li>
                <li><Link href="#" className="hover:text-primary-blue transition">Daftar Mitra</Link></li>
              </ul>
            </div>
            <div className="col-span-2 md:col-span-1">
              <h4 className="font-bold mb-4">Download</h4>
              <button className="bg-slate-900 text-white px-6 py-2 rounded-lg text-sm w-full mb-2 flex items-center justify-center gap-2 hover:bg-slate-800 transition">
                Play Store
              </button>
              <button className="bg-slate-900 text-white px-6 py-2 rounded-lg text-sm w-full flex items-center justify-center gap-2 hover:bg-slate-800 transition">
                App Store
              </button>
            </div>
          </div>
        </div>

        <div className="border-t border-slate-100 pt-8 text-center text-slate-400 text-sm">
          &copy; {new Date().getFullYear()} Foodlab Indonesia. All rights reserved.
        </div>
      </div>
    </footer>
  );
}
