<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Beranda portal: daftar proyek milik klien. Layout lengkap menyusul di Tahap 4. */
class OverviewController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('portal.overview', [
            'projects' => Project::query()->visibleTo($request->user())->orderByDesc('created_at')->get(),
        ]);
    }
}
