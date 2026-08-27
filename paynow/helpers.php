<?php

declare(strict_types=1);

function paynow_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Project root = parent of /paynow
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath = rtrim(dirname($script), '/');
    if ($basePath === '' || $basePath === '\\' || $basePath === '.') {
        $basePath = '';
    }

    return $scheme . '://' . $host . $basePath;
}

function paynow_ensure_orders_dir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "Deny from all\n");
    }
}

function paynow_save_order(string $dir, string $reference, array $data): void
{
    paynow_ensure_orders_dir($dir);
    $safe = preg_replace('/[^A-Za-z0-9_-]/', '', $reference);
    file_put_contents(
        $dir . '/' . $safe . '.json',
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}

function paynow_load_order(string $dir, string $reference): ?array
{
    $safe = preg_replace('/[^A-Za-z0-9_-]/', '', $reference);
    $path = $dir . '/' . $safe . '.json';
    if (!is_file($path)) {
        return null;
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

function paynow_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function paynow_create_client(array $config, ?string $returnWithRef = null): \Paynow\Payments\Paynow
{
    $base = paynow_base_url();
    $returnUrl = $base . '/paynow/return.php';
    if ($returnWithRef !== null && $returnWithRef !== '') {
        $returnUrl .= '?ref=' . rawurlencode($returnWithRef);
    }

    return new \Paynow\Payments\Paynow(
        (string) $config['integration_id'],
        (string) $config['integration_key'],
        $returnUrl,
        $base . '/paynow/update.php'
    );
}
