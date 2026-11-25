<?php

namespace App\Http\Controllers;

use App\Models\ProjectFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectFileController
{
    public function __invoke(ProjectFile $file): StreamedResponse
    {
        Gate::authorize('view', $file);

        $disk = Storage::disk($file->disk);

        abort_unless($disk->exists($file->path), 404);

        $response = $disk->download(
            $file->path,
            $file->original_name ?: basename($file->path),
        );
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
