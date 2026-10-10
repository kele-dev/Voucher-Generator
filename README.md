# Boedjang Voucher Generator

Aplikasi internal untuk membuat, mencetak (gambar/ZIP), dan menukarkan voucher makan multi-brand
(Bebek Boedjang, BTS, Warung Nini, …).

Laravel 13 · PHP 8.3+ · SQLite (WAL) · Blade + Alpine.js + Tailwind · Intervention Image (GD) · queue database.

Spesifikasi asli: `BOEDJANG_VOUCHER_GENERATOR_SPEC_LARAVEL13.md`.

---

## Fitur

| Area | Isi |
|---|---|
| Akses | Login (Fortify), 4 role: `super_admin`, `voucher_admin`, `outlet_staff`, `viewer`. Tidak ada registrasi publik. |
| Master data | Brand (prefix kode), outlet, user + penugasan outlet. |
| Template | Upload JPG/PNG, editor posisi field (drag/resize), preview server, versi. Template yang sudah menerbitkan voucher terkunci. |
| Campaign | Benefit, periode, cakupan outlet, batas total, foto produk, pola kode (preset/kustom). Status draft → aktif ⇄ dijeda → ditutup. |
| Generate | 1–2000 voucher per batch via queue, idempoten, progress langsung, ZIP JPG/PNG (100%/50%), export ulang. |
| Void | Batalkan voucher aktif dengan alasan (tercatat di log aktivitas). |
| Ringkasan | Dashboard jumlah campaign, batch, dan voucher terbit (aktif/kedaluwarsa/dibatalkan), log aktivitas (audit). |

Format kode (per campaign, `config/voucher.php`):

| Preset | Pola | Contoh | Catatan |
|---|---|---|---|
| Mirip contoh + acak *(default)* | `{PREFIX}{DDMM}{SEQ:4}{RAND:3}` | `WN-VM08100005K7Q` | Tidak bisa ditebak |
| Persis contoh | `{PREFIX}{DDMM}{SEQ:3}` | `WN-VM0810005` | **Mudah ditebak**, maks 999 per tanggal kalender (seumur hidup) |
| Random murni | `{PREFIX}-{RAND:8}` | `WN-VM-7K3Q9XZA` | |

Bagian acak tidak memakai I, L, O, 0, 1.

---

## Menjalankan lokal (clone bersih)

Butuh PHP 8.3+ dengan ekstensi `gd`, `zip`, `pdo_sqlite`, `intl`, `mbstring`, `fileinfo`; Composer 2; Node 20+.

```bash
git clone <repo> voucher-generator && cd voucher-generator
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite        # Windows: type nul > database\database.sqlite
php artisan migrate --seed            # mencetak password super admin SEKALI — simpan
npm install && npm run build
php artisan serve                     # atau buka lewat Laravel Herd
php artisan queue:work                # WAJIB di terminal lain: generate & export jalan di queue
php artisan schedule:work             # opsional lokal: heartbeat, backup, pembersihan
```

Seeder lokal membuat 3 brand demo + 2 template (BB, BTS). `warung-nini.jpg` di
`boedjang-voucher-agent-kit/` adalah **contoh voucher jadi**, bukan template.

Tes:

```bash
php artisan test                                  # semua
php artisan test --filter=VoucherGenerationTest       # generate batch, kode unik, ZIP
```

---

## Deploy produksi (VPS Linux)

Contoh berkas di `deploy/` (Nginx, Supervisor, cron, skrip deploy). **Belum dijalankan di server
sungguhan** — sesuaikan path, domain, versi PHP.

1. **Server**: Ubuntu LTS, Nginx, PHP 8.4-FPM (+ gd, zip, sqlite3, intl, mbstring), Composer, Node,
   Supervisor, certbot.
2. **Struktur**:
   ```
   /var/www/voucher/
     releases/<timestamp>/      kode
     current -> releases/...    yang dilayani Nginx (root: current/public)
     shared/.env
     shared/database/database.sqlite
     shared/storage/            template, foto, ZIP, log, sesi
     shared/backups/
   ```
   Pemilik `www-data`, mode 640/750. Hanya `public/` yang di-serve. `database/`, `storage/`,
   `backups/`, `.env` **tidak boleh** bisa diakses web.
3. **Konfigurasi**: salin `.env.production.example` → `shared/.env`, isi `APP_URL`, `HEALTH_TOKEN`,
   path; `php artisan key:generate --force` **sekali** saja (mengganti APP_KEY membuat sesi dan data
   terenkripsi tak terbaca).
4. **Nginx**: `deploy/nginx.conf` + `certbot --nginx`.
5. **Queue worker**: `deploy/supervisor-voucher-worker.conf`. Satu worker (SQLite hanya punya satu
   kunci tulis). `--timeout=1850` < `DB_QUEUE_RETRY_AFTER=2000`, keduanya > export terlama (1800 s).
6. **Scheduler**: `deploy/crontab` (satu baris `schedule:run` tiap menit).
7. **Deploy**: `REPO=<git url> deploy/deploy.sh` — backup dulu, migrate, cache config/route/view,
   `queue:restart`, lalu `voucher:preflight`.
8. **Akun pertama**: `SEED_ADMIN_EMAIL=… SEED_ADMIN_PASSWORD=… php artisan db:seed --force`
   (di produksi tidak ada data demo).

### Checklist go-live

- [ ] `php artisan voucher:preflight` hijau (debug mati, https, cookie secure, log ≠ debug, queue ≠ sync,
      `retry_after` > timeout job, WAL aktif, folder bisa ditulis)
- [ ] `php artisan voucher:backup` lalu **latihan restore** (lihat bawah) di server staging
- [ ] Uptime monitor ke `https://…/health` dengan header `X-Health-Token`
- [ ] `docs/UAT.md` selesai dan ditandatangani
- [ ] Password super admin diganti dari yang dicetak seeder

---

## Operasional

### Jadwal otomatis (`routes/console.php`, WIB)

| Kapan | Tugas |
|---|---|
| tiap menit | heartbeat scheduler + heartbeat queue worker (dibaca `/health`) |
| 02:00 | `voucher:backup` (+ retensi 7 harian, 4 mingguan) |
| 02:30 | `voucher:backup:verify` (checksum, `integrity_check`, isi arsip) |
| 03:00 | `voucher:prune-exports` (ZIP > 30 hari, ZIP yatim, folder tmp) |
| 03:15 | `queue:prune-failed` > 30 hari |

Log aktivitas (audit) **tidak pernah** dihapus.

### Backup & restore

Satu backup = folder `shared/backups/<YYYYmmdd-HHMMSS>/`:

- `database.sqlite` — snapshot konsisten via `VACUUM INTO` (aman saat ada penulisan/WAL, tanpa
  mematikan aplikasi). **Jangan** menyalin `database.sqlite` live dengan `cp`: tanpa file `-wal`
  hasilnya bisa tidak konsisten.
- `storage.zip` — template, sampel, foto produk. ZIP export **tidak** ikut (bisa dibuat ulang).
- `manifest.json` — checksum, ukuran, jumlah migration.

**Backup disimpan di server yang sama.** Salin keluar secara berkala, misalnya mingguan:

```bash
rsync -a voucher-server:/var/www/voucher/shared/backups/ ~/voucher-backups/
```

Perintah:

```bash
php artisan voucher:backup                 # buat sekarang
php artisan voucher:backup:verify          # cek yang terbaru (atau beri nama)
php artisan voucher:restore 20261008-020000
```

Restore (ketik nama backup untuk konfirmasi) berjalan berurutan:

1. backup **pengaman** dari kondisi sekarang;
2. mode maintenance — worker berhenti mengambil job baru;
3. menunggu job yang sedang berjalan selesai (`--wait=120` detik; jika lewat, restore dibatalkan dan
   aplikasi online kembali);
4. ganti database + file; link ZIP yang filenya tidak ada dikosongkan (UI menawarkan *Export ulang*);
5. `migrate --force` (backup bisa lebih tua dari skema sekarang);
6. `queue:restart` — worker wajib tersambung ulang, karena koneksi lamanya masih menunjuk ke file
   database yang sudah diganti. Sinyal ini disimpan di cache, karena itu produksi **wajib**
   `CACHE_STORE=file` (cache di dalam SQLite ikut tertimpa dan sinyalnya tak pernah terbaca worker;
   `voucher:preflight` menolak konfigurasi itu);
7. baru online.

Jika langkah 4–6 gagal, aplikasi **tetap** maintenance dan command mencetak perintah untuk kembali ke
backup pengaman. Untuk kepastian ekstra, hentikan worker manual dulu:
`sudo supervisorctl stop voucher-worker:*` (lalu `start` setelah selesai).

**Latihan restore** (tiap 3 bulan): salin backup ke staging, `voucher:restore`, login, buka satu
voucher lama (gambar harus sama), unduh satu voucher uji.

### Monitoring

- `GET /up` — proses hidup (tanpa cek dependensi).
- `GET /health` — 200 / 503 untuk monitor. Dengan token, berisi detail: database, storage bisa ditulis,
  queue worker (heartbeat < 5 menit), scheduler, umur backup (< 26 jam), failed jobs 24 jam terakhir.

```bash
curl -s -H "X-Health-Token: $HEALTH_TOKEN" https://voucher.example.com/health
```

### Troubleshooting

| Gejala | Penyebab / tindakan |
|---|---|
| Batch "Menunggu" terus | Worker mati → `sudo supervisorctl status`; `/health` → `queue_worker` |
| Batch/export macet | Tombol **Proses ulang** di halaman campaign (aman diulang, tidak menggandakan voucher). Export yang masih berjalan baru bisa diambil alih setelah 10 menit tanpa progres |
| "Terlalu banyak kode tidak dikenal" | Lockout tebak kode: 10 per user+IP/15 menit, 30 per user/jam |
| `database is locked` di log | Kontensi tulis; sudah ada `busy_timeout` 5 s + retry. Jika sering, lihat bagian berikut |
| ZIP batch lama hilang | Dihapus otomatis setelah 30 hari → **Export ulang** |

### Kapan pindah dari SQLite

SQLite cocok untuk penggunaan internal kecil–menengah. Ukur dulu:

```bash
php artisan voucher:benchmark --count=100     # waktu render per voucher, ukuran ZIP
```

Pertimbangkan PostgreSQL/MySQL bila sering muncul `database is locked` di log saat jam ramai, halaman
terasa lambat ketika batch besar sedang dibuat, atau butuh lebih dari satu server aplikasi. Kode
memakai query builder/Eloquent; pindah DB = ganti `DB_*`, migrasi data, dan uji ulang
`VoucherGenerationTest` di DB baru. Backup command saat ini **khusus SQLite**.

### Hardening lanjutan (belum dikerjakan)

- CSP masih mengizinkan `'unsafe-eval'` karena Alpine.js standar. Migrasi ke build `@alpinejs/csp`
  memerlukan pemindahan ekspresi kompleks di editor template & form campaign ke `Alpine.data`.
- Notifikasi otomatis (email/Slack) saat backup gagal — sekarang terlihat lewat `/health` dan log.
- Reset password lewat email (butuh mailer sungguhan).
