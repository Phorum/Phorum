<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Core\Config;
use Phorum\Core\Lang;
use Phorum\Core\Url;
use Phorum\Http\Controller;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\BanMapper;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\NewflagMapper;
use Phorum\Mapper\SearchMapper;
use Phorum\Mapper\SubscriberMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Mapper\UserPermissionMapper;
use Phorum\Model\Forum;
use Phorum\Model\Message;
use Phorum\Mapper\FileMapper;
use Phorum\Mapper\MessageTrackingMapper;
use Phorum\Mapper\SettingMapper;
use Phorum\Model\MessageMeta;
use Phorum\Model\User;
use Phorum\Service\AnnouncementService;
use Phorum\Service\BanService;
use Phorum\Service\DiffRenderer;
use Phorum\Service\FileService;
use Phorum\Service\FloodControlService;
use Phorum\Service\MailService;
use Phorum\Service\MessageService;
use Phorum\Service\NewflagService;
use Phorum\Service\PermissionService;
use Phorum\Service\SchemaOrgService;
use Phorum\Service\SubscriptionService;
use Twig\Environment;

class MessageController extends Controller
{
    private readonly ForumMapper          $forums;
    private readonly MessageMapper        $messages;
    private readonly PermissionService    $perms;
    private readonly FileService          $fileService;
    private readonly NewflagService       $newflags;
    private readonly BanService           $banService;
    private readonly SubscriptionService  $subscriptions;
    private readonly SearchMapper         $searchIndex;
    private readonly MessageService       $messageService;
    private readonly UserMapper           $users;
    private readonly AnnouncementService  $announcements;
    private readonly FloodControlService  $floodControl;
    private readonly SchemaOrgService     $schemaOrg;
    private readonly SettingMapper        $settings;

    public function __construct(
        Config                $config,
        Environment           $twig,
        ?ForumMapper          $forums         = null,
        ?MessageMapper        $messages       = null,
        ?PermissionService    $perms          = null,
        ?FileService          $fileService    = null,
        ?NewflagService       $newflags       = null,
        ?BanService           $banService     = null,
        ?SubscriptionService  $subscriptions  = null,
        ?SearchMapper         $searchIndex    = null,
        ?MessageService       $messageService = null,
        ?UserMapper           $users          = null,
        ?AnnouncementService  $announcements  = null,
        ?FloodControlService  $floodControl   = null,
        ?SchemaOrgService     $schemaOrg      = null,
        ?SettingMapper        $settings       = null,
    ) {
        parent::__construct($config, $twig);
        $this->settings       = $settings      ?? new SettingMapper();
        $this->forums         = $forums        ?? new ForumMapper();
        $this->messages       = $messages      ?? new MessageMapper();
        $this->perms          = $perms         ?? new PermissionService(new UserPermissionMapper());
        $this->fileService    = $fileService   ?? new FileService(new FileMapper());
        $this->newflags       = $newflags      ?? new NewflagService(new NewflagMapper());
        $this->banService     = $banService    ?? new BanService(new BanMapper());
        $this->subscriptions  = $subscriptions ?? new SubscriptionService(new SubscriberMapper(), new UserMapper(), new MailService($config), $config);
        $this->searchIndex    = $searchIndex   ?? new SearchMapper();
        $this->users          = $users          ?? new UserMapper();
        $this->messageService = $messageService ?? new MessageService($this->messages, $this->forums, $this->users, $this->settings);
        $this->announcements  = $announcements  ?? new AnnouncementService();
        $this->floodControl   = $floodControl   ?? new FloodControlService($this->messages, $this->settings);
        $this->schemaOrg      = $schemaOrg      ?? new SchemaOrgService($config);
    }

    /**
     * True if $currentUser may edit $msg in $forum. Moderators always may;
     * otherwise the post must be their own, the ALLOW_EDIT permission bit
     * set, the thread not closed, and (if configured) within the site-wide
     * edit time limit since the post was made.
     *
     * Resolves canModerate/canEdit/edit_time_limit itself — fine for a
     * single message (editMessage()'s use), but callers checking many
     * messages at once (thread()'s can_edit_ids) should precompute those
     * three and call canEditMessageWithContext() per message instead, since
     * they don't vary per message.
     */
    private function canEditMessage(Message $msg, Forum $forum, ?User $currentUser): bool
    {
        if ($currentUser === null) {
            return false;
        }
        return $this->canEditMessageWithContext(
            $msg,
            $currentUser,
            isModerator: $this->perms->canModerate($forum, $currentUser),
            canEditBit:  $this->perms->canEdit($forum, $currentUser),
            editTimeLimit: (int) ($this->settings->getSetting('edit_time_limit') ?? 0),
        );
    }

    /** Per-message part of canEditMessage(), given the already-resolved, message-invariant context. */
    private function canEditMessageWithContext(
        Message $msg,
        User    $currentUser,
        bool    $isModerator,
        bool    $canEditBit,
        int     $editTimeLimit
    ): bool {
        if ($isModerator) {
            return true;
        }
        if ($msg->user_id !== $currentUser->user_id) {
            return false;
        }
        if ($msg->closed) {
            return false;
        }
        if (!$canEditBit) {
            return false;
        }
        if ($editTimeLimit > 0 && (time() - $msg->datestamp) > $editTimeLimit * 60) {
            return false;
        }

        return true;
    }

    public function thread(Request $request): Response
    {
        $forumId  = (int) ($request->tokens['forum_id']  ?? 0);
        $threadId = (int) ($request->tokens['thread_id'] ?? 0);

        $forum = $this->forums->load($forumId);
        if ($forum === null) {
            return $this->notFound();
        }

        if (!$this->perms->canRead($forum, Auth::user())) {
            return $this->forbidden();
        }

        $currentUser  = Auth::user();
        $viewerUserId = $currentUser?->user_id;
        $threaded     = (bool) $forum->threaded_read;

        if ($threaded) {
            // Threaded mode always renders the whole reply tree in one page —
            // an OFFSET window could split a parent from its child.
            $threadMessages = $this->messages->findByThread($threadId, $viewerUserId);
            if ($threadMessages === null) {
                return $this->notFound();
            }

            $root = null;
            foreach ($threadMessages as $msg) {
                if ($msg->message_id === $threadId) {
                    $root = $msg;
                    break;
                }
            }
            if ($root === null) {
                return $this->notFound();
            }

            $page  = 1;
            $pages = 1;
        } else {
            $perPage = $forum->read_length ?: 25;
            $page    = max(1, (int) ($request->query['page'] ?? 1));

            $root = $this->messages->findRoot($threadId, $viewerUserId);
            if ($root === null) {
                return $this->notFound();
            }

            $total = $root->thread_count;
            $pages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            // A message-specific deep link (?msg=) may resolve to a different
            // page than the one requested — redirect there rather than
            // silently showing the wrong page.
            $msgParam = (int) ($request->query['msg'] ?? 0);
            if ($msgParam > 0 && $msgParam !== $threadId) {
                $position = $this->messages->findMessagePosition($threadId, $msgParam, $viewerUserId);
                if ($position !== null) {
                    $targetPage = max(1, (int) ceil($position / $perPage));
                    if ($targetPage !== $page) {
                        return $this->redirect(Url::thread($forumId, $threadId, $msgParam, $targetPage));
                    }
                }
            }

            if ($page > $pages) {
                $page = $pages;
            }
            $offset = ($page - 1) * $perPage;

            $threadMessages = $this->messages->findByThread($threadId, $viewerUserId, $perPage, $offset);
            if ($threadMessages === null) {
                return $this->notFound();
            }
        }

        $canReply           = !$root->closed && $this->perms->canReply($forum, $currentUser);
        $canModerate        = $this->perms->canModerate($forum, $currentUser);
        $canModerateUsers   = $this->perms->canModerateUsers($forum, $currentUser);
        $canViewAttachments = $this->perms->canViewAttachments($forum, $currentUser);

        $currentSub = SubscriberMapper::SUB_NONE;
        if ($currentUser !== null) {
            $currentSub = $this->subscriptions->getSubscription($currentUser->user_id, $forum->forum_id, $threadId);
        }

        $newflags    = $this->newflags;
        $approvedIds = array_map(
            fn($m) => $m->message_id,
            array_filter($threadMessages, fn($m) => $m->status === 2)
        );

        $newIds = [];
        if ($currentUser !== null) {
            $newIds = $newflags->getNewMessageIds($currentUser->user_id, $forumId, $approvedIds);
            $newflags->markRead($currentUser->user_id, $forumId, $approvedIds);
        }

        $this->messages->incrementViewCounts($threadId);

        $this->fileService->hydrateMessages($threadMessages);

        $hookResult = phorum_api_hook('read', $threadMessages);
        if (is_array($hookResult)) {
            $threadMessages = $hookResult;
        }

        $canEditIds = [];
        if ($currentUser !== null) {
            $canEditBit    = $this->perms->canEdit($forum, $currentUser);
            $editTimeLimit = (int) ($this->settings->getSetting('edit_time_limit') ?? 0);
            $canEditIds    = array_values(array_map(
                fn($m) => $m->message_id,
                array_filter($threadMessages, fn($m) => $this->canEditMessageWithContext(
                    $m, $currentUser, $canModerate, $canEditBit, $editTimeLimit
                ))
            ));
        }

        $userIds  = array_values(array_unique(array_filter(
            array_map(fn($m) => $m->user_id, $threadMessages),
            fn($id) => $id > 0
        )));
        $usersMap = $this->users->findByIds($userIds);

        return $this->respond($this->render('message/thread.html.twig', [
            'forum'        => $forum,
            'root'         => $root,
            'messages'     => $threaded
                                ? $this->buildTree($threadMessages, $threadId)
                                : $threadMessages,
            'threaded'     => $threaded,
            'page'         => $page,
            'pages'        => $pages,
            'base_url'     => Url::thread($forumId, $threadId),
            'can_reply'    => $canReply,
            'can_moderate' => $canModerate,
            'can_moderate_users' => $canModerateUsers,
            'can_view_attachments' => $canViewAttachments,
            'can_edit_ids' => $canEditIds,
            'current_sub'  => $currentSub,
            'SUB_NONE'     => SubscriberMapper::SUB_NONE,
            'new_ids'      => $newIds,
            'track_edits'  => (bool) ($this->config->get('track_edits', false) ?? false),
            'users_map'    => $usersMap,
            'theme'        => $this->resolveTheme($forum),
            'announcements' => $this->announcements->getAnnouncementsFor('read', $currentUser?->user_id ?? 0),
            'json_ld'      => $this->schemaOrg->thread($forum, $root, $threadMessages, (string) $this->config->get('site_name', 'Phorum')),
        ]));
    }

    public function post(Request $request): Response
    {
        $forumId  = (int) ($request->tokens['forum_id'] ?? 0);
        $parentId = (int) ($request->query['reply'] ?? 0);

        $forum = $this->forums->load($forumId);
        if ($forum === null || $forum->folder_flag) {
            return $this->notFound();
        }

        $user = Auth::user();
        if ($user === null) {
            return $this->redirect('/login');
        }

        $parent = null;
        if ($parentId > 0) {
            $parent = $this->messages->load($parentId);
            if ($parent === null || $parent->forum_id !== $forumId) {
                return $this->notFound();
            }
            if (!$this->perms->canReply($forum, $user)) {
                return $this->forbidden();
            }
            if ($parent->closed) {
                return $this->forbidden();
            }
        } else {
            if (!$this->perms->canNewThread($forum, $user)) {
                return $this->forbidden();
            }
        }

        $errors    = [];
        $subject   = '';
        $body      = '';
        $isPreview = false;

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $isPreview = ($request->post['action'] ?? '') === 'preview';
            $subject   = trim($request->post['subject'] ?? '');
            $body      = trim($request->post['body']    ?? '');

            if ($subject === '') {
                if ($parent !== null) {
                    $subject = 'Re: ' . $parent->subject;
                } else {
                    $errors[] = Lang::get('post.error_subject_required');
                }
            }
            if (mb_strlen($subject) > 255) {
                $errors[] = Lang::get('post.error_subject_length');
            }
            if ($body === '') {
                $errors[] = Lang::get('post.error_body_required');
            }

            if (!$isPreview) {
                if (empty($errors) && !$this->perms->canModerate($forum, $user)) {
                    $wait = $this->floodControl->secondsRemaining($user->user_id);
                    if ($wait > 0) {
                        $errors[] = Lang::get('post.error_flood_wait', ['seconds' => $wait]);
                    }
                }

                if (empty($errors)) {
                    $authorName = $user->display_name !== '' ? $user->display_name : $user->username;
                    if (
                        $this->banService->checkIp($forumId) ||
                        $this->banService->checkEmail($user->email, $forumId) ||
                        $this->banService->checkUsername($authorName, $forumId) ||
                        $this->banService->checkSpamWords($body, $forumId)
                    ) {
                        $errors[] = Lang::get('post.error_posting_blocked');
                    }
                }

                if (empty($errors)) {
                    $msg = $this->messageService->post($forum, $user, $subject, $body, $parentId);

                    if ($msg->status === 2) {
                        $this->searchIndex->indexMessage(
                            $msg->message_id, $msg->forum_id, $msg->author, $msg->subject, $msg->body
                        );
                    }

                    // Store any uploaded attachments
                    $this->storeUploads($forum, $msg->message_id, $user, $errors, $request);

                    if (empty($errors)) {
                        if ($msg->status === 2) {
                            $this->subscriptions->notifySubscribers($msg, $forum, excludeUserId: $user->user_id);
                        }
                        $this->subscriptions->notifyModerators($msg, $forum);

                        return $this->redirect(Url::thread(
                            $forumId, $msg->thread, $msg->message_id,
                            $this->resolveMessagePage($forum, $msg->thread, $msg->message_id, $user->user_id)
                        ));
                    }
                }
            }
        } elseif ($parent !== null) {
            $subject = 'Re: ' . $parent->subject;
        }

        $showPreview = $isPreview && empty($errors);
        $previewMsg  = null;
        $previewUsersMap = [];

        if ($showPreview) {
            $previewMsg              = new Message();
            $previewMsg->forum_id    = $forum->forum_id;
            $previewMsg->thread      = $parent->thread ?? 0;
            $previewMsg->parent_id   = $parentId;
            $previewMsg->user_id     = $user->user_id;
            $previewMsg->author      = $user->display_name !== '' ? $user->display_name : $user->username;
            $previewMsg->subject     = $subject;
            $previewMsg->body        = $body;
            $previewMsg->datestamp   = time();
            $previewMsg->status      = $forum->moderation > 0
                                        ? MessageMapper::STATUS_UNAPPROVED
                                        : MessageMapper::STATUS_APPROVED;
            $previewMsg->meta        = MessageMeta::fromArray(['format' => 'markdown'])->encode();
            $previewUsersMap         = [$user->user_id => $user];
        }

        return $this->respond($this->render('message/post.html.twig', [
            'forum'             => $forum,
            'parent'            => $parent,
            'subject'           => $subject,
            'body'              => $body,
            'errors'            => $errors,
            'theme'             => $this->resolveTheme($forum),
            'show_preview'      => $showPreview,
            'preview_msg'       => $previewMsg,
            'preview_users_map' => $previewUsersMap,
            'uploads_enabled'   => $this->uploadsGloballyEnabled() || (bool) $user->admin,
        ]));
    }

    public function editMessage(Request $request): Response
    {
        $messageId = (int) ($request->tokens['message_id'] ?? 0);
        $msg       = $this->messages->load($messageId);

        if ($msg === null || $msg->status === MessageMapper::STATUS_DELETED) {
            return $this->notFound();
        }

        $forum = $this->forums->load($msg->forum_id);
        if ($forum === null) {
            return $this->notFound();
        }

        $currentUser = Auth::user();

        if (!$this->canEditMessage($msg, $forum, $currentUser)) {
            return $this->forbidden();
        }

        $errors    = [];
        $subject   = $msg->subject;
        $body      = $msg->body;
        $isPreview = false;

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $isPreview = ($request->post['action'] ?? '') === 'preview';
            $subject   = trim($request->post['subject'] ?? '');
            $body      = trim($request->post['body']    ?? '');

            if ($subject === '') {
                $errors[] = Lang::get('post.error_subject_required');
            }
            if (mb_strlen($subject) > 255) {
                $errors[] = Lang::get('post.error_subject_length');
            }
            if ($body === '') {
                $errors[] = Lang::get('post.error_body_required');
            }

            if (!$isPreview && empty($errors)) {
                $tracker = ($this->config->get('track_edits', false) ?? false)
                    ? new MessageTrackingMapper()
                    : null;
                $msg = $this->messageService->edit(
                    $msg, $subject, $body,
                    editorUserId: $currentUser->user_id,
                    tracker: $tracker,
                );

                if ($msg->status === MessageMapper::STATUS_APPROVED) {
                    $this->searchIndex->indexMessage(
                        $msg->message_id, $msg->forum_id, $msg->author, $msg->subject, $msg->body
                    );
                }

                // Delete attachments the user checked for removal
                $deleteIds = array_map('intval', (array) ($request->post['delete_file'] ?? []));
                foreach ($deleteIds as $fid) {
                    $f = (new FileMapper())->load($fid);
                    if ($f !== null && $f->message_id === $msg->message_id) {
                        $this->fileService->delete($f);
                    }
                }

                // Store any new uploads
                $this->storeUploads($forum, $msg->message_id, $currentUser, $errors, $request);

                if (empty($errors)) {
                    return $this->redirect(Url::thread(
                        $msg->forum_id, $msg->thread, $msg->message_id,
                        $this->resolveMessagePage($forum, $msg->thread, $msg->message_id, $currentUser->user_id)
                    ));
                }
            }
        }

        $attachments = $this->fileService->getAttachments($msg->message_id);

        $showPreview = $isPreview && empty($errors);
        $previewMsg  = null;
        $previewUsersMap = [];

        if ($showPreview) {
            $previewMsg          = clone $msg;
            $previewMsg->subject = $subject;
            $previewMsg->body    = $body;
            $previewUsersMap     = $msg->user_id > 0 ? $this->users->findByIds([$msg->user_id]) : [];
        }

        return $this->respond($this->render('message/edit.html.twig', [
            'forum'             => $forum,
            'msg'               => $msg,
            'subject'           => $subject,
            'body'              => $body,
            'errors'            => $errors,
            'attachments'       => $attachments,
            'theme'             => $this->resolveTheme($forum),
            'show_preview'      => $showPreview,
            'preview_msg'       => $previewMsg,
            'preview_users_map' => $previewUsersMap,
            'uploads_enabled'   => $this->uploadsGloballyEnabled() || (bool) $currentUser->admin,
        ]));
    }

    public function changes(Request $request): Response
    {
        $messageId = (int) ($request->tokens['message_id'] ?? 0);
        $msg       = $this->messages->load($messageId);

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

        if (!($this->config->get('track_edits', false) ?? false)) {
            return $this->redirect(Url::thread($msg->forum_id, $msg->thread, $messageId));
        }

        $tracking = (new MessageTrackingMapper())->findByMessage($messageId);
        if (empty($tracking)) {
            return $this->redirect(Url::thread($msg->forum_id, $msg->thread, $messageId));
        }

        // Build version sequence: each tracking row holds the content BEFORE
        // that edit. The version AFTER the final edit is the current message.
        // We pair consecutive versions to show what changed in each edit.
        $diff     = new DiffRenderer();
        $versions = [];
        $count    = count($tracking);

        for ($i = 0; $i < $count; $i++) {
            $track   = $tracking[$i];
            $oldBody    = $track->diff_body;
            $oldSubject = $track->diff_subject;
            $newBody    = isset($tracking[$i + 1]) ? $tracking[$i + 1]->diff_body : $msg->body;
            $newSubject = isset($tracking[$i + 1]) ? $tracking[$i + 1]->diff_subject : $msg->subject;

            $versions[] = [
                'track_id'      => $track->track_id,
                'user_id'       => $track->user_id,
                'time'          => $track->time,
                'subject_diff'  => $diff->renderHtml($oldSubject, $newSubject),
                'body_diff'     => $diff->renderHtml($oldBody, $newBody),
                'subject_same'  => $oldSubject === $newSubject,
            ];
        }

        // Show newest edits first
        $versions = array_reverse($versions);

        return $this->respond($this->render('message/changes.html.twig', [
            'forum'    => $forum,
            'msg'      => $msg,
            'versions' => $versions,
            'theme'    => $this->resolveTheme($forum),
        ]));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a nested tree for threaded display.
     * Each message gets a 'children' key containing its direct replies.
     *
     * @param  Message[] $messages
     * @return Message[]  top-level messages (only the root in a normal thread)
     */
    private function buildTree(array $messages, int $threadId): array
    {
        $byParent = [];
        $roots    = [];

        foreach ($messages as $msg) {
            if ($msg->parent_id === 0) {
                $roots[] = $msg;
            } else {
                $byParent[$msg->parent_id][] = $msg;
            }
        }

        foreach ($messages as $msg) {
            $msg->children = $byParent[$msg->message_id] ?? [];
        }

        return $roots;
    }

    /**
     * Resolve which flat-mode page a message lands on, for redirects after
     * posting/editing — a new reply is always the newest message, so it
     * always lands on the last page, not page 1. Returns null in threaded
     * mode (no pagination to resolve) or when the position can't be found.
     */
    private function resolveMessagePage(Forum $forum, int $threadId, int $messageId, ?int $viewerUserId): ?int
    {
        if ($forum->threaded_read) {
            return null;
        }

        $perPage  = $forum->read_length ?: 25;
        $position = $this->messages->findMessagePosition($threadId, $messageId, $viewerUserId);

        return $position !== null ? max(1, (int) ceil($position / $perPage)) : null;
    }

    /**
     * Process $_FILES['files'] uploads, validate against forum limits, and
     * store each file. Errors are appended to $errors (passed by reference).
     *
     * @param string[] $errors
     */
    private function storeUploads(\Phorum\Model\Forum $forum, int $messageId, User $user, array &$errors, Request $request): void
    {
        if (empty($request->files['files']['name'])) {
            return;
        }

        if (!$this->uploadsGloballyEnabled() && !$user->admin) {
            $errors[] = Lang::get('attachment.error_uploads_disabled');
            return;
        }

        $existingFiles  = $this->fileService->getAttachments($messageId);
        $existingCount  = count($existingFiles);
        $existingBytes  = array_sum(array_map(fn($f) => $f->filesize, $existingFiles));

        $count = count($request->files['files']['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($request->files['files']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $phpFile = [
                'name'     => $request->files['files']['name'][$i],
                'size'     => $request->files['files']['size'][$i],
                'error'    => $request->files['files']['error'][$i],
                'tmp_name' => $request->files['files']['tmp_name'][$i],
            ];

            $err = $this->fileService->validateUpload($phpFile, $forum, $existingCount, $existingBytes);
            if ($err !== null) {
                $errors[] = $err;
                continue;
            }

            $file = $this->fileService->store($phpFile, $user->user_id, $messageId);
            if ($file !== null) {
                $existingCount++;
                $existingBytes += $file->filesize;
            }
        }
    }

    /** The site-wide file_uploads toggle (Admin > Settings), default enabled when unset. */
    private function uploadsGloballyEnabled(): bool
    {
        return (bool) ($this->settings->getSetting('file_uploads') ?? true);
    }
}
