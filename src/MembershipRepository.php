<?php

declare(strict_types=1);

/**
 * Memberships shown on the site.
 *
 * Nothing here charges anyone — PunchPass and Stripe do that. These rows are a
 * price and a link, so the website can say what things cost without becoming a
 * second source of truth about who has paid.
 *
 * Three things decide where a membership appears, and they are deliberately
 * separate: is_published (does it exist at all), the starts_on/ends_on window
 * (is it in season), and show_on_home (has it earned a place on the home page).
 * Without the last one, adding a Summer Membership would push it straight onto
 * the home page next to the two main ones.
 */
class MembershipRepository
{
    private const FIELDS = [
        'name', 'price', 'period', 'description', 'includes', 'badge',
        'punchpass_url', 'is_published', 'show_on_home',
        'starts_on', 'ends_on', 'class_id', 'sort_order',
    ];

    /** Periods offered in the admin dropdown. */
    public const PERIODS = ['month', 'season', 'one-time'];

    public function __construct(private PDO $db) {}

    public function all(): array
    {
        return $this->db->query(
            'SELECT m.*, c.name AS class_name, c.slug AS class_slug
             FROM memberships m
             LEFT JOIN classes c ON c.id = m.class_id
             ORDER BY m.sort_order ASC, m.id ASC'
        )->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM memberships WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Everything that should be on the site today.
     *
     * The date window works exactly like promotions: null at either end means
     * no limit, so the two main memberships leave both empty and simply run.
     */
    public function live(string $today): array
    {
        return $this->liveQuery($today)->fetchAll();
    }

    /** The main ones — live, and ticked for the home page. */
    public function forHome(string $today): array
    {
        return $this->liveQuery($today, 'AND show_on_home = 1')->fetchAll();
    }

    /** Live memberships attached to one class, for that class's page. */
    public function forClass(int $classId, string $today): array
    {
        return $this->liveQuery($today, 'AND class_id = :class_id', ['class_id' => $classId])->fetchAll();
    }

    private function liveQuery(string $today, string $extra = '', array $params = []): PDOStatement
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM memberships
             WHERE is_published = 1
               AND (starts_on IS NULL OR starts_on <= :today1)
               AND (ends_on   IS NULL OR ends_on   >= :today2)
               ' . $extra . '
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute($params + ['today1' => $today, 'today2' => $today]);
        return $stmt;
    }

    public function create(array $d): int
    {
        $cols = implode(', ', self::FIELDS);
        $vals = ':' . implode(', :', self::FIELDS);
        $this->db->prepare("INSERT INTO memberships ($cols) VALUES ($vals)")->execute(self::bind($d));
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        $set = [];
        foreach (self::FIELDS as $f) { $set[] = "$f = :$f"; }
        $this->db->prepare('UPDATE memberships SET ' . implode(', ', $set) . ' WHERE id = :id')
                 ->execute(self::bind($d) + ['id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM memberships WHERE id = :id')->execute(['id' => $id]);
    }

    /** One per line, blanks dropped — "What's included" on a card. */
    public static function lines(?string $raw): array
    {
        return CoachRepository::lines($raw);
    }

    private static function bind(array $d): array
    {
        $out = [];
        foreach (self::FIELDS as $f) {
            $v = $d[$f] ?? null;
            if ($f === 'is_published' || $f === 'show_on_home') { $out[$f] = !empty($v) ? 1 : 0; continue; }
            if ($f === 'sort_order') { $out[$f] = (int) $v; continue; }
            if ($f === 'class_id')   { $out[$f] = (int) $v > 0 ? (int) $v : null; continue; }
            if ($f === 'price') {
                // Whole dollars only — $35, $65, never $34.99. Typing cents into
                // the box rounds them away rather than quietly displaying a price
                // that does not match PunchPass.
                $v = is_string($v) ? trim($v) : $v;
                $out[$f] = ($v === '' || $v === null) ? null : (int) round((float) $v);
                continue;
            }
            $v = is_string($v) ? trim($v) : $v;
            $out[$f] = ($v === '' || $v === null) ? null : $v;
        }
        return $out;
    }
}
