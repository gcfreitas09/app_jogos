<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProfileRepository;
use App\Repositories\UserRepository;

class ProfileService
{
    private ProfileRepository $profiles;
    private UserRepository $users;

    public function __construct(ProfileRepository $profiles, UserRepository $users)
    {
        $this->profiles = $profiles;
        $this->users = $users;
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
        $name = trim((string) ($payload['name'] ?? ''));
        $avatarUrl = trim((string) ($payload['avatar_url'] ?? ''));
        $selectedSports = $payload['preferred_sports'] ?? [];
        $defaultRadiusKm = (int) ($payload['default_radius_km'] ?? 5);
        $allowLocation = ((string) ($payload['allow_location'] ?? '0')) === '1';

        if (!is_array($selectedSports)) {
            $selectedSports = [];
        }

        $cleanSports = [];
        foreach ($selectedSports as $sportValue) {
            $sport = trim((string) $sportValue);
            if ($sport === '') {
                continue;
            }

            $sport = preg_replace('/\s+/u', ' ', $sport);
            if (!is_string($sport) || $sport === '') {
                continue;
            }

            $cleanSports[] = $sport;
        }
        $cleanSports = array_values(array_unique($cleanSports));

        $errors = [];

        if ($name === '' || mb_strlen($name) < 2) {
            $errors[] = 'Informe um nome com ao menos 2 caracteres.';
        } elseif (mb_strlen($name) > 120) {
            $errors[] = 'Nome deve ter no maximo 120 caracteres.';
        }

        foreach ($cleanSports as $sport) {
            if (mb_strlen($sport) < 2 || mb_strlen($sport) > 60) {
                $errors[] = 'Cada esporte preferido deve ter entre 2 e 60 caracteres.';
                break;
            }
            if (str_contains($sport, ',')) {
                $errors[] = 'O nome do esporte nao pode conter virgula.';
                break;
            }
        }

        if (!InviteService::isValidRadius($defaultRadiusKm)) {
            $errors[] = 'Raio padrao invalido. Informe entre '
                . InviteService::MIN_RADIUS_KM
                . ' e '
                . InviteService::MAX_RADIUS_KM
                . ' km.';
        }

        if ($avatarUrl !== '' && !preg_match('#^(https?://|storage/uploads/avatars/)#i', $avatarUrl)) {
            $errors[] = 'Formato da foto invalido.';
        }

        if ($errors !== []) {
            return [
                'success' => false,
                'errors' => $errors,
            ];
        }

        $this->users->updateName($userId, $name);
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
