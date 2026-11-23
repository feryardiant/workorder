<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Models\Attachment;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class DownloadAttachment
{
    /**
     * Zip the given attachments under the project name.
     *
     * @param  Collection<int, Attachment>  $attachments
     */
    public function handle(Collection $attachments, Project $project, string $path): string
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the attachment archive.');
        }

        $disk = Storage::disk();
        $used = [];

        foreach ($attachments as $attachment) {
            $contents = $disk->exists($attachment->path) ? $disk->get($attachment->path) : null;

            if ($contents === null) {
                continue;
            }

            $zip->addFromString($this->uniqueName($used, basename($attachment->name)), $contents);
        }

        $zip->close();

        return $this->filename($project->title).'.zip';
    }

    /**
     * Give duplicates a numbered suffix so nothing overwrites.
     *
     * @param  array<string, int>  $used
     */
    private function uniqueName(array &$used, string $name): string
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = $extension === '' ? $name : substr($name, 0, -\strlen($extension) - 1);

        $used[$name] = ($used[$name] ?? 0) + 1;

        return $used[$name] === 1
            ? $name
            : $base.' ('.$used[$name].')'.($extension === '' ? '' : '.'.$extension);
    }

    /**
     * Strip everything except letters, numbers, spaces and dashes.
     */
    private function filename(string $title): string
    {
        $name = trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Za-z0-9 -]/', '', $title) ?? '') ?? '');

        return $name === '' ? 'Attachments' : $name;
    }
}
