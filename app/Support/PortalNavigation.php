<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * T4-01. Menu portal klien. Menu global selalu ada; menu proyek (ringkasan, quotation & chat,
 * tagihan) muncul bila klien sedang berada di dalam sebuah proyek. Hanya memuat route yang sudah
 * terdaftar, jadi menu baru (tiket, berkas, akun) muncul otomatis saat tahapnya dibuat.
 */
final class PortalNavigation
{
    /**
     * @return list<array{key: string, route: string, params: array<string, string>, match: string, icon: string, label: string}>
     */
    public static function global(): array
    {
        return self::existing([
            ['key' => 'projects', 'route' => 'portal.overview', 'params' => [], 'match' => 'portal.overview', 'icon' => 'home', 'label' => 'portal.nav.projects'],
        ]);
    }

    /**
     * @return list<array{key: string, route: string, params: array<string, string>, match: string, icon: string, label: string}>
     */
    public static function forProject(string $projectId): array
    {
        $params = ['project' => $projectId];

        return self::existing([
            ['key' => 'overview', 'route' => 'portal.projects.show', 'params' => $params, 'match' => 'portal.projects.show', 'icon' => 'folder', 'label' => 'portal.nav.overview'],
            ['key' => 'negotiation', 'route' => 'portal.projects.negotiation', 'params' => $params, 'match' => 'portal.projects.negotiation', 'icon' => 'chat-bubble-left-right', 'label' => 'portal.nav.negotiation'],
            ['key' => 'invoices', 'route' => 'portal.projects.invoices', 'params' => $params, 'match' => 'portal.projects.invoices', 'icon' => 'banknotes', 'label' => 'portal.nav.invoices'],
        ]);
    }

    /**
     * Item untuk bilah bawah ponsel: Proyek, lalu menu proyek bila ada. Maksimal lima agar tiap target sentuh cukup lebar di 375px.
     *
     * @return list<array{key: string, route: string, params: array<string, string>, match: string, icon: string, label: string}>
     */
    public static function bottom(?string $projectId): array
    {
        $items = [...self::global(), ...($projectId !== null ? self::forProject($projectId) : [])];

        return array_slice($items, 0, 5);
    }

    /**
     * @param  list<array{key: string, route: string, params: array<string, string>, match: string, icon: string, label: string}>  $items
     * @return list<array{key: string, route: string, params: array<string, string>, match: string, icon: string, label: string}>
     */
    private static function existing(array $items): array
    {
        return array_values(array_filter($items, fn (array $item): bool => Route::has($item['route'])));
    }
}
