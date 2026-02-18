<?php
declare(strict_types=1);

namespace App\Core;

class Mailer
{
    private array $config;
    private string $logPath;

    public function __construct(array $config, string $logPath)
    {
        $this->config = $config;
        $this->logPath = $logPath;
    }

    public function send(string $to, string $subject, string $message): bool
    {
        $fromName = $this->config['from_name'] ?? 'App Jogos';
        $fromEmail = $this->config['from_email'] ?? 'no-reply@app-jogos.local';

        $headers = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-type: text/plain; charset=UTF-8',
            sprintf('From: %s <%s>', $fromName, $fromEmail),
        ]);

        $sent = @mail($to, $subject, $message, $headers);

        if (!$sent) {
            $this->logFailedMail($to, $subject, $message);
        }

        return $sent;
    }

    public function sendGameFullNotification(array $invite, array $emails): void
    {
        $uniqueEmails = array_values(array_unique(array_filter($emails)));
        if ($uniqueEmails === []) {
            return;
        }

        $subject = sprintf('Jogo completo: %s', $invite['sport']);
        $dateTime = (string) ($invite['starts_at'] ?? $invite['game_datetime'] ?? '');
        $location = (string) ($invite['location_name'] ?? $invite['location'] ?? '');
        $message = sprintf(
            "As vagas do jogo foram preenchidas.\n\nEsporte: %s\nData: %s\nLocal: %s\n",
            $invite['sport'],
            $dateTime,
            $location
        );

        foreach ($uniqueEmails as $email) {
            $this->send((string) $email, $subject, $message);
        }
    }

    private function logFailedMail(string $to, string $subject, string $message): void
    {
        $directory = dirname($this->logPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $line = sprintf(
            "[%s] Falha no envio | to=%s | subject=%s | message=%s\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            str_replace(["\r", "\n"], ' ', $message)
        );

        file_put_contents($this->logPath, $line, FILE_APPEND);
    }
}
