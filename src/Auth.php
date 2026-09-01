<?php

declare(strict_types=1);

/**
 * Session-backed login, and the two roles.
 *
 * An 'admin' manages the whole site. A 'coach' can edit exactly one thing:
 * their own profile, identified by users.coach_id. That distinction is enforced
 * in the route middleware and again at the point of save — giving three coaches
 * a login should never mean trusting three people with the rest of the site.
 */
class Auth
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_COACH = 'coach';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                // Only insist on HTTPS in production; local dev is plain http.
                'cookie_secure' => Database::isProduction(),
            ]);
        }
    }

    public static function login(array $user): void
    {
        self::start();
        // A new session id on login, so a session fixed before authentication
        // cannot be reused after it.
        session_regenerate_id(true);
        $_SESSION['uid']      = (int) $user['id'];
        $_SESSION['uname']    = $user['username'];
        $_SESSION['display']  = $user['display_name'] ?: $user['username'];
        $_SESSION['role']     = $user['role'] ?: self::ROLE_COACH;
        $_SESSION['coach_id'] = $user['coach_id'] !== null ? (int) $user['coach_id'] : null;
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
    }

    public static function check(): bool
    {
        self::start();
        return !empty($_SESSION['uid']);
    }

    public static function id(): ?int
    {
        self::start();
        return isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : null;
    }

    public static function role(): string
    {
        self::start();
        return (string) ($_SESSION['role'] ?? '');
    }

    public static function isAdmin(): bool
    {
        return self::role() === self::ROLE_ADMIN;
    }

    public static function displayName(): string
    {
        self::start();
        return (string) ($_SESSION['display'] ?? '');
    }

    /** The one coach profile this account may edit. Null for admins. */
    public static function coachId(): ?int
    {
        self::start();
        return isset($_SESSION['coach_id']) ? (int) $_SESSION['coach_id'] : null;
    }

    /**
     * May the logged-in user edit this coach profile?
     *
     * Admins may edit anyone. A coach may edit exactly the row their account is
     * tied to. Checked on the way in AND at save time, because a form that is
     * never rendered can still be posted to.
     */
    public static function canEditCoach(int $coachId): bool
    {
        if (self::isAdmin()) {
            return true;
        }
        return self::coachId() !== null && self::coachId() === $coachId;
    }
}
