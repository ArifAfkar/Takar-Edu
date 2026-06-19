<?php
/* =======================================================
    SYSTEM INITIALIZATION
======================================================= */
require_once '../config/auth.php';

/* =======================================================
    ACCESS CONTROL
======================================================= */
if (!isset($_SESSION['login_id'])) {
    header('Location: ../index.php');
    exit;
}

/* =======================================================
    PAGE CONFIGURATION
======================================================= */
$pageTitle = "Tentang | Takar-Edu";
?>


<!DOCTYPE html>
<html lang="id">

<head>
    <!-- =======================================================
        GLOBAL HEADER / ASSETS
    ======================================================= -->
    <?php require_once '../includes/header.php'; ?>

    <title><?= $pageTitle ?></title>
</head>

<body>

    <!-- =======================================================
        GLOBAL NAVBAR + SIDEBAR
    ======================================================= -->
    <?php require_once '../includes/nav_bar.php'; ?>

    <!-- =======================================================
        MAIN CONTENT AREA
    ======================================================= -->
    <main id="mainContent" class="main-content">

        <!-- =======================================================
            PAGE CONTAINER
        ======================================================= -->
        <div class="page-container">

            <!-- =======================================================
                HERO SECTION
            ======================================================= -->
            <section class="section-card mb-6">

                <div class="about-hero">

                    <!-- ---------- Hero Icon ---------- -->
                    <div class="about-hero-icon">
                        <i data-lucide="scale" class="w-10 h-10"></i>
                    </div>

                    <!-- ---------- Product Name ---------- -->
                    <h1 class="about-hero-title">
                        Takar-Edu
                    </h1>

                    <!-- ---------- Product Subtitle ---------- -->
                    <p class="about-hero-subtitle">
                        Web Asesmen Autentik Berbasis Web
                    </p>

                    <!-- ---------- Version Badge ---------- -->
                    <div class="inline-flex items-center gap-2 mt-4 px-4 py-2 rounded-full bg-blue-50 text-blue-700 text-sm font-semibold">
                        <i data-lucide="git-branch" class="w-4 h-4"></i>
                        Versi 1.0
                    </div>

                    <!-- ---------- Product Description ---------- -->
                    <p class="article-content max-w-3xl mx-auto mt-6">
                        Takar-Edu merupakan platform asesmen autentik berbasis web yang dikembangkan
                        untuk mendukung proses evaluasi pembelajaran secara digital. Sistem ini
                        mendukung asesmen afektif, kognitif, dan psikomotorik melalui berbagai jenis
                        instrumen, stimulus pembelajaran, serta fitur pendukung evaluasi. Salah satu
                        keunggulan utama Takar-Edu adalah kemampuan penilaian otomatis berbasis
                        Artificial Intelligence (AI) untuk soal yang memerlukan jawaban tertulis,
                        seperti pilihan ganda beralasan dan uraian, sehingga proses penilaian dapat
                        dilakukan secara lebih cepat, konsisten, dan terdokumentasi.
                    </p>

                </div>

            </section>

            <!-- =======================================================
                DEVELOPMENT GOALS SECTION
            ======================================================= -->
            <section class="section-card mb-5">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 rounded-xl bg-green-100 text-green-600 flex items-center justify-center">
                        <i data-lucide="target" class="w-6 h-6"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">
                        Tujuan Pengembangan
                    </h2>
                </div>

                <ul class="space-y-3 text-gray-700">
                    <li>✓ Mendukung pelaksanaan asesmen autentik berbasis web.</li>
                    <li>✓ Melatih keterampilan literasi sains peserta didik.</li>
                    <li>✓ Mempermudah pengelolaan evaluasi pembelajaran.</li>
                    <li>✓ Menyediakan sistem penilaian digital yang terintegrasi.</li>
                    <li>✓ Mendukung implementasi pembelajaran abad ke-21.</li>
                </ul>
            </section>

            <!-- =======================================================
                MAIN FEATURES SECTION
            ======================================================= -->
            <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-12 h-12 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center">
                        <i data-lucide="rocket" class="w-6 h-6"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">
                        Fitur Utama
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                    <!-- ---------- Assessment Feature ---------- -->
                    <div class="feature-card">
                        <h3 class="font-bold text-gray-800 mb-3">
                            Asesmen
                        </h3>

                        <ul class="space-y-2 text-sm text-gray-600">
                            <li>• Angket</li>
                            <li>• Pilihan Ganda</li>
                            <li>• Pilihan Ganda Beralasan</li>
                            <li>• Isian Singkat</li>
                            <li>• Uraian</li>
                        </ul>
                    </div>

                    <!-- ---------- Learning Stimulus Feature ---------- -->
                    <div class="feature-card">
                        <h3 class="font-bold text-gray-800 mb-3">
                            Stimulus Pembelajaran
                        </h3>

                        <ul class="space-y-2 text-sm text-gray-600">
                            <li>• Wacana</li>
                            <li>• Tabel</li>
                            <li>• Rumus</li>
                            <li>• Gambar</li>
                            <li>• Simulasi PhET</li>
                        </ul>
                    </div>

                    <!-- ---------- Evaluation Feature ---------- -->
                    <div class="feature-card">
                        <h3 class="font-bold text-gray-800 mb-3">
                            Evaluasi
                        </h3>

                        <ul class="space-y-2 text-sm text-gray-600">
                            <li>• Penilaian AI untuk Jawaban Tertulis</li>
                            <li>• Rubrik Penilaian</li>
                            <li>• Rekap Hasil</li>
                            <li>• Riwayat Pengerjaan</li>
                            <li>• Download PDF</li>
                        </ul>
                    </div>

                </div>
            </section>

            <!-- =======================================================
                SYSTEM INFORMATION SECTION
            ======================================================= -->
            <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-12 h-12 rounded-xl bg-yellow-100 text-yellow-600 flex items-center justify-center">
                        <i data-lucide="info" class="w-6 h-6"></i>
                    </div>

                    <h2 class="text-xl font-bold text-gray-900">
                        Informasi Pengembangan
                    </h2>
                </div>

                <!-- ---------- System Information Table ---------- -->
                <!-- ---------- Developer Information Table ---------- -->
                <div class="overflow-x-auto">
                    <table class="info-table">
                        <tbody>
                            <tr class="border-b">
                                <td class="info-table-label">Nama Produk</td>
                                <td class="info-table-value">Takar-Edu</td>
                            </tr>

                            <tr class="border-b">
                                <td class="info-table-label">Versi Penelitian</td>
                                <td class="info-table-value">0.9</td>
                            </tr>

                            <tr class="border-b">
                                <td class="info-table-label">Versi Saat Ini</td>
                                <td class="info-table-value">1.0</td>
                            </tr>

                            <tr class="border-b">
                                <td class="info-table-label">Platform</td>
                                <td class="info-table-value">Web Application</td>
                            </tr>

                            <tr class="border-b">
                                <td class="info-table-label">Editor Kode</td>
                                <td class="info-table-value">Visual Studio Code</td>
                            </tr>

                            <tr class="border-b">
                                <td class="info-table-label">Bahasa Pemrograman</td>
                                <td class="info-table-value">PHP</td>
                            </tr>

                            <tr class="border-b">
                                <td class="info-table-label">Database</td>
                                <td class="info-table-value">MySQL</td>
                            </tr>

                            <tr class="border-b">
                                <td class="info-table-label">Manajemen Database</td>
                                <td class="info-table-value">phpMyAdmin</td>
                            </tr>

                            <tr class="border-b">
                                <td class="info-table-label">Bahasa Pendukung AI</td>
                                <td class="info-table-value">Python</td>
                            </tr>

                            <tr class="border-b">
                                <td class="info-table-label">Artificial Intelligence</td>
                                <td class="info-table-value">OpenAI</td>
                            </tr>

                            <tr class="border-b">
                                <td class="info-table-label">Lingkungan Pengembangan</td>
                                <td class="info-table-value">XAMPP (Apache, PHP, MySQL)</td>
                            </tr>

                            <tr class="border-b">
                                <td class="info-table-label">Deployment</td>
                                <td class="info-table-value">InfinityFree (PHP & MySQL) + Render (Python AI Service)</td>
                            </tr>

                            <tr>
                                <td class="info-table-label">Catatan AI</td>
                                <td class="info-table-value text-justify">
                                    Fitur penilaian otomatis pada soal pilihan ganda beralasan dan uraian menggunakan layanan OpenAI. Ketersediaan fitur ini bergantung pada saldo atau kuota token API yang tersedia. Jika proses penilaian AI tidak berjalan, kemungkinan saldo API habis, kuota gratis telah habis, atau terjadi gangguan koneksi ke layanan OpenAI.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- =======================================================
                DEVELOPMENT JOURNEY SECTION
            ======================================================= -->
            <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-12 h-12 rounded-xl bg-orange-100 text-orange-600 flex items-center justify-center">
                        <i data-lucide="history" class="w-6 h-6"></i>
                    </div>

                    <h2 class="text-xl font-bold text-gray-900">
                        Perjalanan Pengembangan Takar-Edu
                    </h2>
                </div>

                <div class="article-content">

                    <p>
                        Takar-Edu pada awalnya dikembangkan sebagai bagian dari penelitian
                        skripsi Program Studi Pendidikan Fisika Universitas Lambung Mangkurat.
                        Fokus utama pengembangan sistem ini adalah mendukung pelaksanaan
                        asesmen autentik berbasis web untuk melatihkan keterampilan literasi
                        sains peserta didik sekaligus memfasilitasi proses pengumpulan data
                        penelitian.
                    </p>

                    <p>
                        Karena keterbatasan waktu penelitian, versi sistem yang digunakan
                        pada tahap validasi, uji coba, dan pengambilan data masih berada
                        pada tahap prototipe (versi 0.9). Pada tahap tersebut sistem telah
                        mampu menjalankan fungsi utama yang diperlukan untuk penelitian,
                        meskipun masih terdapat berbagai keterbatasan, kekurangan fitur,
                        serta beberapa kendala teknis yang ditemukan selama penggunaan.
                    </p>

                    <p>
                        Pengembangan Takar-Edu berlangsung pada periode ketika teknologi
                        Artificial Intelligence (AI) untuk membantu pemrograman mengalami
                        perkembangan yang sangat pesat, khususnya pada rentang tahun 2025
                        hingga 2026. Pada tahap awal pengembangan, pemanfaatan AI untuk
                        membangun aplikasi web secara utuh masih memerlukan banyak proses
                        penyesuaian, perbaikan, dan pengujian ulang sehingga pengembangan
                        tetap berlangsung selama berbulan-bulan.
                    </p>

                    <p>
                        Seiring berkembangnya kemampuan AI, proses pengembangan menjadi
                        jauh lebih efektif. Kode yang dihasilkan menjadi lebih terstruktur,
                        lebih sesuai kebutuhan sistem, dan mampu membantu menyelesaikan
                        berbagai permasalahan teknis dengan tingkat akurasi yang jauh lebih
                        baik dibandingkan sebelumnya. Meskipun demikian, seluruh proses
                        perancangan kebutuhan sistem, alur asesmen, struktur basis data,
                        pengujian, validasi, serta pengambilan keputusan pengembangan tetap
                        dilakukan oleh pengembang sesuai kebutuhan penelitian.
                    </p>

                    <p>
                        Pengembangan sistem juga menjadi pengalaman belajar yang unik karena
                        pengembang berasal dari latar belakang Pendidikan Fisika dan bukan
                        bidang Teknik Informatika maupun Ilmu Komputer. Pemahaman dasar
                        mengenai pengembangan web diperoleh melalui pengalaman mengikuti
                        program Magang dan Studi Independen Bersertifikat (MSIB) bidang Web
                        Development, sementara implementasi berbagai fitur lanjutan seperti
                        manajemen kuis, distribusi pengguna, integrasi basis data, hingga
                        penilaian berbasis Artificial Intelligence dilakukan secara bertahap
                        selama proses pengembangan.
                    </p>

                    <p>
                        Setelah penelitian selesai, Takar-Edu terus disempurnakan melalui
                        berbagai perbaikan antarmuka, optimasi struktur sistem, peningkatan
                        stabilitas, penyempurnaan fitur asesmen, serta integrasi penilaian
                        berbasis Artificial Intelligence. Hasil penyempurnaan tersebut
                        diwujudkan dalam Takar-Edu versi 1.0 sebagai versi implementasi
                        pascapenelitian yang lebih stabil, lebih lengkap, dan lebih siap
                        digunakan dibandingkan versi yang digunakan selama penelitian.
                    </p>

                    <p>
                        Perlu diperhatikan bahwa versi Takar-Edu yang digunakan dalam proses
                        penelitian skripsi berbeda dengan versi yang saat ini tersedia. Data
                        penelitian, validasi, dan uji coba pengguna diperoleh menggunakan versi
                        pengembangan (v0.9) yang dikembangkan sesuai kebutuhan penelitian pada
                        saat itu. Setelah penelitian selesai, sistem terus disempurnakan hingga
                        menjadi Takar-Edu versi 1.0 melalui berbagai perbaikan fitur, antarmuka,
                        stabilitas sistem, dan pengalaman pengguna. Oleh karena itu, beberapa
                        tampilan maupun fitur pada versi 1.0 dapat berbeda dengan yang
                        didokumentasikan dalam skripsi, namun tidak mempengaruhi data maupun hasil
                        penelitian yang telah diperoleh sebelumnya.
                    </p>

                </div>
            </section>

            <!-- =======================================================
                DEVELOPER PROFILE SECTION
            ======================================================= -->
            <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-12 h-12 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center">
                        <i data-lucide="user-round" class="w-6 h-6"></i>
                    </div>

                    <h2 class="text-xl font-bold text-gray-900">
                        Pengembang
                    </h2>
                </div>

                <!-- ---------- Developer Photo ---------- -->
                <div class="flex justify-center mb-6">
                    <div class="text-center">
                        <img
                            src="../assets/images/developer.jpg"
                            alt="Foto Pengembang"
                            class="profile-photo"
                        >
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="info-table">
                        <tbody>
                            <tr class="border-b">
                                <td class="info-table-label">
                                    Nama
                                </td>
                                <td class="info-table-value">
                                    M. Arif
                                </td>
                            </tr>

                            <tr class="border-b">
                                <td class="info-table-label">
                                    NIM
                                </td>
                                <td class="info-table-value">
                                    2110121110009
                                </td>
                            </tr>

                            <tr class="border-b">
                                <td class="info-table-label">
                                    Angkatan
                                </td>
                                <td class="info-table-value">
                                    2021
                                </td>
                            </tr>

                            <tr class="border-b">
                                <td class="info-table-label">
                                    Program Studi
                                </td>
                                <td class="info-table-value">
                                    Pendidikan Fisika
                                </td>
                            </tr>

                            <tr class="border-b">
                                <td class="info-table-label">
                                    Fakultas
                                </td>
                                <td class="info-table-value">
                                    Fakultas Keguruan dan Ilmu Pendidikan
                                </td>
                            </tr>

                            <tr class="border-b">
                                <td class="info-table-label">
                                    Universitas
                                </td>
                                <td class="info-table-value">
                                    Universitas Lambung Mangkurat
                                </td>
                            </tr>

                            <tr>
                                <td class="info-table-label">
                                    Judul Skripsi
                                </td>
                                <td class="info-table-value text-justify">
                                    "Pengembangan Asesmen Autentik Berbasis Web Takar-Edu pada Materi Momentum dan Impuls untuk Melatihkan Keterampilan Literasi Sains Peserta Didik"
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- =======================================================
                CONTRIBUTION SECTION
            ======================================================= -->
            <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-12 h-12 rounded-xl bg-gray-100 text-gray-700 flex items-center justify-center">
                        <i data-lucide="code-2" class="w-6 h-6"></i>
                    </div>

                    <h2 class="text-xl font-bold text-gray-900">
                        Kontribusi dan Pengembangan Lanjutan
                    </h2>
                </div>

                <div class="article-content">

                    <p>
                        Takar-Edu dikembangkan sebagai bagian dari penelitian skripsi dan
                        penyempurnaan pascapenelitian. Sistem ini tidak dikembangkan sebagai
                        produk komersial dan tidak memiliki pembatasan khusus untuk tujuan
                        pembelajaran maupun pengembangan akademik.
                    </p>

                    <p>
                        Bagi mahasiswa, peneliti, guru, maupun pengembang yang tertarik
                        mempelajari atau mengembangkan sistem ini lebih lanjut, source code
                        Takar-Edu dapat diakses melalui repositori GitHub yang disediakan
                        oleh pengembang.
                    </p>

                    <!-- ---------- Repository Link ---------- -->
                    <div class="repository-card">
                        <div class="font-semibold mb-2">
                            Repositori GitHub
                        </div>

                        <a
                            href="https://github.com/ArifAfkar/Takar-Edu"
                            target="_blank"
                            class="text-blue-600 hover:underline break-all"
                        >
                            https://github.com/ArifAfkar/Takar-Edu
                        </a>
                    </div>

                    <p>
                        Pengembang berharap Takar-Edu dapat menjadi referensi bagi
                        pengembangan asesmen digital, penelitian pendidikan, maupun
                        implementasi teknologi Artificial Intelligence dalam evaluasi
                        pembelajaran.
                    </p>

                </div>
            </section>

            <!-- =======================================================
                FOOTER NOTE SECTION
            ======================================================= -->
            <section class="notice-card notice-info text-center">
                <p class="text-sm text-blue-700 mt-1">
                    Dikembangkan sebagai bagian dari penelitian pendidikan untuk mendukung asesmen berbasis web abad ke-21.
                </p>
            </section>

        </div>

    </main>


    <script>
    /* =======================================================
        PAGE INITIALIZATION
    ======================================================= */
    document.addEventListener("DOMContentLoaded", function() {

        /* ---------- Render Lucide Icons ---------- */
        if (window.lucide) {
            lucide.createIcons();
        }

    });
    </script>

</body>
</html>