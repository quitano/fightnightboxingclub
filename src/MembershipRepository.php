<?php

declare(strict_types=1);

/**
 * Membership tiers shown on the site.
 *
 * Nothing here charges anyone — PunchPass and Stripe do that. These rows are a
 * price and a link, so the website can say what things cost without becoming a
 * second source of truth about who has paid.
 */
class MembershipRepository
{
    public function __construct(private PDO $db) {}

    public function published(): array
    {
        return $this->db->query(
            'SELECT * FROM memberships WHERE is_published = 1 ORDER BY sort_order ASC, id ASC'
        )->fetchAll();
    }
}
