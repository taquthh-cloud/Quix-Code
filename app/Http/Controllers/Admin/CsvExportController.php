<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportController extends Controller
{
    public function __invoke(Request $request, string $type): StreamedResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless(in_array($type, ['projects', 'invoices', 'clients'], true), 404);

        $term = trim((string) $request->query('q', ''));
        $projectStatus = ProjectStatus::tryFrom((string) $request->query('status', ''));
        $invoiceStatus = InvoiceStatus::tryFrom((string) $request->query('status', ''));
        $like = '%'.addcslashes($term, '\\%_').'%';

        return response()->streamDownload(function () use ($type, $term, $like, $projectStatus, $invoiceStatus): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");

            match ($type) {
                'projects' => $this->writeRows($output, [
                    'Project', 'Client', 'Status', 'Progress', 'Currency', 'Reference budget', 'Final price', 'Created at',
                ], DB::table('projects')
                    ->join('users as clients', 'clients.id', '=', 'projects.client_id')
                    ->whereNull('projects.deleted_at')
                    ->when($projectStatus, fn (Builder $query) => $query->where('projects.status', $projectStatus->value))
                    ->when($term !== '', fn (Builder $query) => $query->where(function (Builder $search) use ($like): void {
                        $search->where('projects.title', 'like', $like)
                            ->orWhere('clients.name', 'like', $like)
                            ->orWhere('clients.email', 'like', $like);
                    }))
                    ->orderByDesc('projects.created_at')
                    ->select([
                        'projects.title', 'clients.name as client', 'projects.status', 'projects.progress_percent',
                        'projects.currency', 'projects.budget_reference', 'projects.final_price', 'projects.created_at',
                    ])
                    ->cursor()),
                'invoices' => $this->writeRows($output, [
                    'Invoice number', 'Project', 'Client', 'Type', 'Status', 'Currency', 'Amount', 'Paid amount', 'Due date',
                ], DB::table('invoices')
                    ->join('projects', 'projects.id', '=', 'invoices.project_id')
                    ->join('users as clients', 'clients.id', '=', 'projects.client_id')
                    ->whereNull('projects.deleted_at')
                    ->when($invoiceStatus, fn (Builder $query) => $query->where('invoices.status', $invoiceStatus->value))
                    ->when($term !== '', fn (Builder $query) => $query->where(function (Builder $search) use ($like): void {
                        $search->where('invoices.invoice_number', 'like', $like)
                            ->orWhere('projects.title', 'like', $like)
                            ->orWhere('clients.name', 'like', $like);
                    }))
                    ->orderByDesc('invoices.created_at')
                    ->select([
                        'invoices.invoice_number', 'projects.title as project', 'clients.name as client',
                        'invoices.type', 'invoices.status', 'invoices.currency', 'invoices.amount',
                        'invoices.paid_amount', 'invoices.due_date',
                    ])
                    ->cursor()),
                'clients' => $this->writeRows($output, [
                    'Name', 'Email', 'WhatsApp', 'WhatsApp opt-in', 'Created at',
                ], DB::table('users')
                    ->where('role', 'client')
                    ->whereNull('deleted_at')
                    ->when($term !== '', fn (Builder $query) => $query->where(function (Builder $search) use ($like): void {
                        $search->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('whatsapp', 'like', $like);
                    }))
                    ->orderBy('name')
                    ->select(['name', 'email', 'whatsapp', 'whatsapp_opt_in', 'created_at'])
                    ->cursor()),
            };

            fclose($output);
        }, "quix-{$type}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** @param resource $output
     * @param  list<string>  $headers
     * @param  iterable<object>  $rows
     */
    private function writeRows($output, array $headers, iterable $rows): bool
    {
        fputcsv($output, $headers, ',', '"', '\\');

        foreach ($rows as $row) {
            $cells = array_map(fn (mixed $value): mixed => $this->spreadsheetSafe($value), array_values((array) $row));
            fputcsv($output, $cells, ',', '"', '\\');
        }

        return true;
    }

    private function spreadsheetSafe(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_match('/^[\\s]*[=+\\-@]/u', $value) === 1 ? "'{$value}" : $value;
    }
}
