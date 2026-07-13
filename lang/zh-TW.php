<?php
declare(strict_types=1);

/**
 * Traditional Chinese (Taiwan) translations for Phorum.
 * Machine-translated — please review with a native speaker.
 */
return [

    // Metadata (not rendered as UI text)
    '_name' => '繁體中文',

    // -------------------------------------------------------------------------
    // Navigation
    // -------------------------------------------------------------------------
    'nav.forum_index'   => '討論區首頁',
    'nav.search'        => '搜尋',
    'nav.messages'      => '私訊',
    'nav.settings'      => '設定',
    'nav.log_out'       => '登出',
    'nav.log_in'        => '登入',
    'nav.register'      => '註冊',
    'nav.powered_by'    => '由 Phorum 驅動',
    'nav.menu'          => '選單',

    // -------------------------------------------------------------------------
    // Forum list (index page)
    // -------------------------------------------------------------------------
    'forum_list.no_forums'   => '尚未建立任何討論區。',
    'forum_list.col_forum'   => '討論區',
    'forum_list.col_posts'   => '文章數',
    'forum_list.col_threads' => '主題數',
    'forum_list.col_last'    => '最新文章',

    // -------------------------------------------------------------------------
    // Forum (thread listing)
    // -------------------------------------------------------------------------
    'forum.new_thread'       => '發起新主題',
    'forum.no_threads'       => '尚無主題。',
    'forum.start_one'        => '來建立一個吧。',
    'forum.col_subject'      => '主題',
    'forum.col_author'       => '作者',
    'forum.col_replies'      => '回覆數',
    'forum.col_posts'        => '文章數',
    'forum.col_last_post'    => '最新文章',
    'forum.sticky'           => '置頂',
    'forum.closed'           => '已關閉',
    'forum.by'               => '由',
    'forum.new'              => '新',
    'forum.mark_read'        => '全部標為已讀',

    // -------------------------------------------------------------------------
    // Thread view
    // -------------------------------------------------------------------------
    'thread.reply'           => '回覆',
    'thread.follow'          => '追蹤',
    'thread.following'       => '已追蹤',
    'thread.reopen'          => '重新開放',
    'thread.close'           => '關閉',
    'thread.move'            => '移動',
    'thread.delete'          => '刪除主題',

    // -------------------------------------------------------------------------
    // Individual message
    // -------------------------------------------------------------------------
    'message.awaiting_approval' => '等待審核',
    'message.reply'             => '回覆',
    'message.edit'              => '編輯',
    'message.edit_title'        => '編輯訊息',
    'message.save_edit'         => '儲存變更',
    'message.edited_note'       => '已編輯',
    'message.changes'           => 'Changes',
    'message.changes_title'     => 'Edit History',
    'message.changes_edit_n'    => 'Edit #{n}',
    'message.changes_by'        => 'by',
    'message.changes_subject'   => 'Subject',
    'message.changes_body'      => 'Message',
    'message.changes_back'      => 'Back to thread',
    'message.approve'           => '核准',
    'message.delete'            => '刪除',

    // -------------------------------------------------------------------------
    // Post / reply form
    // -------------------------------------------------------------------------
    'post.new_thread'        => '發起新主題',
    'post.reply_to'          => '回覆：{subject}',
    'post.reply'             => '回覆',
    'post.subject'           => '標題',
    'post.body'              => '內容',
    'post.submit_thread'     => '發佈主題',
    'post.submit_reply'      => '發佈回覆',
    'post.cancel'            => '取消',

    // -------------------------------------------------------------------------
    // Auth
    // -------------------------------------------------------------------------
    'auth.login_title'       => '登入',
    'auth.username'          => '使用者名稱',
    'auth.password'          => '密碼',
    'auth.remember_me'       => '記住我',
    'auth.login_submit'      => '登入',
    'auth.create_account'    => '建立帳號',
    'auth.register_title'    => '建立帳號',
    'auth.email'             => '電子郵件地址',
    'auth.password_hint'     => '至少 6 個字元',
    'auth.confirm_password'  => '確認密碼',
    'auth.register_submit'   => '建立帳號',
    'auth.have_account'      => '已有帳號？',
    'auth.login_link'        => '立即登入。',
    'auth.forgot_password'   => '忘記密碼？',
    'auth.forgot_title'      => '重設密碼',
    'auth.forgot_email_label' => '電子郵件地址',
    'auth.forgot_submit'     => '寄送重設連結',
    'auth.forgot_sent'       => '若該電子郵件地址已註冊，重設連結已寄出，請查收您的收件匣。',
    'auth.reset_title'       => '設定新密碼',
    'auth.reset_new_password' => '新密碼',
    'auth.reset_confirm'     => '確認新密碼',
    'auth.reset_submit'      => '確認設定新密碼',
    'auth.reset_invalid'     => '此密碼重設連結無效或已過期，請重新申請。',
    'auth.reset_success'     => '您的密碼已更新，您現在已登入。',
    'auth.confirm_pending_title'  => '請查收您的電子郵件',
    'auth.confirm_pending_body'   => '我們已寄送確認連結至 {email}，請點擊以啟用您的帳號。',
    'auth.confirm_pending_resend' => '重新寄送確認電子郵件',
    'auth.confirm_invalid'        => '此確認連結無效或已過期。',
    'auth.resend_title'           => '重新寄送確認電子郵件',
    'auth.resend_email_label'     => '電子郵件地址',
    'auth.resend_submit'          => '重新寄送',
    'auth.resend_sent'            => '若該地址有待確認的項目，新的連結已寄出，請查收您的收件匣。',

    // -------------------------------------------------------------------------
    // User profile
    // -------------------------------------------------------------------------
    'profile.username'       => '使用者名稱',
    'profile.name'           => '姓名',
    'profile.email'          => '電子郵件',
    'profile.joined'         => '加入時間',
    'profile.posts'          => '文章數',
    'profile.last_active'    => '最後上線',
    'profile.signature'      => '簽名檔',
    'profile.recent_posts'   => '最新文章',
    'profile.col_subject'    => '主題',
    'profile.col_date'       => '日期',
    'profile.edit_settings'  => '編輯設定',

    // -------------------------------------------------------------------------
    // User settings
    // -------------------------------------------------------------------------
    'settings.title'             => '帳號設定',
    'settings.saved'             => '設定已儲存。',
    'settings.identity'          => '個人資訊',
    'settings.display_name'      => '顯示名稱',
    'settings.email'             => '電子郵件地址',
    'settings.hide_email'        => '在個人資料中隱藏我的電子郵件地址',
    'settings.password_section'  => '密碼',
    'settings.password_hint'     => '留白則保持目前密碼不變。',
    'settings.new_password'      => '新密碼',
    'settings.confirm_password'  => '確認新密碼',
    'settings.signature_section' => '簽名檔',
    'settings.signature_text'    => '簽名檔內容',
    'settings.show_signature'    => '在我的文章中顯示簽名檔',
    'settings.preferences'       => '偏好設定',
    'settings.threaded_list'     => '在討論區列表中使用樹狀檢視',
    'settings.threaded_read'     => '閱讀主題時使用樹狀檢視',
    'settings.email_notify'      => '當我訂閱的討論區有新文章時寄送電子郵件通知',
    'settings.pm_email_notify'   => '收到私訊時寄送電子郵件通知',
    'settings.tz_offset'         => '時區偏移（小時，-12 至 +14；-99 = 伺服器時間）',
    'settings.save'              => '儲存設定',
    'settings.cancel'            => '取消',

    // -------------------------------------------------------------------------
    // Private messages
    // -------------------------------------------------------------------------
    'pm.private_messages'    => '私人訊息',
    'pm.folders'             => '資料夾',
    'pm.inbox'               => '收件匣',
    'pm.outbox'              => '寄件匣',
    'pm.compose'             => '撰寫',
    'pm.manage_folders'      => '管理資料夾',
    'pm.no_messages'         => '沒有訊息。',
    'pm.col_subject'         => '主題',
    'pm.col_from'            => '寄件人',
    'pm.col_to'              => '收件人',
    'pm.col_date'            => '日期',
    'pm.delete'              => '刪除',
    'pm.compose_title'       => '撰寫訊息',
    'pm.to_label'            => '收件人（使用者名稱）',
    'pm.subject'             => '主題',
    'pm.body'                => '內容',
    'pm.send'                => '傳送',
    'pm.cancel'              => '取消',
    'pm.reply'               => '回覆',
    'pm.back_to_inbox'       => '返回收件匣',
    'pm.move_to_folder'      => '移至資料夾…',
    'pm.move'                => '移動',
    'pm.delete_title'        => '刪除私人訊息',
    'pm.delete_confirm'      => '確認刪除 {author} 寄來的「{subject}」？',
    'pm.create_folder_title' => '新增資料夾',
    'pm.folder_name'         => '資料夾名稱',
    'pm.create'              => '建立',
    'pm.buddy_list'          => 'Buddy List',
    'pm.no_buddies'          => 'You have no buddies yet.',
    'pm.add_buddy'           => 'Add Buddy',
    'pm.remove_buddy'        => 'Remove Buddy',
    'pm.mutual'              => 'Mutual',
    'pm.col_buddy'           => 'User',
    'pm.col_mutual'          => 'Mutual',
    'pm.col_last_active'     => 'Last Active',

    // -------------------------------------------------------------------------
    // Thread subscriptions
    // -------------------------------------------------------------------------
    'sub.title'              => '追蹤主題',
    'sub.following_email'    => '您目前正在追蹤此主題，有新回覆時將收到電子郵件通知。',
    'sub.bookmarked'         => '您已將此主題加入書籤（不寄送電子郵件通知）。',
    'sub.not_following'      => '您尚未追蹤此主題。',
    'sub.follow_email'       => '追蹤並在有新回覆時寄送電子郵件',
    'sub.bookmark'           => '加入書籤（不寄郵件）',
    'sub.unfollow'           => '取消追蹤',
    'sub.back_to_thread'     => '返回主題',
    'sub.follow'             => '追蹤',

    // -------------------------------------------------------------------------
    // Moderation
    // -------------------------------------------------------------------------
    'mod.delete_thread'            => '刪除主題',
    'mod.delete_message'           => '刪除文章',
    'mod.approve_message'          => '核准文章',
    'mod.close_thread'             => '關閉主題',
    'mod.reopen_thread'            => '重新開放主題',
    'mod.delete_thread_confirm'    => '確定要永久刪除主題「{subject}」及其所有回覆嗎？此操作無法復原。',
    'mod.delete_message_confirm'   => '確定要刪除 {author} 發佈的這篇文章嗎？其下的回覆將重新掛載至該主題的上一篇文章。',
    'mod.approve_confirm'          => '核准 {author} 發佈的以下文章，使其對所有讀者可見？',
    'mod.close_confirm'            => '關閉主題「{subject}」？關閉後將不再允許新的回覆。',
    'mod.open_confirm'             => '重新開放主題「{subject}」，允許成員繼續回覆？',
    'mod.yes_delete'               => '確認刪除',
    'mod.approve'                  => '核准',
    'mod.close'                    => '關閉主題',
    'mod.reopen'                   => '重新開放主題',
    'mod.cancel'                   => '取消',
    'mod.move_title'               => '移動主題',
    'mod.move_prompt'              => '將「{subject}」移動至其他討論區：',
    'mod.destination'              => '目標討論區',
    'mod.choose_forum'             => '— 請選擇討論區 —',
    'mod.move_submit'              => '移動主題',

    // -------------------------------------------------------------------------
    // Search
    // -------------------------------------------------------------------------
    'search.title'           => '搜尋',
    'search.messages_label'  => '搜尋文章',
    'search.author'          => '作者',
    'search.match_type'      => '符合方式',
    'search.all_words'       => '包含所有關鍵字',
    'search.any_word'        => '包含任一關鍵字',
    'search.exact_phrase'    => '完整片語',
    'search.posted_within'   => '發文時間',
    'search.last_30'         => '最近 30 天',
    'search.last_90'         => '最近 90 天',
    'search.last_year'       => '最近一年',
    'search.any_time'        => '不限時間',
    'search.threads_only'    => '僅主題發文',
    'search.forums_label'    => '討論區',
    'search.all_forums'      => '所有討論區',
    'search.submit'          => '搜尋',
    'search.no_results'      => '找不到相關結果。',
    'search.showing'         => '顯示',
    'search.of'              => '共',
    'search.result'          => '筆結果',
    'search.results'         => '筆結果',
    'search.col_subject'     => '主題',
    'search.col_author'      => '作者',
    'search.col_forum'       => '討論區',
    'search.col_date'        => '日期',

    // -------------------------------------------------------------------------
    // Errors
    // -------------------------------------------------------------------------
    'error.404_title'        => '找不到頁面',
    'error.404_message'      => '您所要求的頁面不存在。',
    'error.404_return'       => '返回討論區首頁。',
    'error.403_title'        => '存取被拒',
    'error.403_message'      => '您沒有權限存取此討論區。',
    'error.403_login'        => '登入',
    'error.403_login_hint'   => '以存取需要註冊才能瀏覽的討論區。',
    'error.403_return'       => '返回討論區首頁',

    // -------------------------------------------------------------------------
    // Attachments
    // -------------------------------------------------------------------------
    'attachment.label'      => 'Attachments',
    'attachment.add'        => 'Add files',
    'attachment.existing'   => 'Existing attachments',
    'attachment.remove'     => 'Remove',
    'attachment.hint_count' => 'Up to {n} file(s).',
    'attachment.hint_size'  => 'Max {size} per file.',
];
