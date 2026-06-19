<?php
/* =======================================================
    SYSTEM INITIALIZATION
======================================================= */
require_once '../config/auth.php';
require_once '../config/db_connect.php';

/* =======================================================
    ACCESS CONTROL
======================================================= */
if (!isset($_SESSION['login_id']) || !isset($_SESSION['login_user_type'])) {
    header('Location: ../index.php');
    exit;
}

$userType = (int) $_SESSION['login_user_type'];

$isAdmin   = ($userType === 1);
$isTeacher = ($userType === 2);
$isStudent = ($userType === 3);

if (!$isAdmin && !$isTeacher && !$isStudent) {
    header('Location: home.php');
    exit;
}

/* =======================================================
    ROLE CONFIGURATION
======================================================= */
$roleName = $isAdmin ? 'Administrator' : ($isTeacher ? 'Pengajar' : 'Siswa');

/* =======================================================
    HTML ESCAPE HELPER
======================================================= */
function e($text)
{
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

/* =======================================================
    HELP CONTENT DATA
======================================================= */
if ($isAdmin) {
    $guides = [
        'Beranda Administrator' => [
            'Gunakan beranda untuk melihat ringkasan jumlah kuis, pengajar, siswa, dan kuis aktif.',
            'Daftar kuis menampilkan kuis yang tersedia di sistem.',
            'Klik Kelola untuk membuka detail kuis dan mengatur isi kuis.'
        ],
        'Manajemen Pengajar' => [
            'Buka menu Pengajar untuk mengelola akun pengajar.',
            'Klik Tambah Pengajar untuk menambahkan akun baru.',
            'Gunakan Edit untuk memperbarui data pengajar.',
            'Gunakan Nonaktifkan jika akun sementara tidak digunakan.',
            'Gunakan Hapus jika akun sudah tidak diperlukan.'
        ],
        'Manajemen Siswa' => [
            'Buka menu Siswa untuk mengelola data siswa.',
            'Tambahkan siswa sesuai kelas yang tersedia.',
            'Pastikan username siswa tidak sama dengan pengguna lain.',
            'Aktifkan atau nonaktifkan akun sesuai kebutuhan.'
        ],
        'Manajemen Kelas dan Mapel' => [
            'Buka menu Kelas untuk mengelola data kelas dan tingkat.',
            'Buka menu Mapel untuk mengelola mata pelajaran.',
            'Pastikan kelas dan mapel sudah tersedia sebelum membuat kuis dan distribusi.'
        ],
        'Manajemen Wacana' => [
            'Buka menu Wacana untuk membuat stimulus soal berupa teks, gambar, rumus, atau tabel.',
            'Admin dapat membuat wacana privat, dibagikan ke pengajar, atau publik.',
            'Gunakan Detail untuk melihat isi wacana.',
            'Wacana yang sedang digunakan pada soal sebaiknya tidak dihapus sebelum referensinya dilepas.'
        ],
        'Manajemen PhET' => [
            'Buka menu PhET untuk menyimpan simulasi interaktif.',
            'Masukkan judul, deskripsi, mapel, dan URL simulasi.',
            'Pastikan preview simulasi muncul dengan benar.',
            'PhET dapat digunakan sebagai stimulus pada soal kuis.'
        ],
        'Penggunaan Rumus' => [
            'Tombol Rumus tersedia pada Wacana dan Soal.',
            'Gunakan untuk menulis persamaan matematika, fisika, atau kimia.',
            'Contoh: p = mv, I = FΔt, Eₖ = ½mv².',
            'Rumus akan dirender otomatis pada soal, hasil pengerjaan, dan PDF.',
            'Pastikan simbol dan notasi ditulis dengan benar sebelum disimpan.'
        ],
        'Pengelolaan Wacana' => [
            'Wacana digunakan sebagai stimulus soal.',
            'Wacana dapat berisi teks, gambar, tabel, dan rumus.',
            'Admin dapat membuat wacana privat, dibagikan, atau publik.',
            'Wacana dapat digunakan oleh beberapa soal sekaligus.',
            'Hindari menghapus wacana yang masih digunakan pada soal.'
        ],
        'Pengelolaan PhET' => [
            'PhET digunakan sebagai simulasi virtual pembelajaran.',
            'Simpan URL simulasi yang valid.',
            'Preview simulasi akan ditampilkan pada daftar PhET.',
            'PhET dapat digunakan sebagai referensi soal atau praktikum virtual.'
        ],
        'Rubrik Penilaian' => [
            'Rubrik digunakan untuk soal uraian.',
            'Rubrik dapat berisi indikator Benar, Cukup, Kurang, dan Salah.',
            'Rubrik membantu proses penilaian AI menjadi lebih konsisten.',
            'Gunakan rubrik untuk soal yang memiliki banyak kemungkinan jawaban.'
        ],
        'Penilaian AI' => [
            'AI digunakan untuk menilai soal uraian dan pilihan ganda beralasan.',
            'Penilaian didasarkan pada kunci jawaban atau rubrik yang dibuat.',
            'Kategori hasil penilaian meliputi Benar, Cukup, Kurang, dan Salah.',
            'Pastikan kunci jawaban ditulis secara jelas agar hasil penilaian optimal.'
        ],
        'Download PDF' => [
            'Laporan hasil pengerjaan dapat diunduh dalam format PDF.',
            'PDF berisi identitas siswa, nilai, skor, pelanggaran, dan rincian jawaban.',
            'Gunakan PDF sebagai arsip atau dokumentasi hasil evaluasi.',
            'Pastikan seluruh penilaian telah selesai sebelum mengunduh laporan.'
        ],
        'Manajemen Kuis' => [
            'Buka menu Kuis untuk membuat dan mengelola kuis.',
            'Klik Tambah Kuis untuk membuat kuis baru.',
            'Gunakan Atur untuk mengatur distribusi kuis.',
            'Gunakan Kelola untuk menambah, mengedit, atau menghapus soal.',
            'Gunakan Arsipkan untuk menyembunyikan kuis tanpa menghapus data.'
        ],
        'Status Kuis' => [
            'Aktif berarti kuis dapat didistribusikan kepada siswa.',
            'Nonaktif berarti kuis tidak dapat diakses siswa.',
            'Arsip digunakan untuk menyimpan kuis tanpa menghapus data.',
            'Status kuis dapat diubah kapan saja sesuai kebutuhan.'
        ],
        'Distribusi Pengajar' => [
            'Gunakan distribusi pengajar untuk menentukan pengajar yang bertanggung jawab pada kuis.',
            'Pilih pengajar sesuai mata pelajaran dan kelas yang diampu.',
            'Pengajar yang menerima kuis dapat melihat atau mengelola sesuai hak akses yang diberikan.'
        ],
        'Distribusi Siswa' => [
            'Gunakan distribusi siswa untuk menentukan siswa yang menerima kuis.',
            'Pilih siswa berdasarkan kelas yang tersedia.',
            'Siswa yang sudah didistribusikan akan terkunci agar tidak terjadi duplikasi.',
            'Distribusi dapat diaktifkan atau dinonaktifkan.'
        ],
        'Riwayat dan Download PDF' => [
            'Buka menu Hasil atau Riwayat untuk melihat hasil pengerjaan siswa.',
            'Gunakan filter kuis dan pencarian untuk menemukan hasil tertentu.',
            'Klik Detail untuk melihat jawaban siswa secara lengkap.',
            'Klik Download PDF untuk menyimpan laporan hasil pengerjaan.'
        ],
        'Profil Akun' => [
            'Klik menu profil di bagian kanan atas.',
            'Pilih Edit Profil untuk mengganti foto profil.',
            'Upload foto dengan format JPG, JPEG, atau PNG.',
            'Gunakan Hapus Foto jika ingin kembali ke foto bawaan.'
        ]
    ];
} elseif ($isTeacher) {
    $guides = [
        'Beranda Pengajar' => [
            'Gunakan beranda untuk melihat ringkasan kuis, kelas, siswa, dan kuis aktif.',
            'Daftar kuis menampilkan kuis milik sendiri dan kuis yang dibagikan administrator.',
            'Klik Kelola untuk kuis milik sendiri.',
            'Klik Lihat untuk kuis yang bersifat read-only dari administrator.'
        ],
        'Daftar Siswa' => [
            'Buka menu Siswa untuk melihat siswa pada kelas yang Anda ampu.',
            'Data siswa hanya dapat dilihat oleh pengajar.',
            'Pengajar tidak dapat menambah, mengedit, menghapus, atau menonaktifkan akun siswa.',
            'Gunakan pencarian untuk menemukan siswa tertentu.'
        ],
        'Mengelola Wacana' => [
            'Buka menu Wacana untuk membuat stimulus soal.',
            'Klik Tambah Wacana untuk menambahkan wacana baru.',
            'Isi judul dan isi wacana.',
            'Gunakan tombol Rumus untuk menyisipkan rumus.',
            'Wacana dapat digunakan saat membuat soal kuis.'
        ],
        'Mengelola PhET' => [
            'Buka menu PhET untuk menyimpan simulasi interaktif.',
            'Klik Tambah PhET.',
            'Isi judul, deskripsi, dan URL simulasi PhET.',
            'Pastikan preview simulasi tampil dengan benar.',
            'PhET dapat dipilih sebagai referensi saat membuat soal.'
        ],
        'Membuat Kuis' => [
            'Buka menu Kuis.',
            'Klik Tambah Kuis.',
            'Isi judul, deskripsi, durasi, waktu mulai, dan batas akhir.',
            'Simpan kuis sebelum menambahkan soal.',
            'Kuis yang dibuat pengajar dapat dikelola oleh pengajar tersebut.'
        ],
        'Menambahkan Soal' => [
            'Klik Kelola pada kuis yang ingin diedit.',
            'Klik Tambah Soal.',
            'Isi pertanyaan dan poin soal.',
            'Pilih jenis soal: angket, pilihan ganda, pilihan ganda beralasan, isian singkat, atau uraian.',
            'Tambahkan Wacana atau PhET jika diperlukan.',
            'Isi opsi, kunci jawaban, alasan, atau rubrik sesuai jenis soal.',
            'Klik Simpan Soal setelah semua data lengkap.'
        ],
        'Jenis Soal' => [
            'Angket digunakan untuk jawaban skala sikap dan tidak memiliki skor akademik.',
            'Pilihan ganda digunakan untuk memilih satu jawaban benar.',
            'Pilihan ganda beralasan membutuhkan pilihan jawaban dan alasan.',
            'Isian singkat digunakan untuk jawaban pendek seperti angka, istilah, atau simbol.',
            'Uraian digunakan untuk jawaban penjelasan dan dapat dinilai menggunakan AI.'
        ],
        'Pilihan Ganda Beralasan' => [
            'Siswa memilih jawaban dan menuliskan alasan.',
            'Pilihan jawaban menjadi penentu utama benar atau salah.',
            'Jika pilihan jawaban salah, alasan tidak mempengaruhi kategori akhir.',
            'Jika pilihan jawaban benar, alasan akan dinilai menjadi Benar, Kurang, atau Salah.',
            'Jenis soal ini cocok untuk mengukur pemahaman konsep dan penalaran siswa.'
        ],
        'Penggunaan Rumus' => [
            'Gunakan tombol Rumus pada Wacana, Soal, Opsi Jawaban, Alasan, dan Kunci Jawaban.',
            'Rumus dapat digunakan untuk matematika, fisika, kimia, dan simbol ilmiah lainnya.',
            'Contoh penulisan: p = mv, I = F Δt, Eₖ = ½mv².',
            'Rumus yang disimpan akan dirender otomatis pada soal, hasil pengerjaan, dan PDF.',
            'Pastikan rumus ditulis dengan benar agar mudah dibaca siswa.'
        ],
        'Penggunaan Wacana' => [
            'Wacana digunakan sebagai stimulus soal berupa teks, gambar, tabel, atau rumus.',
            'Wacana dapat dipilih saat membuat soal.',
            'Wacana dengan tabel dapat digunakan sebagai data pengamatan maupun tabel jawaban siswa.',
            'Sebaiknya jangan menghapus wacana yang masih digunakan oleh soal.'
        ],
        'Penggunaan PhET' => [
            'PhET digunakan sebagai simulasi virtual pendukung pembelajaran.',
            'Masukkan URL simulasi dan pastikan preview tampil dengan benar.',
            'PhET dapat dipilih sebagai referensi pada soal.',
            'Gunakan PhET untuk praktikum virtual dan pengamatan data.'
        ],
        'Rubrik Penilaian' => [
            'Rubrik digunakan pada soal uraian.',
            'Aktifkan opsi Gunakan Rubrik Penilaian jika diperlukan.',
            'Isi indikator Benar, Cukup, Kurang, dan Salah.',
            'Rubrik membantu AI memberikan penilaian yang lebih konsisten.'
        ],
        'Penilaian AI' => [
            'AI digunakan untuk menilai soal uraian dan pilihan ganda beralasan.',
            'Penilaian didasarkan pada kunci jawaban atau rubrik.',
            'Kategori hasil penilaian dapat berupa Benar, Cukup, Kurang, atau Salah.',
            'Pastikan kunci jawaban dan rubrik ditulis secara jelas.'
        ],
        'Soal Uraian dengan Tabel' => [
            'Gunakan wacana yang memiliki tabel.',
            'Pilih mode Tabel Informasi jika tabel hanya menjadi data pendukung.',
            'Pilih mode Tabel Jawaban Siswa jika siswa harus mengisi sel tabel.',
            'Jika menggunakan tabel jawaban siswa, rubrik penilaian wajib diisi.'
        ],
        'Distribusi Siswa' => [
            'Buka detail kuis.',
            'Klik Distribusi Siswa.',
            'Pilih siswa yang akan menerima kuis.',
            'Siswa yang sudah menerima kuis akan terkunci agar tidak terjadi duplikasi.',
            'Aktifkan distribusi agar kuis muncul pada akun siswa.'
        ],
        'Melihat Hasil' => [
            'Buka menu Hasil atau klik Lihat Hasil pada detail kuis.',
            'Pilih kuis yang ingin diperiksa.',
            'Klik Detail untuk melihat jawaban siswa.',
            'Periksa skor, nilai akhir, kategori jawaban, dan pelanggaran.'
        ],
        'Download Hasil PDF' => [
            'Buka halaman hasil pengerjaan siswa.',
            'Klik Download PDF.',
            'PDF berisi identitas siswa, nilai, pelanggaran, rincian jawaban, kunci jawaban, kategori, dan skor.'
        ],
        'Profil Akun' => [
            'Klik menu profil di bagian kanan atas.',
            'Pilih Edit Profil.',
            'Upload foto profil baru jika diperlukan.',
            'Simpan perubahan foto.'
        ]
    ];
} else {
    $guides = [
        'Beranda Siswa' => [
            'Gunakan beranda untuk melihat ringkasan kuis tersedia, kuis selesai, dan sisa kuis.',
            'Daftar kuis menampilkan kuis yang diberikan kepada Anda.',
            'Klik Mulai atau Kerjakan jika kuis masih tersedia.',
            'Klik Hasil jika kuis sudah selesai dikerjakan.'
        ],
        'Kuis Saya' => [
            'Buka menu Kuis Saya untuk melihat seluruh kuis yang diberikan.',
            'Perhatikan judul kuis, mapel, pengajar, durasi, status, nilai, dan deadline.',
            'Kuis dengan status selesai dapat dibuka melalui tombol Hasil.',
            'Kuis yang belum dikerjakan dapat dibuka melalui tombol Kerjakan jika masih aktif.'
        ],
        'Sebelum Mengerjakan Kuis' => [
            'Pastikan koneksi internet stabil.',
            'Baca informasi durasi dan batas akhir pengerjaan.',
            'Jangan menutup halaman atau berpindah tab saat mengerjakan.',
            'Siapkan catatan atau alat bantu yang diperbolehkan oleh pengajar.'
        ],
        'Mengerjakan Kuis' => [
            'Klik Kerjakan atau Mulai pada kuis yang tersedia.',
            'Baca setiap soal dengan teliti.',
            'Jawab semua soal sesuai jenisnya.',
            'Gunakan navigasi soal untuk berpindah antar soal.',
            'Klik Hentikan atau Kumpulkan jika sudah selesai.'
        ],
        'Jenis Soal' => [
            'Angket digunakan untuk memilih tingkat persetujuan.',
            'Pilihan ganda digunakan untuk memilih satu jawaban.',
            'Pilihan ganda beralasan membutuhkan pilihan dan alasan.',
            'Isian singkat membutuhkan jawaban pendek.',
            'Uraian membutuhkan jawaban penjelasan.',
            'Uraian dengan tabel membutuhkan jawaban teks dan/atau pengisian tabel.'
        ],
        'Pilihan Ganda Beralasan' => [
            'Siswa memilih jawaban dan menuliskan alasan.',
            'Pilihan jawaban menjadi penentu utama benar atau salah.',
            'Jika pilihan jawaban salah, alasan tidak mempengaruhi kategori akhir.',
            'Jika pilihan jawaban benar, alasan akan dinilai menjadi Benar, Kurang, atau Salah.',
            'Jenis soal ini cocok untuk mengukur pemahaman konsep dan penalaran siswa.'
        ],
        'Membaca Rumus' => [
            'Beberapa soal menggunakan rumus matematika atau fisika.',
            'Rumus akan ditampilkan secara otomatis dalam format yang lebih rapi.',
            'Baca rumus dengan teliti sebelum menjawab soal.',
            'Perhatikan satuan dan simbol yang digunakan.'
        ],
        'Mengerjakan Soal dengan Wacana' => [
            'Baca wacana terlebih dahulu sebelum menjawab soal.',
            'Wacana dapat berisi teks, gambar, tabel, maupun rumus.',
            'Gunakan informasi pada wacana sebagai dasar menjawab pertanyaan.'
        ],
        'Mengerjakan Soal dengan PhET' => [
            'Buka simulasi PhET jika tersedia pada soal.',
            'Ikuti langkah percobaan yang diberikan.',
            'Catat hasil pengamatan dan gunakan untuk menjawab soal.',
            'Lakukan percobaan beberapa kali jika diperlukan.'
        ],
        'Mengerjakan Soal Tabel' => [
            'Beberapa soal meminta siswa mengisi tabel.',
            'Isi setiap sel yang tersedia sesuai hasil pengamatan atau perhitungan.',
            'Periksa kembali data sebelum mengumpulkan kuis.',
            'Lengkapi jawaban uraian jika diminta menjelaskan hasil tabel.'
        ],
        'Pelanggaran Saat Kuis' => [
            'Pelanggaran dapat tercatat jika Anda keluar dari halaman pengerjaan.',
            'Berpindah tab atau meninggalkan halaman dapat dihitung sebagai pelanggaran.',
            'Jika batas pelanggaran tercapai, kuis dapat dihentikan otomatis oleh sistem.',
            'Jumlah pelanggaran akan tampil pada hasil pengerjaan.'
        ],
        'Batas Pelanggaran' => [
            'Sistem mencatat pelanggaran ketika siswa meninggalkan halaman pengerjaan.',
            'Jumlah pelanggaran maksimum ditentukan oleh sistem.',
            'Jika batas pelanggaran tercapai, kuis dapat dihentikan otomatis.',
            'Jumlah pelanggaran akan ditampilkan pada hasil pengerjaan dan laporan PDF.'
        ],
        'Melihat Hasil Kuis' => [
            'Buka menu Riwayat atau Kuis Saya.',
            'Klik Hasil atau Detail pada kuis yang ingin dilihat.',
            'Periksa poin diperoleh, nilai akhir, waktu mulai, waktu dikumpulkan, dan pelanggaran.',
            'Lihat rincian jawaban untuk mengetahui skor tiap soal.'
        ],
        'Memahami Nilai dan Skor' => [
            'Skor menunjukkan poin yang diperoleh pada setiap soal.',
            'Nilai akhir dihitung dari total skor dibandingkan total poin maksimum.',
            'Kuis angket tidak menghasilkan nilai akademik.',
            'Nilai ditampilkan dalam rentang 0 sampai 100.',
            'Semakin tinggi skor yang diperoleh, semakin tinggi nilai akhir yang dihasilkan.'
        ],
        'Kategori Penilaian' => [
            'Benar berarti jawaban sesuai dengan kunci atau rubrik.',
            'Cukup berarti jawaban mendekati benar tetapi belum lengkap.',
            'Kurang berarti jawaban hanya memuat sebagian kecil konsep yang sesuai.',
            'Salah berarti jawaban tidak sesuai atau kosong.',
            'Beberapa soal uraian dapat dinilai menggunakan AI sesuai pengaturan kuis.'
        ],
        'Riwayat Hasil' => [
            'Buka menu Riwayat untuk melihat semua kuis yang sudah dikerjakan.',
            'Gunakan filter kuis untuk menampilkan hasil tertentu.',
            'Gunakan pencarian untuk menemukan riwayat dengan cepat.',
            'Klik Download PDF untuk menyimpan laporan hasil.'
        ],
        'Profil Akun' => [
            'Klik menu profil di kanan atas.',
            'Pilih Edit Profil.',
            'Upload foto profil baru jika diperlukan.',
            'Simpan perubahan foto.'
        ]
    ];
}

/* =======================================================
    PAGE CONFIGURATION
======================================================= */
$pageTitle = "Bantuan | Takar-Edu";
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

        <div class="page-container">

            <!-- =======================================================
                HELP HEADER SECTION
            ======================================================= -->
            <section class="section-card mb-4">

                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">

                    <!-- ---------- Page Information ---------- -->
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-50 text-blue-700 text-sm font-semibold mb-3">
                            <i data-lucide="circle-help" class="w-4 h-4"></i>
                            Pusat Bantuan
                        </div>

                        <h1 class="page-title">
                            Bantuan Penggunaan Takar-Edu
                        </h1>

                        <p class="text-sm text-gray-500 mt-1">
                            Takar-Edu v1.0
                        </p>

                        <p class="text-gray-600 mt-2">
                            Panduan ini disesuaikan untuk role:
                            <span class="font-semibold text-blue-600"><?= e($roleName) ?></span>
                        </p>
                    </div>

                    <!-- ---------- Search Input ---------- -->
                    <div class="w-full lg:w-80">
                        <label for="helpSearch" class="sr-only">Cari bantuan</label>
                        <div class="search-wrapper">
                            <input
                                type="text"
                                id="helpSearch"
                                class="search-input"
                                placeholder="Cari bantuan..."
                            >
                            <i data-lucide="search" class="input-icon w-4 h-4"></i>
                        </div>
                    </div>
                </div>
            </section>

            <!-- =======================================================
                HELP GUIDE LIST
            ======================================================= -->
            <section class="space-y-4" id="helpList">

                <?php foreach ($guides as $title => $steps): ?>
                    <!-- ---------- Help Item ---------- -->
                    <div
                        class="help-item help-card"
                        data-title="<?= e(strtolower($title)) ?>"
                        data-content="<?= e(strtolower(implode(' ', $steps))) ?>"
                    >
                        <button
                            type="button"
                            class="help-toggle help-card-header"
                        >
                            <span class="flex items-center gap-3">
                                <span class="help-item-icon">
                                    <i data-lucide="info" class="w-4 h-4"></i>
                                </span>

                                <span class="font-semibold text-gray-900">
                                    <?= e($title) ?>
                                </span>
                            </span>

                            <span class="help-icon accordion-icon">
                                +
                            </span>
                        </button>

                        <!-- Help steps -->
                        <div class="help-content hidden help-card-content">
                            <ol class="list-decimal pl-5 space-y-2 text-sm text-gray-700 leading-relaxed">
                                <?php foreach ($steps as $step): ?>
                                    <li><?= e($step) ?></li>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- =======================================================
                    EMPTY SEARCH STATE
                ======================================================= -->
                <div id="emptyHelp" class="hidden section-card text-center">
                    <div class="w-12 h-12 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center mx-auto mb-3">
                        <i data-lucide="search" class="w-5 h-5"></i>
                    </div>
                    <h2 class="font-bold text-gray-800">Bantuan tidak ditemukan</h2>
                    <p class="text-sm text-gray-500 mt-1">Coba gunakan kata kunci lain.</p>
                </div>
            </section>

            <!-- =======================================================
                USAGE TIPS SECTION
            ======================================================= -->
            <section class="notice-card notice-info mt-6">
                <h2 class="font-bold text-blue-800 flex items-center gap-2">
                    <i data-lucide="lightbulb" class="w-4 h-4"></i>
                    Tips Penggunaan
                </h2>

                <ul class="list-disc pl-5 mt-2 text-sm text-blue-700 space-y-1">
                    <li>Gunakan browser versi terbaru untuk pengalaman terbaik.</li>
                    <li>Pastikan koneksi internet stabil saat mengerjakan kuis.</li>
                    <li>Jangan menutup halaman kuis saat pengerjaan berlangsung.</li>
                    <li>Simpan atau unduh hasil pengerjaan setelah penilaian selesai.</li>
                    <li>Hubungi administrator apabila menemukan kendala sistem.</li>
                </ul>
            </section>

        </div>
    </main>


    <script>
    /* =======================================================
        PAGE INITIALIZATION
    ======================================================= */
    document.addEventListener("DOMContentLoaded", function() {

        /* ---------- Element References ---------- */
        const searchInput = document.getElementById("helpSearch");
        const helpItems = document.querySelectorAll(".help-item");
        const emptyHelp = document.getElementById("emptyHelp");

        /* =======================================================
            ACCORDION TOGGLE
        ======================================================= */
        document.querySelectorAll(".help-toggle").forEach(function(button) {

            button.addEventListener("click", function() {

                const content = this.nextElementSibling;
                const icon = this.querySelector(".help-icon");

                content.classList.toggle("hidden");
                icon.textContent = content.classList.contains("hidden")
                    ? "+"
                    : "−";

            });

        });

        /* =======================================================
            SEARCH FILTER
        ======================================================= */
        if (searchInput) {

            searchInput.addEventListener("input", function() {

                const keyword = this.value.toLowerCase().trim();
                let visibleCount = 0;

                helpItems.forEach(function(item) {

                    const title = item.dataset.title || "";
                    const content = item.dataset.content || "";
                    const isMatch = title.includes(keyword) || content.includes(keyword);

                    item.classList.toggle("hidden", !isMatch);

                    if (isMatch) {
                        visibleCount++;
                    }

                });

                emptyHelp.classList.toggle("hidden", visibleCount > 0);

            });

        }

    });
    </script>

</body>
</html>