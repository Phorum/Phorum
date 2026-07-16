<?php
declare(strict_types=1);

namespace Phorum\Model;

class Forum
{
    public int    $forum_id                 = 0;
    public string $name                     = '';
    public int    $active                   = 0;
    public string $description              = '';
    public string $template                 = '';
    public int    $folder_flag              = 0;
    public int    $parent_id               = 0;
    public int    $list_length_flat         = 0;
    public int    $list_length_threaded     = 0;
    public int    $moderation               = 0;
    public int    $threaded_list            = 0;
    public int    $threaded_read            = 0;
    public int    $float_to_top             = 0;
    public int    $check_duplicate          = 0;
    public string $allow_attachment_types   = '';
    public int    $max_attachment_size      = 0;
    public int    $max_totalattachment_size = 0;
    public int    $max_attachments          = 0;
    public int    $pub_perms                = 0;
    public int    $reg_perms                = 0;
    public int    $display_ip_address       = 1;
    public int    $allow_email_notify       = 1;
    public string $language                 = '';
    public int    $email_moderators         = 0;
    public int    $message_count            = 0;
    public int    $sticky_count             = 0;
    public int    $thread_count             = 0;
    public int    $last_post_time           = 0;
    public int    $display_order            = 0;
    public int    $read_length              = 0;
    public int    $vroot                    = 0;
    public int    $edit_post                = 1;
    public string $template_settings        = '';
    public string $forum_path              = '';
    public int    $count_views              = 0;
    public int    $count_views_per_thread   = 0;
    public int    $display_fixed            = 0;
    public int    $reverse_threading        = 0;
    public ?int   $inherit_id               = null;
    public int    $cache_version            = 0;

    /** Populated at runtime when building the display tree — not persisted. */
    public array  $children                 = [];
}
