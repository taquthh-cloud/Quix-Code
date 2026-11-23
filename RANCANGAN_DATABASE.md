# Rancangan Database — Quix Code (Tahap 1)

Sumber: Master Blueprint & PRD v1.0 (Bagian 5, 6, 7). Stack: Laravel 13, MySQL, Redis.

## 1. Prinsip Desain

| Prinsip | Penerapan |
|---|---|
| ID tidak berurutan | ULID untuk tabel yang muncul di URL klien: `projects`, `quotations`, `invoices`, `revision_tickets`, `case_studies`, `files`. Tabel internal memakai `id` bigint biasa. |
| Uang | Integer satuan terkecil (`unsignedBigInteger`): rupiah untuk IDR, sen untuk USD. Tidak ada float. Format lewat satu helper/Enum `Currency`. |
| Mata uang | `char(3)` per proyek, dikunci saat quotation disetujui; quotation, invoice, payment mengikuti mata uang proyek. |
| Status | Disimpan sebagai `string`, bukan MySQL `ENUM`, lalu di-cast ke PHP Enum di model. Menambah status tidak perlu migrasi. |
| Soft delete | `projects`, `invoices`, `case_studies`. |
| Waktu | Semua `timestamp` disimpan UTC (default Laravel). |
| Data sensitif | `webhook_secret` dan `project_handovers.payload` memakai cast `encrypted`. |
| Dua bahasa | Hanya `case_studies` yang menyimpan teks per bahasa (JSON `{"id","en"}`). Chat dan tiket tidak diterjemahkan. |

## 2. Urutan Migrasi

Nomor file sudah berurutan dari independen ke dependen:

1. `add_quix_columns_to_users_table` (mengubah tabel `users` bawaan Laravel)
2. `projects`
3. `project_assignments`
4. `project_milestones`
5. `quotations`
6. `quotation_items`
7. `order_chats`
8. `invoices`
9. `payments`
10. `revision_tickets`
11. `ticket_comments`
12. `project_git_commits`
13. `webhook_deliveries`
14. `project_handovers`
15. `case_studies`
16. `files`
17. `notifications_log`
18. `activity_logs`
19. `exchange_rates`

Salin ke `database/migrations/`, lalu `php artisan migrate`. Karena `users` sudah dibuat migrasi bawaan Laravel, file 1 hanya menambah kolom, jadi migrasi bawaan tidak perlu diubah.

## 3. Diagram Relasi (ringkas)

```
users (admin/staff/client)
 ├─< projects (client_id)
 │    ├─< project_assignments >─ users (staff_id)
 │    ├─< project_milestones
 │    ├─< quotations ─< quotation_items
 │    ├─< order_chats
 │    ├─< invoices ─< payments
 │    ├─< revision_tickets ─< ticket_comments
 │    ├─< project_git_commits
 │    ├─< webhook_deliveries
 │    ├─< files
 │    ├── project_handovers (1:1)
 │    └── case_studies (1:1)
 ├─< notifications_log
 └─< activity_logs
exchange_rates (mandiri)
```

## 4. Penjelasan Per Tabel

### 4.1 users (tambahan kolom)
| Kolom | Tipe | Keterangan |
|---|---|---|
| role | string(20), index | `admin`, `staff`, `client` (Enum `UserRole`), default `client` |
| whatsapp | string(20) null | Nomor tujuan notifikasi |
| whatsapp_opt_in | boolean | **Tambahan**: blueprint mewajibkan WA hanya untuk klien yang setuju, jadi persetujuan perlu disimpan |
| locale | string(5) | `id` / `en`, dipakai middleware bahasa |
| last_login_at | timestamp null | |

### 4.2 projects
Inti sistem. Semua tabel lain bergantung padanya.
- `client_id` → users (restrict: klien tidak bisa dihapus selama punya proyek)
- `status` → Enum `ProjectStatus`: lead, quoted, negotiating, deal, awaiting_dp, active, awaiting_final, handover, warranty, closed, cancelled. Index karena sering difilter.
- `progress_percent` tinyint 0–100, diisi webhook atau manual admin
- `github_repo` nama repo; `webhook_secret` (**tambahan**) secret HMAC untuk verifikasi `X-Hub-Signature-256`, dienkripsi
- `currency`, `budget_reference` (dari form intake), `final_price` (setelah deal)
- `warranty_ends_at`, `completed_at`
- Index: `status`, `(client_id, status)`

### 4.3 project_assignments
Penghubung proyek ↔ staf. Unik `(project_id, staff_id)`. Policy staf: proyek hanya terlihat bila ada baris di sini. Index `staff_id` untuk query "proyek saya".

### 4.4 project_milestones
`target_progress` (0–100) adalah ambang: saat `projects.progress_percent >= target_progress`, job menandai milestone `done` dan mengisi `completed_at`. Admin/staf bisa mengoreksi manual. Status: pending, in_progress, done. `assigned_to` null jika staf dihapus.

### 4.5 quotations & 4.6 quotation_items
- Satu proyek bisa punya banyak versi penawaran (`version`), unik per proyek.
- Rumus: `subtotal = Σ items.amount` (tiap item `hours × rate`), lalu `total = (subtotal + operational_cost) × (1 + margin_percent/100)`. Blueprint hanya menulis "Waktu × Rate + Operasional + Margin"; di sini margin diasumsikan persen dari subtotal + operasional. Ubah bila definisimu berbeda.
- `accepted_at` + `accepted_ip` (string 45 agar muat IPv6) = bukti persetujuan.
- `sort_order` pada item **ditambahkan** agar urutan baris penawaran tetap.
- Staf tidak boleh membaca kedua tabel ini, jadi batasi di Policy dan query scope, jangan cuma di tampilan.

### 4.7 order_chats
Chat negosiasi admin ↔ klien. Hanya `created_at` (pesan tidak diedit). `read_at` untuk penanda sudah dibaca. Index `(project_id, created_at)` untuk polling `wire:poll`. Tidak boleh terlihat staf.

### 4.8 invoices
- `invoice_number` unik, mis. `INV-2026-0001`
- `type`: dp, termin, final. Skema 30-40-30 menghasilkan dp + termin + final; 50-50 menghasilkan dp + final.
- `status` (Enum `InvoiceStatus`), saran nilai: `unpaid`, `pending_verification`, `paid`, `overdue`, `cancelled`
- `currency` harus sama dengan proyek (validasi di aplikasi)
- FK `restrict` + soft delete: invoice tidak boleh hilang bersama proyek
- Policy unduhan arsip memeriksa invoice `final` berstatus `paid`

### 4.9 payments
Satu invoice dapat punya banyak percobaan bayar (mis. bukti ditolak, lalu unggah ulang). `proof_path` di disk privat. Alur: klien unggah → `pending`; admin verifikasi → `verified` (isi `verified_by`, `verified_at`, invoice menjadi `paid`) atau `rejected` (isi `note`). `note` **ditambahkan** untuk alasan penolakan.

### 4.10 revision_tickets & 4.11 ticket_comments
- `category`: revision / bug_report (bug_report gratis dalam 30 hari garansi)
- `priority`: low / medium / high
- `status` (Enum `TicketStatus`), saran: open, in_progress, review, resolved
- `sort_order` untuk Kanban SortableJS; index `(status, sort_order)` untuk Global Kanban
- Komentar: `attachment_path` untuk screenshot. Staf boleh berkomentar di sini untuk klarifikasi teknis.

### 4.12 project_git_commits
- Unik `(project_id, commit_hash)` mencegah commit ganda
- `commit_message` disimpan setelah tag `[progress:X%]` dihapus, HTML di-escape, panjang dibatasi
- `progress_recorded` = nilai X dari tag (nullable bila commit tanpa tag)
- `author_email` hanya internal, **tidak** ditampilkan ke klien; `author_id` (**tambahan**) memetakan email ke staf agar timeline bisa menampilkan nama staf
- Hash, nama branch, email tidak boleh keluar di resource klien

### 4.13 webhook_deliveries
- `delivery_id` unik = `X-GitHub-Delivery` → pengiriman ulang tidak diproses dua kali (idempotensi)
- `status`: received, processed, failed, ignored
- `payload` JSON; `error` untuk debugging; `project_id` nullable karena delivery bisa tiba sebelum repo cocok proyek mana pun
- Alur: validasi HMAC → simpan → balas 202 → job memproses

### 4.14 project_handovers
Satu per proyek (`unique project_id`). `delivery_method`: download, discord_email, managed_hosting. `payload` terenkripsi. `expires_at`, `download_count`, `downloaded_at` untuk signed URL dan audit unduhan.

### 4.15 case_studies
Satu per proyek (unik). `title` dan `summary` JSON per bahasa, `tech_stack` JSON array, `slug` unik untuk URL publik `/id/works/{slug}` dan `/en/works/{slug}`. Draft sampai `is_published = true`. Soft delete + restrict.

### 4.16 files
Berkas unggahan terpusat. Disk privat default, diakses lewat route terotorisasi. `visibility`: private (admin), client (klien proyek), public. `size` dalam byte.

### 4.17 notifications_log
Jejak tiap notifikasi: channel (whatsapp/email), template, status (queued/sent/failed), `error`. Dipakai untuk fallback email bila WA gagal.

### 4.18 activity_logs
Audit untuk perubahan harga, status, invoice. `subject_type` + `subject_id` dibuat **string** (bukan `morphs`) karena subjek bisa bigint atau ULID. `user_id` null berarti dilakukan sistem/webhook. `properties` JSON berisi `old` dan `new`.

### 4.19 exchange_rates
Hanya untuk estimasi di pemilih mata uang situs publik. Angka mengikat selalu dalam mata uang proyek. `rate` decimal(18,8). Ambil baris `fetched_at` terbaru per pasangan.

## 5. Ringkasan Tambahan di Luar Blueprint

Semua kolom blueprint dipertahankan. Tambahan kecil, hapus bila tidak mau:

| Kolom | Alasan |
|---|---|
| users.whatsapp_opt_in | Persetujuan WA wajib per blueprint |
| projects.webhook_secret | Secret HMAC per repo |
| quotation_items.sort_order | Urutan baris penawaran |
| payments.note | Alasan penolakan bukti bayar |
| project_git_commits.author_id | Menampilkan nama staf di timeline |
| timestamps (created_at/updated_at) | Laravel standar pada hampir semua tabel |

## 6. Catatan Implementasi di Model

```php
// Contoh casts
Project:          status => ProjectStatus::class, currency => Currency::class,
                  webhook_secret => 'encrypted'
Invoice:          type => InvoiceType::class, status => InvoiceStatus::class
RevisionTicket:   status => TicketStatus::class
ProjectHandover:  payload => 'encrypted'
CaseStudy:        title/summary/tech_stack => 'array', is_published => 'boolean'
```

- Model ber-ULID: gunakan trait `HasUlids`.
- Tambahkan `SoftDeletes` di Project, Invoice, CaseStudy.
- Hindari N+1: `with()` pada daftar proyek, invoice, tiket.
- Tes yang disarankan: Policy (klien A tidak bisa akses data klien B, staf tidak melihat harga), transisi `ProjectStatus`, helper uang IDR/USD, hitungan quotation.
