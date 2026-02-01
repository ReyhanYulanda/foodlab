"use client";

import { Smartphone } from "lucide-react";
import Link from "next/link";
import { motion } from "framer-motion";

export default function Hero() {
  return (
    <section className="min-h-screen flex items-center pt-20 pb-20 bg-gradient-to-br from-blue-50 to-white overflow-hidden relative">
      <div className="container mx-auto px-4">
        <div className="flex flex-col lg:flex-row items-center gap-12">
          <motion.div 
            initial={{ opacity: 0, x: -50 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ duration: 0.6 }}
            className="lg:w-1/2 space-y-6"
          >
            <motion.span 
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.2, duration: 0.5 }}
              className="inline-block px-4 py-1.5 bg-blue-100 text-primary-blue font-semibold rounded-full text-sm"
            >
              Solusi Perut Keroncongan di Kampus 🎓
            </motion.span>
            <motion.h1 
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.3, duration: 0.5 }}
              className="text-5xl lg:text-6xl font-bold leading-tight text-slate-900"
            >
              Lapar di Tengah Kelas? <br />
              <span className="text-primary-blue">Foodlab-in Aja!</span>
            </motion.h1>
            <motion.p 
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.4, duration: 0.5 }}
              className="text-lg text-slate-600 max-w-lg"
            >
              Pesan makanan favoritmu dari kantin atau sekitar kampus, diantar
              langsung ke depan kelas, perpustakaan, atau kosan.
            </motion.p>
            <motion.div 
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.5, duration: 0.5 }}
              className="flex flex-col sm:flex-row gap-4 pt-4"
            >
              <Link
                href="#download"
                className="bg-primary-blue text-white px-8 py-4 rounded-full font-semibold flex items-center justify-center gap-2 hover:bg-blue-700 transition shadow-lg shadow-blue-500/30"
              >
                <Smartphone className="w-5 h-5" />
                Download di Play Store
              </Link>
              <Link
                href="#how-it-works"
                className="bg-white text-slate-700 border border-slate-200 px-8 py-4 rounded-full font-semibold hover:bg-slate-50 transition flex items-center justify-center"
              >
                Pelajari Cara Kerja
              </Link>
            </motion.div>
            <motion.div 
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.6, duration: 0.5 }}
              className="pt-8 flex items-center gap-8 text-sm text-slate-500 font-medium"
            >
              <div className="flex items-center gap-2">
                <div className="bg-green-100 p-1 rounded-full">
                  <div className="w-2 h-2 bg-green-500 rounded-full"></div>
                </div>
                <span>10k+ Mahasiswa</span>
              </div>
              <div className="flex items-center gap-2">
                <div className="bg-green-100 p-1 rounded-full">
                  <div className="w-2 h-2 bg-green-500 rounded-full"></div>
                </div>
                <span>50+ Merchant Favorit</span>
              </div>
            </motion.div>
          </motion.div>

          <motion.div 
            initial={{ opacity: 0, x: 50 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ duration: 0.8, delay: 0.2 }}
            className="lg:w-1/2 relative"
          >
            {/* Visual Placeholder for Phone/App */}
            <div className="relative z-10 w-[300px] h-[600px] mx-auto bg-slate-900 rounded-[3rem] border-8 border-slate-800 shadow-2xl flex flex-col items-center justify-center overflow-hidden">
              <div className="absolute top-0 w-full h-full bg-slate-800 animate-pulse flex items-center justify-center">
                <span className="text-slate-600 font-bold">App Preview</span>
              </div>
              {/* Notch */}
              <div className="absolute top-0 w-1/2 h-6 bg-slate-800 rounded-b-xl"></div>
            </div>

            {/* Decorative Blobs */}
            <motion.div 
              animate={{ 
                scale: [1, 1.2, 1],
                rotate: [0, 90, 0],
                x: "-50%",
                y: "-50%"
              }}
              transition={{ 
                duration: 20, 
                repeat: Infinity,
                repeatType: "reverse" 
              }}
              className="absolute top-1/2 left-1/2 w-[500px] h-[500px] bg-primary-yellow/20 rounded-full blur-3xl -z-10"
            ></motion.div>
            <motion.div 
              animate={{ 
                scale: [1, 1.1, 1],
                rotate: [0, -45, 0],
                y: "-50%"
              }}
              transition={{ 
                duration: 15, 
                repeat: Infinity,
                repeatType: "reverse" 
              }}
              className="absolute top-1/2 right-10 w-[300px] h-[300px] bg-primary-blue/20 rounded-full blur-3xl -z-10"
            ></motion.div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}
