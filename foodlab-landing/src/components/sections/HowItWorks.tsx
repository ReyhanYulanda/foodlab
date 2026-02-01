"use client";

import { motion } from "framer-motion";

export default function HowItWorks() {
  return (
    <section
      id="how-it-works"
      className="py-20 bg-slate-50 relative overflow-hidden"
    >
      <div className="container mx-auto px-4 text-center mb-16 relative z-10">
        <motion.div
           initial={{ opacity: 0, y: 20 }}
           whileInView={{ opacity: 1, y: 0 }}
           transition={{ duration: 0.6 }}
           viewport={{ once: true }}
        >
          <span className="text-primary-blue font-semibold tracking-wide uppercase text-sm">
            Gampang Banget
          </span>
          <h2 className="text-3xl lg:text-4xl font-bold mt-2">
            Cuma 4 Langkah Mudah
          </h2>
        </motion.div>
      </div>

      <div className="container mx-auto px-4 relative z-10">
        <div className="grid md:grid-cols-4 gap-8">
          {[
            {
              title: "Pilih Menu",
              icon: "🍔",
              desc: "Cari makanan dari vendor favorit di sekitar kampus.",
            },
            {
              title: "Pesan & Bayar",
              icon: "💳",
              desc: "Bayar pakai E-wallet favoritmu, praktis & aman.",
            },
            {
              title: "Lacak Pesanan",
              icon: "🛵",
              desc: "Pantau kurirmu real-time sampai ke titik jemput.",
            },
            {
              title: "Nikmati",
              icon: "😋",
              desc: "Makan enak tanpa perlu beranjak dari tempatmu.",
            },
          ].map((step, idx) => (
            <motion.div
              key={idx}
              className="relative flex flex-col items-center text-center group"
              initial={{ opacity: 0, y: 30 }}
              whileInView={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.5, delay: idx * 0.1 }}
              viewport={{ once: true }}
            >
              {idx !== 3 && (
                <div className="hidden md:block absolute top-12 left-1/2 w-full h-1 bg-slate-200 -z-10"></div>
              )}
              <div className="w-24 h-24 bg-white rounded-full border-4 border-white shadow-xl flex items-center justify-center text-4xl mb-6 group-hover:scale-110 group-hover:border-primary-yellow transition duration-300">
                {step.icon}
              </div>
              <h3 className="text-xl font-bold mb-2 text-slate-900">
                {step.title}
              </h3>
              <p className="text-slate-600 text-sm px-4">{step.desc}</p>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}
