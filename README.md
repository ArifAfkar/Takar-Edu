# Takar-Edu

Takar-Edu adalah platform asesmen autentik berbasis web yang dikembangkan sebagai bagian dari penelitian pendidikan. Sistem ini mendukung berbagai jenis penilaian, termasuk pilihan ganda, pilihan ganda beralasan, jawaban singkat, uraian, serta integrasi penilaian berbantuan AI menggunakan OpenAI.

## Fitur Utama

* Manajemen pengguna (Admin, Guru, Peserta Didik)
* Manajemen kelas dan mata pelajaran
* Kuis berbasis web
* Soal pilihan ganda
* Soal pilihan ganda beralasan
* Soal jawaban singkat
* Soal uraian
* Penilaian otomatis berbasis AI
* Distribusi kuis ke kelas
* Riwayat pengerjaan dan hasil penilaian
* Integrasi PhET Simulation
* Integrasi wacana pembelajaran
* Dashboard statistik sederhana

## Teknologi yang Digunakan

### Backend

* PHP
* MySQL / MariaDB
* Python

### Frontend

* HTML
* CSS
* JavaScript
* Tailwind CSS
* Lucide Icons

### AI Integration

* OpenAI API

## Requirements

* PHP 8.1 atau lebih baru
* MySQL / MariaDB
* Composer
* Python 3.10 atau lebih baru
* OpenAI API Key

## Instalasi

### 1. Clone Repository

```bash
git clone https://github.com/ArifAfkar/Takar-Edu.git
```

### 2. Install Dependency PHP

```bash
composer install
```

### 3. Install Dependency Python

```bash
pip install -r requirements.txt
```

atau

```bash
pip install openai pymysql python-dotenv
```

### 4. Konfigurasi Environment

Salin file:

```bash
cp .env.example .env
```

Kemudian sesuaikan nilai berikut:

```env
# Database
DB_HOST=localhost
DB_NAME=takaredu_db
DB_USER=root
DB_PASS=

# OpenAI
OPENAI_API_KEY=your_openai_api_key
```

### 5. Import Database

Import file:

```text
database/example_takaredu_db.sql
```

ke MySQL atau MariaDB.

Setelah proses import selesai, pastikan database yang digunakan pada konfigurasi aplikasi sesuai dengan nama database yang dibuat, misalnya:

```env
DB_NAME=takaredu_db
```

### 6. Jalankan Aplikasi

Tempatkan project pada:

```text
xampp/htdocs/takar-edu
```

Lalu buka:

```text
http://localhost/takar-edu
```

## Struktur Proyek

```text
takar-edu/
├── ai/
├── ajax/
├── assets/
├── auth/
├── config/
├── database/
├── includes/
├── pages/
├── uploads/
├── vendor/
├── composer.json
├── requirements.txt
└── index.php
```

## Catatan

Proyek ini dikembangkan sebagai bagian dari penelitian pendidikan dan masih memiliki berbagai keterbatasan. Struktur kode dan implementasi mungkin belum sepenuhnya mengikuti standar pengembangan perangkat lunak profesional karena dikembangkan dengan bantuan AI dan oleh pengembang yang tidak berasal dari latar belakang ilmu komputer atau rekayasa perangkat lunak.

## Lisensi

Proyek ini menggunakan lisensi MIT.
