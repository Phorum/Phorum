<?php
declare(strict_types=1);

namespace Phorum\Service;

use Phorum\Core\Auth;
use Phorum\Core\Config;
use Phorum\Core\CsrfGuard;
use Phorum\Core\SiteSettings;
use Phorum\Mapper\UserMapper;
use Phorum\Model\User;
use Phorum\Service\MailService;

class AuthService
{
    private const RESET_TTL   = 3600;   // 1 hour
    private const CONFIRM_TTL = 172800; // 48 hours

    public function __construct(
        private readonly UserMapper  $users,
        private readonly bool        $secureCookies = false,
        private readonly ?Config     $config        = null,
    ) {}

    /**
     * Authenticate a user by username and password.
     * Returns the User on success, null on failure.
     * Upgrades MD5 passwords to bcrypt transparently.
     */
    public function login(string $username, string $password, bool $remember = false): ?User
    {
        // Allow plugins to bypass built-in auth (LDAP, SSO, etc.)
        $authData = phorum_api_hook('user_authenticate', [
            'username' => $username,
            'password' => $password,
            'user_id'  => 0,
        ]);
        if (is_array($authData) && ($authData['user_id'] ?? 0) > 0) {
            $pluginUser = $this->users->load((int) $authData['user_id']);
            if ($pluginUser instanceof User && $pluginUser->active === UserMapper::ACTIVE) {
                $this->createSession($pluginUser, $remember);
                phorum_api_hook('after_login', $pluginUser);
                return $pluginUser;
            }
        }

        $user = $this->users->findByUsername($username);

        if ($user === null || $user->active !== UserMapper::ACTIVE) {
            phorum_api_hook('failed_login', $username);
            return null;
        }

        if (!$this->verifyPassword($password, $user)) {
            phorum_api_hook('failed_login', $username);
            return null;
        }

        $this->createSession($user, $remember);
        phorum_api_hook('after_login', $user);
        return $user;
    }

    /**
     * Create a session for a User that has already been authenticated by
     * some out-of-band means (e.g. an OAuth provider) — the public
     * counterpart to the private password-login path in login(), for
     * callers that have already resolved a trusted User and just need the
     * same cookie/session-row/after_login side effects login() performs.
     */
    public function loginUser(User $user, bool $remember = false): void
    {
        $this->createSession($user, $remember);
        phorum_api_hook('after_login', $user);
    }

    public function logout(User $user): void
    {
        phorum_api_hook('before_logout', $user);
        phorum_api_hook('user_session_destroy', $user);

        $user->sessid_st         = '';
        $user->sessid_st_timeout = 0;
        $user->sessid_lt         = '';
        $this->users->save($user);

        $this->deleteCookie(Auth::COOKIE_ST);
        $this->deleteCookie(Auth::COOKIE_LT);
        CsrfGuard::rotate();
        Auth::clear();
        phorum_api_hook('after_logout', $user);
    }

    /**
     * Create a new user account. Returns the saved User.
     * The caller is responsible for validation before calling this.
     *
     * When $requireConfirmation is true the account starts needing email
     * confirmation; when $requireModApproval is true it starts needing
     * moderator approval. Either, both, or neither may be set — the caller
     * must NOT auto-login afterward unless both are false.
     */
    public function register(
        string $username,
        string $email,
        string $password,
        bool   $requireConfirmation = false,
        bool   $requireModApproval  = false,
        string $baseUrl             = '',
    ): User {
        phorum_api_hook('before_register', ['username' => $username, 'email' => $email]);

        $user                   = new User();
        $user->username         = $username;
        $user->display_name     = $username;
        $user->email            = $email;
        $user->password         = password_hash($password, PASSWORD_BCRYPT);
        $user->active           = match (true) {
            $requireConfirmation && $requireModApproval => UserMapper::PENDING_BOTH,
            $requireConfirmation                        => UserMapper::PENDING_EMAIL,
            $requireModApproval                         => UserMapper::PENDING_MOD,
            default                                      => UserMapper::ACTIVE,
        };
        $user->date_added       = time();
        $user->date_last_active = time();
        $user->reg_ip           = $_SERVER['REMOTE_ADDR'] ?? '';
        // Self-declared at signup and proved only by confirmEmail() below.
        // Left 0 when require_confirmation is off, which is the case the
        // OAuth link check depends on being able to tell apart.
        $user->email_verified   = 0;

        $this->users->save($user);
        phorum_api_hook('after_register', $user);

        if ($requireConfirmation) {
            $this->sendConfirmationEmail($user, $baseUrl);
        }

        return $user;
    }

    /**
     * Activate an account via its confirmation token.
     *
     * Returns the User on success, null if the token is invalid or expired.
     * If the account was only pending email confirmation it becomes fully
     * ACTIVE; if it was PENDING_BOTH it moves to PENDING_MOD instead. No
     * session is created either way, so callers must check the returned
     * user's `active` value and send them to the login form.
     */
    public function confirmEmail(string $token): ?User
    {
        if ($token === '') {
            return null;
        }

        $user = $this->users->findByPasswordTemp(self::hashToken($token));

        if ($user === null || !in_array($user->active, [UserMapper::PENDING_EMAIL, UserMapper::PENDING_BOTH], true)) {
            return null;
        }

        $expiry = (int) $user->email_temp;
        if ($expiry === 0 || time() > $expiry) {
            return null;
        }

        $stillNeedsModApproval = $user->active === UserMapper::PENDING_BOTH;
        $user->active          = $stillNeedsModApproval ? UserMapper::PENDING_MOD : UserMapper::ACTIVE;
        $user->password_temp   = '';
        $user->email_temp      = '';
        // Following a link only this mailbox received is the proof.
        $user->email_verified  = 1;
        $this->users->save($user);

        // Deliberately no session here. This link lives for 48 hours in the
        // recipient's mailbox and in every access log that recorded the
        // request; logging the visitor in would make it a two-day login
        // credential rather than an activation link. The account is now
        // active and the caller sends them to the login form.
        return $user;
    }

    /**
     * Re-send a confirmation email for an account still awaiting email
     * confirmation (PENDING_EMAIL or PENDING_BOTH).
     * Returns true whether or not the address is registered, so callers
     * cannot enumerate accounts via timing.
     */
    public function resendConfirmation(string $email, string $baseUrl): bool
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || !in_array($user->active, [UserMapper::PENDING_EMAIL, UserMapper::PENDING_BOTH], true)) {
            return true; // silent
        }

        $this->sendConfirmationEmail($user, $baseUrl);
        return true;
    }

    // -------------------------------------------------------------------------
    // Password reset
    // -------------------------------------------------------------------------

    /**
     * Generate a reset token for the user with the given email address and send
     * the reset link. Returns true whether or not the email exists so callers
     * cannot enumerate accounts via timing.
     */
    public function requestPasswordReset(string $email, string $baseUrl): bool
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || $user->active !== UserMapper::ACTIVE) {
            return true; // silent — don't reveal whether the address is registered
        }

        $token = bin2hex(random_bytes(32));

        // Only the hash is stored. password_temp used to hold the raw token,
        // which made any database read — a backup, a replica, a SQL injection
        // elsewhere — a working password reset for every account with one
        // pending. The column is varchar(255), so a sha256 hex digest fits
        // without touching the Phorum 6 schema.
        $user->password_temp = self::hashToken($token);
        $user->email_temp    = (string) (time() + self::RESET_TTL);
        $this->users->save($user);

        if ($this->config !== null) {
            $mail    = new MailService($this->config);
            $link    = rtrim($baseUrl, '/') . '/reset-password?token=' . urlencode($token);
            $name    = $user->display_name ?: $user->username;
            $site    = SiteSettings::name();
            $mail->send(
                toAddress: $user->email,
                toName:    $name,
                subject:   'Password reset request — ' . $site,
                body:      "Hi {$name},\n\n"
                         . "Someone requested a password reset for your account on {$site}.\n\n"
                         . "Click the link below to choose a new password. "
                         . "The link expires in one hour.\n\n"
                         . "{$link}\n\n"
                         . "If you did not request this, you can safely ignore this email.\n",
            );
        }

        return true;
    }

    /**
     * Validate a reset token against a user record.
     * Returns the User on success, null if the token is invalid or expired.
     */
    public function validateResetToken(string $token): ?User
    {
        if ($token === '') {
            return null;
        }

        $user = $this->users->findByPasswordTemp(self::hashToken($token));

        // Require a fully active account — non-active accounts use
        // password_temp for email confirmation tokens, not password reset.
        if ($user === null || $user->active !== UserMapper::ACTIVE) {
            return null;
        }

        $expiry = (int) $user->email_temp;
        if ($expiry === 0 || time() > $expiry) {
            return null;
        }

        return $user;
    }

    /**
     * Apply a new password for the user and clear the reset token fields.
     * Logs the user in automatically afterward.
     */
    public function resetPassword(User $user, string $newPassword): void
    {
        // Completing a reset means this mailbox received the link, which is
        // the same proof confirmEmail() accepts. It's also the route by which
        // accounts backfilled as unverified become verified again.
        $user->email_verified = 1;

        $this->applyNewPassword($user, $newPassword);
    }

    /**
     * Set a new password and end every session the account currently has.
     *
     * The single place a password changes, so the session consequences can't
     * be forgotten at one call site and remembered at another. sessid_st and
     * sessid_lt are single-valued columns, so clearing them logs out whoever
     * else is holding a cookie — which matters most for sessid_lt, the
     * year-long remember-me token that a password change previously left
     * working. A fresh session is then issued for the person making the
     * change, so they stay logged in on this device only.
     *
     * @param bool $startSession False when the caller isn't the account owner
     *                           acting in their own browser (an admin reset,
     *                           say) and no new session should be created.
     */
    public function applyNewPassword(User $user, string $newPassword, bool $startSession = true): void
    {
        $user->password              = password_hash($newPassword, PASSWORD_BCRYPT);
        $user->password_temp         = '';
        $user->email_temp            = '';
        $user->force_password_change = 0;

        $user->sessid_st         = '';
        $user->sessid_st_timeout = 0;
        $user->sessid_lt         = '';

        $this->users->save($user);

        if ($startSession) {
            $this->createSession($user, remember: false);
        }
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function sendConfirmationEmail(User $user, string $baseUrl): void
    {
        $token               = bin2hex(random_bytes(32));
        $user->password_temp = self::hashToken($token);
        $user->email_temp    = (string) (time() + self::CONFIRM_TTL);
        $this->users->save($user);

        if ($this->config !== null) {
            $mail = new MailService($this->config);
            $link = rtrim($baseUrl, '/') . '/confirm-email?token=' . urlencode($token);
            $name = $user->display_name ?: $user->username;
            $site = SiteSettings::name();
            $mail->send(
                toAddress: $user->email,
                toName:    $name,
                subject:   'Confirm your registration — ' . $site,
                body:      "Hi {$name},\n\n"
                         . "Thanks for registering on {$site}.\n\n"
                         . "Click the link below to confirm your email address and activate your account. "
                         . "The link expires in 48 hours.\n\n"
                         . "{$link}\n\n"
                         . "If you did not register, you can safely ignore this email.\n",
            );
        }
    }

    /**
     * Confirm $user really knows $password — the re-authentication check for
     * changes that would let someone take the account over permanently.
     *
     * Note this shares the legacy-MD5 upgrade path with login(), so a
     * successful check on an un-migrated account rehashes it to bcrypt.
     */
    public function verifyCurrentPassword(User $user, string $password): bool
    {
        return $password !== '' && $this->verifyPassword($password, $user);
    }

    /**
     * The stored form of an emailed one-time token.
     *
     * A plain sha256 rather than a password hash: these are 32 bytes of
     * `random_bytes` output, so there is nothing to brute-force and no need
     * for a slow KDF — the point is only that what sits in the database isn't
     * usable as-is. Unsalted so the lookup stays a single indexed equality
     * match rather than a scan-and-compare.
     */
    private static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function verifyPassword(string $password, User $user): bool
    {
        // Modern bcrypt password
        if (password_verify($password, $user->password)) {
            return true;
        }

        // Legacy MD5 from old Phorum — upgrade on successful match
        if (hash_equals(md5($password), $user->password)) {
            $user->password = password_hash($password, PASSWORD_BCRYPT);
            $this->users->save($user);
            return true;
        }

        return false;
    }

    private function createSession(User $user, bool $remember): void
    {
        // New identity, new session id and CSRF token — so a session an
        // attacker fixed before login can't carry into the authenticated one,
        // and any token they already read stops working.
        CsrfGuard::rotate();

        $stToken = bin2hex(random_bytes(16));

        $user->sessid_st         = $stToken;
        $user->sessid_st_timeout = time() + 3600;
        $user->date_last_active  = time();

        if ($remember) {
            $ltToken         = bin2hex(random_bytes(16));
            $user->sessid_lt = $ltToken;
            $this->setCookie(Auth::COOKIE_LT, $ltToken, time() + (86400 * 365));
        }

        $this->users->save($user);
        $this->setCookie(Auth::COOKIE_ST, $stToken, 0);
        Auth::setUser($user);
        phorum_api_hook('user_session_create', $user);
    }

    private function setCookie(string $name, string $value, int $expires): void
    {
        setcookie($name, $value, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => $this->secureCookies,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function deleteCookie(string $name): void
    {
        $this->setCookie($name, '', time() - 3600);
    }
}
