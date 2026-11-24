<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $term = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', 'all');
        abort_unless(in_array($type, ['all', 'projects', 'invoices', 'clients'], true), 404);
        $like = '%'.addcslashes($term, '\\%_').'%';

        $projects = collect();
        $invoices = collect();
        $clients = collect();

        if ($term !== '' && in_array($type, ['all', 'projects'], true)) {
            $projects = Project::query()
                ->with('client:id,name')
                ->where(fn ($query) => $query
                    ->where('title', 'like', $like)
                    ->orWhereHas('client', fn ($client) => $client->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)))
                ->latest('created_at')
                ->limit(10)
                ->get();
        }

        if ($term !== '' && in_array($type, ['all', 'invoices'], true)) {
            $invoices = Invoice::query()
                ->with(['project:id,title,client_id', 'project.client:id,name'])
                ->where(fn ($query) => $query
                    ->where('invoice_number', 'like', $like)
                    ->orWhereHas('project', fn ($project) => $project->where('title', 'like', $like)
                        ->orWhereHas('client', fn ($client) => $client->where('name', 'like', $like))))
                ->latest('created_at')
                ->limit(10)
                ->get();
        }

        if ($term !== '' && in_array($type, ['all', 'clients'], true)) {
            $clients = User::query()
                ->where('role', UserRole::Client->value)
                ->where(fn ($query) => $query
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('whatsapp', 'like', $like))
                ->orderBy('name')
                ->limit(10)
                ->get(['id', 'name', 'email', 'whatsapp']);
        }

        return view('admin.search', compact('term', 'type', 'projects', 'invoices', 'clients'));
    }
}
