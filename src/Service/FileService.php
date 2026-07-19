<?php
declare(strict_types=1);

namespace Phorum\Service;

use Phorum\Hook\HookDispatcher;
use Phorum\Mapper\FileMapper;
use Phorum\Model\File;
use Phorum\Model\FileMeta;
use Phorum\Model\Forum;
use Phorum\Model\Message;

class FileService
{
    public function __construct(protected FileMapper $mapper) {}

    /**
     * Validate a single PHP $_FILES entry against forum attachment settings.
     * Returns an error string, or null if the file is acceptable.
     *
     * @param array{name:string,size:int,error:int,tmp_name:string} $phpFile
     */
    public function validateUpload(
        array $phpFile,
        Forum $forum,
        int   $existingCount,
        int   $existingTotalBytes
    ): ?string {
        if ($phpFile['error'] !== UPLOAD_ERR_OK) {
            return 'File upload failed (error code ' . $phpFile['error'] . ').';
        }

        if ($forum->max_attachments > 0 && ($existingCount + 1) > $forum->max_attachments) {
            return 'You may not attach more than ' . $forum->max_attachments . ' file(s) per message.';
        }

        if ($forum->max_attachment_size > 0 && $phpFile['size'] > $forum->max_attachment_size) {
            return basename($phpFile['name']) . ' exceeds the maximum attachment size of '
                . $this->formatBytes($forum->max_attachment_size) . '.';
        }

        if ($forum->max_totalattachment_size > 0
            && ($existingTotalBytes + $phpFile['size']) > $forum->max_totalattachment_size
        ) {
            return 'Total attachment size exceeds the limit of '
                . $this->formatBytes($forum->max_totalattachment_size) . '.';
        }

        if ($forum->allow_attachment_types !== '') {
            $allowed = array_map('strtolower', explode(';', $forum->allow_attachment_types));
            $ext     = strtolower(pathinfo($phpFile['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                return 'File type ".' . $ext . '" is not allowed.';
            }
        }

        return null;
    }

    /**
     * Store an uploaded file and link it to a message.
     * Fires the file_store hook so plugins can intercept storage.
     *
     * @param array{name:string,size:int,error:int,tmp_name:string} $phpFile
     */
    public function store(array $phpFile, int $userId, int $messageId): ?File
    {
        if (!is_uploaded_file($phpFile['tmp_name'])) {
            return null;
        }

        $rawData = file_get_contents($phpFile['tmp_name']);
        if ($rawData === false) {
            return null;
        }

        // Create skeleton DB record first so the plugin knows file_id
        $file               = new File();
        $file->user_id      = $userId;
        $file->filename     = basename($phpFile['name']);
        $file->filesize     = $phpFile['size'];
        $file->file_data    = '';
        $file->add_datetime = time();
        $file->message_id   = $messageId;
        $file->link         = File::LINK_MESSAGE;
        $file->mime_type    = MimeDetector::detect($rawData, $file->filename);
        $file->meta         = FileMeta::fromImageData($rawData)?->encode();

        $this->mapper->save($file);

        // Build the hook payload (raw bytes, not base64)
        $payload = [
            'file_id'      => $file->file_id,
            'user_id'      => $userId,
            'filename'     => $file->filename,
            'filesize'     => $file->filesize,
            'file_data'    => $rawData,
            'message_id'   => $messageId,
            'link'         => File::LINK_MESSAGE,
            'add_datetime' => $file->add_datetime,
        ];

        $payload = HookDispatcher::getInstance()->dispatch('file_store', $payload);

        if ($payload === false) {
            $this->mapper->delete($file->file_id);
            return null;
        }

        // If file_data is still populated the plugin wants DB storage
        if ((string) $payload['file_data'] !== '') {
            $file->file_data = base64_encode($payload['file_data']);
            $this->mapper->save($file);
        }

        return $file;
    }

    /**
     * Retrieve raw file bytes for a File.
     * Fires the file_retrieve hook; falls back to base64-decoding from DB.
     */
    public function retrieve(File $file): string
    {
        $payload = [
            'file_id'   => $file->file_id,
            'filename'  => $file->filename,
            'filesize'  => $file->filesize,
            'file_data' => null,
            'mime_type' => null,
            'result'    => 0,
        ];

        [$payload] = HookDispatcher::getInstance()->dispatch('file_retrieve', [$payload, 0]);

        if ($payload['file_data'] !== null) {
            return (string) $payload['file_data'];
        }

        return base64_decode($file->file_data);
    }

    /**
     * Delete a file, firing the file_delete hook for plugin storage cleanup.
     */
    public function delete(File $file): void
    {
        HookDispatcher::getInstance()->dispatch('file_delete', $file->file_id);
        $this->mapper->delete($file->file_id);
    }

    /**
     * Load all attachments for a message.
     *
     * @return File[]
     */
    public function getAttachments(int $messageId): array
    {
        return $this->mapper->findByMessage($messageId);
    }

    /**
     * Populate Message::$attachments for every message in the array in one query.
     *
     * @param Message[] $messages
     */
    public function hydrateMessages(array $messages): void
    {
        if (empty($messages)) {
            return;
        }

        $ids = array_map(fn($m) => $m->message_id, $messages);
        $map = $this->mapper->findByMessages($ids);

        foreach ($messages as $msg) {
            $msg->attachments = $map[$msg->message_id] ?? [];
        }
    }

    /**
     * Delete all attachments for a message (used on message/thread delete).
     */
    public function deleteForMessage(int $messageId): void
    {
        foreach ($this->mapper->findByMessage($messageId) as $file) {
            $this->delete($file);
        }
    }

    /**
     * Validate an avatar upload against allowed types and a max byte size.
     * Returns an error string, or null if the file is acceptable.
     *
     * @param array{name:string,size:int,error:int,tmp_name:string} $phpFile
     */
    public function validateAvatarUpload(array $phpFile, int $maxBytes): ?string
    {
        if ($phpFile['error'] !== UPLOAD_ERR_OK) {
            return 'Avatar upload failed (error code ' . $phpFile['error'] . ').';
        }

        if ($maxBytes > 0 && $phpFile['size'] > $maxBytes) {
            return 'Avatar must be smaller than ' . $this->formatBytes($maxBytes) . '.';
        }

        $ext = strtolower(pathinfo($phpFile['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return 'Avatar must be a JPG, PNG, GIF, or WebP image.';
        }

        return null;
    }

    /**
     * Store an avatar image for a user, replacing any prior avatar.
     * Fires the file_store hook so plugins can intercept storage.
     *
     * @param array{name:string,size:int,error:int,tmp_name:string} $phpFile
     */
    public function storeAvatar(array $phpFile, int $userId): ?File
    {
        if (!is_uploaded_file($phpFile['tmp_name'])) {
            return null;
        }

        $rawData = file_get_contents($phpFile['tmp_name']);
        if ($rawData === false) {
            return null;
        }

        $file               = new File();
        $file->user_id      = $userId;
        $file->filename     = basename($phpFile['name']);
        $file->filesize     = $phpFile['size'];
        $file->file_data    = '';
        $file->add_datetime = time();
        $file->message_id   = 0;
        $file->link         = File::LINK_USER;

        $this->mapper->save($file);

        $payload = [
            'file_id'      => $file->file_id,
            'user_id'      => $userId,
            'filename'     => $file->filename,
            'filesize'     => $file->filesize,
            'file_data'    => $rawData,
            'message_id'   => 0,
            'link'         => File::LINK_USER,
            'add_datetime' => $file->add_datetime,
        ];

        $payload = HookDispatcher::getInstance()->dispatch('file_store', $payload);

        if ($payload === false) {
            $this->mapper->delete($file->file_id);
            return null;
        }

        if ((string) $payload['file_data'] !== '') {
            $file->file_data = base64_encode($payload['file_data']);
            $this->mapper->save($file);
        }

        return $file;
    }

    /**
     * Delete the avatar for a user (if one exists).
     */
    public function deleteAvatarForUser(int $userId): void
    {
        $existing = $this->mapper->findAvatarForUser($userId);
        if ($existing !== null) {
            $this->delete($existing);
        }
    }

    /**
     * Purge orphaned editor-link files older than $maxAgeSeconds.
     * Fires file_purge_stale so plugins can add their own stale files.
     */
    public function purgeStale(int $maxAgeSeconds = 86400): void
    {
        $cutoff     = time() - $maxAgeSeconds;
        $staleFiles = $this->mapper->findStaleEditorFiles($cutoff);

        // Allow plugins to extend the stale list
        $staleFiles = HookDispatcher::getInstance()->dispatch('file_purge_stale', $staleFiles);

        foreach ((array) $staleFiles as $file) {
            $this->delete($file);
        }
    }

    // -------------------------------------------------------------------------

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
