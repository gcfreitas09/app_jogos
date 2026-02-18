<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (is_authenticated()) {
    redirect('explore.php');
}

$errors = [];
$email = '';

if (is_post()) {
    if (!verify_csrf()) {
        $errors[] = 'Token CSRF inválido.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($authService->login($email, $password)) {
            set_flash('success', 'Login realizado com sucesso.');
            redirect('explore.php');
        }

        $errors[] = 'E-mail ou senha inválidos.';
    }
}

$title = 'Login';
$currentUser = null;
require __DIR__ . '/templates/header.php';
?>
<section class="card">
    <h1>Entrar</h1>
    <p class="muted">Acesse sua conta para gerenciar convites e inscrições.</p>

    <?php if ($errors !== []): ?>
        <ul class="error-list">
            <?php foreach ($errors as $error): ?>
                <li><?php echo e((string) $error); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="<?php echo e(url('login.php')); ?>" class="grid">
        <?php echo csrf_field(); ?>
        <div>
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" required value="<?php echo e($email); ?>">
        </div>
        <div>
            <label for="password">Senha</label>
            <input id="password" name="password" type="password" required>
        </div>
        <button type="submit">Entrar</button>
    </form>
</section>
<?php
require __DIR__ . '/templates/footer.php';
