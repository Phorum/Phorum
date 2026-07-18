<?php
declare(strict_types=1);

namespace Phorum\Mod\S3Storage;

use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
use Phorum\Mapper\SettingMapper;

/**
 * Thin wrapper around the AWS S3 client. Uses the explicit
 * getCommand()/execute() form (rather than the SDK's magic per-operation
 * methods like $client->putObject([...])) throughout, since only those two
 * plus createPresignedRequest() are real declared methods on
 * S3ClientInterface — the magic methods are dispatched via __call() and
 * can't be stubbed by a plain interface mock, which matters for testing
 * this class without real AWS calls.
 *
 * Every AWS call is caught and logged rather than thrown, matching this
 * codebase's convention (see WebhookDispatcher) of never letting an
 * external-service failure break the request that triggered it.
 */
class S3StorageService
{
    private const MIME_MAP = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',
        'pdf'  => 'application/pdf',
        'txt'  => 'text/plain',
        'zip'  => 'application/zip',
        'gz'   => 'application/gzip',
        'mp3'  => 'audio/mpeg',
        'mp4'  => 'video/mp4',
        'webm' => 'video/webm',
    ];

    private ?S3ClientInterface $client = null;

    public function __construct(
        private readonly SettingMapper       $settings,
        private readonly ?S3ClientInterface  $injectedClient = null,
    ) {}

    /** The S3 object key for a given file_id — deterministic, no separate mapping table needed. */
    public function keyForFile(int $fileId): string
    {
        $prefix = trim((string) ($this->settings->getSetting('s3_key_prefix') ?? ''), '/');
        return $prefix !== '' ? "{$prefix}/{$fileId}" : (string) $fileId;
    }

    /** Best-effort content type from a filename's extension; 'application/octet-stream' if unknown. */
    public function mimeForFilename(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return self::MIME_MAP[$ext] ?? 'application/octet-stream';
    }

    /** Upload bytes to $key. Returns false (never throws) on any failure. */
    public function putObject(string $key, string $bytes, string $contentType): bool
    {
        try {
            $this->client()->execute($this->client()->getCommand('PutObject', [
                'Bucket'      => $this->bucket(),
                'Key'         => $key,
                'Body'        => $bytes,
                'ContentType' => $contentType,
            ]));
            return true;
        } catch (\Throwable $e) {
            error_log("S3Storage: putObject failed for key {$key}: {$e->getMessage()}");
            return false;
        }
    }

    /** Fetch raw bytes for $key, or null on any failure — the fallback path for non-web callers of FileService::retrieve(). */
    public function getObject(string $key): ?string
    {
        try {
            $result = $this->client()->execute($this->client()->getCommand('GetObject', [
                'Bucket' => $this->bucket(),
                'Key'    => $key,
            ]));
            return (string) $result['Body'];
        } catch (\Throwable $e) {
            error_log("S3Storage: getObject failed for key {$key}: {$e->getMessage()}");
            return null;
        }
    }

    /** Delete the object at $key. Never throws. */
    public function deleteObject(string $key): void
    {
        try {
            $this->client()->execute($this->client()->getCommand('DeleteObject', [
                'Bucket' => $this->bucket(),
                'Key'    => $key,
            ]));
        } catch (\Throwable $e) {
            error_log("S3Storage: deleteObject failed for key {$key}: {$e->getMessage()}");
        }
    }

    /**
     * A short-lived signed GET URL for $key, with the response Content-Type/
     * Content-Disposition overridden so the browser gets the right headers
     * without Phorum ever needing to store a mime_type column or fetch bytes
     * itself. Null on any failure (caller falls back to serving bytes directly).
     */
    public function presignedGetUrl(
        string $key,
        string $contentType,
        string $disposition,
        string $filename,
        int    $ttlSeconds = 300,
    ): ?string {
        try {
            $safeName = preg_replace('/[\r\n";]/', '_', $filename);
            $command  = $this->client()->getCommand('GetObject', [
                'Bucket'                     => $this->bucket(),
                'Key'                        => $key,
                'ResponseContentType'        => $contentType,
                'ResponseContentDisposition' => $disposition . '; filename="' . $safeName . '"',
            ]);
            $request = $this->client()->createPresignedRequest($command, "+{$ttlSeconds} seconds");
            return (string) $request->getUri();
        } catch (\Throwable $e) {
            error_log("S3Storage: presign failed for key {$key}: {$e->getMessage()}");
            return null;
        }
    }

    // -------------------------------------------------------------------------

    private function bucket(): string
    {
        return (string) ($this->settings->getSetting('s3_bucket') ?? '');
    }

    private function client(): S3ClientInterface
    {
        if ($this->injectedClient !== null) {
            return $this->injectedClient;
        }

        if ($this->client === null) {
            $this->client = new S3Client([
                'version'     => 'latest',
                'region'      => (string) ($this->settings->getSetting('s3_region') ?? ''),
                'credentials' => [
                    'key'    => (string) ($this->settings->getSetting('s3_access_key') ?? ''),
                    'secret' => (string) ($this->settings->getSetting('s3_secret_key') ?? ''),
                ],
            ]);
        }

        return $this->client;
    }
}
