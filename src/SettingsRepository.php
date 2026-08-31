<?php

declare(strict_types=1);

/**
 * Site-wide details — phone, address, hours, the home video.
 *
 * Loaded once per request and cached, because the header, footer and contact
 * block all want the same handful of values and none of them should each run a
 * query for it.
 */
class SettingsRepository
{
    private static ?array $cache = null;

    public function __construct(private PDO $db) {}

    public function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach ($this->db->query('SELECT setting_key, setting_value FROM settings') as $r) {
                self::$cache[$r['setting_key']] = $r['setting_value'];
            }
        }
        return self::$cache;
    }

    public function get(string $key, string $default = ''): string
    {
        return (string) ($this->all()[$key] ?? $default);
    }

    public function save(string $key, ?string $value): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = :v2'
        );
        $stmt->execute(['k' => $key, 'v' => $value, 'v2' => $value]);
        self::$cache = null;
    }
}
