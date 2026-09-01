<?php

declare(strict_types=1);

/**
 * Classes — what a session actually is.
 *
 * Gabe asked for "a description of what each class entails"; Kids Boxing wanted
 * its own page and its own nav tab. Both are this table. PunchPass still owns
 * when a class runs and who booked it; this describes what you are booking.
 */
class ClassRepository
{
    private const FIELDS = [
        'name', 'slug', 'summary', 'description', 'age_min', 'age_max',
        'photo_path', 'punchpass_url', 'show_in_nav', 'is_published', 'sort_order',
    ];

    public function __construct(private PDO $db) {}

    public function published(): array
    {
        return $this->db->query(
            'SELECT * FROM classes WHERE is_published = 1 ORDER BY sort_order ASC, name ASC'
        )->fetchAll();
    }

    /** The ones that earn their own nav tab. Cached — the header asks every page. */
    public function navClasses(): array
    {
        static $cache = null;
        if ($cache === null) {
            $cache = $this->db->query(
                'SELECT name, slug FROM classes
                 WHERE is_published = 1 AND show_in_nav = 1
                 ORDER BY sort_order ASC, name ASC'
            )->fetchAll();
        }
        return $cache;
    }

    public function all(): array
    {
        return $this->db->query('SELECT * FROM classes ORDER BY sort_order ASC, name ASC')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM classes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM classes WHERE slug = :s AND is_published = 1');
        $stmt->execute(['s' => $slug]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $d): int
    {
        $cols = implode(', ', self::FIELDS);
        $vals = ':' . implode(', :', self::FIELDS);
        $this->db->prepare("INSERT INTO classes ($cols) VALUES ($vals)")->execute($this->bind($d));
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        $set = [];
        foreach (self::FIELDS as $f) { $set[] = "$f = :$f"; }
        $this->db->prepare('UPDATE classes SET ' . implode(', ', $set) . ' WHERE id = :id')
                 ->execute($this->bind($d, $id) + ['id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM classes WHERE id = :id')->execute(['id' => $id]);
    }

    /** "Kids Boxing" -> "kids-boxing", made unique against what already exists. */
    public function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/', '-', strtolower($name))), '-');
        $base = $base !== '' ? $base : 'class';
        $slug = $base;
        $n = 2;
        while (true) {
            $sql = 'SELECT COUNT(*) FROM classes WHERE slug = :s'
                 . ($ignoreId ? ' AND id != :id' : '');
            $stmt = $this->db->prepare($sql);
            $stmt->execute($ignoreId ? ['s' => $slug, 'id' => $ignoreId] : ['s' => $slug]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $slug;
            }
            $slug = $base . '-' . $n++;
        }
    }

    /** "Ages 8-14", "Ages 16+", or nothing when no limits are set. */
    public static function ageLabel(array $c): string
    {
        $min = $c['age_min'] ?? null;
        $max = $c['age_max'] ?? null;
        if ($min !== null && $max !== null) return "Ages {$min}–{$max}";
        if ($min !== null) return "Ages {$min}+";
        if ($max !== null) return "Up to age {$max}";
        return '';
    }

    private function bind(array $d, ?int $id = null): array
    {
        $out = [];
        foreach (self::FIELDS as $f) {
            $v = $d[$f] ?? null;
            if ($f === 'slug') {
                $out[$f] = $this->uniqueSlug(
                    trim((string) ($d['slug'] ?? '')) !== '' ? $d['slug'] : (string) ($d['name'] ?? ''),
                    $id
                );
                continue;
            }
            if ($f === 'show_in_nav' || $f === 'is_published') { $out[$f] = !empty($v) ? 1 : 0; continue; }
            if ($f === 'sort_order') { $out[$f] = (int) $v; continue; }
            if ($f === 'age_min' || $f === 'age_max') {
                $out[$f] = ($v === '' || $v === null) ? null : (int) $v;
                continue;
            }
            $v = is_string($v) ? trim($v) : $v;
            $out[$f] = ($v === '' || $v === null) ? null : $v;
        }
        return $out;
    }
}
