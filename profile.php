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

$profile = $profileService->getProfile((int) $currentUser['id']);
if ($profile === null) {
    set_flash('error', 'Perfil não encontrado.');
    redirect('explore.php');
}

$errors = [];
$avatarUrl = (string) ($profile['avatar_url'] ?? '');
$preferredSports = is_array($profile['preferred_sports_list']) ? $profile['preferred_sports_list'] : [];
$defaultRadiusKm = (int) ($profile['default_radius_km'] ?? 5);
$allowLocation = (bool) ($profile['allow_location'] ?? true);

if (is_post()) {
    if (!verify_csrf()) {
        $errors[] = 'Token CSRF inválido.';
    } else {
        $avatarUrl = trim((string) ($_POST['avatar_url'] ?? ''));
        $postedSports = $_POST['preferred_sports'] ?? [];
        $preferredSports = is_array($postedSports)
            ? array_values(array_filter(array_map(
                static fn (mixed $sport): string => trim((string) $sport),
                $postedSports
            )))
            : [];
        $defaultRadiusKm = (int) ($_POST['default_radius_km'] ?? $defaultRadiusKm);
        $allowLocation = ((string) ($_POST['allow_location'] ?? '0')) === '1';

        $result = $profileService->updateProfile(
            (int) $currentUser['id'],
            [
                'avatar_url' => $avatarUrl,
                'preferred_sports' => $preferredSports,
                'default_radius_km' => $defaultRadiusKm,
                'allow_location' => $allowLocation ? '1' : '0',
            ]
        );

        if ($result['success']) {
            set_flash('success', 'Perfil atualizado com sucesso.');
            redirect('profile.php');
        }

        $errors = $result['errors'];
    }
}

$title = 'Perfil';
require __DIR__ . '/templates/header.php';
?>
<section class="hero">
    <div class="hero-content">
        <h1>Perfil</h1>
        <p>Gerencie preferências de esportes e configurações de localização.</p>
    </div>
</section>

<section class="card card-soft">
    <h2>Conta</h2>
    <div class="detail-grid">
        <div>
            <span class="detail-label">Nome</span>
            <strong><?php echo e((string) $profile['name']); ?></strong>
        </div>
        <div>
            <span class="detail-label">E-mail</span>
            <strong><?php echo e((string) $profile['email']); ?></strong>
        </div>
    </div>
</section>

<section class="card card-soft">
    <h2>Preferências</h2>

    <?php if ($errors !== []): ?>
        <ul class="error-list">
            <?php foreach ($errors as $error): ?>
                <li><?php echo e((string) $error); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="<?php echo e(url('profile.php')); ?>" class="grid grid-2">
        <?php echo csrf_field(); ?>

        <div style="grid-column: 1 / -1;">
            <label for="avatar_url">Foto (URL opcional)</label>
            <input id="avatar_url" type="url" name="avatar_url" value="<?php echo e($avatarUrl); ?>" placeholder="https://...">
            <?php if ($avatarUrl !== ''): ?>
                <div style="margin-top: 10px;">
                    <img src="<?php echo e($avatarUrl); ?>" alt="Avatar" style="width:72px;height:72px;border-radius:999px;object-fit:cover;border:1px solid #d7e2e8;">
                </div>
            <?php endif; ?>
        </div>

        <div style="grid-column: 1 / -1;">
            <label>Esportes preferidos</label>
            <div class="invite-grid">
                <?php foreach (App\Services\InviteService::allowedSports() as $sport): ?>
                    <label class="card-soft" style="padding: 10px; border: 1px solid #d7e2e8; border-radius: 12px;">
                        <input
                            type="checkbox"
                            name="preferred_sports[]"
                            value="<?php echo e($sport); ?>"
                            <?php echo in_array($sport, $preferredSports, true) ? 'checked' : ''; ?>
                            style="width: auto; margin-right: 8px;"
                        >
                        <?php echo e($sport); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div>
            <label for="default_radius_km">Raio padrão de busca</label>
            <select id="default_radius_km" name="default_radius_km">
                <?php foreach (App\Services\InviteService::allowedRadii() as $radius): ?>
                    <option value="<?php echo e((string) $radius); ?>" <?php echo $defaultRadiusKm === $radius ? 'selected' : ''; ?>>
                        <?php echo e((string) $radius); ?> km
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="allow_location">Permitir localização</label>
            <input type="hidden" name="allow_location" value="0">
            <select id="allow_location" name="allow_location">
                <option value="1" <?php echo $allowLocation ? 'selected' : ''; ?>>Sim</option>
                <option value="0" <?php echo !$allowLocation ? 'selected' : ''; ?>>Não</option>
            </select>
        </div>

        <div style="grid-column: 1 / -1;">
            <button type="submit">Salvar perfil</button>
        </div>
    </form>
</section>
<?php
require __DIR__ . '/templates/footer.php';
