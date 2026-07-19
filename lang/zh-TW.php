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
    'nav.skip_to_content' => '跳至主要內容',
    'nav.breadcrumb'    => '麵包屑導覽',
    'nav.primary'       => '主要',
    'nav.menu'          => '選單',
    'pagination.nav_label' => '分頁導覽',

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
    'forum.feed_link'        => 'RSS',

    // -------------------------------------------------------------------------
    // Thread view
    // -------------------------------------------------------------------------
    'thread.reply'           => '回覆',
    'thread.follow'          => '追蹤',
    'thread.following'       => '已追蹤',
    'thread.reopen'          => '重新開放',
    'thread.close'           => '關閉',
    'thread.move'            => '移動',
    'thread.merge'           => '合併',
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
    'message.report'            => '檢舉',
    'message.registered'        => '已註冊',
    'message.posts'             => '文章數',

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
    'post.preview'           => '預覽',
    'post.error_subject_required' => '請輸入標題。',
    'post.error_subject_length'   => '標題長度不可超過 255 個字元。',
    'post.error_body_required'    => '請輸入訊息內容。',
    'post.error_flood_wait'       => '請再等待 {seconds} 秒後才能再次發文。',
    'post.error_posting_blocked'  => '您的帳號目前無法發文。',

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
    'auth.error_missing_credentials'  => '請輸入您的使用者名稱和密碼。',
    'auth.error_invalid_credentials'  => '使用者名稱或密碼錯誤。',
    'auth.error_registration_blocked' => '您的帳號目前無法註冊。',
    'auth.error_invalid_email'        => '請輸入有效的電子郵件地址。',
    'auth.error_password_min_length'  => '密碼至少須為 6 個字元。',
    'auth.error_passwords_mismatch'   => '兩次輸入的密碼不一致。',
    'auth.error_username_required'    => '請輸入使用者名稱。',
    'auth.error_username_length'      => '使用者名稱長度須介於 2 到 50 個字元之間。',
    'auth.error_email_required'       => '請輸入有效的電子郵件地址。',
    'auth.error_username_taken'       => '此使用者名稱已被使用。',

    // -------------------------------------------------------------------------
    // OAuth login
    // -------------------------------------------------------------------------
    'oauth.button_google' => '使用 Google 繼續',
    'oauth.button_github' => '使用 GitHub 繼續',
    'oauth.error_provider_error'        => '登入已取消或提供者傳回錯誤。請再試一次。',
    'oauth.error_state_mismatch'        => '您的登入工作階段已過期或無效。請再試一次。',
    'oauth.error_token_exchange_failed' => '無法完成與該提供者的登入。請再試一次。',
    'oauth.error_email_not_verified'    => '您的電子郵件地址尚未在該提供者處驗證，因此我們無法為您登入。請在提供者處驗證您的電子郵件後再試一次。',
    'oauth.error_login_failed'          => '登入時發生問題。請再試一次。',
    'oauth.error_account_inactive'      => '您的帳戶尚未啟用。請查看您的電子郵件以取得確認連結。',
    'oauth.error_not_configured'        => '該登入選項目前無法使用。',

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
    'settings.threaded_read'     => '閱讀主題時使用樹狀檢視',
    'settings.email_notify'      => '當我訂閱的討論區有新文章時寄送電子郵件通知',
    'settings.pm_email_notify'   => '收到私訊時寄送電子郵件通知',
    'settings.tz_offset'         => '時區偏移（小時，-12 至 +14；-99 = 伺服器時間）',
    'settings.save'              => '儲存設定',
    'settings.cancel'            => '取消',
    'settings.avatar_section'    => '大頭貼',
    'settings.avatar_current'    => '目前的大頭貼',
    'settings.avatar_upload'     => '上傳新的大頭貼',
    'settings.avatar_hint'       => 'JPG、PNG、GIF 或 WebP 格式，檔案大小上限 100 KB。',
    'settings.avatar_delete'     => '移除目前的大頭貼',
    'settings.error_display_name_required' => '請輸入顯示名稱。',
    'settings.error_display_name_length'   => '顯示名稱長度不可超過 50 個字元。',
    'settings.error_email_required'        => '請輸入有效的電子郵件地址。',
    'settings.error_email_taken'           => '此電子郵件地址已被其他帳號使用。',
    'settings.error_password_min_length'   => '新密碼至少須為 6 個字元。',
    'settings.error_passwords_mismatch'    => '兩次輸入的密碼不一致。',
    'settings.error_tz_offset'             => '時區偏移必須介於 -12 至 +14 之間，或為 -99（伺服器時間）。',

    // -------------------------------------------------------------------------
    // Forced password change
    // -------------------------------------------------------------------------
    'force_password_change.title'     => '變更您的密碼',
    'force_password_change.message'   => '管理員要求您在繼續之前設定新密碼。',
    'force_password_change.new_password'     => '新密碼',
    'force_password_change.confirm_password' => '確認新密碼',
    'force_password_change.save'      => '設定密碼',
    'force_password_change.error_password_min_length' => '新密碼至少須為 6 個字元。',
    'force_password_change.error_passwords_mismatch'  => '兩次輸入的密碼不一致。',

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
    'pm.error_recipient_required'   => '請輸入收件人。',
    'pm.error_user_not_found'       => '找不到使用者「{username}」。',
    'pm.error_subject_required'     => '請輸入主題。',
    'pm.error_body_required'        => '請輸入訊息內容。',
    'pm.error_folder_name_required' => '請輸入資料夾名稱。',
    'pm.error_folder_name_length'   => '資料夾名稱長度不可超過 60 個字元。',

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
    'sub.confirm_title'      => '確認操作',
    'sub.confirm_remove'     => '確定要取消追蹤此主題嗎？',
    'sub.confirm_bookmark'   => '將您的訂閱切換為書籤（不寄送電子郵件通知）？',
    'sub.confirm_yes'        => '是，確認',
    'sub.confirm_cancel'     => '取消',

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
    'mod.merge_title'               => '合併主題',
    'mod.merge_prompt'              => '將「{subject}」合併至另一個主題。合併主題的文章將附加到目標主題，且此主題的訂閱將不會被保留。',
    'mod.merge_target'              => '目標主題 ID',
    'mod.merge_target_hint'         => '要合併進去的主題數字 ID（可在其網址中找到）。',
    'mod.merge_submit'              => '合併主題',
    'mod.merge_error_not_found'      => '找不到該主題 ID。',
    'mod.merge_error_same_thread'    => '請選擇另一個不同的主題進行合併。',
    'mod.merge_error_failed'         => '無法合併至該主題。',
    'mod.moderate'                 => '管理',
    'mod.queue'                    => '審核佇列',
    'mod.queue_title'              => '待審核文章佇列',
    'mod.queue_empty'              => '目前沒有待審核的文章。',
    'mod.queue_forum'              => '討論區',
    'mod.queue_posted'             => '發佈時間',
    'mod.reports_title'            => '被檢舉的內容',
    'mod.reports_empty'            => '目前沒有待處理的檢舉。',
    'mod.reports_message_missing'  => '（被檢舉的文章已不存在）',
    'mod.reports_reported'         => '已檢舉',
    'mod.reports_resolve'          => '結案',
    'mod.reports_dismiss'          => '駁回',
    'mod.reports_view'             => '在主題中檢視',
    'report.title'                 => '檢舉文章',
    'report.intro'                 => '要向管理員檢舉 {author} 發佈的這篇文章嗎？',
    'report.reason_label'          => '原因（選填）',
    'report.submit'                => '送出檢舉',

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
    // Install
    // -------------------------------------------------------------------------
    'install.page_title'               => 'Phorum 安裝程式',
    'install.requirements_heading'     => '系統需求',
    'install.requirement_failed'       => '失敗',
    'install.fix_requirements'         => '請先修正上述需求問題後再繼續。',
    'install.fix_requirements_hint_1'  => '請確認',
    'install.fix_requirements_hint_and' => '和',
    'install.fix_requirements_hint_2'  => '已存在（從 .example 檔案複製而來），且資料庫憑證正確無誤。',
    'install.errors_heading'           => '請修正以下問題',
    'install.setup_heading'            => '網站與管理員設定',
    'install.site_name_label'          => '網站名稱',
    'install.admin_account_heading'    => '管理員帳號',
    'install.username_label'           => '使用者名稱',
    'install.email_label'              => '電子郵件',
    'install.password_label'           => '密碼（至少 8 個字元）',
    'install.confirm_password_label'   => '確認密碼',
    'install.submit'                   => '安裝 Phorum',
    'install.complete_page_title'      => '安裝完成 — Phorum',
    'install.complete_heading'         => '安裝完成',
    'install.complete_message'         => '資料庫結構已建立，您的管理員帳號已準備就緒。',
    'install.go_to_forum'              => '前往討論區',
    'install.admin_panel'              => '管理面板',
    'install.error_site_name_required'  => '請輸入網站名稱。',
    'install.error_username_required'   => '請輸入管理員使用者名稱。',
    'install.error_username_format'     => '使用者名稱長度須為 3–50 個字元（僅限英文字母、數字、_ . -）。',
    'install.error_email_required'      => '請輸入有效的管理員電子郵件地址。',
    'install.error_password_min_length' => '管理員密碼至少須為 8 個字元。',
    'install.error_passwords_mismatch'  => '兩次輸入的密碼不一致。',
    'install.error_failed'              => '安裝失敗：{message}',

    // -------------------------------------------------------------------------
    // Upgrade (existing Phorum 6 database → Phorum 10)
    // -------------------------------------------------------------------------
    'upgrade.page_title'          => 'Phorum 升級',
    'upgrade.detected_heading'    => '偵測到現有的 Phorum 6 資料庫',
    'upgrade.detected_message'    => '此資料庫是由 Phorum 6 建立的。Phorum 10 與 Phorum 6 的資料庫結構相容 — 不會變更、刪除或轉換任何現有資料。',
    'upgrade.up_to_date'          => '不需要進行任何結構變更 — 此資料庫已是最新版本。',
    'upgrade.new_tables_heading'  => '將新增以下新資料表：',
    'upgrade.new_patches_heading' => '將套用以下結構更新：',
    'upgrade.submit'              => '繼續',
    'upgrade.complete_page_title' => '升級完成 — Phorum',
    'upgrade.complete_heading'    => '升級完成',
    'upgrade.complete_message'    => '您的 Phorum 6 資料庫現已可在 Phorum 10 上運作。',
    'upgrade.go_to_forum'         => '前往討論區',
    'upgrade.admin_panel'         => '管理面板',

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
    'error.disabled_title'    => '網站無法使用',
    'error.disabled_message'  => '本網站暫時停用，請稍後再回來查看。',
    'error.admin_only_title'   => '網站無法使用',
    'error.admin_only_message' => '本網站因維護作業暫時關閉，請稍後再回來查看。',
    'error.read_only_title'    => '唯讀模式',
    'error.read_only_message'  => '本網站目前為唯讀模式，發文與登入功能暫時停用。',
    'banner.read_only'         => '本網站目前為唯讀模式 — 發文與登入功能暫時停用。',

    // -------------------------------------------------------------------------
    // Announcements
    // -------------------------------------------------------------------------
    'announcements.heading' => '公告',

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
