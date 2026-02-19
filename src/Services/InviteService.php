<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Mailer;
use App\Repositories\InviteMemberRepository;
use App\Repositories\InviteRepository;
use DateTimeImmutable;
use PDO;
use Throwable;

class InviteService
{
    public const SPORTS = [
        'Futevolei',
        'Tenis',
        'Padel',
        'Beach Tennis',
    ];
    public const LEVELS = ['iniciante', 'intermediario', 'avancado'];
    public const PERIODS = ['all', 'today', 'week', 'month'];
    public const RADII = [1, 5, 10, 25, 50];
    public const MIN_RADIUS_KM = 1;
    public const MAX_RADIUS_KM = 200;
    public const PRIVACY = ['public', 'private'];

    private PDO $pdo;
    private InviteRepository $invites;
    private InviteMemberRepository $members;
    private Mailer $mailer;

    public function __construct(
        PDO $pdo,
        InviteRepository $invites,
        InviteMemberRepository $members,
        Mailer $mailer
    ) {
        $this->pdo = $pdo;
        $this->invites = $invites;
        $this->members = $members;
        $this->mailer = $mailer;
    }

    public static function allowedSports(): array
    {
        return self::SPORTS;
    }

    public static function allowedPeriods(): array
    {
        return self::PERIODS;
    }

    public static function allowedLevels(): array
    {
        return self::LEVELS;
    }

    public static function allowedRadii(): array
    {
        return self::RADII;
    }

    public static function isValidRadius(int $radius): bool
    {
        return $radius >= self::MIN_RADIUS_KM && $radius <= self::MAX_RADIUS_KM;
    }

    public static function allowedPrivacy(): array
    {
        return self::PRIVACY;
    }

    public function createInvite(int $creatorId, array $payload): array
    {
        $errors = [];

        $sport = trim((string) ($payload['sport'] ?? ''));
        $sportCustom = $this->normalizeCustomSportName((string) ($payload['sport_custom'] ?? ''));
        $level = trim((string) ($payload['level'] ?? 'intermediario'));
        $startsAtRaw = trim((string) ($payload['starts_at'] ?? ''));
        $locationName = trim((string) ($payload['location_name'] ?? ''));
        $address = trim((string) ($payload['address'] ?? ''));
        if ($address === '') {
            $address = $locationName;
        }
        $lat = $this->parseFloat($payload['lat'] ?? null);
        $lng = $this->parseFloat($payload['lng'] ?? null);
        $maxPlayers = (int) ($payload['max_players'] ?? 0);
        $privacy = trim((string) ($payload['privacy'] ?? 'public'));
        $description = $this->nullableText($payload['description'] ?? null);
        $price = $this->parseFloat($payload['price'] ?? null);
        $rulesText = $this->nullableText($payload['rules_text'] ?? null);

        $startsAt = $this->parseDate($startsAtRaw);

        if ($sport === 'outros') {
            if ($sportCustom === null) {
                $errors[] = 'Informe o esporte em "Outros".';
            } else {
                $sport = $sportCustom;
            }
        } elseif (!in_array($sport, self::SPORTS, true)) {
            $errors[] = 'Esporte inválido.';
        }

        if ($sport !== '' && strlen($sport) > 60) {
            $errors[] = 'Nome do esporte deve ter no máximo 60 caracteres.';
        }

        if (!in_array($level, self::LEVELS, true)) {
            $errors[] = 'Nível inválido.';
        }

        if ($startsAt === null) {
            $errors[] = 'Data e hora inválidas.';
        } elseif ($startsAt <= new DateTimeImmutable('now')) {
            $errors[] = 'A data do jogo deve ser futura.';
        }

        if ($locationName === '' || strlen($locationName) < 3) {
            $errors[] = 'Informe um nome de local com ao menos 3 caracteres.';
        }

        if ($address === '' || strlen($address) < 3) {
            $errors[] = 'Informe um endereço válido.';
        }

        if ($lat === null || $lng === null) {
            $errors[] = 'Selecione um local da lista para preencher latitude e longitude.';
        }

        if ($lat !== null && ($lat < -90 || $lat > 90)) {
            $errors[] = 'Latitude inválida.';
        }

        if ($lng !== null && ($lng < -180 || $lng > 180)) {
            $errors[] = 'Longitude inválida.';
        }

        if ($maxPlayers < 2 || $maxPlayers > 40) {
            $errors[] = 'Número de jogadores deve ficar entre 2 e 40.';
        }

        if (!in_array($privacy, self::PRIVACY, true)) {
            $errors[] = 'Privacidade inválida.';
        }

        if ($price !== null && $price < 0) {
            $errors[] = 'Preço não pode ser negativo.';
        }

        if ($errors !== []) {
            return [
                'success' => false,
                'errors' => $errors,
                'invite_id' => null,
            ];
        }

        $inviteId = $this->invites->create(
            $creatorId,
            $sport,
            $level,
            (string) $startsAt?->format('Y-m-d H:i:s'),
            $locationName,
            $address,
            $lat,
            $lng,
            $maxPlayers,
            $privacy,
            $description,
            $price,
            $rulesText
        );

        return [
            'success' => true,
            'errors' => [],
            'invite_id' => $inviteId,
        ];
    }

    public function listInvites(
        int $currentUserId,
        ?string $sport,
        string $period = 'all',
        bool $onlyWithSlots = false,
        ?float $userLat = null,
        ?float $userLng = null,
        ?int $radiusKm = null
    ): array {
        $normalizedSport = $this->normalizeSport($sport);
        $normalizedPeriod = $this->normalizePeriod($period);
        $normalizedRadius = $this->normalizeRadius($radiusKm);

        $rows = $this->invites->listExplore(
            $currentUserId,
            $normalizedSport,
            $normalizedPeriod,
            $onlyWithSlots,
            $userLat,
            $userLng,
            $normalizedRadius
        );

        return $this->decorateMany($rows, $currentUserId);
    }

    public function getInviteDetail(
        int $inviteId,
        int $currentUserId,
        ?float $userLat = null,
        ?float $userLng = null
    ): ?array {
        if ($inviteId <= 0) {
            return null;
        }

        $invite = $this->invites->findDetailedById($inviteId, $currentUserId, $userLat, $userLng);
        if ($invite === null) {
            return null;
        }

        $detail = $this->decorateInvite($invite, $currentUserId, new DateTimeImmutable('now'));
        $detail['players'] = $this->members->listActiveMembersByRole($inviteId, 'player');
        $detail['waitlist'] = $this->members->listActiveMembersByRole($inviteId, 'waitlist');

        return $detail;
    }

    public function getMyGames(int $userId): array
    {
        return [
            'joined' => $this->decorateMany(
                $this->invites->listByMembershipRole($userId, 'player', false),
                $userId
            ),
            'created' => $this->decorateMany(
                $this->invites->listCreatedByUser($userId, false),
                $userId
            ),
            'waitlist' => $this->decorateMany(
                $this->invites->listByMembershipRole($userId, 'waitlist', false),
                $userId
            ),
            'history' => $this->decorateMany(
                $this->invites->listHistoryForUser($userId),
                $userId
            ),
        ];
    }

    public function joinInvite(int $inviteId, int $userId): array
    {
        if ($inviteId <= 0) {
            return [
                'success' => false,
                'message' => 'Convite inválido.',
            ];
        }

        try {
            $this->pdo->beginTransaction();

            $invite = $this->invites->findByIdForUpdate($inviteId);
            if ($invite === null) {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Convite não encontrado.',
                ];
            }

            $startsAt = new DateTimeImmutable((string) $invite['starts_at']);
            if ($startsAt <= new DateTimeImmutable('now')) {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Este jogo já foi encerrado.',
                ];
            }

            if ((int) $invite['creator_id'] === $userId) {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Você não pode entrar no próprio convite.',
                ];
            }

            $existing = $this->members->findByInviteAndUserForUpdate($inviteId, $userId);
            if ($existing !== null && (string) $existing['status'] === 'active') {
                $this->pdo->rollBack();
                if ((string) $existing['role'] === 'waitlist') {
                    $position = isset($existing['position']) ? (int) $existing['position'] : null;
                    return [
                        'success' => false,
                        'message' => $position !== null
                            ? 'Você já está na fila de espera (posição ' . $position . ').'
                            : 'Você já está na fila de espera.',
                    ];
                }

                return [
                    'success' => false,
                    'message' => 'Você já está inscrito neste jogo.',
                ];
            }

            $playersBefore = $this->members->countActivePlayers($inviteId);
            $maxPlayers = (int) $invite['max_players'];
            $role = $playersBefore < $maxPlayers ? 'player' : 'waitlist';
            $position = $role === 'waitlist' ? $this->members->nextWaitlistPosition($inviteId) : null;

            if ($existing !== null) {
                $this->members->reactivateMember((int) $existing['id'], $role, $position);
            } else {
                $this->members->insertActive($inviteId, $userId, $role, $position);
            }

            $playersAfter = $playersBefore + ($role === 'player' ? 1 : 0);
            $isNowFull = $playersAfter >= $maxPlayers;
            $shouldNotify = false;

            if ($isNowFull) {
                $this->invites->setStatus($inviteId, 'full');
                if ($invite['completed_notified_at'] === null) {
                    $this->invites->markCompletedNotifiedNow($inviteId);
                    $shouldNotify = true;
                }
            } else {
                $this->invites->setStatus($inviteId, 'open');
            }

            $this->pdo->commit();

            if ($isNowFull && $shouldNotify) {
                $emails = $this->members->listActivePlayerEmails($inviteId);
                $emails[] = (string) $invite['creator_email'];
                $this->mailer->sendGameFullNotification($invite, $emails);
            }

            if ($role === 'waitlist') {
                return [
                    'success' => true,
                    'message' => 'Jogo lotado. Você entrou na fila de espera na posição ' . (string) $position . '.',
                    'role' => 'waitlist',
                    'waitlist_position' => $position,
                ];
            }

            return [
                'success' => true,
                'message' => $isNowFull
                    ? 'Inscrição confirmada. O jogo completou as vagas.'
                    : 'Inscrição realizada com sucesso.',
                'role' => 'player',
                'waitlist_position' => null,
            ];
        } catch (Throwable $throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return [
                'success' => false,
                'message' => 'Não foi possível concluir a inscrição agora.',
            ];
        }
    }

    public function leaveInvite(int $inviteId, int $userId): array
    {
        if ($inviteId <= 0) {
            return [
                'success' => false,
                'message' => 'Convite inválido.',
            ];
        }

        try {
            $this->pdo->beginTransaction();

            $invite = $this->invites->findByIdForUpdate($inviteId);
            if ($invite === null) {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Convite não encontrado.',
                ];
            }

            $startsAt = new DateTimeImmutable((string) $invite['starts_at']);
            if ($startsAt <= new DateTimeImmutable('now')) {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Não é possível sair de jogo encerrado.',
                ];
            }

            $member = $this->members->findByInviteAndUserForUpdate($inviteId, $userId);
            if ($member === null || (string) $member['status'] !== 'active') {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Você não está ativo neste convite.',
                ];
            }

            $message = 'Sua inscrição foi cancelada.';

            if ((string) $member['role'] === 'waitlist') {
                $position = isset($member['position']) ? (int) $member['position'] : null;
                $this->members->markAsLeft((int) $member['id']);
                if ($position !== null) {
                    $this->members->compressWaitlistFromPosition($inviteId, $position);
                }
                $message = 'Você saiu da fila de espera.';
            } else {
                $this->members->markAsLeft((int) $member['id']);

                $promoted = $this->members->findFirstWaitlistForUpdate($inviteId);
                if ($promoted !== null) {
                    $promotedPosition = isset($promoted['position']) ? (int) $promoted['position'] : null;
                    $this->members->promoteWaitlistToPlayer((int) $promoted['id']);
                    if ($promotedPosition !== null) {
                        $this->members->compressWaitlistFromPosition($inviteId, $promotedPosition);
                    }
                    $message = 'Você saiu do jogo. ' . (string) $promoted['name'] . ' foi promovido da fila.';
                }
            }

            $playersActive = $this->members->countActivePlayers($inviteId);
            $maxPlayers = (int) $invite['max_players'];

            if ($playersActive >= $maxPlayers) {
                $this->invites->setStatus($inviteId, 'full');
            } else {
                $this->invites->setStatus($inviteId, 'open');
                $this->invites->clearCompletedNotified($inviteId);
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => $message,
            ];
        } catch (Throwable $throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return [
                'success' => false,
                'message' => 'Não foi possível sair deste convite agora.',
            ];
        }
    }

    public function deleteInvite(int $inviteId, int $userId): array
    {
        if ($inviteId <= 0) {
            return [
                'success' => false,
                'message' => 'Convite invalido.',
            ];
        }

        try {
            $this->pdo->beginTransaction();

            $invite = $this->invites->findByIdForUpdate($inviteId);
            if ($invite === null) {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Convite nao encontrado.',
                ];
            }

            if ((int) $invite['creator_id'] !== $userId) {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Apenas o criador pode excluir este convite.',
                ];
            }

            $startsAt = new DateTimeImmutable((string) $invite['starts_at']);
            if ($startsAt <= new DateTimeImmutable('now')) {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Nao e possivel excluir convite ja iniciado.',
                ];
            }

            $this->invites->deleteById($inviteId);
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => 'Convite excluido com sucesso.',
            ];
        } catch (Throwable $throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return [
                'success' => false,
                'message' => 'Nao foi possivel excluir o convite agora.',
            ];
        }
    }

    private function parseDate(string $rawDate): ?DateTimeImmutable
    {
        $cleanDate = trim($rawDate);
        if ($cleanDate === '') {
            return null;
        }

        $fromHtml = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $cleanDate);
        if ($fromHtml !== false) {
            return $fromHtml;
        }

        $fromSql = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $cleanDate);
        if ($fromSql !== false) {
            return $fromSql;
        }

        return null;
    }

    private function parseFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim(str_replace(',', '.', $value));
            if ($value === '') {
                return null;
            }
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return $text;
    }

    private function normalizeCustomSportName(string $value): ?string
    {
        $text = trim(preg_replace('/\s+/', ' ', $value));
        if ($text === '') {
            return null;
        }

        return function_exists('mb_substr')
            ? mb_substr($text, 0, 60)
            : substr($text, 0, 60);
    }

    private function normalizeSport(?string $sport): ?string
    {
        $normalized = $sport !== null ? trim($sport) : null;
        if ($normalized === null || $normalized === '') {
            return null;
        }

        if (!in_array($normalized, self::SPORTS, true)) {
            return null;
        }

        return $normalized;
    }

    private function normalizePeriod(string $period): string
    {
        $normalized = trim($period);
        if (!in_array($normalized, self::PERIODS, true)) {
            return 'all';
        }

        return $normalized;
    }

    private function normalizeRadius(?int $radius): ?int
    {
        if ($radius === null) {
            return null;
        }

        if (!self::isValidRadius($radius)) {
            return null;
        }

        return $radius;
    }

    private function decorateMany(array $rows, int $currentUserId): array
    {
        $now = new DateTimeImmutable('now');

        return array_map(
            fn (array $row): array => $this->decorateInvite($row, $currentUserId, $now),
            $rows
        );
    }

    private function decorateInvite(array $invite, int $currentUserId, DateTimeImmutable $now): array
    {
        $startsAt = new DateTimeImmutable((string) $invite['starts_at']);
        $playersCount = (int) $invite['players_count'];
        $waitlistCount = (int) $invite['waitlist_count'];
        $maxPlayers = (int) $invite['max_players'];
        $isPast = $startsAt <= $now;
        $isFull = $playersCount >= $maxPlayers;
        $membershipRole = $invite['user_membership_role'];
        if (!is_string($membershipRole)) {
            $membershipRole = null;
        }

        $isCreator = (int) $invite['creator_id'] === $currentUserId;
        $status = 'open';
        $statusLabel = 'Aberto';
        if ($isPast) {
            $status = 'ended';
            $statusLabel = 'Encerrado';
        } elseif ($isFull) {
            $status = 'full';
            $statusLabel = 'Completo';
        }

        $canJoin = !$isPast && !$isCreator && $membershipRole === null;
        $canLeave = !$isPast && $membershipRole !== null;
        $joinMode = $isFull ? 'waitlist' : 'player';
        $joinLabel = $isFull ? 'Entrar na fila' : 'Entrar';

        $distance = null;
        $distanceLabel = 'Sem distância';
        if ($invite['distance_km'] !== null) {
            $distance = round((float) $invite['distance_km'], 1);
            $distanceLabel = number_format($distance, 1, ',', '.') . ' km';
        }

        $invite['players_count'] = $playersCount;
        $invite['waitlist_count'] = $waitlistCount;
        $invite['max_players'] = $maxPlayers;
        $invite['slots_left'] = max(0, $maxPlayers - $playersCount);
        $invite['is_past'] = $isPast;
        $invite['is_full'] = $isFull;
        $invite['is_creator'] = $isCreator;
        $invite['user_membership_role'] = $membershipRole;
        $invite['can_join'] = $canJoin;
        $invite['can_leave'] = $canLeave;
        $invite['join_mode'] = $joinMode;
        $invite['join_label'] = $joinLabel;
        $invite['status_derived'] = $status;
        $invite['status_label'] = $statusLabel;
        $invite['starts_at_label'] = $startsAt->format('d/m/Y H:i');
        $invite['distance_km'] = $distance;
        $invite['distance_label'] = $distanceLabel;

        return $invite;
    }
}
