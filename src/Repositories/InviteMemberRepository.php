<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

class InviteMemberRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByInviteAndUserForUpdate(int $inviteId, int $userId): ?array
    {
        $sql = 'SELECT *
                FROM invite_members
                WHERE invite_id = :invite_id AND user_id = :user_id
                LIMIT 1
                FOR UPDATE';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':invite_id' => $inviteId,
            ':user_id' => $userId,
        ]);

        $member = $stmt->fetch();

        return $member === false ? null : $member;
    }

    public function insertActive(
        int $inviteId,
        int $userId,
        string $role,
        ?int $position
    ): void {
        $sql = 'INSERT INTO invite_members (invite_id, user_id, role, position, status, joined_at)
                VALUES (:invite_id, :user_id, :role, :position, :status, NOW())';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':invite_id' => $inviteId,
            ':user_id' => $userId,
            ':role' => $role,
            ':position' => $position,
            ':status' => 'active',
        ]);
    }

    public function reactivateMember(int $memberId, string $role, ?int $position): void
    {
        $sql = 'UPDATE invite_members
                SET
                    role = :role,
                    position = :position,
                    status = :status,
                    joined_at = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $memberId,
            ':role' => $role,
            ':position' => $position,
            ':status' => 'active',
        ]);
    }

    public function markAsLeft(int $memberId): void
    {
        $stmt = $this->pdo->prepare("UPDATE invite_members SET status = 'left' WHERE id = :id");
        $stmt->execute([':id' => $memberId]);
    }

    public function countActivePlayers(int $inviteId): int
    {
        $sql = "SELECT COUNT(*)
                FROM invite_members
                WHERE invite_id = :invite_id
                  AND role = 'player'
                  AND status = 'active'";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':invite_id' => $inviteId]);

        return (int) $stmt->fetchColumn();
    }

    public function countActiveWaitlist(int $inviteId): int
    {
        $sql = "SELECT COUNT(*)
                FROM invite_members
                WHERE invite_id = :invite_id
                  AND role = 'waitlist'
                  AND status = 'active'";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':invite_id' => $inviteId]);

        return (int) $stmt->fetchColumn();
    }

    public function nextWaitlistPosition(int $inviteId): int
    {
        $sql = "SELECT COALESCE(MAX(position), 0) + 1
                FROM invite_members
                WHERE invite_id = :invite_id
                  AND role = 'waitlist'
                  AND status = 'active'";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':invite_id' => $inviteId]);

        return (int) $stmt->fetchColumn();
    }

    public function findFirstWaitlistForUpdate(int $inviteId): ?array
    {
        $sql = "SELECT
                    im.*,
                    u.name,
                    u.email
                FROM invite_members im
                INNER JOIN users u ON u.id = im.user_id
                WHERE im.invite_id = :invite_id
                  AND im.role = 'waitlist'
                  AND im.status = 'active'
                ORDER BY im.position ASC, im.id ASC
                LIMIT 1
                FOR UPDATE";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':invite_id' => $inviteId]);

        $member = $stmt->fetch();

        return $member === false ? null : $member;
    }

    public function promoteWaitlistToPlayer(int $memberId): void
    {
        $sql = "UPDATE invite_members
                SET role = 'player', position = NULL, status = 'active'
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $memberId]);
    }

    public function compressWaitlistFromPosition(int $inviteId, int $position): void
    {
        $sql = "UPDATE invite_members
                SET position = position - 1
                WHERE invite_id = :invite_id
                  AND role = 'waitlist'
                  AND status = 'active'
                  AND position > :position";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':invite_id' => $inviteId,
            ':position' => $position,
        ]);
    }

    public function listActiveMembersByRole(int $inviteId, string $role): array
    {
        $sql = 'SELECT
                    im.id,
                    im.user_id,
                    im.role,
                    im.position,
                    im.joined_at,
                    u.name,
                    u.email
                FROM invite_members im
                INNER JOIN users u ON u.id = im.user_id
                WHERE im.invite_id = :invite_id
                  AND im.role = :role
                  AND im.status = :status
                ORDER BY
                    CASE WHEN im.position IS NULL THEN 0 ELSE 1 END,
                    im.position ASC,
                    im.joined_at ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':invite_id' => $inviteId,
            ':role' => $role,
            ':status' => 'active',
        ]);

        return $stmt->fetchAll();
    }

    public function listActivePlayerEmails(int $inviteId): array
    {
        $sql = "SELECT DISTINCT u.email
                FROM invite_members im
                INNER JOIN users u ON u.id = im.user_id
                WHERE im.invite_id = :invite_id
                  AND im.role = 'player'
                  AND im.status = 'active'";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':invite_id' => $inviteId]);

        return array_map(
            static fn (array $row): string => (string) $row['email'],
            $stmt->fetchAll()
        );
    }
}
