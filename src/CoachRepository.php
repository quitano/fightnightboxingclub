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
