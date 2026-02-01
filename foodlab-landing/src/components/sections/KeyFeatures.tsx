"use client";

import clsx from "clsx";
import { motion } from "framer-motion";

export default function KeyFeatures() {
  const rows = [
    {
      feature: "Ongkir Kampus",
      competitor: "Mahal (>10rb)",
      foodlab: "Murah meriah (Mulai 2rb)",
      highlight: true,
    },
    {
      feature: "Titik Jemput",
      competitor: "Gerbang Depan",
      foodlab: "Lobby / Depan Gedung",
      highlight: true,
    },
    {
      feature: "Voucher Mahasiswa",
      competitor: "Jarang",
      foodlab: "Banyak (Exam Promo, dll)",
      highlight: false,
    },
    {
      feature: "Minimum Order",
      competitor: "Tinggi",
      foodlab: "Tanpa Minimum Order",
      highlight: false,
    },
  ];

  return (
    <section className="py-20 bg-white">
      <div className="container mx-auto px-4">
        <motion.div
           className="text-center mb-16"
           initial={{ opacity: 0, y: 20 }}
           whileInView={{ opacity: 1, y: 0 }}
           transition={{ duration: 0.6 }}
           viewport={{ once: true }}
        >
          <h2 className="text-3xl lg:text-4xl font-bold mb-4">
            Kenapa Foodlab Lebih Oke?
          </h2>
          <p className="text-slate-600">
            Dibanding aplikasi sebelah, kita lebih ngertiin mahasiswa.
          </p>
        </motion.div>

        <motion.div
           className="max-w-4xl mx-auto overflow-hidden rounded-2xl border border-slate-200 shadow-lg"
           initial={{ opacity: 0, y: 40 }}
           whileInView={{ opacity: 1, y: 0 }}
           transition={{ duration: 0.6, delay: 0.2 }}
           viewport={{ once: true }}
        >
          <div className="grid grid-cols-3 bg-slate-50 border-b border-slate-200 text-sm md:text-base">
            <div className="p-4 font-bold text-slate-500">Fitur</div>
            <div className="p-4 font-bold text-slate-500 text-center">
              Aplikasi Umum
            </div>
            <div className="p-4 font-bold text-primary-blue bg-blue-50/50 text-center relative">
              Foodlab
              <div className="absolute top-0 left-0 w-full h-1 bg-primary-blue"></div>
            </div>
          </div>

          {rows.map((row, idx) => (
            <div
              key={idx}
              className="grid grid-cols-3 border-b border-slate-100 last:border-0 hover:bg-slate-50 transition"
            >
              <div className="p-4 font-medium text-slate-700 flex items-center">
                {row.feature}
              </div>
              <div className="p-4 text-slate-500 text-center flex items-center justify-center border-l border-slate-100">
                {row.competitor}
              </div>
              <div
                className={clsx(
                  "p-4 font-bold text-center flex items-center justify-center border-l border-slate-100",
                  row.highlight
                    ? "text-green-600 bg-green-50/30"
                    : "text-primary-blue"
                )}
              >
                {row.highlight && <span className="mr-1">✅</span>} {row.foodlab}
              </div>
            </div>
          ))}
        </motion.div>
      </div>
    </section>
  );
}
