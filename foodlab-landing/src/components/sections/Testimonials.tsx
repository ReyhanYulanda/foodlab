"use client";

import { motion } from "framer-motion";

export default function Testimonials() {
  return (
    <section className="py-20 bg-slate-900 text-white">
      <div className="container mx-auto px-4 text-center mb-16">
        <motion.h2 
          className="text-3xl lg:text-4xl font-bold mb-4"
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6 }}
          viewport={{ once: true }}
        >
          Kata Teman-Teman Kampus
        </motion.h2>
      </div>
      <div className="container mx-auto px-4 grid md:grid-cols-2 gap-8 max-w-4xl">
        <motion.div 
          className="bg-slate-800 p-8 rounded-2xl relative"
          initial={{ opacity: 0, x: -50 }}
          whileInView={{ opacity: 1, x: 0 }}
          transition={{ duration: 0.6 }}
          viewport={{ once: true }}
        >
          <div className="text-6xl text-primary-yellow absolute top-4 left-4 opacity-20">
            "
          </div>
          <p className="text-lg italic mb-6 relative z-10">
            “Sangat membantu pas lagi ngerjain skripsi di perpus, gak perlu
            takut kehilangan tempat duduk cuma buat cari makan.”
          </p>
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 bg-slate-700 rounded-full flex items-center justify-center font-bold text-xl">
              A
            </div>
            <div className="text-left">
              <div className="font-bold">Andi</div>
              <div className="text-sm text-slate-400">Mahasiswa Teknik</div>
            </div>
          </div>
        </motion.div>
        
        <motion.div 
          className="bg-slate-800 p-8 rounded-2xl relative"
          initial={{ opacity: 0, x: 50 }}
          whileInView={{ opacity: 1, x: 0 }}
          transition={{ duration: 0.6, delay: 0.2 }}
          viewport={{ once: true }}
        >
          <div className="text-6xl text-primary-yellow absolute top-4 left-4 opacity-20">
            "
          </div>
          <p className="text-lg italic mb-6 relative z-10">
            “Tugas numpuk, laper, panas. Foodlab penyelamat banget sih. Titik
            jemputnya pas di depan lab!”
          </p>
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 bg-slate-700 rounded-full flex items-center justify-center font-bold text-xl">
              S
            </div>
            <div className="text-left">
              <div className="font-bold">Siti</div>
              <div className="text-sm text-slate-400">Mahasiswa DKV</div>
            </div>
          </div>
        </motion.div>
      </div>
    </section>
  );
}
