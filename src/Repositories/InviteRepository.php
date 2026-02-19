<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

class InviteRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(
        int $creatorId,
        string $sport,
        string $level,
        string $startsAt,
        string $locationName,
        string $address,
        ?float $lat,
        ?float $lng,
        int $maxPlayers,
        string $privacy,
        ?string $description,
        ?float $price,
        ?string $rulesText
    ): int {
        $sql = 'INSERT INTO invites (
                    creator_id,
                    sport,
                    level,
                    game_datetime,
                    starts_at,
                    location,
                    location_name,
                    address,
                    lat,
                    lng,
                    max_participants,
                    max_players,
                    privacy,
                    description,
                    price,
                    rules_text,
                    status,
                    completed_notified_at
                ) VALUES (
                    :creator_id,
                    :sport,
                    :level,
                    :game_datetime,
                    :starts_at,
                    :location,
                    :location_name,
                    :address,
                    :lat,
                    :lng,
                    :max_participants,
                    :max_players,
                    :privacy,
                    :description,
                    :price,
                    :rules_text,
                    :status,
                    NULL
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':creator_id' => $creatorId,
            ':sport' => $sport,
            ':level' => $level,
            ':game_datetime' => $startsAt,
            ':starts_at' => $startsAt,
            ':location' => $locationName,
            ':location_name' => $locationName,
            ':address' => $address,
            ':lat' => $lat,
            ':lng' => $lng,
            ':max_participants' => $maxPlayers,
            ':max_players' => $maxPlayers,
            ':privacy' => $privacy,
            ':description' => $description,
            ':price' => $price,
            ':rules_text' => $rulesText,
            ':status' => 'open',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function listExplore(
        int $currentUserId,
        ?string $sport,
        string $period,
        bool $onlyWithSlots,
        ?float $userLat,
        ?float $userLng,
        ?int $radiusKm
    ): array {
        $params = [
            ':current_user_visibility' => $currentUserId,
            ':current_user_membership' => $currentUserId,
        ];

        $distanceExpression = $this->distanceExpression($userLat, $userLng, $params, 'explore');

        $sql = 'SELECT
                    i.id,
                    i.creator_id,
                    i.sport,
                    i.starts_at,
                    i.location_name,
                    i.address,
                    i.lat,
                    i.lng,
                    i.max_players,
                    i.privacy,
                    i.description,
                    i.price,
                    i.rules_text,
                    i.status,
                    i.completed_notified_at,
                    i.created_at,
                    u.name AS creator_name,
                    u.email AS creator_email,
                    COALESCE(SUM(CASE WHEN im.role = \'player\' AND im.status = \'active\' THEN 1 ELSE 0 END), 0) AS players_count,
                    COALESCE(SUM(CASE WHEN im.role = \'waitlist\' AND im.status = \'active\' THEN 1 ELSE 0 END), 0) AS waitlist_count,
                    MAX(CASE WHEN im.user_id = :current_user_membership AND im.status = \'active\' THEN im.role ELSE NULL END) AS user_membership_role,
                    ' . $distanceExpression . ' AS distance_km
                FROM invites i
                INNER JOIN users u ON u.id = i.creator_id
                LEFT JOIN invite_members im ON im.invite_id = i.id
                WHERE (i.privacy = \'public\' OR i.creator_id = :current_user_visibility)';

        if ($sport !== null && $sport !== '') {
            $sql .= ' AND i.sport = :sport';
            $params[':sport'] = $sport;
        }

        if ($period === 'today') {
            $sql .= ' AND i.starts_at >= CURDATE() AND i.starts_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)';
        } elseif ($period === 'week') {
            $sql .= ' AND i.starts_at >= CURDATE() AND i.starts_at < DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
        } elseif ($period === 'month') {
            $sql .= ' AND i.starts_at >= CURDATE() AND i.starts_at < DATE_ADD(CURDATE(), INTERVAL 1 MONTH)';
        }

        $sql .= ' GROUP BY
                    i.id,
                    i.creator_id,
                    i.sport,
                    i.starts_at,
                    i.location_name,
                    i.address,
                    i.lat,
                    i.lng,
                    i.max_players,
                    i.privacy,
                    i.description,
                    i.price,
                    i.rules_text,
                    i.status,
                    i.completed_notified_at,
                    i.created_at,
                    u.name,
                    u.email';

        $having = [];
        if ($onlyWithSlots) {
            $having[] = "COALESCE(SUM(CASE WHEN im.role = 'player' AND im.status = 'active' THEN 1 ELSE 0 END), 0) < i.max_players";
            $having[] = 'i.starts_at > NOW()';
        }

        if ($userLat !== null && $userLng !== null && $radiusKm !== null) {
            $having[] = 'distance_km IS NOT NULL';
            $having[] = 'distance_km <= :radius_km';
            $params[':radius_km'] = $radiusKm;
        }

        if ($having !== []) {
            $sql .= ' HAVING ' . implode(' AND ', $having);
        }

        if ($userLat !== null && $userLng !== null) {
            $sql .= ' ORDER BY (distance_km IS NULL) ASC, distance_km ASC, i.starts_at ASC';
        } else {
            $sql .= ' ORDER BY i.starts_at ASC';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findByIdForUpdate(int $inviteId): ?array
    {
        $sql = 'SELECT
                    i.id,
                    i.creator_id,
                    i.sport,
                    i.starts_at,
                    i.location_name,
                    i.address,
                    i.lat,
                    i.lng,
                    i.max_players,
                    i.privacy,
                    i.description,
                    i.price,
                    i.rules_text,
                    i.status,
                    i.completed_notified_at,
                    u.name AS creator_name,
                    u.email AS creator_email
                FROM invites i
                INNER JOIN users u ON u.id = i.creator_id
                WHERE i.id = :id
                LIMIT 1
                FOR UPDATE';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $inviteId]);
        $invite = $stmt->fetch();

        return $invite === false ? null : $invite;
    }

    public function findDetailedById(
        int $inviteId,
        int $currentUserId,
        ?float $userLat,
        ?float $userLng
    ): ?array {
        $params = [
            ':invite_id' => $inviteId,
            ':current_user_membership' => $currentUserId,
        ];

        $distanceExpression = $this->distanceExpression($userLat, $userLng, $params, 'detail');

        $sql = 'SELECT
                    i.id,
                    i.creator_id,
                    i.sport,
                    i.starts_at,
                    i.location_name,
                    i.address,
                    i.lat,
                    i.lng,
                    i.max_players,
                    i.privacy,
                    i.description,
                    i.price,
                    i.rules_text,
                    i.status,
                    i.completed_notified_at,
                    i.created_at,
                    u.name AS creator_name,
                    u.email AS creator_email,
                    COALESCE(SUM(CASE WHEN im.role = \'player\' AND im.status = \'active\' THEN 1 ELSE 0 END), 0) AS players_count,
                    COALESCE(SUM(CASE WHEN im.role = \'waitlist\' AND im.status = \'active\' THEN 1 ELSE 0 END), 0) AS waitlist_count,
                    MAX(CASE WHEN im.user_id = :current_user_membership AND im.status = \'active\' THEN im.role ELSE NULL END) AS user_membership_role,
                    ' . $distanceExpression . ' AS distance_km
                FROM invites i
                INNER JOIN users u ON u.id = i.creator_id
                LEFT JOIN invite_members im ON im.invite_id = i.id
                WHERE i.id = :invite_id
                GROUP BY
                    i.id,
                    i.creator_id,
                    i.sport,
                    i.starts_at,
                    i.location_name,
                    i.address,
                    i.lat,
                    i.lng,
                    i.max_players,
                    i.privacy,
                    i.description,
                    i.price,
                    i.rules_text,
                    i.status,
                    i.completed_notified_at,
                    i.created_at,
                    u.name,
                    u.email
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $invite = $stmt->fetch();

        return $invite === false ? null : $invite;
    }

    public function listCreatedByUser(int $userId, bool $includePast): array
    {
        $sql = 'SELECT
                    i.id,
                    i.creator_id,
                    i.sport,
                    i.starts_at,
                    i.location_name,
                    i.address,
                    i.lat,
                    i.lng,
                    i.max_players,
                    i.privacy,
                    i.description,
                    i.price,
                    i.rules_text,
                    i.status,
                    i.completed_notified_at,
                    i.created_at,
                    u.name AS creator_name,
                    u.email AS creator_email,
                    COALESCE(SUM(CASE WHEN im.role = \'player\' AND im.status = \'active\' THEN 1 ELSE 0 END), 0) AS players_count,
                    COALESCE(SUM(CASE WHEN im.role = \'waitlist\' AND im.status = \'active\' THEN 1 ELSE 0 END), 0) AS waitlist_count,
                    NULL AS user_membership_role,
                    NULL AS distance_km
                FROM invites i
                INNER JOIN users u ON u.id = i.creator_id
                LEFT JOIN invite_members im ON im.invite_id = i.id
                WHERE i.creator_id = :user_id';

        if (!$includePast) {
            $sql .= ' AND i.starts_at > NOW()';
        }

        $sql .= ' GROUP BY
                    i.id,
                    i.creator_id,
                    i.sport,
                    i.starts_at,
                    i.location_name,
                    i.address,
                    i.lat,
                    i.lng,
                    i.max_players,
                    i.privacy,
                    i.description,
                    i.price,
                    i.rules_text,
                    i.status,
                    i.completed_notified_at,
                    i.created_at,
                    u.name,
                    u.email
                ORDER BY i.starts_at ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public function listByMembershipRole(int $userId, string $role, bool $includePast): array
    {
        $sql = 'SELECT
                    i.id,
                    i.creator_id,
                    i.sport,
                    i.starts_at,
                    i.location_name,
                    i.address,
                    i.lat,
                    i.lng,
                    i.max_players,
                    i.privacy,
                    i.description,
                    i.price,
                    i.rules_text,
                    i.status,
                    i.completed_notified_at,
                    i.created_at,
                    u.name AS creator_name,
                    u.email AS creator_email,
                    COALESCE(SUM(CASE WHEN allm.role = \'player\' AND allm.status = \'active\' THEN 1 ELSE 0 END), 0) AS players_count,
                    COALESCE(SUM(CASE WHEN allm.role = \'waitlist\' AND allm.status = \'active\' THEN 1 ELSE 0 END), 0) AS waitlist_count,
                    :role AS user_membership_role,
                    NULL AS distance_km
                FROM invites i
                INNER JOIN users u ON u.id = i.creator_id
                INNER JOIN invite_members mym ON mym.invite_id = i.id
                LEFT JOIN invite_members allm ON allm.invite_id = i.id
                WHERE mym.user_id = :user_id
                  AND mym.role = :role_filter
                  AND mym.status = \'active\'';

        if (!$includePast) {
            $sql .= ' AND i.starts_at > NOW()';
        }

        $sql .= ' GROUP BY
                    i.id,
                    i.creator_id,
                    i.sport,
                    i.starts_at,
                    i.location_name,
                    i.address,
                    i.lat,
                    i.lng,
                    i.max_players,
                    i.privacy,
                    i.description,
                    i.price,
                    i.rules_text,
                    i.status,
                    i.completed_notified_at,
                    i.created_at,
                    u.name,
                    u.email
                ORDER BY i.starts_at ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':role_filter' => $role,
            ':role' => $role,
        ]);

        return $stmt->fetchAll();
    }

    public function listHistoryForUser(int $userId): array
    {
        $sql = 'SELECT
                    i.id,
                    i.creator_id,
                    i.sport,
                    i.starts_at,
                    i.location_name,
                    i.address,
                    i.lat,
                    i.lng,
                    i.max_players,
                    i.privacy,
                    i.description,
                    i.price,
                    i.rules_text,
                    i.status,
                    i.completed_notified_at,
                    i.created_at,
                    u.name AS creator_name,
                    u.email AS creator_email,
                    COALESCE(SUM(CASE WHEN allm.role = \'player\' AND allm.status = \'active\' THEN 1 ELSE 0 END), 0) AS players_count,
                    COALESCE(SUM(CASE WHEN allm.role = \'waitlist\' AND allm.status = \'active\' THEN 1 ELSE 0 END), 0) AS waitlist_count,
                    MAX(CASE WHEN mym.user_id = :user_id_role_case AND mym.status = \'active\' THEN mym.role ELSE NULL END) AS user_membership_role,
                    NULL AS distance_km
                FROM invites i
                INNER JOIN users u ON u.id = i.creator_id
                LEFT JOIN invite_members allm ON allm.invite_id = i.id
                LEFT JOIN invite_members mym ON mym.invite_id = i.id AND mym.user_id = :user_id_role_join
                WHERE i.starts_at <= NOW()
                  AND (
                    i.creator_id = :user_id_owner
                    OR EXISTS (
                        SELECT 1
                        FROM invite_members mm
                        WHERE mm.invite_id = i.id
                          AND mm.user_id = :user_id_member
                    )
                  )
                GROUP BY
                    i.id,
                    i.creator_id,
                    i.sport,
                    i.starts_at,
                    i.location_name,
                    i.address,
                    i.lat,
                    i.lng,
                    i.max_players,
                    i.privacy,
                    i.description,
                    i.price,
                    i.rules_text,
                    i.status,
                    i.completed_notified_at,
                    i.created_at,
                    u.name,
                    u.email
                ORDER BY i.starts_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id_role_case' => $userId,
            ':user_id_role_join' => $userId,
            ':user_id_owner' => $userId,
            ':user_id_member' => $userId,
        ]);

        return $stmt->fetchAll();
    }

    public function setStatus(int $inviteId, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE invites SET status = :status WHERE id = :id');
        $stmt->execute([
            ':id' => $inviteId,
            ':status' => $status,
        ]);
    }

    public function markCompletedNotifiedNow(int $inviteId): void
    {
        $stmt = $this->pdo->prepare('UPDATE invites SET completed_notified_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $inviteId]);
    }

    public function clearCompletedNotified(int $inviteId): void
    {
        $stmt = $this->pdo->prepare('UPDATE invites SET completed_notified_at = NULL WHERE id = :id');
        $stmt->execute([':id' => $inviteId]);
    }

    public function deleteById(int $inviteId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM invites WHERE id = :id');
        $stmt->execute([':id' => $inviteId]);
    }

    private function distanceExpression(
        ?float $userLat,
        ?float $userLng,
        array &$params,
        string $prefix
    ): string {
        if ($userLat === null || $userLng === null) {
            return 'NULL';
        }

        $params[':' . $prefix . '_user_lat_1'] = $userLat;
        $params[':' . $prefix . '_user_lng_1'] = $userLng;
        $params[':' . $prefix . '_user_lat_2'] = $userLat;

        return '(CASE
                    WHEN i.lat IS NULL OR i.lng IS NULL THEN NULL
                    ELSE (
                        6371 * ACOS(
                            COS(RADIANS(:' . $prefix . '_user_lat_1))
                            * COS(RADIANS(i.lat))
                            * COS(RADIANS(i.lng) - RADIANS(:' . $prefix . '_user_lng_1))
                            + SIN(RADIANS(:' . $prefix . '_user_lat_2))
                            * SIN(RADIANS(i.lat))
                        )
                    )
                END)';
    }
}
