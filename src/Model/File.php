<?php
declare(strict_types=1);

namespace Phorum\Model;

class File
{
    public const LINK_USER     = 'user';
    public const LINK_MESSAGE  = 'message';
    public const LINK_EDITOR   = 'editor';
    public const LINK_TEMPFILE = 'tempfile';

    public int    $file_id      = 0;
    public int    $user_id      = 0;
    public string $filename     = '';
    public int    $filesize     = 0;
    /** Base64-encoded in the DB; decoded to raw bytes by FileService::retrieve(). */
    public string $file_data    = '';
    public int    $add_datetime = 0;
    public int    $message_id   = 0;
    public string $link         = '';
    public string $mime_type    = '';
    /** JSON blob decoded via FileMeta — image width/height today. */
    public ?string $meta        = null;
}
