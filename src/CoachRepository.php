<?php

declare(strict_types=1);

/** Coach profiles. Each coach owns exactly one of these — see users.coach_id. */
class CoachRepository
{
    public function __construct(private PDO $db) {}

    /** Published coaches in display order, for the public page. */
    public function published(): array
    {
        return $this->db->query(
            'SELECT * FROM coaches WHERE is_published = 1
             ORDER BY sort_order ASC, name ASC'
        )->fetchAll();
    }

    /** Everything, including hidden profiles, for the admin list. */
    public function all(): array
    {
        return $this->db->query(
            'SELECT * FROM coaches ORDER BY sort_order ASC, name ASC'
        )->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM coaches WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** The fields a form may set. Anything else is ignored on save. */
    private const FIELDS = [
        'name', 'role_title', 'phone', 'email', 'bio', 'certifications',
        'specialties', 'rates', 'instagram', 'facebook', 'tiktok',
        'booking_url', 'photo_path', 'is_published', 'sort_order',
    ];

    public function create(array $d): int
    {
        $cols = implode(', ', self::FIELDS);
        $vals = ':' . implode(', :', self::FIELDS);
        $this->db->prepare("INSERT INTO coaches ($cols) VALUES ($vals)")->execute(self::bind($d));
        return (int) $this->db->lastInsertId();
    }

    /**
     * Updates only the columns given.
     *
     * Not a whole-row write on purpose. A coach editing their own profile posts
     * a form that has no is_published or sort_order on it, and a full update
     * would blank both — the same trap that wiped events.network on the other
     * site.
     */
    public function update(int $id, array $d): void
    {
        $set = [];
        $params = ['id' => $id];
        foreach (self::FIELDS as $f) {
            if (!array_key_exists($f, $d)) {
                continue;
            }
            $set[] = "$f = :$f";
            $params[$f] = self::clean($f, $d[$f]);
        }
        if (!$set) {
            return;
        }
        $this->db->prepare('UPDATE coaches SET ' . implode(', ', $set) . ' WHERE id = :id')
                 ->execute($params);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM coaches WHERE id = :id')->execute(['id' => $id]);
    }

    private static function bind(array $d): array
    {
        $out = [];
        foreach (self::FIELDS as $f) {
            $out[$f] = self::clean($f, $d[$f] ?? null);
        }
        return $out;
    }

    private static function clean(string $field, $v)
    {
        if ($field === 'is_published') {
            return !empty($v) ? 1 : 0;
        }
        if ($field === 'sort_order') {
            return (int) $v;
        }
        $v = is_string($v) ? trim($v) : $v;
        return ($v === '' || $v === null) ? null : $v;
    }

    /**
     * Certifications and specialties are stored one per line, because a
     * textarea is the right editor for a handful of bullet points and three
     * coaches do not justify two join tables.
     */
    public static function lines(?string $text): array
    {
        if (!$text) {
            return [];
        }
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text))));
    }
}
