<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_auth();

$currentUser = $authService->currentUser();
if ($currentUser === null) {
    $authService->logout();
    set_flash('error', 'Sua sessão expirou. Faça login novamente.');
    redirect('login.php');
}

$inviteId = (int) ($_GET['id'] ?? 0);
if ($inviteId <= 0) {
    set_flash('error', 'Convite inválido.');
    redirect('explore.php');
}

$invite = $inviteService->getInviteDetail($inviteId, (int) $currentUser['id']);
if ($invite === null) {
    set_flash('error', 'Convite não encontrado.');
    redirect('explore.php');
}

$returnPath = 'invite.php?id=' . (string) $inviteId;
$mapUrl = null;
if ((string) ($invite['address'] ?? '') !== '' || (string) ($invite['location_name'] ?? '') !== '') {
    $mapQuery = trim((string) ($invite['address'] ?? ''));
    if ($mapQuery === '') {
        $mapQuery = trim((string) ($invite['location_name'] ?? ''));
    }
    $mapUrl = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($mapQuery);
} elseif ($invite['lat'] !== null && $invite['lng'] !== null) {
    $mapUrl = 'https://www.google.com/maps/search/?api=1&query=' . $invite['lat'] . ',' . $invite['lng'];
}

$resolveAvatarUrl = static function (?string $avatar): string {
    $clean = trim((string) $avatar);
    if ($clean === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $clean) === 1) {
        return $clean;
    }

    return url($clean);
};

$title = 'Detalhe do Convite';
require __DIR__ . '/templates/header.php';
?>
<style>
    .participant-main {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }
    .participant-avatar {
        width: 34px;
        height: 34px;
        border-radius: 999px;
        object-fit: cover;
        border: 1px solid #b8ccda;
        flex: 0 0 34px;
    }
    html[data-theme='dark'] .participant-avatar {
        border-color: #3c5f79;
    }
</style>
<section class="card card-soft">
    <div class="section-head">
        <h1 style="margin: 0;">Detalhes do Convite</h1>
        <span class="badge status <?php echo e((string) $invite['status_derived']); ?>">
            <?php echo e((string) $invite['status_label']); ?>
        </span>
    </div>

    <div class="detail-grid">
        <div>
            <span class="detail-label">Esporte</span>
            <strong><?php echo e((string) $invite['sport']); ?></strong>
        </div>
        <div>
            <span class="detail-label">Início</span>
            <strong><?php echo e((string) $invite['starts_at_label']); ?></strong>
        </div>
        <div>
            <span class="detail-label">Local</span>
            <strong><?php echo e((string) $invite['location_name']); ?></strong>
        </div>
        <div>
            <span class="detail-label">Criador</span>
            <strong><?php echo e((string) $invite['creator_name']); ?></strong>
        </div>
        <div>
            <span class="detail-label">Privacidade</span>
            <strong><?php echo e((string) ($invite['privacy'] === 'private' ? 'Privado' : 'Público')); ?></strong>
        </div>
        <div>
            <span class="detail-label">Jogadores</span>
            <strong><?php echo e((string) $invite['players_count']); ?> / <?php echo e((string) $invite['max_players']); ?></strong>
        </div>
        <div>
            <span class="detail-label">Fila</span>
            <strong><?php echo e((string) $invite['waitlist_count']); ?></strong>
        </div>
        <div>
            <span class="detail-label">Preço</span>
            <strong><?php echo $invite['price'] !== null ? 'R$ ' . e(number_format((float) $invite['price'], 2, ',', '.')) : 'Gratuito'; ?></strong>
        </div>
    </div>

    <?php if ((string) ($invite['description'] ?? '') !== ''): ?>
        <div style="margin-top: 14px;">
            <span class="detail-label">Descrição</span>
            <p style="margin: 6px 0 0;"><?php echo nl2br(e((string) $invite['description'])); ?></p>
        </div>
    <?php endif; ?>

    <?php if ((string) ($invite['rules_text'] ?? '') !== ''): ?>
        <div style="margin-top: 14px;">
            <span class="detail-label">Regras</span>
            <p style="margin: 6px 0 0;"><?php echo nl2br(e((string) $invite['rules_text'])); ?></p>
        </div>
    <?php endif; ?>

    <div class="actions-row" style="margin-top: 16px;">
        <a class="btn btn-outline" href="<?php echo e(url('explore.php')); ?>">Voltar para explorar</a>
        <?php if ($mapUrl !== null): ?>
            <a class="btn btn-outline" href="<?php echo e($mapUrl); ?>" target="_blank" rel="noopener noreferrer">Abrir no mapa</a>
        <?php endif; ?>

        <?php if ($invite['can_join']): ?>
            <form method="post" action="<?php echo e(url('join_invite.php')); ?>" class="inline-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="invite_id" value="<?php echo e((string) $invite['id']); ?>">
                <input type="hidden" name="redirect_to" value="<?php echo e($returnPath); ?>">
                <button type="submit"><?php echo e((string) $invite['join_label']); ?></button>
            </form>
        <?php elseif ($invite['can_leave']): ?>
            <form method="post" action="<?php echo e(url('leave_invite.php')); ?>" class="inline-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="invite_id" value="<?php echo e((string) $invite['id']); ?>">
                <input type="hidden" name="redirect_to" value="<?php echo e($returnPath); ?>">
                <button type="submit" class="btn-danger">
                    <?php echo $invite['user_membership_role'] === 'waitlist' ? 'Sair da fila' : 'Sair do jogo'; ?>
                </button>
            </form>
        <?php elseif ($invite['is_creator']): ?>
            <form method="post" action="<?php echo e(url('delete_invite.php')); ?>" class="inline-form" onsubmit="return confirm('Tem certeza que deseja excluir este convite?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="invite_id" value="<?php echo e((string) $invite['id']); ?>">
                <input type="hidden" name="redirect_to" value="my_games.php">
                <button type="submit" class="btn-danger">Excluir convite</button>
            </form>
            <span class="hint">Você criou este convite.</span>
        <?php else: ?>
            <span class="hint">Sem ações disponíveis.</span>
        <?php endif; ?>
    </div>
</section>

<section class="card card-soft">
    <div class="section-head">
        <h2 style="margin: 0;">Jogadores confirmados</h2>
        <span class="pill-count"><?php echo e((string) count($invite['players'])); ?></span>
    </div>
    <?php if ($invite['players'] === []): ?>
        <div class="empty-state card-soft">Nenhum jogador confirmado ainda.</div>
    <?php else: ?>
        <div class="participant-list">
            <?php foreach ($invite['players'] as $member): ?>
                <?php $memberAvatar = $resolveAvatarUrl((string) ($member['avatar_url'] ?? '')); ?>
                <div class="participant-item">
                    <div class="participant-main">
                        <?php if ($memberAvatar !== ''): ?>
                            <img
                                src="<?php echo e($memberAvatar); ?>"
                                alt="Foto de <?php echo e((string) $member['name']); ?>"
                                class="participant-avatar"
                            >
                        <?php endif; ?>
                        <div>
                            <strong><?php echo e((string) $member['name']); ?></strong>
                            <p class="muted" style="margin: 2px 0 0;"><?php echo e((string) $member['email']); ?></p>
                        </div>
                    </div>
                    <span class="muted"><?php echo e((new DateTimeImmutable((string) $member['joined_at']))->format('d/m/Y H:i')); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="card card-soft">
    <div class="section-head">
        <h2 style="margin: 0;">Fila de espera</h2>
        <span class="pill-count"><?php echo e((string) count($invite['waitlist'])); ?></span>
    </div>
    <?php if ($invite['waitlist'] === []): ?>
        <div class="empty-state card-soft">Não há pessoas na fila de espera.</div>
    <?php else: ?>
        <div class="participant-list">
            <?php foreach ($invite['waitlist'] as $member): ?>
                <?php $memberAvatar = $resolveAvatarUrl((string) ($member['avatar_url'] ?? '')); ?>
                <div class="participant-item">
                    <div class="participant-main">
                        <?php if ($memberAvatar !== ''): ?>
                            <img
                                src="<?php echo e($memberAvatar); ?>"
                                alt="Foto de <?php echo e((string) $member['name']); ?>"
                                class="participant-avatar"
                            >
                        <?php endif; ?>
                        <div>
                            <strong>#<?php echo e((string) $member['position']); ?> - <?php echo e((string) $member['name']); ?></strong>
                            <p class="muted" style="margin: 2px 0 0;"><?php echo e((string) $member['email']); ?></p>
                        </div>
                    </div>
                    <span class="muted"><?php echo e((new DateTimeImmutable((string) $member['joined_at']))->format('d/m/Y H:i')); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php
require __DIR__ . '/templates/footer.php';
