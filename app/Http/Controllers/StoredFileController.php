<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Project;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StoredFileController extends Controller
{
    /**
     * Stream a stored file through the application.
     */
    public function __invoke(string $path): StreamedResponse
    {
        abort_unless(preg_match('#^(attachments|avatars|photos|logos)/[A-Za-z0-9._-]+$#', $path) === 1, 404);

        if (str_starts_with($path, 'attachments/')) {
            $organization = app(CurrentOrganization::class)->get();
            $attachment = Attachment::query()->where('path', $path)->first();

            abort_if($organization === null || $attachment === null, 404);

            $attachable = $attachment->attachable;
            $ownerId = $attachable instanceof Project
                ? $attachable->owner_organization_id
                : $attachable->project->owner_organization_id;

            abort_unless($ownerId === $organization->getKey(), 404);
        }

        $disk = Storage::disk();

        abort_unless($disk->exists($path), 404);

        return Response::streamDownload(function () use ($disk, $path): void {
            $stream = $disk->readStream($path);

            if ($stream !== null) {
                fpassthru($stream);
                fclose($stream);
            }
        }, basename($path), [
            'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=86400',
        ], 'inline');
    }
}
