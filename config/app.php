<?php
declare(strict_types=1);

return [
    'app_name' => 'App Jogos',
    'base_url' => 'http://localhost/app_jogos',
    'timezone' => 'America/Sao_Paulo',
    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'app_jogos',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'from_email' => 'no-reply@app-jogos.local',
        'from_name' => 'App Jogos',
    ],
    'google_maps' => [
        'api_key' => getenv('GOOGLE_MAPS_API_KEY') ?: '',
    ],
];
