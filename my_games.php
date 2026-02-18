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

$games = $inviteService->getMyGames((int) $currentUser['id']);
$joinedGames = $games['joined'];
$createdGames = $games['created'];
$waitlistGames = $games['waitlist'];
$historyGames = $games['history'];

$returnPath = 'my_games.php';

$title = 'Meus Jogos';
require __DIR__ . '/templates/header.php';
?>
<section class="hero">
    <div class="hero-content">
        <h1>Meus Jogos</h1>
        <p>Acompanhe convites que você participa, cria e aguarda na fila de espera.</p>
    </div>
    <a class="btn btn-lg" href="<?php echo e(url('create_invite.php')); ?>">Criar Convite</a>
</section>

<section class="section-block">
    <div class="section-head">
        <h2>Inscritos / Aceitos</h2>
        <span class="pill-count"><?php echo e((string) count($joinedGames)); ?></span>
    </div>
    <?php if ($joinedGames === []): ?>
        <div class="empty-state card-soft">Você não está inscrito em jogos futuros.</div>
    <?php else: ?>
        <div class="invite-grid">
            <?php foreach ($joinedGames as $invite): ?>
                <article class="invite-card">
                    <div class="invite-top">
                        <span class="badge sport"><?php echo e((string) $invite['sport']); ?></span>
                        <span class="badge status <?php echo e((string) $invite['status_derived']); ?>"><?php echo e((string) $invite['status_label']); ?></span>
                    </div>
                    <h3><?php echo e((string) $invite['location_name']); ?></h3>
                    <p class="muted">Início: <?php echo e((string) $invite['starts_at_label']); ?></p>
                    <p class="muted">Vagas: <?php echo e((string) $invite['players_count']); ?> / <?php echo e((string) $invite['max_players']); ?></p>
                    <div class="actions-row">
                        <a class="btn btn-outline" href="<?php echo e(url('invite.php?id=' . (string) $invite['id'])); ?>">Detalhes</a>
                        <?php if ($invite['can_leave']): ?>
                            <form method="post" action="<?php echo e(url('leave_invite.php')); ?>" class="inline-form">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="invite_id" value="<?php echo e((string) $invite['id']); ?>">
                                <input type="hidden" name="redirect_to" value="<?php echo e($returnPath); ?>">
                                <button type="submit" class="btn-danger">Sair</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section-block">
    <div class="section-head">
        <h2>Criados por mim</h2>
        <span class="pill-count"><?php echo e((string) count($createdGames)); ?></span>
    </div>
    <?php if ($createdGames === []): ?>
        <div class="empty-state card-soft">Você ainda não criou convites futuros.</div>
    <?php else: ?>
        <div class="invite-grid">
            <?php foreach ($createdGames as $invite): ?>
                <article class="invite-card">
                    <div class="invite-top">
                        <span class="badge sport"><?php echo e((string) $invite['sport']); ?></span>
                        <span class="badge status <?php echo e((string) $invite['status_derived']); ?>"><?php echo e((string) $invite['status_label']); ?></span>
                    </div>
                    <h3><?php echo e((string) $invite['location_name']); ?></h3>
                    <p class="muted">Início: <?php echo e((string) $invite['starts_at_label']); ?></p>
                    <p class="muted">Jogadores: <?php echo e((string) $invite['players_count']); ?> / <?php echo e((string) $invite['max_players']); ?></p>
                    <p class="muted">Fila: <?php echo e((string) $invite['waitlist_count']); ?></p>
                    <div class="actions-row">
                        <a class="btn btn-outline" href="<?php echo e(url('invite.php?id=' . (string) $invite['id'])); ?>">Abrir convite</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section-block">
    <div class="section-head">
        <h2>Fila de espera</h2>
        <span class="pill-count"><?php echo e((string) count($waitlistGames)); ?></span>
    </div>
    <?php if ($waitlistGames === []): ?>
        <div class="empty-state card-soft">Você não está em nenhuma fila de espera.</div>
    <?php else: ?>
        <div class="invite-grid">
            <?php foreach ($waitlistGames as $invite): ?>
                <article class="invite-card">
                    <div class="invite-top">
                        <span class="badge sport"><?php echo e((string) $invite['sport']); ?></span>
                        <span class="pill-count">Fila</span>
                    </div>
                    <h3><?php echo e((string) $invite['location_name']); ?></h3>
                    <p class="muted">Início: <?php echo e((string) $invite['starts_at_label']); ?></p>
                    <p class="muted">Fila atual: <?php echo e((string) $invite['waitlist_count']); ?> pessoa(s)</p>
                    <div class="actions-row">
                        <a class="btn btn-outline" href="<?php echo e(url('invite.php?id=' . (string) $invite['id'])); ?>">Detalhes</a>
                        <?php if ($invite['can_leave']): ?>
                            <form method="post" action="<?php echo e(url('leave_invite.php')); ?>" class="inline-form">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="invite_id" value="<?php echo e((string) $invite['id']); ?>">
                                <input type="hidden" name="redirect_to" value="<?php echo e($returnPath); ?>">
                                <button type="submit" class="btn-danger">Sair da fila</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section-block">
    <div class="section-head">
        <h2>Histórico</h2>
        <span class="pill-count"><?php echo e((string) count($historyGames)); ?></span>
    </div>
    <?php if ($historyGames === []): ?>
        <div class="empty-state card-soft">Sem histórico de jogos no momento.</div>
    <?php else: ?>
        <div class="invite-grid past-grid">
            <?php foreach ($historyGames as $invite): ?>
                <article class="invite-card invite-card-past">
                    <div class="invite-top">
                        <span class="badge sport"><?php echo e((string) $invite['sport']); ?></span>
                        <span class="badge status ended">Encerrado</span>
                    </div>
                    <h3><?php echo e((string) $invite['location_name']); ?></h3>
                    <p class="muted"><?php echo e((string) $invite['starts_at_label']); ?></p>
                    <div class="actions-row">
                        <a class="btn btn-outline" href="<?php echo e(url('invite.php?id=' . (string) $invite['id'])); ?>">Ver detalhes</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php
require __DIR__ . '/templates/footer.php';
