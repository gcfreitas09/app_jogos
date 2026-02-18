<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;

class AuthService
{
    private UserRepository $users;

    public function __construct(UserRepository $users)
    {
        $this->users = $users;
    }

    public function register(string $name, string $email, string $password): array
    {
        $errors = [];
        $cleanName = trim($name);
        $cleanEmail = strtolower(trim($email));

        if ($cleanName === '' || strlen($cleanName) < 2) {
            $errors[] = 'Informe um nome com ao menos 2 caracteres.';
        }

        if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Informe um e-mail válido.';
        }

        if (strlen($password) < 6) {
            $errors[] = 'A senha deve conter ao menos 6 caracteres.';
        }

        if ($this->users->findByEmail($cleanEmail) !== null) {
            $errors[] = 'Este e-mail já está cadastrado.';
        }

        if ($errors !== []) {
            return [
                'success' => false,
                'errors' => $errors,
            ];
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $this->users->create($cleanName, $cleanEmail, $passwordHash);

        return [
            'success' => true,
            'errors' => [],
        ];
    }

    public function login(string $email, string $password): bool
    {
        $cleanEmail = strtolower(trim($email));
        $user = $this->users->findByEmail($cleanEmail);

        if ($user === null) {
            return false;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];

        return true;
    }

    public function logout(): void
    {
        unset($_SESSION['user_id'], $_SESSION['csrf_token']);
        session_regenerate_id(true);
    }

    public function currentUser(): ?array
    {
        if (!is_authenticated()) {
            return null;
        }

        $userId = (int) $_SESSION['user_id'];

        return $this->users->findById($userId);
    }
}
