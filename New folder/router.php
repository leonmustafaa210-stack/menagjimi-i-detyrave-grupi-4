<?php
declare(strict_types=1);

// Serveri i integruar PHP i dërgon adresat /api/ te backend-i i aplikacionit.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (is_string($path) && ($path === '/api' || str_starts_with($path, '/api/'))) {
    require __DIR__ . DIRECTORY_SEPARATOR . 'app.php';
    exit;
}

return false;
