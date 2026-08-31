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
