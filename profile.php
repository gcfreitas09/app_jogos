<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_auth();

$currentUser = $authService->currentUser();
if ($currentUser === null) {
    $authService->logout();
    set_flash('error', 'Sua sessao expirou. Faca login novamente.');
    redirect('login.php');
}

$profile = $profileService->getProfile((int) $currentUser['id']);
if ($profile === null) {
    set_flash('error', 'Perfil nao encontrado.');
    redirect('explore.php');
}

$allowedSports = App\Services\InviteService::allowedSports();
$errors = [];
$displayName = (string) ($profile['name'] ?? '');
$avatarUrl = (string) ($profile['avatar_url'] ?? '');
$preferredSports = is_array($profile['preferred_sports_list']) ? $profile['preferred_sports_list'] : [];
$defaultRadiusKm = (int) ($profile['default_radius_km'] ?? 5);
$allowLocation = (bool) ($profile['allow_location'] ?? true);

$normalizeSportsText = static function (string $raw): array {
    $items = preg_split('/[,;\n\r]+/', $raw) ?: [];
    $clean = [];
    foreach ($items as $item) {
        $sport = trim((string) $item);
        if ($sport === '') {
            continue;
        }
        $sport = preg_replace('/\s+/u', ' ', $sport);
        if (!is_string($sport) || $sport === '') {
            continue;
        }
        $clean[] = $sport;
    }

    return array_values(array_unique($clean));
};

$avatarLocalFile = static function (string $storedValue): ?string {
    if (!preg_match('#^storage/uploads/avatars/[a-zA-Z0-9._-]+$#', $storedValue)) {
        return null;
    }

    return __DIR__ . '/' . $storedValue;
};

if (is_post()) {
    if (!verify_csrf()) {
        $errors[] = 'Token CSRF invalido.';
    } else {
        $oldAvatarUrl = $avatarUrl;
        $displayName = trim((string) ($_POST['name'] ?? $displayName));

        $postedSports = $_POST['preferred_sports'] ?? [];
        $selectedSports = is_array($postedSports)
            ? array_values(array_filter(array_map(
                static fn (mixed $sport): string => trim((string) $sport),
                $postedSports
            )))
            : [];

        $customSportsInput = trim((string) ($_POST['preferred_sports_other'] ?? ''));
        $customSports = $normalizeSportsText($customSportsInput);
        $preferredSports = array_values(array_unique(array_merge($selectedSports, $customSports)));

        $defaultRadiusKm = (int) ($_POST['default_radius_km'] ?? $defaultRadiusKm);
        $allowLocation = ((string) ($_POST['allow_location'] ?? '0')) === '1';

        $removeAvatar = ((string) ($_POST['remove_avatar'] ?? '0')) === '1';
        $uploadedAvatarUrl = null;

        $avatarUpload = $_FILES['avatar_file'] ?? null;
        if (is_array($avatarUpload) && (int) ($avatarUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $uploadError = (int) ($avatarUpload['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadError !== UPLOAD_ERR_OK) {
                $errors[] = 'Nao foi possivel enviar a imagem agora.';
            } else {
                $tmpPath = (string) ($avatarUpload['tmp_name'] ?? '');
                $size = (int) ($avatarUpload['size'] ?? 0);

                if ($size <= 0 || $size > (5 * 1024 * 1024)) {
                    $errors[] = 'A imagem deve ter no maximo 5 MB.';
                } elseif ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
                    $errors[] = 'Arquivo de imagem invalido.';
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = (string) $finfo->file($tmpPath);
                    $allowedMimes = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        'image/gif' => 'gif',
                    ];

                    if (!isset($allowedMimes[$mime])) {
                        $errors[] = 'Formato de imagem nao suportado. Use JPG, PNG, WEBP ou GIF.';
                    } else {
                        $avatarDir = __DIR__ . '/storage/uploads/avatars';
                        if (!is_dir($avatarDir) && !mkdir($avatarDir, 0775, true) && !is_dir($avatarDir)) {
                            $errors[] = 'Nao foi possivel criar a pasta de upload.';
                        } else {
                            $random = bin2hex(random_bytes(6));
                            $fileName = 'u' . (string) $currentUser['id'] . '_' . date('YmdHis') . '_' . $random . '.' . $allowedMimes[$mime];
                            $targetPath = $avatarDir . '/' . $fileName;

                            if (!move_uploaded_file($tmpPath, $targetPath)) {
                                $errors[] = 'Falha ao salvar a imagem enviada.';
                            } else {
                                $uploadedAvatarUrl = 'storage/uploads/avatars/' . $fileName;
                                $avatarUrl = $uploadedAvatarUrl;
                            }
                        }
                    }
                }
            }
        }

        if ($removeAvatar && $uploadedAvatarUrl === null) {
            $avatarUrl = '';
        }

        if ($errors === []) {
            $result = $profileService->updateProfile(
                (int) $currentUser['id'],
                [
                    'name' => $displayName,
                    'avatar_url' => $avatarUrl,
                    'preferred_sports' => $preferredSports,
                    'default_radius_km' => $defaultRadiusKm,
                    'allow_location' => $allowLocation ? '1' : '0',
                ]
            );

            if ($result['success']) {
                $oldLocalPath = $avatarLocalFile($oldAvatarUrl);
                $newLocalPath = $avatarLocalFile($avatarUrl);
                if ($oldLocalPath !== null && $oldLocalPath !== $newLocalPath && is_file($oldLocalPath)) {
                    @unlink($oldLocalPath);
                }

                set_flash('success', 'Perfil atualizado com sucesso.');
                redirect('profile.php');
            }

            if ($uploadedAvatarUrl !== null) {
                $uploadedLocalPath = $avatarLocalFile($uploadedAvatarUrl);
                if ($uploadedLocalPath !== null && is_file($uploadedLocalPath)) {
                    @unlink($uploadedLocalPath);
                }
            }

            $errors = $result['errors'];
        }
    }
}

$customSportsSelected = array_values(array_filter(
    $preferredSports,
    static fn (string $sport): bool => !in_array($sport, $allowedSports, true)
));
$preferredSportsOtherText = implode(', ', $customSportsSelected);
$avatarPreviewUrl = $avatarUrl;
if ($avatarPreviewUrl !== '' && !str_starts_with($avatarPreviewUrl, 'http://') && !str_starts_with($avatarPreviewUrl, 'https://')) {
    $avatarPreviewUrl = url($avatarPreviewUrl);
}

$title = 'Perfil';
require __DIR__ . '/templates/header.php';
?>
<section class="hero">
    <div class="hero-content">
        <h1>Perfil</h1>
        <p>Gerencie preferencias de esportes e configuracoes de localizacao.</p>
    </div>
</section>

<section class="card card-soft">
    <h2>Conta</h2>
    <div class="detail-grid">
        <div>
            <span class="detail-label">Nome atual</span>
            <strong><?php echo e((string) $profile['name']); ?></strong>
        </div>
        <div>
            <span class="detail-label">E-mail</span>
            <strong><?php echo e((string) $profile['email']); ?></strong>
        </div>
    </div>
</section>

<section class="card card-soft">
    <h2>Preferencias</h2>

    <?php if ($errors !== []): ?>
        <ul class="error-list">
            <?php foreach ($errors as $error): ?>
                <li><?php echo e((string) $error); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="<?php echo e(url('profile.php')); ?>" class="grid grid-2" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>

        <div>
            <label for="name">Nome</label>
            <input id="name" type="text" name="name" value="<?php echo e($displayName); ?>" maxlength="120" required>
        </div>

        <div>
            <label for="email_readonly">E-mail</label>
            <input id="email_readonly" type="text" value="<?php echo e((string) $profile['email']); ?>" readonly>
        </div>

        <div style="grid-column: 1 / -1;">
            <label for="avatar_file">Foto de perfil (upload)</label>
            <input id="avatar_file" type="file" name="avatar_file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
            <p class="muted" style="margin: 8px 0 0;">Formatos: JPG, PNG, WEBP ou GIF. Tamanho maximo: 5 MB.</p>
            <?php if ($avatarPreviewUrl !== ''): ?>
                <div style="margin-top: 10px; display: flex; align-items: center; gap: 12px;">
                    <img src="<?php echo e($avatarPreviewUrl); ?>" alt="Avatar" style="width:72px;height:72px;border-radius:999px;object-fit:cover;border:1px solid #d7e2e8;">
                    <label style="display:inline-flex;align-items:center;gap:8px;font-weight:600;">
                        <input type="checkbox" name="remove_avatar" value="1" style="width:auto;">
                        Remover foto atual
                    </label>
                </div>
            <?php endif; ?>
        </div>

        <div style="grid-column: 1 / -1;">
            <label>Esportes preferidos</label>
            <div class="invite-grid">
                <?php foreach ($allowedSports as $sport): ?>
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
            <div style="margin-top: 10px;">
                <label for="preferred_sports_other">Outros esportes</label>
                <input
                    id="preferred_sports_other"
                    type="text"
                    name="preferred_sports_other"
                    value="<?php echo e($preferredSportsOtherText); ?>"
                    placeholder="Ex: Handebol, Basquete, Volei"
                >
                <p class="muted" style="margin: 8px 0 0;">Separe esportes por virgula.</p>
            </div>
        </div>

        <div>
            <label for="default_radius_km">Raio padrão de busca por games (km)</label>
            <input
                id="default_radius_km"
                type="number"
                name="default_radius_km"
                min="<?php echo e((string) App\Services\InviteService::MIN_RADIUS_KM); ?>"
                max="<?php echo e((string) App\Services\InviteService::MAX_RADIUS_KM); ?>"
                step="1"
                list="default_radius_km_options"
                value="<?php echo e((string) $defaultRadiusKm); ?>"
                required
            >
            <datalist id="default_radius_km_options">
                <?php foreach (App\Services\InviteService::allowedRadii() as $radius): ?>
                    <option value="<?php echo e((string) $radius); ?>"><?php echo e((string) $radius); ?> km</option>
                <?php endforeach; ?>
            </datalist>
        </div>

        <div>
            <label for="allow_location">Permitir localizacao</label>
            <input type="hidden" name="allow_location" value="0">
            <select id="allow_location" name="allow_location">
                <option value="1" <?php echo $allowLocation ? 'selected' : ''; ?>>Sim</option>
                <option value="0" <?php echo !$allowLocation ? 'selected' : ''; ?>>Nao</option>
            </select>
        </div>

        <div style="grid-column: 1 / -1;">
            <button type="submit">Salvar perfil</button>
        </div>
    </form>
</section>
<?php
require __DIR__ . '/templates/footer.php';
