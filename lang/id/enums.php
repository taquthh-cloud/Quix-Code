<?php

return [
    'user_role' => ['admin' => 'Super Admin', 'staff' => 'Staf', 'client' => 'Klien'],
    'project_status' => [
        'lead' => 'Prospek', 'quoted' => 'Penawaran Terkirim', 'negotiating' => 'Negosiasi',
        'deal' => 'Deal', 'awaiting_dp' => 'Menunggu DP', 'active' => 'Dikerjakan', 'on_hold' => 'Ditunda',
        'awaiting_final' => 'Menunggu Pelunasan', 'handover' => 'Serah Terima',
        'warranty' => 'Masa Garansi', 'closed' => 'Selesai', 'cancelled' => 'Dibatalkan',
    ],
    'invoice_type' => ['dp' => 'Uang Muka (DP)', 'termin' => 'Termin', 'final' => 'Pelunasan'],
    'invoice_status' => [
        'draft' => 'Draf', 'sent' => 'Terkirim', 'unpaid' => 'Belum Dibayar', 'awaiting_verification' => 'Menunggu Verifikasi',
        'paid' => 'Lunas', 'cancelled' => 'Dibatalkan', 'void' => 'Divoid',
    ],
    'ticket_status' => ['open' => 'Terbuka', 'in_progress' => 'Dikerjakan', 'resolved' => 'Selesai', 'closed' => 'Ditutup'],
    'currency' => ['IDR' => 'Rupiah (IDR)', 'USD' => 'Dolar AS (USD)'],
    'quotation_status' => ['draft' => 'Draf', 'sent' => 'Terkirim', 'accepted' => 'Disetujui', 'rejected' => 'Ditolak', 'expired' => 'Kedaluwarsa', 'superseded' => 'Digantikan'],
    'payment_status' => ['pending' => 'Menunggu Verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Ditolak'],
    'milestone_status' => ['pending' => 'Belum Mulai', 'in_progress' => 'Berjalan', 'done' => 'Selesai'],
    'ticket_category' => ['revision' => 'Revisi', 'bug_report' => 'Laporan Bug'],
    'ticket_priority' => ['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi'],
    'handover_method' => ['download' => 'Unduh Langsung', 'discord_email' => 'Kirim via Discord/Email', 'managed_hosting' => 'Managed Hosting'],
    'file_visibility' => ['private' => 'Privat', 'client' => 'Klien', 'public' => 'Publik'],
];
