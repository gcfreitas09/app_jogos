<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

class ProfileRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByUserId(int $userId): ?array
    {
        $sql = 'SELECT
                    u.id,
                    u.name,
                    u.email,
                    u.created_at,
                    p.avatar_url,
                    p.preferred_sports,
                    p.default_radius_km,
                    p.allow_location
                FROM users u
                LEFT JOIN user_profiles p ON p.user_id = u.id
                WHERE u.id = :user_id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function upsert(
        int $userId,
        ?string $avatarUrl,
        string $preferredSports,
        int $defaultRadiusKm,
        bool $allowLocation
    ): void {
        $sql = 'INSERT INTO user_profiles (user_id, avatar_url, preferred_sports, default_radius_km, allow_location)
                VALUES (:user_id, :avatar_url, :preferred_sports, :default_radius_km, :allow_location)
                ON DUPLICATE KEY UPDATE
                    avatar_url = VALUES(avatar_url),
                    preferred_sports = VALUES(preferred_sports),
                    default_radius_km = VALUES(default_radius_km),
                    allow_location = VALUES(allow_location)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':avatar_url' => $avatarUrl,
            ':preferred_sports' => $preferredSports,
            ':default_radius_km' => $defaultRadiusKm,
            ':allow_location' => $allowLocation ? 1 : 0,
        ]);
    }
}
