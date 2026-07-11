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
    'nav.menu'          => '菜单',

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
    'forum.col_last_post'    => '最新回复',
    'forum.sticky'           => '置顶',
    'forum.closed'           => '已关闭',
    'forum.by'               => '由',
    'forum.new'              => '新',
    'forum.mark_read'        => '全部标为已读',

    // -------------------------------------------------------------------------
    // Thread view
    // -------------------------------------------------------------------------
    'thread.reply'           => '回复',
    'thread.follow'          => '关注',
    'thread.following'       => '已关注',
    'thread.reopen'          => '重新开放',
    'thread.close'           => '关闭',
    'thread.move'            => '移动',
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
    'settings.threaded_list'     => '在版块列表中使用树形视图',
    'settings.threaded_read'     => '阅读主题时使用树形视图',
    'settings.email_notify'      => '当我订阅的版块有新帖子时发送邮件通知',
    'settings.pm_email_notify'   => '收到私信时发送邮件通知',
    'settings.tz_offset'         => '时区偏移（小时，-12 至 +14；-99 = 服务器时间）',
    'settings.save'              => '保存设置',
    'settings.cancel'            => '取消',

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
