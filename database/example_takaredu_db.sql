-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 19 Jun 2026 pada 12.58
-- Versi server: 10.4.27-MariaDB
-- Versi PHP: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `example_takaredu_db`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `answers`
--

CREATE TABLE `answers` (
  `id` int(11) NOT NULL COMMENT 'ID jawaban siswa/pengguna (PK)',
  `student_id` int(11) NOT NULL COMMENT 'Relasi pengguna pengisi jawaban (students)',
  `quiz_id` int(11) NOT NULL COMMENT 'Relasi kuis yang dikerjakan (quiz_list)',
  `question_id` int(11) NOT NULL COMMENT 'Relasi soal yang dijawab (questions)',
  `option_id` int(11) DEFAULT NULL COMMENT 'Relasi opsi jawaban pilihan (question_opt)',
  `is_right` tinyint(1) DEFAULT NULL COMMENT 'Status evaluasi jawaban: 1=benar, 0=salah',
  `answer_text` text DEFAULT NULL COMMENT 'Jawaban teks/esai/alasan siswa',
  `created_at` datetime DEFAULT current_timestamp() COMMENT 'Waktu jawaban dikirim'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Data jawaban siswa terhadap setiap soal yang dikerjakan';

-- --------------------------------------------------------

--
-- Struktur dari tabel `answer_evaluations`
--

CREATE TABLE `answer_evaluations` (
  `id` int(11) NOT NULL COMMENT 'Primary key evaluasi jawaban (PK)',
  `answer_id` int(11) NOT NULL COMMENT 'Relasi ke tabel answers sebagai jawaban yang dievaluasi (answers)',
  `category` varchar(50) DEFAULT NULL COMMENT 'Kategori hasil evaluasi (Benar/Salah/Kurang/Positif/Negatif/dll)',
  `score` tinyint(4) NOT NULL COMMENT 'Nilai hasil evaluasi jawaban AI',
  `rubric_reference` text DEFAULT NULL COMMENT 'Acuan rubrik, ketentuan, atau indikator penilaian',
  `created_at` datetime DEFAULT current_timestamp() COMMENT 'Waktu evaluasi dibuat'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Hasil evaluasi dan penilaian jawaban menggunakan AI atau penilaian manual';

-- --------------------------------------------------------

--
-- Struktur dari tabel `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL COMMENT 'ID kelas (PK)',
  `class_name` varchar(100) NOT NULL COMMENT 'Nama kelas',
  `grade_level` varchar(10) DEFAULT NULL COMMENT 'Tingkat Kelas',
  `description` varchar(255) DEFAULT NULL COMMENT 'Deskripsi/keterangan kelas',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu data kelas dibuat',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Waktu data kelas terakhir diperbarui'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Data kelas pembelajaran berdasarkan tingkat dan rombongan belajar';

-- --------------------------------------------------------

--
-- Struktur dari tabel `history`
--

CREATE TABLE `history` (
  `id` int(11) NOT NULL COMMENT 'Primary key riwayat hasil akhir kuis (PK)',
  `quiz_student_id` int(11) NOT NULL COMMENT 'Relasi penugasan kuis siswa (quiz_student_list)',
  `final_score` tinyint(4) DEFAULT NULL COMMENT 'Total skor akhir yang diperoleh siswa',
  `max_score` tinyint(4) DEFAULT NULL COMMENT 'Total skor maksimum kuis',
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu hasil akhir kuis disimpan'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Riwayat pengerjaan kuis dan aktivitas asesmen siswa';

-- --------------------------------------------------------

--
-- Struktur dari tabel `phet`
--

CREATE TABLE `phet` (
  `id` int(11) NOT NULL COMMENT 'ID simulasi PhET (PK)',
  `phet_title` varchar(225) NOT NULL COMMENT 'Judul simulasi PhET',
  `subject_id` int(11) DEFAULT NULL COMMENT 'Relasi mata pelajaran (subjects)',
  `description` text DEFAULT NULL COMMENT 'Deskripsi simulasi',
  `original_url` text DEFAULT NULL COMMENT 'URL asli simulasi',
  `iframe_phet` varchar(500) NOT NULL COMMENT 'Embed simulasi',
  `user_id` int(11) NOT NULL COMMENT 'Relasi pembuat (users)',
  `creator_role` tinyint(1) NOT NULL DEFAULT 2 COMMENT 'Role pembuat: 1=admin,2=teacher',
  `visibility_scope` tinyint(1) NOT NULL DEFAULT 1 COMMENT '''Akses simulasi: 1=privat, 2=dibagikan ke guru tertentu, 3=publik semua guru',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Status simulasi: 1=aktif,0=nonaktif',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu data phet dibuat',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Waktu data phet terakhir diperbarui'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Bank simulasi PhET yang digunakan sebagai media eksperimen virtual';

-- --------------------------------------------------------

--
-- Struktur dari tabel `phet_teacher_access`
--

CREATE TABLE `phet_teacher_access` (
  `id` int(11) NOT NULL COMMENT 'Primary key akses pengajar ke simulasi PhET (PK)',
  `phet_id` int(11) NOT NULL COMMENT 'Relasi simulasi PhET yang dibagikan (phet)',
  `teacher_id` int(11) NOT NULL COMMENT 'Relasi pengajar penerima akses (teachers)',
  `grade_level` varchar(20) DEFAULT NULL COMMENT 'Tingkat Kelas',
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu data akses diberikan',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Waktu akses terakhir diperbarui'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tabel akses berbagi simulasi PhET antar guru';

-- --------------------------------------------------------

--
-- Struktur dari tabel `questions`
--

CREATE TABLE `questions` (
  `id` int(11) NOT NULL COMMENT 'ID soal (PK)',
  `question` text NOT NULL COMMENT 'Isi soal',
  `quiz_id` int(11) NOT NULL COMMENT 'Relasi kuis (quiz_list)',
  `order_by` int(11) NOT NULL COMMENT 'Nomor urut soal',
  `points` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Bobot nilai soal',
  `wacana_id` int(11) DEFAULT NULL COMMENT 'Relasi wacana (wacana)',
  `phet_id` int(11) DEFAULT NULL COMMENT 'Relasi simulasi PhET (phet)',
  `question_type` enum('likert','multiple_choice','reasoned_multiple_choice','short_answer','essay') DEFAULT NULL COMMENT 'Jenis soal',
  `statement_type` enum('positive','negative') DEFAULT NULL COMMENT 'Tipe pernyataan angket',
  `answer_key_text` text DEFAULT NULL COMMENT 'Kunci jawaban teks / alasan / uraian',
  `rubric_text` text DEFAULT NULL COMMENT 'Rubrik dan indikator penilaian jawaban uraian',
  `answer_table_config` longtext DEFAULT NULL COMMENT 'Struktur tabel jawaban yang digunakan pada soal',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu data soal dibuat',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Waktu data soal terakhir diperbarui'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Bank soal yang dimiliki setiap kuis';

-- --------------------------------------------------------

--
-- Struktur dari tabel `question_opt`
--

CREATE TABLE `question_opt` (
  `id` int(11) NOT NULL COMMENT 'ID opsi jawaban (PK)',
  `question_id` int(11) NOT NULL COMMENT 'Relasi soal (questions)',
  `order_by` tinyint(2) NOT NULL COMMENT 'Urutan ospi',
  `option_text` text NOT NULL COMMENT 'Isi opsi jawaban',
  `is_right` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Status jawaban benar:\r\n1=benar, 0=salah',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu data opsi dibuat',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Waktu data opsi terakhir diperbarui'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Data opsi jawaban untuk soal pilihan ganda dan pilihan ganda beralasan';

-- --------------------------------------------------------

--
-- Struktur dari tabel `quiz_list`
--

CREATE TABLE `quiz_list` (
  `id` int(11) NOT NULL COMMENT 'ID kuis (PK)',
  `quiz_title` varchar(255) NOT NULL COMMENT 'Judul kuis',
  `description` text DEFAULT NULL COMMENT 'Deskripsi kuis',
  `created_by` int(11) DEFAULT NULL COMMENT 'Relasi pembuat kuis (users)',
  `quiz_duration` int(11) DEFAULT NULL COMMENT 'Durasi pengerjaan (menit)',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Status kuis: 1 = aktif, 0 = nonaktif',
  `open_at` datetime DEFAULT NULL COMMENT 'Waktu kuis mulai dapat diakses siswa',
  `due_date` datetime DEFAULT NULL COMMENT 'Batas akhir pengerjaan kuis',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu data kuis dibuat',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Waktu data kuis terakhir diperbarui'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Data kuis dan asesmen yang dibuat dalam sistem';

-- --------------------------------------------------------

--
-- Struktur dari tabel `quiz_student_list`
--

CREATE TABLE `quiz_student_list` (
  `id` int(11) NOT NULL COMMENT 'ID penugasan kuis ke siswa (PK)',
  `quiz_id` int(11) NOT NULL COMMENT 'Relasi kuis yang ditugaskan (quiz_list)',
  `student_id` int(11) NOT NULL COMMENT 'Relasi siswa penerima kuis (students)',
  `status` tinyint(1) DEFAULT 1 COMMENT 'Status penugasan kuis:\r\n0=nonaktif,\r\n1=ditugaskan,\r\n2=sedang dikerjakan,\r\n3=selesai,\r\n4=expired',
  `started_at` datetime DEFAULT NULL COMMENT 'Waktu siswa mulai mengerjakan kuis',
  `completed_at` datetime DEFAULT NULL COMMENT 'Waktu siswa menyelesaikan kuis',
  `violation_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Jumlah pelanggaran saat kuis berlangsung',
  `auto_submit` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Status pengumpulan otomatis saat waktu kuis habis',
  `submit_reason` varchar(50) DEFAULT NULL COMMENT 'Alasan kuis dihentikan atau dikumpulkan',
  `assigned_at` datetime DEFAULT current_timestamp() COMMENT 'Waktu kuis ditugaskan ke siswa',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Waktu data penugasan terakhir diperbarui'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Distribusi kuis kepada peserta didik penerima asesmen';

-- --------------------------------------------------------

--
-- Struktur dari tabel `quiz_teacher_list`
--

CREATE TABLE `quiz_teacher_list` (
  `id` int(11) NOT NULL COMMENT 'ID penugasan kuis yang dikelola pengajar',
  `quiz_id` int(11) NOT NULL COMMENT 'Relasi kuis yang dikelola pengajar (quiz_list)',
  `teacher_id` int(11) NOT NULL COMMENT 'Relasi pengajar pengelola kuis (teachers)',
  `class_id` int(11) NOT NULL COMMENT 'Relasi kelas target kuis (classes)',
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu pengajar ditugaskan ke kuis',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Waktu data penugasan pengajar terakhir diperbarui'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Distribusi kuis kepada pengajar yang diberi hak akses';

-- --------------------------------------------------------

--
-- Struktur dari tabel `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL COMMENT 'ID siswa (PK)',
  `user_id` int(11) NOT NULL COMMENT 'Relasi akun pengguna (users)',
  `class_id` int(11) DEFAULT NULL COMMENT 'Relasi kelas (classes)',
  `gender` enum('L','P') DEFAULT NULL COMMENT 'Jenis kelamin siswa',
  `created_at` datetime DEFAULT current_timestamp() COMMENT 'Waktu data siswa dibuat',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Waktu data siswa terakhir diperbarui'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Data profil peserta didik yang terhubung dengan akun pengguna';

-- --------------------------------------------------------

--
-- Struktur dari tabel `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL COMMENT 'ID mapel (PK)',
  `subject_name` varchar(100) NOT NULL COMMENT 'Nama mata pelajaran',
  `subject_code` varchar(20) DEFAULT NULL COMMENT 'Kode mata pelajaran',
  `description` varchar(255) DEFAULT NULL COMMENT 'Deskripsi/keterangan mapel',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu data mapel dibuat',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Waktu data kelas terakhir diperbarui'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Data mata pelajaran yang digunakan dalam sistem asesmen';

-- --------------------------------------------------------

--
-- Struktur dari tabel `teachers`
--

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL COMMENT 'ID pengajar (PK)',
  `user_id` int(11) NOT NULL COMMENT 'Relasi akun pengguna (users)',
  `subject_id` int(11) DEFAULT NULL COMMENT 'Relasi mata pelajaran (subjects)',
  `gender` enum('L','P') DEFAULT NULL COMMENT 'Jenis kelamin pengajar',
  `created_at` datetime DEFAULT current_timestamp() COMMENT 'Waktu data pengajar dibuat',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Waktu data pengajar terakhir diperbarui'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Data profil pengajar yang terhubung dengan akun pengguna';

-- --------------------------------------------------------

--
-- Struktur dari tabel `teacher_class_assignments`
--

CREATE TABLE `teacher_class_assignments` (
  `id` int(11) NOT NULL COMMENT 'ID penugasan guru-kelas-mapel (PK)',
  `teacher_id` int(11) NOT NULL COMMENT 'Relasi guru pengajar utama (teachers)',
  `class_id` int(11) NOT NULL COMMENT 'Relasi kelas yang diajar (classes)',
  `subject_id` int(11) NOT NULL COMMENT 'Relasi mata pelajaran yang diajarkan (subjects)',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Status penugasan: 1=aktif, 0=nonaktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu data penugasan dibuat',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Waktu data penugasan terakhir diperbarui'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Relasi pengajar dengan kelas yang diampu';

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL COMMENT 'ID pengguna (PK)',
  `name` varchar(150) NOT NULL COMMENT 'Nama lengkap pengguna',
  `user_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Role pengguna:\r\n1=admin,\r\n2=teacher,\r\n3=student',
  `username` varchar(25) NOT NULL COMMENT 'Username login',
  `password` varchar(255) NOT NULL COMMENT 'Password terenkripsi',
  `profile_image` varchar(255) DEFAULT NULL COMMENT 'Foto profil pengguna',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Status akun:\r\n1=aktif, 0=nonaktif',
  `created_at` datetime DEFAULT current_timestamp() COMMENT 'Waktu data akun dibuat',
  `updated_at` datetime DEFAULT current_timestamp() COMMENT 'Waktu data akun terakhir diperbarui',
  `last_login` datetime DEFAULT NULL COMMENT 'Waktu login terakhir',
  `failed_login_attempts` int(11) DEFAULT 0 COMMENT 'Jumlah login gagal',
  `locked_until` datetime DEFAULT NULL COMMENT 'Waktu blokir sementara'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Data akun pengguna sistem (admin, pengajar, dan siswa)';

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `name`, `user_type`, `username`, `password`, `profile_image`, `status`, `created_at`, `updated_at`, `last_login`, `failed_login_attempts`, `locked_until`) VALUES
(1, 'Administrator', 1, 'admin', '$2y$10$eyGl7sqe33W/fjhUlfzXrONuVgD2lDl6oFPR3LoRs0qPaU1dTNWw.', NULL, 1, '2026-05-05 19:12:22', '2026-05-09 11:47:06', '2026-06-18 22:12:42', 0, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `wacana`
--

CREATE TABLE `wacana` (
  `id` int(11) NOT NULL COMMENT 'ID wacana (PK)',
  `wacana_title` varchar(255) NOT NULL COMMENT 'Judul wacana',
  `subject_id` int(11) DEFAULT NULL COMMENT 'Relasi mata pelajaran (subjects)',
  `description` text DEFAULT NULL COMMENT 'Isi utama narasi wacana',
  `user_id` int(11) NOT NULL COMMENT 'Relasi pembuat (users)',
  `creator_role` tinyint(1) NOT NULL DEFAULT 2 COMMENT 'Role pembuat: 1=admin, 2=teacher',
  `visibility_scope` tinyint(1) NOT NULL DEFAULT 1 COMMENT '''Akses wacana: 1=privat, 2=dibagikan ke guru tertentu, 3=publik semua guru',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Status wacana: 1=aktif, 0=nonaktif/archive',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu data wacana dibuat',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Waktu data wacana terakhir diperbarui'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Bank wacana atau stimulus pembelajaran yang dapat digunakan pada soal';

-- --------------------------------------------------------

--
-- Struktur dari tabel `wacana_teacher_access`
--

CREATE TABLE `wacana_teacher_access` (
  `id` int(11) NOT NULL COMMENT 'Primary key akses pengajar ke wacana (PK)',
  `wacana_id` int(11) NOT NULL COMMENT 'Relasi wacana yang dibagikan (wacana)',
  `teacher_id` int(11) NOT NULL COMMENT 'Relasi pengajar penerima akses (teachers)',
  `grade_level` varchar(20) DEFAULT NULL COMMENT 'Tingkat Kelas',
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu data akses diberikan',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Waktu data akses terakhir diperbarui'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `answers`
--
ALTER TABLE `answers`
  ADD PRIMARY KEY (`id`) USING BTREE COMMENT 'Primary key data jawaban',
  ADD UNIQUE KEY `unique_student_quiz_question` (`student_id`,`quiz_id`,`question_id`) USING BTREE COMMENT 'Satu siswa satu jawaban per soal dalam kuis',
  ADD KEY `fk_answer_student` (`student_id`) USING BTREE COMMENT 'Relasi siswa pengisi jawaban (students)',
  ADD KEY `fk_answer_quiz` (`quiz_id`) USING BTREE COMMENT 'Relasi kuis yang dikerjakan (quiz_list)',
  ADD KEY `fk_answer_question` (`question_id`) USING BTREE COMMENT 'Relasi soal yang dijawab (questions)',
  ADD KEY `fk_answer_option` (`option_id`) USING BTREE COMMENT 'Relasi opsi jawaban pilihan (question_opt)',
  ADD KEY `idx_answer_quiz_student` (`quiz_id`,`student_id`) USING BTREE COMMENT 'Dashboard progres siswa per kuis',
  ADD KEY `idx_answer_result` (`quiz_id`,`student_id`,`is_right`) USING BTREE COMMENT 'Analisis hasil benar/salah siswa dalam kuis';

--
-- Indeks untuk tabel `answer_evaluations`
--
ALTER TABLE `answer_evaluations`
  ADD PRIMARY KEY (`id`) USING BTREE COMMENT 'Primary key data evaluasi jawaban',
  ADD UNIQUE KEY `unique_answer_eval` (`answer_id`) COMMENT 'Satu jawaban memiliki satu evaluasi utama',
  ADD KEY `fk_eval_answer` (`answer_id`) USING BTREE COMMENT 'Relasi evaluasi terhadap jawaban siswa (answers)',
  ADD KEY `idx_eval_category` (`category`) COMMENT 'Analisis kategori evaluasi',
  ADD KEY `idx_eval_score` (`score`) COMMENT 'Analisis distribusi nilai';

--
-- Indeks untuk tabel `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`) USING BTREE COMMENT 'Primary key data kelas',
  ADD UNIQUE KEY `unique_class_name` (`class_name`) USING BTREE COMMENT 'Nama kelas unik';

--
-- Indeks untuk tabel `history`
--
ALTER TABLE `history`
  ADD PRIMARY KEY (`id`) USING BTREE COMMENT 'Primary key riwayat hasil akhir kuis',
  ADD UNIQUE KEY `unique_quiz_student_history` (`quiz_student_id`) USING BTREE COMMENT 'satu penugasan siswa hanya memiliki satu riwayat akhir',
  ADD KEY `fk_history_quiz_student` (`quiz_student_id`) USING BTREE COMMENT 'Relasi penugasan kuis siswa (quiz_student_list)',
  ADD KEY `idx_history_score` (`final_score`) COMMENT 'Analisis distribusi nilai akhir',
  ADD KEY `idx_history_submitted` (`submitted_at`) COMMENT 'Monitoring waktu submit hasil akhir';

--
-- Indeks untuk tabel `phet`
--
ALTER TABLE `phet`
  ADD PRIMARY KEY (`id`) USING BTREE COMMENT 'Primary key data PhET',
  ADD UNIQUE KEY `unique_user_phet_title` (`user_id`,`phet_title`) COMMENT 'Satu pengguna tidak dapat memiliki judul simulasi sama lebih dari sekali',
  ADD KEY `fk_phet_subject` (`subject_id`) USING BTREE COMMENT 'Relasi mata pelajaran (subjects)',
  ADD KEY `fk_phet_user` (`user_id`) USING BTREE COMMENT 'Relasi pembuat (users)',
  ADD KEY `idx_phet_status` (`status`) COMMENT 'Index status aktif/nonaktif',
  ADD KEY `idx_phet_user_status` (`user_id`,`status`) COMMENT 'Dashboard pengguna berdasarkan status',
  ADD KEY `idx_visibility_scope` (`visibility_scope`) COMMENT 'Index pencarian berdasarkan cakupan akses simulasi';

--
-- Indeks untuk tabel `phet_teacher_access`
--
ALTER TABLE `phet_teacher_access`
  ADD PRIMARY KEY (`id`) USING BTREE COMMENT 'Primary key akses pengajar ke PhET',
  ADD UNIQUE KEY `unique_phet_teacher` (`phet_id`,`teacher_id`) USING BTREE COMMENT 'Satu pengajar hanya boleh satu akses per PhET',
  ADD KEY `fk_pta_phet` (`phet_id`) USING BTREE COMMENT 'Relasi PhET yang dibagikan (phet)',
  ADD KEY `fk_pta_teacher` (`teacher_id`) USING BTREE COMMENT 'Relasi pengajar penerima akses (teachers)',
  ADD KEY `idx_pta_phet_teacher` (`phet_id`,`teacher_id`) USING BTREE COMMENT 'Dashboard akses pengajar berdasarkan simulasi';

--
-- Indeks untuk tabel `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`) USING BTREE COMMENT 'Primary key data soal',
  ADD UNIQUE KEY `unique_quiz_order` (`quiz_id`,`order_by`) COMMENT 'Nomor urut soal unik per kuis',
  ADD KEY `fk_question_quiz` (`quiz_id`) USING BTREE COMMENT 'Relasi kuis (quiz_list)',
  ADD KEY `fk_question_wacana` (`wacana_id`) USING BTREE COMMENT 'Relasi wacana (wacana)',
  ADD KEY `fk_question_phet` (`phet_id`) USING BTREE COMMENT 'Relasi simulasi PhET (phet)',
  ADD KEY `idx_question_type` (`question_type`) COMMENT 'Filter dan analisis berdasarkan jenis soal',
  ADD KEY `idx_statement_type` (`statement_type`) COMMENT 'Filter tipe pernyataan angket (positif/negatif)',
  ADD KEY `idx_quiz_type` (`quiz_id`,`question_type`) COMMENT 'Dashboard soal berdasarkan kuis dan jenis soal';

--
-- Indeks untuk tabel `question_opt`
--
ALTER TABLE `question_opt`
  ADD PRIMARY KEY (`id`) USING BTREE COMMENT 'Primary key opsi',
  ADD UNIQUE KEY `unique_question_option_order` (`question_id`,`order_by`) COMMENT 'Urutan opsi unik per soal',
  ADD KEY `fk_option_question` (`question_id`) USING BTREE COMMENT 'Relasi soal (questions)',
  ADD KEY `idx_question_correct` (`question_id`,`is_right`) COMMENT 'Lookup jawaban benar per soal';

--
-- Indeks untuk tabel `quiz_list`
--
ALTER TABLE `quiz_list`
  ADD PRIMARY KEY (`id`) USING BTREE COMMENT 'Primary key data kuis',
  ADD UNIQUE KEY `unique_quiz_title_teacher` (`quiz_title`,`created_by`) USING BTREE COMMENT 'Judul kuis unik per pengajar',
  ADD KEY `fk_quiz_creator` (`created_by`) USING BTREE COMMENT 'Relasi pembuat kuis (users)',
  ADD KEY `idx_quiz_teacher_status` (`created_by`,`status`) USING BTREE COMMENT 'Dashboard kuis pengajar berdasarkan status',
  ADD KEY `idx_quiz_class_status` (`status`) USING BTREE COMMENT 'Filter kuis aktif per kelas',
  ADD KEY `idx_quiz_subject_status` (`status`) USING BTREE COMMENT 'Filter kuis berdasarkan mata pelajaran',
  ADD KEY `idx_quiz_schedule` (`open_at`,`due_date`) USING BTREE COMMENT 'Index jadwal kuis aktif';

--
-- Indeks untuk tabel `quiz_student_list`
--
ALTER TABLE `quiz_student_list`
  ADD PRIMARY KEY (`id`) USING BTREE COMMENT 'Primary key penugasan kuis siswa',
  ADD UNIQUE KEY `unique_quiz_student` (`quiz_id`,`student_id`) COMMENT 'Satu siswa satu penugasan per kuis',
  ADD KEY `fk_qsl_quiz` (`quiz_id`) USING BTREE COMMENT 'Relasi kuis yang ditugaskan (quiz_list)',
  ADD KEY `fk_qsl_student` (`student_id`) USING BTREE COMMENT 'Relasi siswa penerima kuis (students)',
  ADD KEY `idx_qsl_student_status` (`student_id`,`status`) COMMENT 'Daftar tugas aktif siswa',
  ADD KEY `idx_qsl_schedule` (`assigned_at`,`completed_at`) COMMENT 'Monitoring jadwal pengerjaan siswa',
  ADD KEY `idx_qsl_quiz_status` (`quiz_id`,`status`) COMMENT 'Monitoring progres kuis siswa';

--
-- Indeks untuk tabel `quiz_teacher_list`
--
ALTER TABLE `quiz_teacher_list`
  ADD PRIMARY KEY (`id`) USING BTREE COMMENT 'Primary key penugasan pengajar kuis',
  ADD UNIQUE KEY `unique_quiz_teacher_class` (`quiz_id`,`teacher_id`,`class_id`) USING BTREE COMMENT 'Satu pengajar hanya boleh ditugaskan satu kali pada kelas yang sama dalam satu kuis',
  ADD KEY `fk_qtl_teacher` (`teacher_id`) USING BTREE COMMENT 'Relasi pengajar pengelola kuis (teachers)',
  ADD KEY `fk_qtl_quiz` (`quiz_id`) USING BTREE COMMENT 'Relasi kuis yang dikelola pengajar (quiz_list)',
  ADD KEY `fk_qtl_class` (`class_id`) USING BTREE COMMENT 'Relasi kelas target kuis (classes)',
  ADD KEY `idx_qtl_schedule` (`assigned_at`) USING BTREE COMMENT 'Monitoring jadwal penugasan guru';

--
-- Indeks untuk tabel `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`) USING BTREE COMMENT 'Primary key data siswa',
  ADD UNIQUE KEY `unique_student_user` (`user_id`) USING BTREE COMMENT 'Satu akun user hanya untuk satu data siswa',
  ADD KEY `fk_student_user` (`user_id`) USING BTREE COMMENT 'Relasi akun pengguna siswa (users)',
  ADD KEY `fk_student_class` (`class_id`) USING BTREE COMMENT 'Relasi kelas siswa (classes)';

--
-- Indeks untuk tabel `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`) USING BTREE COMMENT 'Primary key data mapel',
  ADD UNIQUE KEY `unique_subject_name` (`subject_name`) USING BTREE COMMENT 'Nama mapel unik',
  ADD UNIQUE KEY `unique_subject_code` (`subject_code`) USING BTREE COMMENT 'Kode mapel unik';

--
-- Indeks untuk tabel `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`) USING BTREE COMMENT 'Primary key data pengajar',
  ADD UNIQUE KEY `unique_teacher_user` (`user_id`) COMMENT 'Satu akun user hanya untuk satu data pengajar',
  ADD KEY `fk_teacher_user` (`user_id`) USING BTREE COMMENT 'Relasi akun pengguna pengajar (users)',
  ADD KEY `fk_teacher_subject` (`subject_id`) USING BTREE COMMENT 'Relasi mata pelajaran pengajar (subjects)';

--
-- Indeks untuk tabel `teacher_class_assignments`
--
ALTER TABLE `teacher_class_assignments`
  ADD PRIMARY KEY (`id`) USING BTREE COMMENT 'Primary key data penugasan guru-kelas-mapel',
  ADD UNIQUE KEY `unique_assignment` (`teacher_id`,`class_id`,`subject_id`) COMMENT 'Satu guru hanya boleh satu penugasan unik per kelas dan mapel',
  ADD KEY `fk_tca_teacher` (`teacher_id`) USING BTREE COMMENT 'Relasi guru pengajar utama (teachers)',
  ADD KEY `fk_tca_class` (`class_id`) USING BTREE COMMENT 'Relasi kelas penugasan guru (classes)',
  ADD KEY `fk_tca_subject` (`subject_id`) USING BTREE COMMENT 'Relasi mata pelajaran penugasan guru (subjects)',
  ADD KEY `idx_tca_status` (`status`) COMMENT 'Index status penugasan aktif/nonaktif';

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`) USING BTREE COMMENT 'Primary key akun',
  ADD UNIQUE KEY `unique_username` (`username`) USING BTREE COMMENT 'Username unik login',
  ADD KEY `idx_user_type` (`user_type`) USING BTREE COMMENT 'Indeks role pengguna',
  ADD KEY `idx_status` (`status`) USING BTREE COMMENT 'Indeks status akun',
  ADD KEY `idx_user_type_status` (`user_type`,`status`) COMMENT 'Filter role pengguna berdasarkan status aktif';

--
-- Indeks untuk tabel `wacana`
--
ALTER TABLE `wacana`
  ADD PRIMARY KEY (`id`) USING BTREE COMMENT 'Primary key data wacana',
  ADD UNIQUE KEY `unique_user_wacana_title` (`user_id`,`wacana_title`) USING BTREE COMMENT 'Satu pengguna tidak dapat memiliki judul wacana yang sama lebih dari sekali',
  ADD KEY `fk_wacana_user` (`user_id`) USING BTREE COMMENT 'Relasi pembuat (users)',
  ADD KEY `fk_wacana_subject` (`subject_id`) USING BTREE COMMENT 'Relasi mata pelajaran (subjects)',
  ADD KEY `idx_wacana_status` (`status`) COMMENT 'Index status aktif/nonaktif',
  ADD KEY `idx_wacana_user_status` (`user_id`,`status`) COMMENT 'Dashboard guru berdasarkan user dan status',
  ADD KEY `idx_visibility_scope` (`visibility_scope`) USING BTREE COMMENT 'Index pencarian berdasarkan cakupan akses wacana';

--
-- Indeks untuk tabel `wacana_teacher_access`
--
ALTER TABLE `wacana_teacher_access`
  ADD PRIMARY KEY (`id`) USING BTREE COMMENT 'Primary key akses pengajar ke wacana',
  ADD UNIQUE KEY `unique_wacana_teacher` (`wacana_id`,`teacher_id`) USING BTREE COMMENT 'Satu pengajar hanya boleh satu akses per wacana',
  ADD KEY `fk_wta_wacana` (`wacana_id`) USING BTREE COMMENT 'Relasi wacana yang dibagikan (wacana)',
  ADD KEY `fk_wta_teacher` (`teacher_id`) USING BTREE COMMENT 'Relasi pengajar penerima akses (teachers)',
  ADD KEY `idx_wta_teacher_wacana` (`teacher_id`,`wacana_id`) USING BTREE COMMENT 'Dashboard akses pengajar berdasarkan teacher';

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `answers`
--
ALTER TABLE `answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID jawaban siswa/pengguna (PK)', AUTO_INCREMENT=235;

--
-- AUTO_INCREMENT untuk tabel `answer_evaluations`
--
ALTER TABLE `answer_evaluations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary key evaluasi jawaban (PK)', AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT untuk tabel `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID kelas (PK)', AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT untuk tabel `history`
--
ALTER TABLE `history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary key riwayat hasil akhir kuis (PK)', AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT untuk tabel `phet`
--
ALTER TABLE `phet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID simulasi PhET (PK)', AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT untuk tabel `phet_teacher_access`
--
ALTER TABLE `phet_teacher_access`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary key akses pengajar ke simulasi PhET (PK)', AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT untuk tabel `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID soal (PK)', AUTO_INCREMENT=210;

--
-- AUTO_INCREMENT untuk tabel `question_opt`
--
ALTER TABLE `question_opt`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID opsi jawaban (PK)', AUTO_INCREMENT=811;

--
-- AUTO_INCREMENT untuk tabel `quiz_list`
--
ALTER TABLE `quiz_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID kuis (PK)', AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT untuk tabel `quiz_student_list`
--
ALTER TABLE `quiz_student_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID penugasan kuis ke siswa (PK)', AUTO_INCREMENT=147;

--
-- AUTO_INCREMENT untuk tabel `quiz_teacher_list`
--
ALTER TABLE `quiz_teacher_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID penugasan kuis yang dikelola pengajar', AUTO_INCREMENT=125;

--
-- AUTO_INCREMENT untuk tabel `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID siswa (PK)', AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID mapel (PK)', AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID pengajar (PK)', AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT untuk tabel `teacher_class_assignments`
--
ALTER TABLE `teacher_class_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID penugasan guru-kelas-mapel (PK)', AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID pengguna (PK)', AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT untuk tabel `wacana`
--
ALTER TABLE `wacana`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID wacana (PK)', AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT untuk tabel `wacana_teacher_access`
--
ALTER TABLE `wacana_teacher_access`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary key akses pengajar ke wacana (PK)', AUTO_INCREMENT=29;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `answers`
--
ALTER TABLE `answers`
  ADD CONSTRAINT `fk_answer_option` FOREIGN KEY (`option_id`) REFERENCES `question_opt` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_answer_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_answer_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quiz_list` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_answer_user` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `answer_evaluations`
--
ALTER TABLE `answer_evaluations`
  ADD CONSTRAINT `fk_eval_answer` FOREIGN KEY (`answer_id`) REFERENCES `answers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `history`
--
ALTER TABLE `history`
  ADD CONSTRAINT `fk_history_quiz_student` FOREIGN KEY (`quiz_student_id`) REFERENCES `quiz_student_list` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `phet`
--
ALTER TABLE `phet`
  ADD CONSTRAINT `fk_phet_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_phet_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `phet_teacher_access`
--
ALTER TABLE `phet_teacher_access`
  ADD CONSTRAINT `fk_pta_phet` FOREIGN KEY (`phet_id`) REFERENCES `phet` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pta_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `fk_question_phet` FOREIGN KEY (`phet_id`) REFERENCES `phet` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_question_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quiz_list` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_question_wacana` FOREIGN KEY (`wacana_id`) REFERENCES `wacana` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `question_opt`
--
ALTER TABLE `question_opt`
  ADD CONSTRAINT `fk_option_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `quiz_list`
--
ALTER TABLE `quiz_list`
  ADD CONSTRAINT `fk_quiz_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `quiz_student_list`
--
ALTER TABLE `quiz_student_list`
  ADD CONSTRAINT `fk_qsl_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quiz_list` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_qsl_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `quiz_teacher_list`
--
ALTER TABLE `quiz_teacher_list`
  ADD CONSTRAINT `fk_qtl_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_qtl_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quiz_list` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_qtl_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_student_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_student_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `fk_teacher_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_teacher_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `teacher_class_assignments`
--
ALTER TABLE `teacher_class_assignments`
  ADD CONSTRAINT `fk_tca_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tca_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tca_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `wacana`
--
ALTER TABLE `wacana`
  ADD CONSTRAINT `fk_wacana_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_wacana_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `wacana_teacher_access`
--
ALTER TABLE `wacana_teacher_access`
  ADD CONSTRAINT `fk_wta_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_wta_wacana` FOREIGN KEY (`wacana_id`) REFERENCES `wacana` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
