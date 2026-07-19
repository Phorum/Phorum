<?php
declare(strict_types=1);

namespace Phorum\Service;

use Phorum\Core\Auth;
use Phorum\Core\Config;
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
            if ($pluginUser instanceof User && $pluginUser->active) {
                $this->createSession($pluginUser, $remember);
                phorum_api_hook('after_login', $pluginUser);
                return $pluginUser;
            }
        }

        $user = $this->users->findByUsername($username);

        if ($user === null || !$user->active) {
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
        Auth::clear();
        phorum_api_hook('after_logout', $user);
    }

    /**
     * Create a new user account. Returns the saved User.
     * The caller is responsible for validation before calling this.
     *
     * When $requireConfirmation is true the account is created inactive and a
     * confirmation email is sent; the caller must NOT auto-login afterward.
     */
    public function register(
        string $username,
        string $email,
        string $password,
        bool   $requireConfirmation = false,
        string $baseUrl             = '',
    ): User {
        phorum_api_hook('before_register', ['username' => $username, 'email' => $email]);

        $user                   = new User();
        $user->username         = $username;
        $user->display_name     = $username;
        $user->email            = $email;
        $user->password         = password_hash($password, PASSWORD_BCRYPT);
        $user->active           = $requireConfirmation ? 0 : 1;
        $user->date_added       = time();
        $user->date_last_active = time();

        $this->users->save($user);
        phorum_api_hook('after_register', $user);

        if ($requireConfirmation) {
            $this->sendConfirmationEmail($user, $baseUrl);
        }

        return $user;
    }

    /**
     * Activate an account via its confirmation token.
     * Returns the logged-in User on success, null if the token is invalid or expired.
     */
    public function confirmEmail(string $token): ?User
    {
        if ($token === '') {
            return null;
        }

        $user = $this->users->findByPasswordTemp($token);

        if ($user === null || $user->active !== 0) {
            return null;
        }

        $expiry = (int) $user->email_temp;
        if ($expiry === 0 || time() > $expiry) {
            return null;
        }

        $user->active        = 1;
        $user->password_temp = '';
        $user->email_temp    = '';
        $this->users->save($user);

        $this->createSession($user, remember: false);
        return $user;
    }

    /**
     * Re-send a confirmation email for an unconfirmed account.
     * Returns true whether or not the address is registered, so callers
     * cannot enumerate accounts via timing.
     */
    public function resendConfirmation(string $email, string $baseUrl): bool
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || $user->active !== 0) {
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

        if ($user === null || !$user->active) {
            return true; // silent — don't reveal whether the address is registered
        }

        $token = bin2hex(random_bytes(32));

        $user->password_temp = $token;
        $user->email_temp    = (string) (time() + self::RESET_TTL);
        $this->users->save($user);

        if ($this->config !== null) {
            $mail    = new MailService($this->config);
            $link    = rtrim($baseUrl, '/') . '/reset-password?token=' . urlencode($token);
            $name    = $user->display_name ?: $user->username;
            $site    = (string) $this->config->get('site_name', 'Phorum');
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

        $user = $this->users->findByPasswordTemp($token);

        // Require active account — inactive accounts use password_temp for
        // email confirmation tokens, not password reset.
        if ($user === null || !$user->active) {
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
        $user->password               = password_hash($newPassword, PASSWORD_BCRYPT);
        $user->password_temp          = '';
        $user->email_temp             = '';
        $user->force_password_change  = 0;
        $this->users->save($user);

        $this->createSession($user, remember: false);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function sendConfirmationEmail(User $user, string $baseUrl): void
    {
        $token            = bin2hex(random_bytes(32));
        $user->password_temp = $token;
        $user->email_temp    = (string) (time() + self::CONFIRM_TTL);
        $this->users->save($user);

        if ($this->config !== null) {
            $mail = new MailService($this->config);
            $link = rtrim($baseUrl, '/') . '/confirm-email?token=' . urlencode($token);
            $name = $user->display_name ?: $user->username;
            $site = (string) $this->config->get('site_name', 'Phorum');
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
