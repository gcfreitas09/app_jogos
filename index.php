<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (is_authenticated()) {
    redirect('explore.php');
}

redirect('login.php');
