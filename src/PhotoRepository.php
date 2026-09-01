<?php

declare(strict_types=1);

/** Gallery photos. */
class PhotoRepository
{
    public function __construct(private PDO $db) {}

    public function all(): array
    {
        return $this->db->query(
            'SELECT * FROM photos ORDER BY sort_order ASC, id DESC'
        )->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM photos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function create(string $path, ?string $caption, ?string $album, int $sort = 0): int
    {
        $this->db->prepare(
            'INSERT INTO photos (photo_path, caption, album, sort_order) VALUES (:p, :c, :a, :s)'
        )->execute(['p' => $path, 'c' => $caption ?: null, 'a' => $album ?: null, 's' => $sort]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        $this->db->prepare(
            'UPDATE photos SET caption = :c, album = :a, is_published = :pub, sort_order = :s
             WHERE id = :id'
        )->execute([
            'c'   => trim((string) ($d['caption'] ?? '')) ?: null,
            'a'   => trim((string) ($d['album'] ?? '')) ?: null,
            'pub' => !empty($d['is_published']) ? 1 : 0,
            's'   => (int) ($d['sort_order'] ?? 0),
            'id'  => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM photos WHERE id = :id')->execute(['id' => $id]);
    }

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
