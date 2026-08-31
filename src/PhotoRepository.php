<?php

declare(strict_types=1);

/** Gallery photos. */
class PhotoRepository
{
    public function __construct(private PDO $db) {}

    public function published(?string $album = null): array
    {
        $sql = 'SELECT * FROM photos WHERE is_published = 1';
        $params = [];
        if ($album !== null && $album !== '') {
            $sql .= ' AND album = :album';
            $params['album'] = $album;
        }
        $sql .= ' ORDER BY sort_order ASC, id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Album names in use, for the gallery filter. */
    public function albums(): array
    {
        return $this->db->query(
            "SELECT DISTINCT album FROM photos
             WHERE is_published = 1 AND album IS NOT NULL AND album != ''
             ORDER BY album"
        )->fetchAll(PDO::FETCH_COLUMN);
    }
}
