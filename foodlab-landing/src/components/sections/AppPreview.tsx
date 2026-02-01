"use client";

import { motion } from "framer-motion";

export default function AppPreview() {
  return (
    <section className="py-20 bg-blue-50 overflow-hidden">
      <div className="container mx-auto px-4 flex flex-col items-center">
        <motion.h2 
          className="text-3xl lg:text-4xl font-bold mb-12 text-center"
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6 }}
          viewport={{ once: true }}
        >
          Tampilan Kece, Gampang Pakai
        </motion.h2>

        <div className="flex gap-8 overflow-x-auto pb-10 w-full justify-center snap-x">
          {[1, 2, 3].map((i) => (
            <motion.div
              key={i}
              className="snap-center shrink-0 w-[280px] h-[580px] bg-slate-900 rounded-[2.5rem] border-8 border-slate-800 shadow-2xl overflow-hidden relative group"
              initial={{ opacity: 0, y: 50 }}
              whileInView={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6, delay: i * 0.2 }}
              viewport={{ once: true }}
            >
              <div className="absolute inset-0 bg-slate-800 flex items-center justify-center group-hover:bg-slate-700 transition">
                <span className="text-slate-500 font-medium">Screen {i}</span>
              </div>
              <div className="absolute top-0 w-1/2 h-6 bg-slate-800 rounded-b-xl left-1/2 -translate-x-1/2"></div>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}
