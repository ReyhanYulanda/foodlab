import Navbar from "@/components/layout/Navbar";
import Hero from "@/components/sections/Hero";
import PainPoints from "@/components/sections/PainPoints";
import HowItWorks from "@/components/sections/HowItWorks";
import KeyFeatures from "@/components/sections/KeyFeatures";
import Testimonials from "@/components/sections/Testimonials";
import AppPreview from "@/components/sections/AppPreview";
import DownloadCTA from "@/components/sections/DownloadCTA";
import Footer from "@/components/layout/Footer";

export default function Home() {
  return (
    <main className="min-h-screen">
      <Navbar />
      <Hero />
      <PainPoints />
      <HowItWorks />
      <KeyFeatures />
      <Testimonials />
      <AppPreview />
      <DownloadCTA />
      <Footer />
    </main>
  );
}
