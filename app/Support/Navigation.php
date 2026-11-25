<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Menu area kerja internal (Bagian 4 blueprint). Setiap menu punya peran yang boleh melihatnya.
 * Menu hanya tampil bila route-nya sudah ada, jadi menu baru muncul otomatis saat tahapnya dibuat.
 */
class Navigation
{
    /**
     * @return list<array{key: string, route: string, match: string, icon: string, label: string, roles: list<UserRole>}>
     */
    public static function definitions(): array
    {
        $internal = [UserRole::Admin, UserRole::Staff];
        $adminOnly = [UserRole::Admin];

        return [
            ['key' => 'dashboard', 'route' => 'admin.dashboard', 'match' => 'admin.dashboard', 'icon' => 'home', 'label' => 'admin.nav.command_center', 'roles' => $internal],
            ['key' => 'search', 'route' => 'admin.search', 'match' => 'admin.search', 'icon' => 'magnifying-glass', 'label' => 'admin.search_page.title', 'roles' => $adminOnly],
            ['key' => 'leads', 'route' => 'admin.leads.index', 'match' => 'admin.leads.*', 'icon' => 'inbox', 'label' => 'admin.nav.leads', 'roles' => $adminOnly],
            ['key' => 'projects', 'route' => 'admin.projects.index', 'match' => 'admin.projects.*', 'icon' => 'folder', 'label' => 'admin.nav.projects', 'roles' => $internal],
            ['key' => 'kanban', 'route' => 'admin.kanban', 'match' => 'admin.kanban', 'icon' => 'view-columns', 'label' => 'admin.nav.kanban', 'roles' => $internal],
            ['key' => 'billing', 'route' => 'admin.billing.index', 'match' => 'admin.billing.*', 'icon' => 'banknotes', 'label' => 'admin.nav.billing', 'roles' => $adminOnly],
            ['key' => 'portfolio', 'route' => 'admin.portfolio.index', 'match' => 'admin.portfolio.*', 'icon' => 'star', 'label' => 'admin.nav.portfolio', 'roles' => $adminOnly],
            ['key' => 'team', 'route' => 'admin.team.index', 'match' => 'admin.team.*', 'icon' => 'users', 'label' => 'admin.nav.team', 'roles' => $adminOnly],
            ['key' => 'activity', 'route' => 'admin.activity', 'match' => 'admin.activity', 'icon' => 'clipboard-document-list', 'label' => 'admin.nav.activity', 'roles' => $adminOnly],
        ];
    }

    /** Menu yang boleh dilihat peran user (tanpa memeriksa route). */
    public static function itemsFor(User $user): array
    {
        return array_values(array_filter(
            self::definitions(),
            fn (array $item) => in_array($user->role, $item['roles'], true),
        ));
    }

    /** Menu untuk sidebar: sesuai peran DAN route-nya sudah terdaftar. */
    public static function sidebarFor(User $user): array
    {
        return array_values(array_filter(
            self::itemsFor($user),
            fn (array $item) => Route::has($item['route']),
        ));
    }
}
