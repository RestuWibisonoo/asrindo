<?php
// Konfigurasi Data Produk (Bisa ditarik dari Database MySQL nantinya)
$company_name = "ASRINDO";
$hero_title = "Solusi Alat Berat Handal untuk Proyek Anda";
$hero_description = "Kami menyediakan layanan penjualan dan penyewaan unit alat berat kualitas prima dengan jaminan garansi resmi, suku cadang lengkap, serta dukungan teknisi 24/7.";

$features = [
    [
        "icon" => "M13 10V3L4 14h7v7l9-11h-7z", // SVG Path
        "title" => "Performa Maksimal",
        "desc" => "Unit mesin dalam kondisi prima, selalu melewati uji kelayakan berkala sebelum dikirim ke lokasi proyek."
    ],
    [
        "icon" => "M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z",
        "title" => "Garansi Resmi",
        "desc" => "Jaminan perlindungan unit dan servis penuh untuk menjamin kelancaran operasional bisnis Anda."
    ],
    [
        "icon" => "M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z",
        "title" => "Suku Cadang Asli",
        "desc" => "Ketersediaan suku cadang terlengkap dan original untuk berbagai tipe dan merek alat berat."
    ],
    [
        "icon" => "M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z",
        "title" => "Dukungan Teknisi 24/7",
        "desc" => "Tim teknisi berpengalaman siap diterjunkan langsung ke lokasi proyek saat dibutuhkan."
    ]
];
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company_name; ?> - Penjualan Alat Berat</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: '#000000',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-white text-slate-800 antialiased selection:bg-black selection:text-white">

    <header class="sticky top-0 z-50 bg-white/90 backdrop-blur-md border-b border-slate-100">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <a href="#hero" class="text-2xl font-black tracking-tight text-slate-900">
                <?php echo $company_name; ?>
            </a>
            
            <nav class="hidden md:flex items-center space-x-8 text-sm font-medium text-slate-600">
                <a href="#hero" class="hover:text-black transition-colors">Beranda</a>
                <a href="#fitur" class="hover:text-black transition-colors">Keunggulan</a>
                <a href="#produk" class="hover:text-black transition-colors">Katalog Unit</a>
                <a href="#kontak" class="hover:text-black transition-colors">Kontak</a>
            </nav>

            <div class="flex items-center space-x-4">
                <a href="https://wa.me/6281234567890" target="_blank" class="bg-black text-white px-5 py-2.5 rounded-md font-medium text-sm hover:bg-slate-800 transition-all">
                    Hubungi Sales
                </a>
            </div>
        </div>
    </header>

    <section id="hero" class="py-20 lg:py-28 max-w-7xl mx-auto px-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div>
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-slate-900 leading-[1.15] mb-6">
                    <?php echo $hero_title; ?>
                </h1>
                <p class="text-lg text-slate-600 leading-relaxed mb-8 max-w-xl">
                    <?php echo $hero_description; ?>
                </p>
                <div class="flex flex-col sm:flex-row gap-4">
                    <a href="#produk" class="inline-flex justify-center items-center px-6 py-3.5 bg-black text-white rounded-md font-medium hover:bg-slate-800 transition-all">
                        Lihat Katalog Unit
                    </a>
                    <a href="#kontak" class="inline-flex justify-center items-center px-6 py-3.5 border border-slate-300 rounded-md font-medium text-slate-700 hover:border-black hover:text-black transition-all">
                        Minta Penawaran
                    </a>
                </div>
            </div>
            
            <div class="relative flex justify-center lg:justify-end">
                <div class="w-full max-w-md bg-slate-100 rounded-2xl p-8 border border-slate-200 aspect-square flex items-center justify-center">
                    <svg class="w-48 h-48 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0h4m-4 0v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
            </div>
        </div>
    </section>

    <section id="fitur" class="py-20 bg-slate-50/50 border-y border-slate-100">
        <div class="max-w-7xl mx-auto px-6">
            <div class="mb-16">
                <h2 class="text-3xl font-bold tracking-tight text-slate-900 mb-3">
                    Semua yang Anda butuhkan untuk operasional berat
                </h2>
                <p class="text-slate-600 text-lg">
                    Standar tinggi armada dan pelayanan terbaik untuk efisiensi bisnis Anda.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <?php foreach ($features as $feature): ?>
                <div class="space-y-3">
                    <div class="w-10 h-10 rounded-full bg-black text-white flex items-center justify-center mb-4">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $feature['icon']; ?>"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900"><?php echo $feature['title']; ?></h3>
                    <p class="text-sm text-slate-600 leading-relaxed"><?php echo $feature['desc']; ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="produk" class="py-20 max-w-7xl mx-auto px-6">
        <div class="text-center mb-16">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Pilihan Armada</p>
            <h2 class="text-3xl font-bold tracking-tight text-slate-900">Unit Alat Berat Terpopuler</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="border border-slate-200 rounded-xl overflow-hidden hover:border-black transition-all">
                <div class="h-48 bg-slate-100 flex items-center justify-center">
                    <span class="text-slate-400 font-medium">[ Foto Excavator ]</span>
                </div>
                <div class="p-6">
                    <h3 class="font-bold text-xl mb-2">Excavator Hydraulic 20 Ton</h3>
                    <p class="text-slate-600 text-sm mb-4">Ideal untuk pengerjaan galian tanah skala besar, konstruksi jalan, dan pertambangan.</p>
                    <a href="#kontak" class="text-black font-semibold text-sm hover:underline">Sewa / Beli Unit &rarr;</a>
                </div>
            </div>

            <div class="border border-slate-200 rounded-xl overflow-hidden hover:border-black transition-all">
                <div class="h-48 bg-slate-100 flex items-center justify-center">
                    <span class="text-slate-400 font-medium">[ Foto Bulldozer ]</span>
                </div>
                <div class="p-6">
                    <h3 class="font-bold text-xl mb-2">Bulldozer Heavy Duty</h3>
                    <p class="text-slate-600 text-sm mb-4">Dirancang untuk pemerataan lahan, dorongan material berat, dan pembukaan jalur.</p>
                    <a href="#kontak" class="text-black font-semibold text-sm hover:underline">Sewa / Beli Unit &rarr;</a>
                </div>
            </div>

            <div class="border border-slate-200 rounded-xl overflow-hidden hover:border-black transition-all">
                <div class="h-48 bg-slate-100 flex items-center justify-center">
                    <span class="text-slate-400 font-medium">[ Foto Wheel Loader ]</span>
                </div>
                <div class="p-6">
                    <h3 class="font-bold text-xl mb-2">Wheel Loader 3m³</h3>
                    <p class="text-slate-600 text-sm mb-4">Efisiensi tinggi untuk memindahkan material curah ke truk pemuat di area kerja.</p>
                    <a href="#kontak" class="text-black font-semibold text-sm hover:underline">Sewa / Beli Unit &rarr;</a>
                </div>
            </div>
        </div>
    </section>

    <section id="kontak" class="py-20 bg-black text-white">
        <div class="max-w-4xl mx-auto px-6 text-center">
            <h2 class="text-3xl sm:text-4xl font-extrabold mb-4">Siap Memulai Proyek Anda?</h2>
            <p class="text-slate-400 text-lg mb-8">Konsultasikan kebutuhan alat berat Anda dengan tim ahli kami secara gratis.</p>
            <div class="flex flex-col sm:flex-row justify-center gap-4">
                <a href="https://wa.me/6281234567890" target="_blank" class="bg-white text-black px-8 py-4 rounded-md font-bold hover:bg-slate-200 transition-all">
                    Hubungi via WhatsApp
                </a>
            </div>
        </div>
    </section>

    <footer class="py-8 border-t border-slate-100 text-center text-xs text-slate-500">
        <p>&copy; <?php echo date('Y'); ?> <?php echo $company_name; ?>. All rights reserved.</p>
    </footer>

</body>
</html>