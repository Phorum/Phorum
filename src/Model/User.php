<?php
declare(strict_types=1);

namespace Phorum\Model;

class User
{
    public int    $user_id           = 0;
    public string $username          = '';
    public string $real_name         = '';
    public string $display_name      = '';
    public string $password          = '';
    public string $password_temp     = '';
    public string $sessid_lt         = '';
    public string $sessid_st         = '';
    public int    $sessid_st_timeout = 0;
    public string $email             = '';
    public string $email_temp        = '';
    public int    $hide_email        = 1;
    public int    $active            = 0;
    public string $signature         = '';
    public int    $threaded_list     = 0;
    public int    $posts             = 0;
    public int    $admin             = 0;
    public int    $threaded_read     = 0;
    public int    $date_added        = 0;
    public int    $date_last_active  = 0;
    public int    $last_active_forum = 0;
    public int    $hide_activity     = 0;
    public int    $show_signature    = 0;
    public int    $email_notify      = 0;
    public int    $pm_email_notify   = 1;
    public int    $pm_new_count      = 0;
    public float  $tz_offset         = -99.00;
    public int    $is_dst            = 0;
    public string $user_language     = '';
    public string $user_template     = '';
    public int    $moderation_email  = 1;
    public string $settings_data     = '';
    public string $moderator_data    = '';
    public int    $force_password_change = 0;
}
