<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers;

use Phorum\Http\Controller;
use Phorum\Http\Request;
use Phorum\Http\Response;

class ThemeController extends Controller
{
    private const ALLOWED_EXTENSIONS = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf'];

    private const MIME_TYPES = [
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
    ];

    public function asset(Request $request): Response
    {
        $theme = $request->tokens['theme'] ?? '';
        $file  = $request->tokens['file']  ?? '';

        // Validate theme name: letters, digits, hyphens, underscores only
        if (!preg_match('/^[\w-]+$/', $theme)) {
            return $this->respond('', 404);
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, strict: true)) {
            return $this->respond('', 403);
        }

        $base = ROOT_PATH . '/themes/' . $theme . '/';
        $path = realpath($base . $file);

        // Reject null (non-existent), non-files, and paths outside the theme directory
        if ($path === false || !is_file($path) || !str_starts_with($path, realpath($base) . DIRECTORY_SEPARATOR)) {
            return $this->respond('', 404);
        }

        $mime    = self::MIME_TYPES[$ext] ?? 'application/octet-stream';
        $content = (string) file_get_contents($path);
        $mtime   = (int) filemtime($path);
        $etag    = '"' . md5($content) . '"';

        $cacheHeaders = [
            'Content-Type'  => $mime,
            'Cache-Control' => 'public, max-age=86400',
            'Last-Modified' => gmdate('D, d M Y H:i:s', $mtime) . ' GMT',
            'ETag'          => $etag,
        ];

        // Honour conditional requests
        $ifNoneMatch     = $request->server['HTTP_IF_NONE_MATCH']     ?? '';
        $ifModifiedSince = $request->server['HTTP_IF_MODIFIED_SINCE'] ?? '';

        if ($ifNoneMatch === $etag ||
            ($ifModifiedSince !== '' && strtotime($ifModifiedSince) >= $mtime)) {
            return new Response('', 304, $cacheHeaders);
        }

        return new Response($content, 200, $cacheHeaders);
    }
}
