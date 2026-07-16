<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Core\Config;
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
        $this->messageService = $messageService ?? new MessageService($this->messages, $this->forums, $this->users);
        $this->announcements  = $announcements  ?? new AnnouncementService();
        $this->floodControl   = $floodControl   ?? new FloodControlService($this->messages, $this->settings);
        $this->schemaOrg      = $schemaOrg      ?? new SchemaOrgService($config);
    }

    /**
     * True if $currentUser may edit $msg in $forum. Moderators always may;
     * otherwise the post must be their own, the ALLOW_EDIT permission bit
     * set, the thread not closed, and (if configured) within the site-wide
     * edit time limit since the post was made.
     */
    private function canEditMessage(Message $msg, Forum $forum, ?User $currentUser): bool
    {
        if ($currentUser === null) {
            return false;
        }
        if ($this->perms->canModerate($forum, $currentUser)) {
            return true;
        }
        if ($msg->user_id !== $currentUser->user_id) {
            return false;
        }
        if ($msg->closed) {
            return false;
        }
        if (!$this->perms->canEdit($forum, $currentUser)) {
            return false;
        }

        $limit = (int) ($this->settings->getSetting('edit_time_limit') ?? 0);
        if ($limit > 0 && (time() - $msg->datestamp) > $limit * 60) {
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

        $threadMessages = $this->messages->findByThread($threadId);
        if ($threadMessages === null) {
            return $this->notFound();
        }

        // The root message is the one whose message_id equals the thread id
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

        $currentUser = Auth::user();
        $threaded    = (bool) $forum->threaded_read;
        $canReply    = !$root->closed && $this->perms->canReply($forum, $currentUser);
        $canModerate = $this->perms->canModerate($forum, $currentUser);

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

        $canEditIds = array_values(array_map(
            fn($m) => $m->message_id,
            array_filter($threadMessages, fn($m) => $this->canEditMessage($m, $forum, $currentUser))
        ));

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
            'can_reply'    => $canReply,
            'can_moderate' => $canModerate,
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

        $errors  = [];
        $subject = '';
        $body    = '';

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $subject = trim($request->post['subject'] ?? '');
            $body    = trim($request->post['body']    ?? '');

            if ($subject === '') {
                if ($parent !== null) {
                    $subject = 'Re: ' . $parent->subject;
                } else {
                    $errors[] = 'Subject is required.';
                }
            }
            if (mb_strlen($subject) > 255) {
                $errors[] = 'Subject must be 255 characters or fewer.';
            }
            if ($body === '') {
                $errors[] = 'Message body is required.';
            }

            if (empty($errors) && !$this->perms->canModerate($forum, $user)) {
                $wait = $this->floodControl->secondsRemaining($user->user_id);
                if ($wait > 0) {
                    $errors[] = "Please wait {$wait} more second(s) before posting again.";
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
                    $errors[] = 'Posting is not allowed from your account.';
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
                $this->storeUploads($forum, $msg->message_id, $user->user_id, $errors, $request);

                if (empty($errors)) {
                    if ($msg->status === 2) {
                        $this->subscriptions->notifySubscribers($msg, $forum, excludeUserId: $user->user_id);
                    }
                    $this->subscriptions->notifyModerators($msg, $forum);

                    return $this->redirect("/forum/{$forumId}/thread/{$msg->thread}#msg-{$msg->message_id}");
                }
            }
        } elseif ($parent !== null) {
            $subject = 'Re: ' . $parent->subject;
        }

        return $this->respond($this->render('message/post.html.twig', [
            'forum'    => $forum,
            'parent'   => $parent,
            'subject'  => $subject,
            'body'     => $body,
            'errors'   => $errors,
            'theme'    => $this->resolveTheme($forum),
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

        $errors  = [];
        $subject = $msg->subject;
        $body    = $msg->body;

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $subject = trim($request->post['subject'] ?? '');
            $body    = trim($request->post['body']    ?? '');

            if ($subject === '') {
                $errors[] = 'Subject is required.';
            }
            if (mb_strlen($subject) > 255) {
                $errors[] = 'Subject must be 255 characters or fewer.';
            }
            if ($body === '') {
                $errors[] = 'Message body is required.';
            }

            if (empty($errors)) {
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
                $this->storeUploads($forum, $msg->message_id, $currentUser->user_id, $errors, $request);

                if (empty($errors)) {
                    return $this->redirect("/forum/{$msg->forum_id}/thread/{$msg->thread}#msg-{$msg->message_id}");
                }
            }
        }

        $attachments = $this->fileService->getAttachments($msg->message_id);

        return $this->respond($this->render('message/edit.html.twig', [
            'forum'       => $forum,
            'msg'         => $msg,
            'subject'     => $subject,
            'body'        => $body,
            'errors'      => $errors,
            'attachments' => $attachments,
            'theme'       => $this->resolveTheme($forum),
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
            return $this->redirect("/forum/{$msg->forum_id}/thread/{$msg->thread}#msg-{$messageId}");
        }

        $tracking = (new MessageTrackingMapper())->findByMessage($messageId);
        if (empty($tracking)) {
            return $this->redirect("/forum/{$msg->forum_id}/thread/{$msg->thread}#msg-{$messageId}");
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
     * Process $_FILES['files'] uploads, validate against forum limits, and
     * store each file. Errors are appended to $errors (passed by reference).
     *
     * @param string[] $errors
     */
    private function storeUploads(\Phorum\Model\Forum $forum, int $messageId, int $userId, array &$errors, Request $request): void
    {
        if (empty($request->files['files']['name'])) {
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

            $file = $this->fileService->store($phpFile, $userId, $messageId);
            if ($file !== null) {
                $existingCount++;
                $existingBytes += $file->filesize;
            }
        }
    }
}
