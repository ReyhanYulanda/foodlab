import { Download } from "lucide-react";
import Link from "next/link";

export default function Navbar() {
  return (
    <nav className="fixed w-full bg-white/80 backdrop-blur-md z-50 border-b border-slate-100">
      <div className="container mx-auto px-4 py-4 flex justify-between items-center">
        <div className="text-2xl font-bold text-primary-blue">
          Foodlab<span className="text-primary-yellow">.</span>
        </div>
        <Link 
            href="#download"
            className="bg-primary-blue text-white px-6 py-2 rounded-full font-medium hover:bg-blue-700 transition"
        >
          Download App
        </Link>
      </div>
    </nav>
  );
}
