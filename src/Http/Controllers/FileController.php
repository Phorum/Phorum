<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Core\Config;
use Phorum\Http\Controller;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\FileMapper;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\UserPermissionMapper;
use Phorum\Model\File;
use Phorum\Service\FileService;
use Phorum\Service\MimeDetector;
use Phorum\Service\PermissionService;
use Twig\Environment;

class FileController extends Controller
{
    private readonly FileMapper        $fileMapper;
    private readonly MessageMapper     $messages;
    private readonly ForumMapper       $forums;
    private readonly PermissionService $perms;
    private readonly FileService       $fileService;

    public function __construct(
        Config             $config,
        Environment        $twig,
        ?FileMapper        $fileMapper  = null,
        ?MessageMapper     $messages    = null,
        ?ForumMapper       $forums      = null,
        ?PermissionService $perms       = null,
        ?FileService       $fileService = null,
    ) {
        parent::__construct($config, $twig);
        $this->fileMapper  = $fileMapper  ?? new FileMapper();
        $this->messages    = $messages    ?? new MessageMapper();
        $this->forums      = $forums      ?? new ForumMapper();
        $this->perms       = $perms       ?? new PermissionService(new UserPermissionMapper());
        $this->fileService = $fileService ?? new FileService($this->fileMapper);
    }

    /**
     * Serve a message attachment, enforcing forum read permission.
     * URL: GET /file/{file_id}/{filename}
     */
    public function serve(Request $request): Response
    {
        $fileId = (int) ($request->tokens['file_id'] ?? 0);

        $file = $this->fileMapper->load($fileId);

        if ($file === null || $file->link !== File::LINK_MESSAGE) {
            return $this->notFound();
        }

        $msg = $this->messages->load($file->message_id);
        if ($msg === null || $msg->status === MessageMapper::STATUS_DELETED) {
            return $this->notFound();
        }

        $forum = $this->forums->load($msg->forum_id);
        if ($forum === null) {
            return $this->notFound();
        }

        if (!$this->perms->canRead($forum, Auth::user())) {
            return $this->forbidden();
        }

        $redirectUrl = phorum_api_hook('file_serve_url', $file, 'attachment');
        if (is_string($redirectUrl) && $redirectUrl !== '') {
            return $this->redirect($redirectUrl);
        }

        $rawData  = $this->fileService->retrieve($file);

        // MIME detection via finfo, falling back to extension map — always
        // re-sniffed on the actual bytes here (never the stored mime_type
        // column), since this feeds the security check right below.
        $mimeType = MimeDetector::detect($rawData, $file->filename);

        // Force download for anything that could execute in the browser:
        // HTML/script tags in the first 1 KB, or SVG (which allows inline JS)
        $forceDownload = false;
        if (
            preg_match('/<(html|script|iframe|object|embed|form)\b/i', substr($rawData, 0, 1024)) ||
            $mimeType === 'image/svg+xml'
        ) {
            $forceDownload = true;
            $mimeType      = 'application/octet-stream';
        }

        // Strip characters that would break the Content-Disposition header
        $safeName = preg_replace('/[\r\n";]/', '_', $file->filename);

        // Range support (video seeking/scrubbing) — only for content already
        // cleared as safe to serve inline; the dangerous-content path above
        // always wins regardless of any Range header on the request.
        if (!$forceDownload) {
            $rangeResponse = $this->rangeResponse($request, $rawData, $mimeType, $safeName);
            if ($rangeResponse !== null) {
                return $rangeResponse;
            }
        }

        return new Response($rawData, 200, [
            'Content-Type'           => $mimeType,
            'Content-Disposition'    => ($forceDownload ? 'attachment' : 'inline')
                                        . '; filename="' . $safeName . '"',
            'Content-Length'         => (string) strlen($rawData),
            'Accept-Ranges'          => 'bytes',
            'Cache-Control'          => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Serve a user avatar by user_id lookup.
     * URL: GET /avatar/{user_id}
     */
    public function avatar(Request $request): Response
    {
        $userId = (int) ($request->tokens['user_id'] ?? 0);

        $file = $this->fileMapper->findAvatarForUser($userId);
        if ($file === null) {
            return $this->notFound();
        }

        $redirectUrl = phorum_api_hook('file_serve_url', $file, 'inline');
        if (is_string($redirectUrl) && $redirectUrl !== '') {
            return $this->redirect($redirectUrl);
        }

        $rawData  = $this->fileService->retrieve($file);
        $mimeType = MimeDetector::detect($rawData, $file->filename);
        $safeName = preg_replace('/[\r\n";]/', '_', $file->filename);

        return new Response($rawData, 200, [
            'Content-Type'           => $mimeType,
            'Content-Disposition'    => 'inline; filename="' . $safeName . '"',
            'Content-Length'         => (string) strlen($rawData),
            'Cache-Control'          => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Build a 206 Partial Content (or 416) response for a Range request,
     * or null when there's no Range header to honor — the caller falls
     * back to its normal full-body 200 response in that case.
     *
     * Native <video> playback relies on Range requests for seeking, since
     * FileService::retrieve() already materializes the full byte string in
     * memory, slicing it here is a small, self-contained addition.
     */
    private function rangeResponse(Request $request, string $rawData, string $mimeType, string $safeName): ?Response
    {
        $rangeHeader = $request->server['HTTP_RANGE'] ?? '';
        if ($rangeHeader === '' || !preg_match('/^bytes=(\d*)-(\d*)$/', $rangeHeader, $m)) {
            return null;
        }

        $totalLength = strlen($rawData);
        $start       = $m[1] === '' ? 0 : (int) $m[1];
        $end         = $m[2] === '' ? $totalLength - 1 : (int) $m[2];
        $end         = min($end, $totalLength - 1);

        if ($totalLength === 0 || $start > $end || $start >= $totalLength) {
            return new Response('', 416, [
                'Content-Range' => "bytes */{$totalLength}",
            ]);
        }

        $chunk = substr($rawData, $start, $end - $start + 1);

        return new Response($chunk, 206, [
            'Content-Type'           => $mimeType,
            'Content-Disposition'    => 'inline; filename="' . $safeName . '"',
            'Content-Range'          => "bytes {$start}-{$end}/{$totalLength}",
            'Accept-Ranges'          => 'bytes',
            'Content-Length'         => (string) strlen($chunk),
            'Cache-Control'          => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

}
