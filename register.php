<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (is_authenticated()) {
    redirect('explore.php');
}

$errors = [];
$name = '';
$email = '';

if (is_post()) {
    if (!verify_csrf()) {
        $errors[] = 'Token CSRF inválido.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $result = $authService->register($name, $email, $password);
        if ($result['success']) {
            set_flash('success', 'Conta criada com sucesso. Faça login para continuar.');
            redirect('login.php');
        }

        $errors = $result['errors'];
    }
}

$title = 'Cadastro';
$currentUser = null;
require __DIR__ . '/templates/header.php';
?>
<section class="card">
    <h1>Cadastro</h1>
    <p class="muted">Crie sua conta para publicar e entrar em jogos recreativos.</p>

    <?php if ($errors !== []): ?>
        <ul class="error-list">
            <?php foreach ($errors as $error): ?>
                <li><?php echo e((string) $error); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="<?php echo e(url('register.php')); ?>" class="grid">
        <?php echo csrf_field(); ?>
        <div>
            <label for="name">Nome</label>
            <input id="name" name="name" type="text" required value="<?php echo e($name); ?>">
        </div>
        <div>
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" required value="<?php echo e($email); ?>">
        </div>
        <div>
            <label for="password">Senha</label>
            <input id="password" name="password" type="password" minlength="6" required>
        </div>
        <button type="submit">Criar conta</button>
    </form>
</section>
<?php
require __DIR__ . '/templates/footer.php';
