<?php

declare(strict_types=1);

/** Home-page promotions — kids classes, camps, seasonal offers. */
class PromotionRepository
{
    public function __construct(private PDO $db) {}

    /**
     * Promotions that should be on the page today.
     *
     * The date window is the point: a summer camp stops showing itself in
     * September without anyone remembering to take it down. Null dates mean
     * "no limit at that end", so a permanent promotion needs no dates at all.
     */
    private const FIELDS = [
        'title', 'body', 'photo_path', 'link_url', 'link_label',
        'starts_on', 'ends_on', 'is_published', 'sort_order',
    ];

    public function all(): array
    {
        return $this->db->query(
            'SELECT * FROM promotions ORDER BY sort_order ASC, id DESC'
        )->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM promotions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $d): int
    {
        $cols = implode(', ', self::FIELDS);
        $vals = ':' . implode(', :', self::FIELDS);
        $this->db->prepare("INSERT INTO promotions ($cols) VALUES ($vals)")->execute(self::bind($d));
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        $set = [];
        foreach (self::FIELDS as $f) { $set[] = "$f = :$f"; }
        $this->db->prepare('UPDATE promotions SET ' . implode(', ', $set) . ' WHERE id = :id')
                 ->execute(self::bind($d) + ['id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM promotions WHERE id = :id')->execute(['id' => $id]);
    }

    private static function bind(array $d): array
    {
        $out = [];
        foreach (self::FIELDS as $f) {
            $v = $d[$f] ?? null;
            if ($f === 'is_published') { $out[$f] = !empty($v) ? 1 : 0; continue; }
            if ($f === 'sort_order')   { $out[$f] = (int) $v; continue; }
            $v = is_string($v) ? trim($v) : $v;
            $out[$f] = ($v === '' || $v === null) ? null : $v;
        }
        return $out;
    }

    public function live(string $today): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM promotions
             WHERE is_published = 1
               AND (starts_on IS NULL OR starts_on <= :today1)
               AND (ends_on   IS NULL OR ends_on   >= :today2)
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['today1' => $today, 'today2' => $today]);
        return $stmt->fetchAll();
    }
}
