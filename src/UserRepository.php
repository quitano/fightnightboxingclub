<?php

declare(strict_types=1);

/** Admin and coach accounts. */
class UserRepository
{
    public function __construct(private PDO $db) {}

    public function findByUsername(string $username): ?array
    {
        if ($username === '') {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = :u');
        $stmt->execute(['u' => $username]);
        return $stmt->fetch() ?: null;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** With the coach's name, so the admin list can show who each account is. */
    public function all(): array
    {
        return $this->db->query(
            'SELECT u.*, c.name AS coach_name
             FROM users u LEFT JOIN coaches c ON c.id = u.coach_id
             ORDER BY u.role ASC, u.username ASC'
        )->fetchAll();
    }

    public function create(array $d): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, password_hash, display_name, email, role, coach_id, can_manage_photos)
             VALUES (:u, :h, :dn, :em, :role, :cid, :photos)'
        );
        $stmt->execute([
            'u'    => $d['username'],
            'h'    => password_hash($d['password'], PASSWORD_DEFAULT),
            'dn'   => $d['display_name'] ?: null,
            'em'   => $d['email'] ?: null,
            'role' => $d['role'] === Auth::ROLE_ADMIN ? Auth::ROLE_ADMIN : Auth::ROLE_COACH,
            // A coach account without a coach_id could edit nothing at all, so
            // the form insists on one; this is the last guard.
            'cid'  => !empty($d['coach_id']) ? (int) $d['coach_id'] : null,
            'photos' => !empty($d['can_manage_photos']) ? 1 : 0,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** Password is only touched when a new one was actually typed. */
    public function update(int $id, array $d): void
    {
        $sql = 'UPDATE users SET display_name = :dn, email = :em, role = :role, coach_id = :cid, can_manage_photos = :photos';
        $params = [
            'dn'   => $d['display_name'] ?: null,
            'em'   => $d['email'] ?: null,
            'role' => $d['role'] === Auth::ROLE_ADMIN ? Auth::ROLE_ADMIN : Auth::ROLE_COACH,
            'cid'  => !empty($d['coach_id']) ? (int) $d['coach_id'] : null,
            'photos' => !empty($d['can_manage_photos']) ? 1 : 0,
            'id'   => $id,
        ];
        if (!empty($d['password'])) {
            $sql .= ', password_hash = :h';
            $params['h'] = password_hash($d['password'], PASSWORD_DEFAULT);
        }
        $sql .= ' WHERE id = :id';
        $this->db->prepare($sql)->execute($params);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $id]);
    }

    public function touchLogin(int $id): void
    {
        $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $id]);
    }

    public function countAdmins(): int
    {
        return (int) $this->db->query(
            "SELECT COUNT(*) FROM users WHERE role = 'admin'"
        )->fetchColumn();
    }
}
