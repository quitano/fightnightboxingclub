<?php

declare(strict_types=1);

/**
 * One PDO connection, and the config behind it.
 *
 * Deliberately the same shape as the fightnights version so there is one
 * pattern to learn across both sites: defaults in code, secrets in a gitignored
 * config.local.php, and a missing config degrading rather than exploding.
 */
class Database
{
    private const DEFAULTS = [
        'db_host' => '127.0.0.1',
        'db_name' => 'fnboxing_club',
        'db_user' => 'root',
        'db_pass' => '',
        'env' => 'development',
        'site_url' => 'http://localhost:8090',
        'punchpass_url' => 'https://fightnight.punchpass.com',
        'punchpass_classes_url' => 'https://fightnight.punchpass.com/classes',
        'punchpass_passes_url' => 'https://fightnight.punchpass.com/passes',
        // Named zones are not loaded in MySQL on the droplet, so the session
        // offset is set numerically on connect — same lesson as fightnights.
        'timezone' => 'America/New_York',
    ];

    private static ?array $config = null;
    private static ?PDO $pdo = null;

    public static function config(): array
    {
        if (self::$config === null) {
            $path = __DIR__ . '/config.local.php';
            $local = is_file($path) ? require $path : [];
            self::$config = array_merge(self::DEFAULTS, is_array($local) ? $local : []);
        }
        return self::$config;
    }

    public static function isProduction(): bool
    {
        return (self::config()['env'] ?? '') === 'production';
    }

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $c = self::config();
            self::$pdo = new PDO(
                "mysql:host={$c['db_host']};dbname={$c['db_name']};charset=utf8mb4",
                $c['db_user'],
                $c['db_pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            self::applyTimezone(self::$pdo, $c);
        }
        return self::$pdo;
    }

    /** MySQL's named-timezone tables are not loaded, so pass the offset. */
    private static function applyTimezone(PDO $pdo, array $config): void
    {
        try {
            $offset = (new DateTime('now', new DateTimeZone($config['timezone'])))->format('P');
            $pdo->exec("SET time_zone = '{$offset}'");
        } catch (Throwable $e) {
            // A server without tzdata keeps working on UTC rather than failing
            // to serve the page at all.
        }
    }
}
