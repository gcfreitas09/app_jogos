<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProfileRepository;

class ProfileService
{
    private ProfileRepository $profiles;

    public function __construct(ProfileRepository $profiles)
    {
        $this->profiles = $profiles;
    }

    public function getProfile(int $userId): ?array
    {
        $profile = $this->profiles->findByUserId($userId);
        if ($profile === null) {
            return null;
        }

        $profile['allow_location'] = (int) ($profile['allow_location'] ?? 1) === 1;
        $profile['default_radius_km'] = (int) ($profile['default_radius_km'] ?? 5);
        $profile['preferred_sports_list'] = $this->explodeSports((string) ($profile['preferred_sports'] ?? ''));

        return $profile;
    }

    public function updateProfile(int $userId, array $payload): array
    {
        $avatarUrl = trim((string) ($payload['avatar_url'] ?? ''));
        $selectedSports = $payload['preferred_sports'] ?? [];
        $defaultRadiusKm = (int) ($payload['default_radius_km'] ?? 5);
        $allowLocation = ((string) ($payload['allow_location'] ?? '0')) === '1';

        if (!is_array($selectedSports)) {
            $selectedSports = [];
        }

        $cleanSports = array_values(array_unique(array_filter(array_map(
            static fn (mixed $sport): string => trim((string) $sport),
            $selectedSports
        ))));

        $errors = [];

        foreach ($cleanSports as $sport) {
            if (!in_array($sport, InviteService::allowedSports(), true)) {
                $errors[] = 'Esporte inválido na preferência.';
                break;
            }
        }

        if (!in_array($defaultRadiusKm, InviteService::allowedRadii(), true)) {
            $errors[] = 'Raio padrão inválido.';
        }

        if ($avatarUrl !== '' && filter_var($avatarUrl, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'URL de foto inválida.';
        }

        if ($errors !== []) {
            return [
                'success' => false,
                'errors' => $errors,
            ];
        }

        $this->profiles->upsert(
            $userId,
            $avatarUrl !== '' ? $avatarUrl : null,
            implode(',', $cleanSports),
            $defaultRadiusKm,
            $allowLocation
        );

        return [
            'success' => true,
            'errors' => [],
        ];
    }

    private function explodeSports(string $sports): array
    {
        if ($sports === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $sport): string => trim($sport),
            explode(',', $sports)
        )));
    }
}
