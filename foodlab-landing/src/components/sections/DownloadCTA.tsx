
export default function DownloadCTA() {
  return (
    <section
      id="download"
      className="py-20 bg-gradient-to-br from-blue-600 to-blue-800 text-white"
    >
      <div className="container mx-auto px-4 text-center">
        <h2 className="text-4xl lg:text-5xl font-bold mb-6">
          Siap Pesan Sekarang?
        </h2>
        <p className="text-blue-100 text-lg mb-10 max-w-2xl mx-auto">
          Download aplikasi Foodlab sekarang dan rasakan kemudahan pesan makanan
          di kampus!
        </p>
        <div className="flex flex-col sm:flex-row gap-4 justify-center items-center">
          <a
            href="#"
            className="bg-white text-blue-600 px-8 py-4 rounded-xl font-bold hover:bg-blue-50 transition flex items-center gap-3 shadow-lg"
          >
            <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
              <path d="M3,20.5V3.5C3,2.91 3.34,2.39 3.84,2.15L13.69,12L3.84,21.85C3.34,21.6 3,21.09 3,20.5M16.81,15.12L6.05,21.34L14.54,12.85L16.81,15.12M20.16,10.81C20.5,11.08 20.75,11.5 20.75,12C20.75,12.5 20.53,12.9 20.18,13.18L17.89,14.5L15.39,12L17.89,9.5L20.16,10.81M6.05,2.66L16.81,8.88L14.54,11.15L6.05,2.66Z" />
            </svg>
            <div className="text-left">
              <div className="text-xs text-blue-400">Download di</div>
              <div className="text-base font-bold">Google Play</div>
            </div>
          </a>
        </div>
      </div>
    </section>
  );
}
