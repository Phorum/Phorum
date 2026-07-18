<?php
declare(strict_types=1);

/**
 * Simplified Chinese (Mainland China) translations for Phorum.
 * Machine-translated — please review with a native speaker.
 */
return [

    // Metadata (not rendered as UI text)
    '_name' => '简体中文',

    // -------------------------------------------------------------------------
    // Navigation
    // -------------------------------------------------------------------------
    'nav.forum_index'   => '论坛首页',
    'nav.search'        => '搜索',
    'nav.messages'      => '私信',
    'nav.settings'      => '设置',
    'nav.log_out'       => '退出登录',
    'nav.log_in'        => '登录',
    'nav.register'      => '注册',
    'nav.powered_by'    => '由 Phorum 驱动',
    'nav.skip_to_content' => '跳转到主要内容',
    'nav.breadcrumb'    => '面包屑导航',
    'nav.primary'       => '主要导航',
    'nav.menu'          => '菜单',
    'pagination.nav_label' => '分页',

    // -------------------------------------------------------------------------
    // Forum list (index page)
    // -------------------------------------------------------------------------
    'forum_list.no_forums'   => '暂无论坛版块。',
    'forum_list.col_forum'   => '版块',
    'forum_list.col_posts'   => '帖子',
    'forum_list.col_threads' => '主题',
    'forum_list.col_last'    => '最新回复',

    // -------------------------------------------------------------------------
    // Forum (thread listing)
    // -------------------------------------------------------------------------
    'forum.new_thread'       => '发起新主题',
    'forum.no_threads'       => '暂无主题。',
    'forum.start_one'        => '来发一个吧。',
    'forum.col_subject'      => '主题',
    'forum.col_author'       => '作者',
    'forum.col_replies'      => '回复',
    'forum.col_posts'        => '帖子',
    'forum.col_last_post'    => '最新回复',
    'forum.sticky'           => '置顶',
    'forum.closed'           => '已关闭',
    'forum.by'               => '由',
    'forum.new'              => '新',
    'forum.mark_read'        => '全部标为已读',
    'forum.feed_link'        => 'RSS',

    // -------------------------------------------------------------------------
    // Thread view
    // -------------------------------------------------------------------------
    'thread.reply'           => '回复',
    'thread.follow'          => '关注',
    'thread.following'       => '已关注',
    'thread.reopen'          => '重新开放',
    'thread.close'           => '关闭',
    'thread.move'            => '移动',
    'thread.merge'           => '合并',
    'thread.delete'          => '删除主题',

    // -------------------------------------------------------------------------
    // Individual message
    // -------------------------------------------------------------------------
    'message.awaiting_approval' => '等待审核',
    'message.reply'             => '回复',
    'message.edit'              => '编辑',
    'message.edit_title'        => '编辑消息',
    'message.save_edit'         => '保存更改',
    'message.edited_note'       => '已编辑',
    'message.changes'           => 'Changes',
    'message.changes_title'     => 'Edit History',
    'message.changes_edit_n'    => 'Edit #{n}',
    'message.changes_by'        => 'by',
    'message.changes_subject'   => 'Subject',
    'message.changes_body'      => 'Message',
    'message.changes_back'      => 'Back to thread',
    'message.approve'           => '通过',
    'message.delete'            => '删除',
    'message.report'            => '举报',
    'message.registered'        => '注册时间',
    'message.posts'             => '帖子数',

    // -------------------------------------------------------------------------
    // Post / reply form
    // -------------------------------------------------------------------------
    'post.new_thread'        => '发起新主题',
    'post.reply_to'          => '回复：{subject}',
    'post.reply'             => '回复',
    'post.subject'           => '标题',
    'post.body'              => '内容',
    'post.submit_thread'     => '发布主题',
    'post.submit_reply'      => '发布回复',
    'post.cancel'            => '取消',
    'post.preview'           => '预览',
    'post.error_subject_required' => '标题为必填项。',
    'post.error_subject_length'   => '标题不能超过 255 个字符。',
    'post.error_body_required'    => '内容为必填项。',
    'post.error_flood_wait'       => '请再等待 {seconds} 秒后再发布。',
    'post.error_posting_blocked'  => '您的账户不允许发帖。',

    // -------------------------------------------------------------------------
    // Auth
    // -------------------------------------------------------------------------
    'auth.login_title'       => '登录',
    'auth.username'          => '用户名',
    'auth.password'          => '密码',
    'auth.remember_me'       => '记住我',
    'auth.login_submit'      => '登录',
    'auth.create_account'    => '创建账户',
    'auth.register_title'    => '创建账户',
    'auth.email'             => '电子邮箱',
    'auth.password_hint'     => '至少 6 个字符',
    'auth.confirm_password'  => '确认密码',
    'auth.register_submit'   => '创建账户',
    'auth.have_account'      => '已有账户？',
    'auth.login_link'        => '立即登录。',
    'auth.forgot_password'   => '忘记密码？',
    'auth.forgot_title'      => '重置密码',
    'auth.forgot_email_label' => '电子邮箱',
    'auth.forgot_submit'     => '发送重置链接',
    'auth.forgot_sent'       => '如果该邮箱地址已注册，重置链接已发送至您的邮箱，请查收。',
    'auth.reset_title'       => '设置新密码',
    'auth.reset_new_password' => '新密码',
    'auth.reset_confirm'     => '确认新密码',
    'auth.reset_submit'      => '确认设置新密码',
    'auth.reset_invalid'     => '此密码重置链接无效或已过期，请重新申请。',
    'auth.reset_success'     => '您的密码已更新，您现在已登录。',
    'auth.confirm_pending_title'  => '请查收您的邮件',
    'auth.confirm_pending_body'   => '我们已向 {email} 发送了一封确认邮件，请点击链接激活您的账户。',
    'auth.confirm_pending_resend' => '重新发送确认邮件',
    'auth.confirm_invalid'        => '此确认链接无效或已过期。',
    'auth.resend_title'           => '重新发送确认邮件',
    'auth.resend_email_label'     => '电子邮箱',
    'auth.resend_submit'          => '重新发送',
    'auth.resend_sent'            => '若该地址有待确认的注册，新链接已发送，请查收您的收件箱。',
    'auth.error_missing_credentials'  => '请输入用户名和密码。',
    'auth.error_invalid_credentials'  => '用户名或密码无效。',
    'auth.error_registration_blocked' => '您的账户不允许进行注册。',
    'auth.error_invalid_email'        => '请输入有效的电子邮箱地址。',
    'auth.error_password_min_length'  => '密码至少需要 6 个字符。',
    'auth.error_passwords_mismatch'   => '两次输入的密码不一致。',
    'auth.error_username_required'    => '用户名为必填项。',
    'auth.error_username_length'      => '用户名长度必须在 2 到 50 个字符之间。',
    'auth.error_email_required'       => '需要提供有效的电子邮箱地址。',
    'auth.error_username_taken'       => '该用户名已被使用。',

    // -------------------------------------------------------------------------
    // User profile
    // -------------------------------------------------------------------------
    'profile.username'       => '用户名',
    'profile.name'           => '姓名',
    'profile.email'          => '电子邮箱',
    'profile.joined'         => '注册时间',
    'profile.posts'          => '帖子数',
    'profile.last_active'    => '最后活跃',
    'profile.signature'      => '个性签名',
    'profile.recent_posts'   => '最新帖子',
    'profile.col_subject'    => '主题',
    'profile.col_date'       => '日期',
    'profile.edit_settings'  => '编辑设置',

    // -------------------------------------------------------------------------
    // User settings
    // -------------------------------------------------------------------------
    'settings.title'             => '账户设置',
    'settings.saved'             => '设置已保存。',
    'settings.identity'          => '个人信息',
    'settings.display_name'      => '显示名称',
    'settings.email'             => '电子邮箱',
    'settings.hide_email'        => '在个人资料中隐藏我的邮箱地址',
    'settings.password_section'  => '密码',
    'settings.password_hint'     => '留空则保持当前密码不变。',
    'settings.new_password'      => '新密码',
    'settings.confirm_password'  => '确认新密码',
    'settings.signature_section' => '个性签名',
    'settings.signature_text'    => '签名内容',
    'settings.show_signature'    => '在我的帖子中显示签名',
    'settings.preferences'       => '偏好设置',
    'settings.threaded_read'     => '阅读主题时使用树形视图',
    'settings.email_notify'      => '当我订阅的版块有新帖子时发送邮件通知',
    'settings.pm_email_notify'   => '收到私信时发送邮件通知',
    'settings.tz_offset'         => '时区偏移（小时，-12 至 +14；-99 = 服务器时间）',
    'settings.save'              => '保存设置',
    'settings.cancel'            => '取消',
    'settings.avatar_section'    => '头像',
    'settings.avatar_current'    => '当前头像',
    'settings.avatar_upload'     => '上传新头像',
    'settings.avatar_hint'       => '支持 JPG、PNG、GIF 或 WebP 格式，最大 100 KB。',
    'settings.avatar_delete'     => '删除当前头像',
    'settings.error_display_name_required' => '显示名称为必填项。',
    'settings.error_display_name_length'   => '显示名称不能超过 50 个字符。',
    'settings.error_email_required'        => '需要提供有效的电子邮箱地址。',
    'settings.error_email_taken'           => '该电子邮箱地址已被其他账户使用。',
    'settings.error_password_min_length'   => '新密码至少需要 6 个字符。',
    'settings.error_passwords_mismatch'    => '两次输入的密码不一致。',
    'settings.error_tz_offset'             => '时区偏移必须在 -12 至 +14 之间，或使用 -99 表示服务器时间。',

    // -------------------------------------------------------------------------
    // Forced password change
    // -------------------------------------------------------------------------
    'force_password_change.title'     => '修改您的密码',
    'force_password_change.message'   => '管理员要求您在继续之前设置新密码。',
    'force_password_change.new_password'     => '新密码',
    'force_password_change.confirm_password' => '确认新密码',
    'force_password_change.save'      => '设置密码',
    'force_password_change.error_password_min_length' => '新密码至少需要 6 个字符。',
    'force_password_change.error_passwords_mismatch'  => '两次输入的密码不一致。',

    // -------------------------------------------------------------------------
    // Private messages
    // -------------------------------------------------------------------------
    'pm.private_messages'    => '私信',
    'pm.folders'             => '文件夹',
    'pm.inbox'               => '收件箱',
    'pm.outbox'              => '发件箱',
    'pm.compose'             => '写信',
    'pm.manage_folders'      => '管理文件夹',
    'pm.no_messages'         => '暂无私信。',
    'pm.col_subject'         => '主题',
    'pm.col_from'            => '发件人',
    'pm.col_to'              => '收件人',
    'pm.col_date'            => '日期',
    'pm.delete'              => '删除',
    'pm.compose_title'       => '撰写私信',
    'pm.to_label'            => '收件人（用户名）',
    'pm.subject'             => '主题',
    'pm.body'                => '内容',
    'pm.send'                => '发送',
    'pm.cancel'              => '取消',
    'pm.reply'               => '回复',
    'pm.back_to_inbox'       => '返回收件箱',
    'pm.move_to_folder'      => '移动到文件夹…',
    'pm.move'                => '移动',
    'pm.delete_title'        => '删除私信',
    'pm.delete_confirm'      => '确认删除 {author} 发来的私信"{subject}"？',
    'pm.create_folder_title' => '新建文件夹',
    'pm.folder_name'         => '文件夹名称',
    'pm.create'              => '创建',
    'pm.buddy_list'          => 'Buddy List',
    'pm.no_buddies'          => 'You have no buddies yet.',
    'pm.add_buddy'           => 'Add Buddy',
    'pm.remove_buddy'        => 'Remove Buddy',
    'pm.mutual'              => 'Mutual',
    'pm.col_buddy'           => 'User',
    'pm.col_mutual'          => 'Mutual',
    'pm.col_last_active'     => 'Last Active',
    'pm.error_recipient_required'   => '收件人为必填项。',
    'pm.error_user_not_found'       => '未找到用户"{username}"。',
    'pm.error_subject_required'     => '主题为必填项。',
    'pm.error_body_required'        => '内容为必填项。',
    'pm.error_folder_name_required' => '文件夹名称为必填项。',
    'pm.error_folder_name_length'   => '文件夹名称不能超过 60 个字符。',

    // -------------------------------------------------------------------------
    // Thread subscriptions
    // -------------------------------------------------------------------------
    'sub.title'              => '关注主题',
    'sub.following_email'    => '您正在关注此主题，有新回复时将收到邮件通知。',
    'sub.bookmarked'         => '您已收藏此主题（不发送邮件通知）。',
    'sub.not_following'      => '您尚未关注此主题。',
    'sub.follow_email'       => '关注并在有新回复时发送邮件',
    'sub.bookmark'           => '收藏（不发邮件）',
    'sub.unfollow'           => '取消关注',
    'sub.back_to_thread'     => '返回主题',
    'sub.follow'             => '关注',
    'sub.confirm_title'      => '确认操作',
    'sub.confirm_remove'     => '确定要取消关注此主题吗？',
    'sub.confirm_bookmark'   => '将您的关注切换为收藏（不发送邮件通知）？',
    'sub.confirm_yes'        => '是，确认',
    'sub.confirm_cancel'     => '取消',

    // -------------------------------------------------------------------------
    // Moderation
    // -------------------------------------------------------------------------
    'mod.delete_thread'            => '删除主题',
    'mod.delete_message'           => '删除帖子',
    'mod.approve_message'          => '通过审核',
    'mod.close_thread'             => '关闭主题',
    'mod.reopen_thread'            => '重新开放主题',
    'mod.delete_thread_confirm'    => '确定要永久删除主题"{subject}"及其所有回复吗？此操作无法撤销。',
    'mod.delete_message_confirm'   => '确定要删除 {author} 发布的这条帖子吗？其下的回复将重新挂载到该主题的上一条帖子。',
    'mod.approve_confirm'          => '通过 {author} 发布的以下帖子，使其对所有读者可见？',
    'mod.close_confirm'            => '关闭主题"{subject}"？关闭后将不再允许新的回复。',
    'mod.open_confirm'             => '重新开放主题"{subject}"，允许成员继续回复？',
    'mod.yes_delete'               => '确认删除',
    'mod.approve'                  => '通过',
    'mod.close'                    => '关闭主题',
    'mod.reopen'                   => '重新开放主题',
    'mod.cancel'                   => '取消',
    'mod.move_title'               => '移动主题',
    'mod.move_prompt'              => '将"{subject}"移动到其他版块：',
    'mod.destination'              => '目标版块',
    'mod.choose_forum'             => '— 请选择版块 —',
    'mod.move_submit'              => '移动主题',
    'mod.merge_title'               => '合并主题',
    'mod.merge_prompt'              => '将"{subject}"合并到另一个主题。合并后，该主题的帖子将追加到目标主题末尾，且此主题的关注设置不会被保留。',
    'mod.merge_target'              => '目标主题 ID',
    'mod.merge_target_hint'         => '要合并到的主题的数字 ID（可在其网址中查看）。',
    'mod.merge_submit'              => '合并主题',
    'mod.merge_error_not_found'     => '未找到该主题 ID。',
    'mod.merge_error_same_thread'   => '请选择另一个不同的主题进行合并。',
    'mod.merge_error_failed'        => '无法合并到该主题。',
    'mod.moderate'                 => '管理',
    'mod.queue'                    => '审核队列',
    'mod.queue_title'              => '待审核消息队列',
    'mod.queue_empty'              => '当前没有待审核的消息。',
    'mod.queue_forum'              => '版块',
    'mod.queue_posted'             => '发布时间',
    'mod.reports_title'            => '被举报内容',
    'mod.reports_empty'            => '暂无待处理的举报。',
    'mod.reports_message_missing'  => '（被举报的消息已不存在）',
    'mod.reports_reported'         => '举报',
    'mod.reports_resolve'          => '处理',
    'mod.reports_dismiss'          => '忽略',
    'mod.reports_view'             => '在主题中查看',
    'report.title'                 => '举报消息',
    'report.intro'                 => '将 {author} 发布的这条消息举报给管理员？',
    'report.reason_label'          => '原因（可选）',
    'report.submit'                => '提交举报',

    // -------------------------------------------------------------------------
    // Search
    // -------------------------------------------------------------------------
    'search.title'           => '搜索',
    'search.messages_label'  => '搜索帖子',
    'search.author'          => '作者',
    'search.match_type'      => '匹配方式',
    'search.all_words'       => '包含所有词',
    'search.any_word'        => '包含任意词',
    'search.exact_phrase'    => '精确短语',
    'search.posted_within'   => '发布时间',
    'search.last_30'         => '最近 30 天',
    'search.last_90'         => '最近 90 天',
    'search.last_year'       => '最近一年',
    'search.any_time'        => '不限时间',
    'search.threads_only'    => '仅主题发帖',
    'search.forums_label'    => '版块',
    'search.all_forums'      => '所有版块',
    'search.submit'          => '搜索',
    'search.no_results'      => '未找到相关结果。',
    'search.showing'         => '显示',
    'search.of'              => '共',
    'search.result'          => '条结果',
    'search.results'         => '条结果',
    'search.col_subject'     => '主题',
    'search.col_author'      => '作者',
    'search.col_forum'       => '版块',
    'search.col_date'        => '日期',

    // -------------------------------------------------------------------------
    // Install
    // -------------------------------------------------------------------------
    'install.page_title'               => 'Phorum 安装程序',
    'install.requirements_heading'     => '环境要求',
    'install.requirement_failed'       => '未通过',
    'install.fix_requirements'         => '请先解决以上环境要求问题，然后再继续。',
    'install.fix_requirements_hint_1'  => '请确保',
    'install.fix_requirements_hint_and' => '和',
    'install.fix_requirements_hint_2'  => '文件已存在（从 .example 文件复制而来），并且数据库凭据正确。',
    'install.errors_heading'           => '请修正以下问题',
    'install.setup_heading'            => '站点与管理员设置',
    'install.site_name_label'          => '站点名称',
    'install.admin_account_heading'    => '管理员账户',
    'install.username_label'           => '用户名',
    'install.email_label'              => '电子邮箱',
    'install.password_label'           => '密码（至少 8 个字符）',
    'install.confirm_password_label'   => '确认密码',
    'install.submit'                   => '安装 Phorum',
    'install.complete_page_title'      => '安装完成 — Phorum',
    'install.complete_heading'         => '安装完成',
    'install.complete_message'         => '数据库结构已创建，您的管理员账户已准备就绪。',
    'install.go_to_forum'              => '前往论坛',
    'install.admin_panel'              => '管理面板',
    'install.error_site_name_required'  => '站点名称为必填项。',
    'install.error_username_required'   => '管理员用户名为必填项。',
    'install.error_username_format'     => '用户名长度必须为 3–50 个字符（仅限字母、数字、_、.、-）。',
    'install.error_email_required'      => '需要提供有效的管理员电子邮箱地址。',
    'install.error_password_min_length' => '管理员密码至少需要 8 个字符。',
    'install.error_passwords_mismatch'  => '两次输入的密码不一致。',
    'install.error_failed'              => '安装失败：{message}',

    // -------------------------------------------------------------------------
    // Upgrade (existing Phorum 6 database → Phorum 10)
    // -------------------------------------------------------------------------
    'upgrade.page_title'          => 'Phorum 升级',
    'upgrade.detected_heading'    => '检测到现有的 Phorum 6 数据库',
    'upgrade.detected_message'    => '此数据库由 Phorum 6 创建。Phorum 10 与 Phorum 6 的数据库结构兼容 — 现有数据不会被更改、删除或转换。',
    'upgrade.up_to_date'          => '无需进行数据库结构变更 — 该数据库已是最新版本。',
    'upgrade.new_tables_heading'  => '将添加以下新表：',
    'upgrade.new_patches_heading' => '将应用以下数据库结构更新：',
    'upgrade.submit'              => '继续',
    'upgrade.complete_page_title' => '升级完成 — Phorum',
    'upgrade.complete_heading'    => '升级完成',
    'upgrade.complete_message'    => '您的 Phorum 6 数据库现已准备好在 Phorum 10 上运行。',
    'upgrade.go_to_forum'         => '前往论坛',
    'upgrade.admin_panel'         => '管理面板',

    // -------------------------------------------------------------------------
    // Errors
    // -------------------------------------------------------------------------
    'error.404_title'        => '页面未找到',
    'error.404_message'      => '您请求的页面不存在。',
    'error.404_return'       => '返回论坛首页。',
    'error.403_title'        => '访问被拒绝',
    'error.403_message'      => '您没有权限访问此版块。',
    'error.403_login'        => '登录',
    'error.403_login_hint'   => '以访问需要注册才能查看的版块。',
    'error.403_return'       => '返回论坛首页',
    'error.disabled_title'    => '站点不可用',
    'error.disabled_message'  => '本站点暂时已禁用，请稍后再来查看。',
    'error.admin_only_title'   => '站点不可用',
    'error.admin_only_message' => '本站点因维护暂时关闭，请稍后再来查看。',
    'error.read_only_title'    => '只读模式',
    'error.read_only_message'  => '本站点当前处于只读模式，发帖和登录功能已暂时禁用。',
    'banner.read_only'         => '本站点当前处于只读模式 — 发帖和登录功能已暂时禁用。',

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
