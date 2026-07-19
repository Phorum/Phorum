<?php
declare(strict_types=1);

namespace Phorum\Service;

/**
 * Shared MIME-type detection — finfo-based content sniffing with an
 * extension-based fallback. Used by both FileService::store() (compute once
 * at upload time, for cheap display-time template branching) and
 * FileController::serve() (re-sniffed on the actual bytes at serve time as a
 * security check — see the caveat on detect() below).
 */
class MimeDetector
{
    public const MIME_MAP = [
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

    /**
     * Detect the MIME type of $data by content-sniffing, falling back to
     * $filename's extension when finfo is unavailable or inconclusive.
     *
     * Security note: when used to decide whether content is safe to serve
     * inline (as FileController::serve() does), always call this on the
     * actual retrieved bytes at serve time — never substitute a value stored
     * at upload time, since a mismatched/renamed extension or forged content
     * shouldn't be trusted from an earlier point in time.
     */
    public static function detect(string $data, string $filename): string
    {
        if (function_exists('finfo_buffer')) {
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $detected = finfo_buffer($finfo, $data);
            if ($detected !== false && $detected !== '') {
                return $detected;
            }
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return self::MIME_MAP[$ext] ?? 'application/octet-stream';
    }
}
