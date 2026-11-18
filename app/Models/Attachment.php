<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Number;

/**
 * A file attached to a project or a top-level task.
 *
 * @property int $id
 * @property string $attachable_type
 * @property int $attachable_id
 * @property string $title
 * @property string $name
 * @property string $path
 * @property string $mime
 * @property int $size
 * @property-read Project|Task $attachable
 * @property-read string $url
 * @property-read string $human_size
 */
#[Fillable(['title', 'name', 'path', 'mime', 'size'])]
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the streamed file URL.
     *
     * @return Attribute<string, never>
     */
    protected function url(): Attribute
    {
        return Attribute::make(get: fn (): string => route('uploads', ['path' => $this->path]));
    }

    /**
     * Determine whether the file is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    /**
     * Determine whether the file is a PDF.
     */
    public function isPdf(): bool
    {
        return $this->mime === 'application/pdf';
    }

    /**
     * Get the human-readable file size.
     *
     * @return Attribute<string, never>
     */
    protected function humanSize(): Attribute
    {
        return Attribute::make(get: fn (): string => Number::fileSize($this->size));
    }

    /**
     * Get the file-type label used by the icon column.
     */
    public function typeLabel(): string
    {
        return match (true) {
            $this->isImage() => __('Image'),
            $this->isPdf() => __('PDF'),
            default => __('Document'),
        };
    }
}
