<?php
declare(strict_types=1);

namespace Phorum\Mod\S3Storage;

use Phorum\Hook\HookDispatcher;
use Phorum\Model\File;

/**
 * Registers the hook callbacks that back FileService's storage with S3
 * instead of the default DB blob. Kept separate from s3storage.php (the
 * module's boot file) so tests can call register() directly with an
 * injected S3StorageService and HookDispatcher, exercising the real wiring
 * rather than a hand-copied duplicate — mirrors mods/webhooks/WebhookHooks.php.
 */
class S3StorageHooks
{
    public static function register(S3StorageService $s3, ?HookDispatcher $hooks = null): void
    {
        $hooks ??= HookDispatcher::getInstance();

        // Upload bytes to S3; clearing file_data tells FileService::store()
        // to skip its default base64-into-the-DB fallback. Returning false
        // rejects the upload outright (FileService deletes the skeleton row).
        $hooks->register('file_store', static function (array $payload) use ($s3) {
            $key       = $s3->keyForFile((int) $payload['file_id']);
            $mimeType  = $s3->mimeForFilename((string) $payload['filename']);
            $succeeded = $s3->putObject($key, (string) $payload['file_data'], $mimeType);

            if (!$succeeded) {
                return false;
            }

            $payload['file_data'] = '';
            return $payload;
        });

        // Byte-fallback path for any non-web caller of FileService::retrieve().
        // Must return a 2-element array with the payload at index 0 — an
        // existing core quirk (see FileService::retrieve()'s destructuring),
        // not something introduced here.
        $hooks->register('file_retrieve', static function (array $data) use ($s3): array {
            [$payload] = $data;
            $bytes = $s3->getObject($s3->keyForFile((int) $payload['file_id']));
            if ($bytes !== null) {
                $payload['file_data'] = $bytes;
            }
            return [$payload, 0];
        });

        // Return value is ignored by FileService::delete() — this is a
        // pure side effect. Safe to call even for a file_id that was never
        // stored in S3 (e.g. uploaded before the module was enabled).
        $hooks->register('file_delete', static function (int $fileId) use ($s3): void {
            $s3->deleteObject($s3->keyForFile($fileId));
        });

        // FileController dispatches this right after its permission checks
        // and before FileService::retrieve() — returning a URL string here
        // makes it redirect instead of streaming bytes; returning null falls
        // through to the unchanged default behavior.
        $hooks->register('file_serve_url', static function (File $file, string $disposition) use ($s3): ?string {
            $key  = $s3->keyForFile($file->file_id);
            $mime = $s3->mimeForFilename($file->filename);
            return $s3->presignedGetUrl($key, $mime, $disposition, $file->filename);
        });
    }
}
