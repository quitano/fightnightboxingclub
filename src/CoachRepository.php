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

    /** A published profile by its own URL. Hidden coaches stay unreachable. */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM coaches WHERE slug = :s AND is_published = 1');
        $stmt->execute(['s' => $slug]);
        return $stmt->fetch() ?: null;
    }

    /**
     * "Kristen Alcime" -> "kristen-alcime", made unique and never a reserved word.
     *
     * The URL follows the name, which is what makes it worth advertising: nobody
     * has to be told what their link is. A coach who married and changed her
     * name gets the new URL by saving the new name.
     */
    public function uniqueSlug(string $from, ?int $ignoreId = null): string
    {
        $base = trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/', '-', strtolower($from))), '-');
        $base = $base !== '' ? $base : 'coach';
        // A reserved word is pushed off the collision path immediately rather
        // than being handed out and quietly shadowed by the real page.
        $slug = in_array($base, self::RESERVED, true) ? $base . '-2' : $base;
        $n = 2;
        while (true) {
            $sql = 'SELECT COUNT(*) FROM coaches WHERE slug = :s' . ($ignoreId ? ' AND id != :id' : '');
            $stmt = $this->db->prepare($sql);
            $stmt->execute($ignoreId ? ['s' => $slug, 'id' => $ignoreId] : ['s' => $slug]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $slug;
            }
            $slug = $base . '-' . $n++;
        }
    }

    /**
     * Settles the slug before a write.
     *
     * A new coach always gets one, built from the name. An existing coach keeps
     * theirs unless the form actually carried a slug field: renaming somebody
     * must not silently move the URL they have been handing out, and a coach
     * editing their own profile posts no slug at all. Clearing the box in the
     * admin is the deliberate way to rebuild it from the new name.
     */
    private function withSlug(array $d, ?int $id = null): array
    {
        if ($id !== null && !array_key_exists('slug', $d)) {
            unset($d['slug']);
            return $d;
        }
        $explicit = trim((string) ($d['slug'] ?? ''));
        $name     = trim((string) ($d['name'] ?? ''));
        if ($explicit === '' && $name === '') {
            unset($d['slug']);
            return $d;
        }
        $d['slug'] = $this->uniqueSlug($explicit !== '' ? $explicit : $name, $id);
        return $d;
    }

    /** The fields a form may set. Anything else is ignored on save. */
    private const FIELDS = [
        'name', 'slug', 'role_title', 'phone', 'email', 'bio', 'certifications',
        'specialties', 'rates', 'instagram', 'facebook', 'tiktok',
        'booking_url', 'photo_path', 'is_published', 'sort_order',
    ];

    /**
     * Words a coach URL may not take.
     *
     * Coach pages sit at the root — /kristen-alcime — so they share a namespace
     * with the real pages. The router prefers a literal route over a wildcard,
     * so a coach called "Contact" would not actually break /contact; they would
     * just have a page nobody could ever reach. Refusing the name up front is
     * the difference between a puzzling dead page and an obvious rename.
     */
    private const RESERVED = [
        'admin', 'classes', 'contact', 'css', 'gallery', 'img', 'index.php',
        'js', 'meet-the-team', 'memberships', 'personal-training', 'robots.txt',
        'sitemap.xml', 'uploads', 'favicon.ico',
    ];

    public function create(array $d): int
    {
        $cols = implode(', ', self::FIELDS);
        $vals = ':' . implode(', :', self::FIELDS);
        $this->db->prepare("INSERT INTO coaches ($cols) VALUES ($vals)")
                 ->execute(self::bind($this->withSlug($d)));
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
        $d = $this->withSlug($d, $id);
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
