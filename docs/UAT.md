# UAT — Boedjang Voucher Generator

Diturunkan dari spesifikasi (Phase 5 & daftar UAT). Jalankan di staging dengan data mirip produksi,
**queue worker dan scheduler hidup**. Isi kolom Hasil (✓ / ✗ + catatan) dan Penguji.

Tanggal: ____________ Versi/commit: ____________ Lingkungan: ____________

## A. Akses

| # | Langkah | Harapan | Hasil | Penguji |
|---|---|---|---|---|
| A1 | Login sebagai super admin | Masuk ke dashboard | | |
| A2 | Login dengan password salah 6× | Ditolak, lalu dibatasi (429) | | |
| A3 | Buka `/register` | 404 | | |
| A4 | Nonaktifkan user yang sedang login di browser lain | Sesi user itu berakhir | | |
| A5 | Login sebagai staff outlet | Menu: Redeem, Dashboard, Campaign, Voucher, Laporan; tanpa User/Aktivitas | | |

## B. Master data & template

| # | Langkah | Harapan | Hasil | Penguji |
|---|---|---|---|---|
| B1 | Buat brand dengan prefix `WN-VM` | Tersimpan | | |
| B2 | Buat outlet untuk brand tsb, tugaskan staff | Staff hanya melihat outletnya | | |
| B3 | Pilih/unggah template, atur field kode & tanggal di editor | Posisi tersimpan | | |
| B4 | Preview server ketiga desain (BB, BTS, + desain baru) | Logo/menu utuh, kode terbaca, tanggal tampil, tidak terpotong, font sesuai | | |
| B5 | Unggah file bukan gambar / > 10 MB | Ditolak dengan pesan jelas | | |

## C. Campaign & generate

| # | Langkah | Harapan | Hasil | Penguji |
|---|---|---|---|---|
| C1 | Buat campaign (benefit, periode, outlet tertentu, foto produk) | Draft tersimpan; contoh kode tampil | | |
| C2 | Pilih preset "Persis contoh" | Peringatan "mudah ditebak" tampil | | |
| C3 | Aktifkan campaign, preview 1 voucher | Sesuai template | | |
| C4 | Generate **100** voucher | Progress berjalan; selesai; 100 kode unik | | |
| C5 | Klik Generate dua kali cepat | Hanya satu batch | | |
| C6 | Unduh ZIP | 100 gambar, terbuka semua | | |
| C7 | Unduh satu voucher (JPG & PNG) | Terbuka, sesuai | | |
| C8 | Ubah benefit campaign setelah generate | Voucher lama tetap benefit lama | | |
| C9 | Coba edit/hapus template yang sudah dipakai | Ditolak; "Versi baru" boleh | | |

## D. Redeem

| # | Langkah | Harapan | Hasil | Penguji |
|---|---|---|---|---|
| D1 | Staff: cek kode valid di outletnya | Kartu hijau + detail | | |
| D2 | Staff: gunakan voucher | Berhasil, tercatat outlet & kasir | | |
| D3 | Gunakan kode yang sama lagi | "Voucher sudah pernah digunakan" | | |
| D4 | Kode kedaluwarsa | Ditolak "kedaluwarsa" | | |
| D5 | Kode yang di-void admin | Ditolak "dibatalkan" | | |
| D6 | Kode campaign outlet lain | "Tidak berlaku di outlet ini", tanpa detail | | |
| D7 | Kode ngawur 10× | Terkunci sementara | | |
| D8 | Dua kasir menukar kode yang sama hampir bersamaan | Tepat satu berhasil | | |
| D9 | Kode diketik huruf kecil / berspasi | Tetap dikenali | | |

## E. Laporan & audit

| # | Langkah | Harapan | Hasil | Penguji |
|---|---|---|---|---|
| E1 | Dashboard setelah D2 | Angka "dipakai" bertambah | | |
| E2 | Laporan + filter outlet/tanggal | Angka konsisten dengan transaksi | | |
| E3 | Unduh CSV | Hanya transaksi; kode yang belum dipakai tidak ada | | |
| E4 | Staff membuka laporan | Hanya outletnya; tanpa angka per campaign | | |
| E5 | Super admin buka Log aktivitas | Login, generate, redeem, void, download ZIP tercatat; kode disamarkan; tanpa password | | |

## F. Operasional

| # | Langkah | Harapan | Hasil | Penguji |
|---|---|---|---|---|
| F1 | `php artisan voucher:preflight` | Hijau | | |
| F2 | `voucher:backup` lalu `voucher:backup:verify` | Valid | | |
| F3 | Latihan restore di staging | Data & gambar voucher lama kembali | | |
| F4 | Matikan worker 6 menit, cek `/health` | 503, `queue_worker` gagal | | |
| F5 | `voucher:benchmark --count=100` | Catat hasil: ____ ms/voucher | | |

Persetujuan: ______________________ (nama, tanggal)
