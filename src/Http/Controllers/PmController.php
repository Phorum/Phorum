<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Core\Config;
use Phorum\Http\Controller;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\PmBuddyMapper;
use Phorum\Mapper\PmFolderMapper;
use Phorum\Mapper\PmMessageMapper;
use Phorum\Mapper\PmXrefMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Model\MessageMeta;
use Phorum\Service\MailService;
use Phorum\Service\PmService;
use Twig\Environment;

class PmController extends Controller
{
    private readonly PmService     $pmService;
    private readonly UserMapper    $users;
    private readonly PmBuddyMapper $buddies;

    public function __construct(
        Config          $config,
        Environment     $twig,
        ?PmService      $pmService = null,
        ?UserMapper     $users     = null,
        ?PmBuddyMapper  $buddies   = null,
    ) {
        parent::__construct($config, $twig);
        $this->pmService = $pmService ?? new PmService(
            messages: new PmMessageMapper(),
            xrefs:    new PmXrefMapper(),
            folders:  new PmFolderMapper(),
            users:    new UserMapper(),
            mailer:   new MailService($config),
            config:   $config,
        );
        $this->users   = $users   ?? new UserMapper();
        $this->buddies = $buddies ?? new PmBuddyMapper();
    }

    private function requireLogin(Request $request): ?Response
    {
        if (Auth::user() === null) {
            return $this->redirect('/login?redirect=' . urlencode($request->server['REQUEST_URI'] ?? '/pm'));
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Inbox
    // -------------------------------------------------------------------------

    public function inbox(Request $request): Response
    {
        if ($r = $this->requireLogin($request)) { return $r; }

        $user    = Auth::user();
        $service = $this->pmService;
        $rows    = $service->listFolder($user->user_id, specialFolder: 'inbox');
        $folders = $service->listFolders($user->user_id);

        return $this->respond($this->render('pm/list.html.twig', [
            'folder_name' => 'Inbox',
            'folder_key'  => 'inbox',
            'rows'        => $rows,
            'folders'     => $folders,
        ]));
    }

    // -------------------------------------------------------------------------
    // Outbox
    // -------------------------------------------------------------------------

    public function outbox(Request $request): Response
    {
        if ($r = $this->requireLogin($request)) { return $r; }

        $user    = Auth::user();
        $service = $this->pmService;
        $rows    = $service->listFolder($user->user_id, specialFolder: 'outbox');
        $folders = $service->listFolders($user->user_id);

        return $this->respond($this->render('pm/list.html.twig', [
            'folder_name' => 'Outbox',
            'folder_key'  => 'outbox',
            'rows'        => $rows,
            'folders'     => $folders,
        ]));
    }

    // -------------------------------------------------------------------------
    // Custom folder
    // -------------------------------------------------------------------------

    public function folder(Request $request): Response
    {
        if ($r = $this->requireLogin($request)) { return $r; }

        $user       = Auth::user();
        $pmFolderId = (int) ($request->tokens['pm_folder_id'] ?? 0);
        $service    = $this->pmService;

        // Verify folder belongs to user
        $folders    = $service->listFolders($user->user_id);
        $activeFolder = null;
        foreach ($folders as $f) {
            if ($f->pm_folder_id === $pmFolderId) {
                $activeFolder = $f;
                break;
            }
        }

        if ($activeFolder === null) {
            return $this->notFound();
        }

        $rows = $service->listCustomFolder($user->user_id, $pmFolderId);

        return $this->respond($this->render('pm/list.html.twig', [
            'folder_name'  => $activeFolder->foldername,
            'folder_key'   => 'custom',
            'pm_folder_id' => $pmFolderId,
            'rows'         => $rows,
            'folders'      => $folders,
        ]));
    }

    // -------------------------------------------------------------------------
    // Compose / Reply
    // -------------------------------------------------------------------------

    public function compose(Request $request): Response
    {
        if ($r = $this->requireLogin($request)) { return $r; }

        $user    = Auth::user();
        $service = $this->pmService;
        $users   = $this->users;
        $errors  = [];
        $success = false;

        // Pre-fill recipient from URL token or from replying to a PM
        $toUserId    = (int) ($request->tokens['to_user_id'] ?? 0);
        $replyXrefId = (int) ($request->tokens['reply_xref_id'] ?? 0);
        $toUser      = $toUserId > 0 ? $users->load($toUserId) : null;
        $replySubject = '';
        $replyBody    = '';

        if ($replyXrefId > 0) {
            $data = $service->getMessage($replyXrefId, $user->user_id);
            if ($data !== null) {
                $orig = $data['message'];
                $toUser       = $users->load($orig->user_id);
                $replySubject = str_starts_with($orig->subject, 'Re: ')
                    ? $orig->subject
                    : 'Re: ' . $orig->subject;
                $replyBody    = "\n\n--- {$orig->author} wrote ---\n"
                    . wordwrap($orig->message, 72, "\n", true);
            }
        }

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $toUsername = trim($request->post['to_username'] ?? '');
            $subject    = trim($request->post['subject']     ?? '');
            $body       = trim($request->post['body']        ?? '');

            if ($toUsername === '') {
                $errors[] = 'Recipient is required.';
            } else {
                $toUser = $users->findByUsername($toUsername);
                if ($toUser === null || !$toUser->active) {
                    $errors[] = "User \"{$toUsername}\" not found.";
                }
            }

            if ($subject === '') {
                $errors[] = 'Subject is required.';
            }

            if ($body === '') {
                $errors[] = 'Message body is required.';
            }

            if (empty($errors) && $toUser !== null) {
                $service->send(
                    fromUserId: $user->user_id,
                    author:     $user->display_name !== '' ? $user->display_name : $user->username,
                    toUserIds:  [$toUser->user_id],
                    subject:    $subject,
                    body:       $body,
                );
                return $this->redirect('/pm');
            }
        }

        return $this->respond($this->render('pm/compose.html.twig', [
            'to_user'      => $toUser,
            'subject'      => $request->post['subject']  ?? $replySubject,
            'body'         => $request->post['body']      ?? $replyBody,
            'errors'       => $errors,
            'folders'      => $service->listFolders($user->user_id),
        ]));
    }

    // -------------------------------------------------------------------------
    // Read
    // -------------------------------------------------------------------------

    public function read(Request $request): Response
    {
        if ($r = $this->requireLogin($request)) { return $r; }

        $user      = Auth::user();
        $pmXrefId  = (int) ($request->tokens['pm_xref_id'] ?? 0);
        $service   = $this->pmService;

        $data = $service->getMessage($pmXrefId, $user->user_id);
        if ($data === null) {
            return $this->notFound();
        }

        $xref    = $data['xref'];
        $message = $data['message'];

        $recipients = MessageMeta::decode($message->meta)->get('recipients', []);

        $sender = $this->users->load($message->user_id);

        return $this->respond($this->render('pm/read.html.twig', [
            'xref'       => $xref,
            'message'    => $message,
            'recipients' => $recipients,
            'sender'     => $sender,
            'folders'    => $service->listFolders($user->user_id),
        ]));
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    public function delete(Request $request): Response
    {
        if ($r = $this->requireLogin($request)) { return $r; }

        $user     = Auth::user();
        $pmXrefId = (int) ($request->tokens['pm_xref_id'] ?? 0);

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $this->pmService->delete($pmXrefId, $user->user_id);
            return $this->redirect('/pm');
        }

        // GET — confirmation page
        $data = $this->pmService->getMessage($pmXrefId, $user->user_id);
        if ($data === null) {
            return $this->notFound();
        }

        return $this->respond($this->render('pm/delete_confirm.html.twig', [
            'xref'    => $data['xref'],
            'message' => $data['message'],
        ]));
    }

    // -------------------------------------------------------------------------
    // Move
    // -------------------------------------------------------------------------

    public function move(Request $request): Response
    {
        if ($r = $this->requireLogin($request)) { return $r; }

        $user       = Auth::user();
        $pmXrefId   = (int) ($request->tokens['pm_xref_id'] ?? 0);
        $pmFolderId = (int) ($request->post['pm_folder_id'] ?? 0);

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            if ($pmFolderId > 0) {
                $this->pmService->move($pmXrefId, $user->user_id, $pmFolderId);
            }
        }

        return $this->redirect('/pm');
    }

    // -------------------------------------------------------------------------
    // Folder management
    // -------------------------------------------------------------------------

    public function folders(Request $request): Response
    {
        if ($r = $this->requireLogin($request)) { return $r; }

        $user    = Auth::user();
        $service = $this->pmService;
        $errors  = [];

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $name = trim($request->post['foldername'] ?? '');
            if ($name === '') {
                $errors[] = 'Folder name is required.';
            } elseif (mb_strlen($name) > 60) {
                $errors[] = 'Folder name must be 60 characters or fewer.';
            } else {
                $service->createFolder($user->user_id, $name);
                return $this->redirect('/pm/folders');
            }
        }

        return $this->respond($this->render('pm/folders.html.twig', [
            'folders' => $service->listFolders($user->user_id),
            'errors'  => $errors,
        ]));
    }

    public function deleteFolder(Request $request): Response
    {
        if ($r = $this->requireLogin($request)) { return $r; }

        $user       = Auth::user();
        $pmFolderId = (int) ($request->tokens['pm_folder_id'] ?? 0);

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $this->pmService->deleteFolder($pmFolderId, $user->user_id);
        }

        return $this->redirect('/pm/folders');
    }

    // -------------------------------------------------------------------------
    // Buddy list
    // -------------------------------------------------------------------------

    public function buddyList(Request $request): Response
    {
        if ($r = $this->requireLogin($request)) { return $r; }

        $user    = Auth::user();
        $buddies = $this->buddies->listBuddies($user->user_id);
        $folders = $this->pmService->listFolders($user->user_id);

        $buddies = phorum_api_hook('buddy_list', $buddies) ?? $buddies;

        return $this->respond($this->render('pm/buddies.html.twig', [
            'buddies' => $buddies,
            'folders' => $folders,
        ]));
    }

    public function addBuddy(Request $request): Response
    {
        if ($r = $this->requireLogin($request)) { return $r; }

        $user        = Auth::user();
        $buddyUserId = (int) ($request->tokens['buddy_user_id'] ?? 0);

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            if ($buddyUserId > 0 && $buddyUserId !== $user->user_id) {
                $buddyUser = $this->users->load($buddyUserId);
                if ($buddyUser !== null && $buddyUser->active) {
                    $this->buddies->add($user->user_id, $buddyUserId);
                    phorum_api_hook('buddy_add', $buddyUserId);
                }
            }
        }

        return $this->redirect($this->safeReturnTo($request, '/pm/buddies'));
    }

    public function removeBuddy(Request $request): Response
    {
        if ($r = $this->requireLogin($request)) { return $r; }

        $user        = Auth::user();
        $buddyUserId = (int) ($request->tokens['buddy_user_id'] ?? 0);

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            if ($buddyUserId > 0) {
                $this->buddies->remove($user->user_id, $buddyUserId);
                phorum_api_hook('buddy_delete', $buddyUserId);
            }
        }

        return $this->redirect($this->safeReturnTo($request, '/pm/buddies'));
    }

    /** Validate a return_to POST field: must be an internal path. */
    private function safeReturnTo(Request $request, string $fallback): string
    {
        $path = trim($request->post['return_to'] ?? '');
        return (str_starts_with($path, '/') && !str_starts_with($path, '//'))
            ? $path
            : $fallback;
    }
}
