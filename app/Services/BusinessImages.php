<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The logo and the signature that print at the head and foot of a bill.
 *
 * Both columns have existed since the first migration and both are published by
 * BusinessResource, but until now only a phone backup could fill them: nothing
 * in the panel or the API could upload one.
 *
 * Files follow the convention the importer already established — the `public`
 * disk, under business_images/, named by a fresh uuid — so an image that
 * arrived in a backup and one uploaded here are indistinguishable afterwards.
 *
 * They are not served from a public URL, though. A logo is harmless, but a
 * signature is the owner's actual signature, and a guessable link to it is not
 * something to hand out. Both go out through an authorised route instead, which
 * is why nothing here calls Storage::url().
 */
class BusinessImages
{
    public const FIELDS = ['logo_path', 'signature_path'];

    private const DIRECTORY = 'business_images';

    /** Replaces one of the two images, and drops the file it replaced. */
    public function store(Business $business, string $field, UploadedFile $file): Business
    {
        $this->guardField($field);

        // The extension is guessed from the file's own bytes, not from the name
        // the browser sent, which is the uploader's to choose.
        $filename = Str::uuid().'.'.($file->extension() ?: 'jpg');
        $this->disk()->putFileAs(self::DIRECTORY, $file, $filename);

        $previous = $business->{$field};
        $business->update([$field => self::DIRECTORY.'/'.$filename]);

        $this->forget($previous);

        return $business;
    }

    public function remove(Business $business, string $field): Business
    {
        $this->guardField($field);

        $previous = $business->{$field};
        $business->update([$field => null]);

        $this->forget($previous);

        return $business;
    }

    /** The bytes and mime type, or null if the business has no such image. */
    public function read(Business $business, string $field): ?array
    {
        $this->guardField($field);

        $path = $business->{$field};

        if (! $path || ! $this->disk()->exists($path)) {
            return null;
        }

        return [
            'contents' => $this->disk()->get($path),
            'mime' => $this->disk()->mimeType($path) ?: 'application/octet-stream',
        ];
    }

    /**
     * An image that arrived in a backup can be referenced by more than one
     * business, because the importer maps one file per path in the payload. So
     * a file is only deleted once nothing else points at it.
     */
    private function forget(?string $path): void
    {
        if (! $path) {
            return;
        }

        $stillUsed = Business::withTrashed()
            ->where(fn ($q) => $q->where('logo_path', $path)->orWhere('signature_path', $path))
            ->exists();

        if (! $stillUsed && $this->disk()->exists($path)) {
            $this->disk()->delete($path);
        }
    }

    private function guardField(string $field): void
    {
        if (! in_array($field, self::FIELDS, true)) {
            throw new \InvalidArgumentException("{$field} is not a business image.");
        }
    }

    private function disk()
    {
        return Storage::disk('public');
    }
}
