<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Project;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofController extends Controller
{
    public function __invoke(int $payment): StreamedResponse
    {
        $user = auth()->user();
        $record = Payment::query()
            ->whereHas('invoice', function ($invoice) use ($user): void {
                $invoice->whereIn('project_id', Project::visibleIds($user));
            })
            ->findOrFail($payment);

        $this->authorize('view', $record);
        $path = $record->proof_path;
        abort_unless(is_string($path)
            && str_starts_with($path, 'payments/')
            && ! str_contains($path, '..')
            && preg_match('#^payments/[A-Za-z0-9/_-]+\\.[A-Za-z0-9]{2,8}$#D', $path) === 1, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        $response = $disk->download($path, basename($path));
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
