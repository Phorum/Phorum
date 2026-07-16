<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Core\Config;
use Phorum\Core\RedirectGuard;
use Phorum\Http\Controller;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\FileMapper;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\PmBuddyMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Service\FileService;
use Twig\Environment;

class UserController extends Controller
{
    private readonly UserMapper    $users;
    private readonly MessageMapper $messages;
    private readonly FileService   $fileService;
    private readonly FileMapper    $fileMapper;
    private readonly PmBuddyMapper $buddies;

    public function __construct(
        Config          $config,
        Environment     $twig,
        ?UserMapper     $users       = null,
        ?MessageMapper  $messages    = null,
        ?FileService    $fileService = null,
        ?FileMapper     $fileMapper  = null,
        ?PmBuddyMapper  $buddies     = null,
    ) {
        parent::__construct($config, $twig);
        $this->fileMapper  = $fileMapper  ?? new FileMapper();
        $this->users       = $users       ?? new UserMapper();
        $this->messages    = $messages    ?? new MessageMapper();
        $this->fileService = $fileService ?? new FileService($this->fileMapper);
        $this->buddies     = $buddies     ?? new PmBuddyMapper();
    }

    // -------------------------------------------------------------------------
    // Public profile
    // -------------------------------------------------------------------------

    public function profile(Request $request): Response
    {
        $userId = (int) ($request->tokens['user_id'] ?? 0);

        $profile = $this->users->load($userId);

        if ($profile === null || !$profile->active) {
            return $this->notFound();
        }

        $recentPosts = $this->messages->findByUser($userId, limit: 15);
        $avatar      = $this->fileMapper->findAvatarForUser($userId);

        $viewer   = Auth::user();
        $isBuddy  = ($viewer !== null && $viewer->user_id !== $userId)
            ? $this->buddies->isBuddy($viewer->user_id, $userId)
            : false;

        return $this->respond($this->render('user/profile.html.twig', [
            'profile'      => $profile,
            'recent_posts' => $recentPosts ?? [],
            'avatar'       => $avatar,
            'is_buddy'     => $isBuddy,
        ]));
    }

    // -------------------------------------------------------------------------
    // Settings (authenticated)
    // -------------------------------------------------------------------------

    public function settings(Request $request): Response
    {
        $currentUser = Auth::user();
        if ($currentUser === null) {
            return $this->redirect('/login?redirect=/user/settings');
        }

        $errors      = [];
        $success     = false;
        $avatarFile  = $this->fileMapper->findAvatarForUser($currentUser->user_id);

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $displayName  = trim($request->post['display_name']   ?? '');
            $email        = trim($request->post['email']          ?? '');
            $password     = $request->post['password']            ?? '';
            $password2    = $request->post['password2']           ?? '';
            $signature    = trim($request->post['signature']      ?? '');
            $showSig      = !empty($request->post['show_signature']);
            $hideEmail    = !empty($request->post['hide_email']);
            $threadedList = !empty($request->post['threaded_list']);
            $threadedRead = !empty($request->post['threaded_read']);
            $emailNotify  = !empty($request->post['email_notify']);
            $pmNotify     = !empty($request->post['pm_email_notify']);
            $tzOffset     = (float) ($request->post['tz_offset']  ?? -99);
            $deleteAvatar = !empty($request->post['delete_avatar']);

            // Validate avatar if one was uploaded
            $phpAvatar = null;
            $rawUpload = $request->files['avatar'] ?? [];
            if (!empty($rawUpload['name']) && ($rawUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $phpAvatar    = $rawUpload;
                $maxBytes     = (int) $this->config->get('avatar_max_size', 100 * 1024);
                $avatarErr    = $this->fileService->validateAvatarUpload($phpAvatar, $maxBytes);
                if ($avatarErr !== null) {
                    $errors[]  = $avatarErr;
                    $phpAvatar = null;
                }
            }

            if ($displayName === '') {
                $errors[] = 'Display name is required.';
            } elseif (mb_strlen($displayName) > 50) {
                $errors[] = 'Display name must be 50 characters or fewer.';
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'A valid email address is required.';
            } elseif ($email !== $currentUser->email) {
                $taken = $this->users->findByEmail($email);
                if ($taken !== null && $taken->user_id !== $currentUser->user_id) {
                    $errors[] = 'That email address is already in use by another account.';
                }
            }

            if ($password !== '') {
                if (strlen($password) < 6) {
                    $errors[] = 'New password must be at least 6 characters.';
                } elseif ($password !== $password2) {
                    $errors[] = 'Passwords do not match.';
                }
            }

            if ($tzOffset !== -99.0 && ($tzOffset < -12.0 || $tzOffset > 14.0)) {
                $errors[] = 'Timezone offset must be between -12 and +14, or -99 for server time.';
            }

            if (empty($errors)) {
                $currentUser->display_name    = $displayName;
                $currentUser->email           = $email;
                $currentUser->signature       = $signature;
                $currentUser->show_signature  = $showSig ? 1 : 0;
                $currentUser->hide_email      = $hideEmail ? 1 : 0;
                $currentUser->threaded_list   = $threadedList ? 1 : 0;
                $currentUser->threaded_read   = $threadedRead ? 1 : 0;
                $currentUser->email_notify    = $emailNotify ? 1 : 0;
                $currentUser->pm_email_notify = $pmNotify ? 1 : 0;
                $currentUser->tz_offset       = $tzOffset;

                if ($password !== '') {
                    $currentUser->password = password_hash($password, PASSWORD_BCRYPT);
                    $currentUser->force_password_change = 0;
                }

                $this->users->save($currentUser);
                Auth::setUser($currentUser);

                if ($deleteAvatar || $phpAvatar !== null) {
                    $this->fileService->deleteAvatarForUser($currentUser->user_id);
                    $avatarFile = null;
                }
                if ($phpAvatar !== null) {
                    $avatarFile = $this->fileService->storeAvatar($phpAvatar, $currentUser->user_id);
                }

                $success = true;
            }
        }

        return $this->respond($this->render('user/settings.html.twig', [
            'profile'    => $currentUser,
            'avatar'     => $avatarFile,
            'errors'     => $errors,
            'success'    => $success,
        ]));
    }

    // -------------------------------------------------------------------------
    // Forced password change (admin-flagged accounts)
    // -------------------------------------------------------------------------

    public function forcePasswordChange(Request $request): Response
    {
        $currentUser = Auth::user();
        if ($currentUser === null) {
            return $this->redirect('/login?redirect=' . urlencode('/user/change-password'));
        }

        $redirect = RedirectGuard::sanitizePath($request->query['redirect'] ?? $request->post['redirect'] ?? '/');

        $errors = [];

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $password  = $request->post['password']  ?? '';
            $password2 = $request->post['password2'] ?? '';

            if (strlen($password) < 6) {
                $errors[] = 'New password must be at least 6 characters.';
            } elseif ($password !== $password2) {
                $errors[] = 'Passwords do not match.';
            }

            if (empty($errors)) {
                $currentUser->password               = password_hash($password, PASSWORD_BCRYPT);
                $currentUser->force_password_change   = 0;
                $this->users->save($currentUser);
                Auth::setUser($currentUser);

                return $this->redirect($redirect);
            }
        }

        return $this->respond($this->render('user/change_password.html.twig', [
            'errors'   => $errors,
            'redirect' => $redirect,
        ]));
    }
}
