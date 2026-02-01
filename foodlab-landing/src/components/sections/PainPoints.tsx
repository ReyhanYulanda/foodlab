"use client";

import { motion } from "framer-motion";

export default function PainPoints() {
  return (
    <section className="py-20 bg-white overflow-hidden">
      <div className="container mx-auto px-4 text-center mb-16">
        <motion.div
           initial={{ opacity: 0, y: 20 }}
           whileInView={{ opacity: 1, y: 0 }}
           transition={{ duration: 0.6 }}
           viewport={{ once: true }}
        >
          <h2 className="text-3xl lg:text-4xl font-bold mb-4">
            Kenapa Sih Harus Foodlab?
          </h2>
          <p className="text-slate-600 max-w-2xl mx-auto">
            Kami paham banget rasanya jadi mahasiswa sibuk yang perutnya gak bisa
            diajak kompromi.
          </p>
        </motion.div>
      </div>

      <div 
        className="container mx-auto px-4 grid md:grid-cols-3 gap-8"
      >
        {/* Card 1 */}
        <motion.div 
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5, delay: 0.1 }}
          viewport={{ once: true }}
          className="p-8 rounded-2xl bg-slate-50 hover:bg-blue-50 transition border border-slate-100 group"
        >
          <div className="w-14 h-14 bg-red-100 text-red-600 rounded-xl flex items-center justify-center mb-6 text-2xl group-hover:scale-110 transition">
            😓
          </div>
          <h3 className="text-xl font-bold mb-3">Mager Keluar Kelas</h3>
          <p className="text-slate-600">
            Lagi asik dengerin dosen (atau ngantuk), tapi perut bunyi? Gak perlu
            jalan jauh ke kantin.
          </p>
        </motion.div>

        {/* Card 2 */}
        <motion.div 
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5, delay: 0.2 }}
          viewport={{ once: true }}
          className="p-8 rounded-2xl bg-slate-50 hover:bg-blue-50 transition border border-slate-100 group"
        >
          <div className="w-14 h-14 bg-orange-100 text-orange-600 rounded-xl flex items-center justify-center mb-6 text-2xl group-hover:scale-110 transition">
            ⏳
          </div>
          <h3 className="text-xl font-bold mb-3">Antrean Panjang</h3>
          <p className="text-slate-600">
            Waktu istirahat cuma sebentar, eh antrean kantin kayak uler tangga.
            Foodlab-in aja biar cepet.
          </p>
        </motion.div>

        {/* Card 3 */}
        <motion.div 
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5, delay: 0.3 }}
          viewport={{ once: true }}
          className="p-8 rounded-2xl bg-slate-50 hover:bg-blue-50 transition border border-slate-100 group"
        >
          <div className="w-14 h-14 bg-green-100 text-green-600 rounded-xl flex items-center justify-center mb-6 text-2xl group-hover:scale-110 transition">
            💸
          </div>
          <h3 className="text-xl font-bold mb-3">Hemat Budget</h3>
          <p className="text-slate-600">
            Ongkir mahal? Tenang, tarif kita bersahabat banget sama kantong
            mahasiswa akhir bulan.
          </p>
        </motion.div>
      </div>
    </section>
  );
}
