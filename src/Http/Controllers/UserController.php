<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Core\Config;
use Phorum\Core\Lang;
use Phorum\Core\RedirectGuard;
use Phorum\Http\Controller;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\FileMapper;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\PmBuddyMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Mapper\UserPermissionMapper;
use Phorum\Service\FileService;
use Phorum\Service\PermissionService;
use Twig\Environment;

class UserController extends Controller
{
    private readonly UserMapper       $users;
    private readonly MessageMapper    $messages;
    private readonly FileService      $fileService;
    private readonly FileMapper       $fileMapper;
    private readonly PmBuddyMapper    $buddies;
    private readonly PermissionService $perms;

    public function __construct(
        Config              $config,
        Environment         $twig,
        ?UserMapper         $users       = null,
        ?MessageMapper      $messages    = null,
        ?FileService        $fileService = null,
        ?FileMapper         $fileMapper  = null,
        ?PmBuddyMapper      $buddies     = null,
        ?PermissionService  $perms       = null,
    ) {
        parent::__construct($config, $twig);
        $this->fileMapper  = $fileMapper  ?? new FileMapper();
        $this->users       = $users       ?? new UserMapper();
        $this->messages    = $messages    ?? new MessageMapper();
        $this->fileService = $fileService ?? new FileService($this->fileMapper);
        $this->buddies     = $buddies     ?? new PmBuddyMapper();
        $this->perms       = $perms       ?? new PermissionService(new UserPermissionMapper());
    }

    // -------------------------------------------------------------------------
    // Public profile
    // -------------------------------------------------------------------------

    public function profile(Request $request): Response
    {
        $userId = (int) ($request->tokens['user_id'] ?? 0);

        $profile = $this->users->load($userId);

        // Deliberately a loose falsy check, not `!== 1`: only literal 0
        // (INACTIVE) hides a profile entirely — pending accounts (negative
        // active values) still have a viewable profile, matching legacy
        // Phorum 6's `active == 0` check in profile.php. PHP only treats
        // exactly 0 as falsy among ints, so this already implements that.
        if ($profile === null || !$profile->active) {
            return $this->notFound();
        }

        $viewer      = Auth::user();
        $recentPosts = $this->messages->findByUser($userId, limit: 15, viewerUserId: $viewer?->user_id);
        $avatar      = $this->fileMapper->findAvatarForUser($userId);

        $isBuddy  = ($viewer !== null && $viewer->user_id !== $userId)
            ? $this->buddies->isBuddy($viewer->user_id, $userId)
            : false;

        // Site admins and ALLOW_MODERATE_USERS holders bypass hide_email/hide_activity.
        $canViewHidden = ($viewer?->admin ?? false) || $this->perms->canModerateUsersAnywhere($viewer);

        return $this->respond($this->render('user/profile.html.twig', [
            'profile'         => $profile,
            'recent_posts'    => $recentPosts ?? [],
            'avatar'          => $avatar,
            'is_buddy'        => $isBuddy,
            'can_view_hidden' => $canViewHidden,
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
            $threadedRead = !empty($request->post['threaded_read']);
            $emailNotify  = (int) ($request->post['email_notify'] ?? 0);
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
                $errors[] = Lang::get('settings.error_display_name_required');
            } elseif (mb_strlen($displayName) > 50) {
                $errors[] = Lang::get('settings.error_display_name_length');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = Lang::get('settings.error_email_required');
            } elseif ($email !== $currentUser->email) {
                $taken = $this->users->findByEmail($email);
                if ($taken !== null && $taken->user_id !== $currentUser->user_id) {
                    $errors[] = Lang::get('settings.error_email_taken');
                }
            }

            if ($password !== '') {
                if (strlen($password) < 6) {
                    $errors[] = Lang::get('settings.error_password_min_length');
                } elseif ($password !== $password2) {
                    $errors[] = Lang::get('settings.error_passwords_mismatch');
                }
            }

            if ($tzOffset !== -99.0 && ($tzOffset < -12.0 || $tzOffset > 14.0)) {
                $errors[] = Lang::get('settings.error_tz_offset');
            }

            if (!in_array($emailNotify, [0, 1, 2], true)) {
                $errors[] = Lang::get('settings.error_email_notify');
            }

            if (empty($errors)) {
                $currentUser->display_name    = $displayName;
                $currentUser->email           = $email;
                $currentUser->signature       = $signature;
                $currentUser->show_signature  = $showSig ? 1 : 0;
                $currentUser->hide_email      = $hideEmail ? 1 : 0;
                $currentUser->threaded_read   = $threadedRead ? 1 : 0;
                $currentUser->email_notify    = $emailNotify;
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
                $errors[] = Lang::get('force_password_change.error_password_min_length');
            } elseif ($password !== $password2) {
                $errors[] = Lang::get('force_password_change.error_passwords_mismatch');
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
