<?php
/**
 * Authon PHP SDK - Full Usage Example
 * Run: php example.php
 */

require_once 'authon.php';

// ============ SETUP ============
$auth = new Authon('your-app-id', 'your-api-key');

// ============ CONNECT ============
if (!$auth->init()) {
    echo "[-] Failed to connect to Authon API\n";
    exit(1);
}
echo "[+] Connected: {$auth->appName} v{$auth->appVersion}\n";

// ============ AUTHENTICATE ============
echo "\n[1] Login (Username + Password)\n";
echo "[2] License Key\n";
echo "\n> ";
$choice = trim(fgets(STDIN));

if ($choice === '1') {
    echo "Username: ";
    $username = trim(fgets(STDIN));
    echo "Password: ";
    $password = trim(fgets(STDIN));
    $result = $auth->login($username, $password);
} else {
    echo "License Key: ";
    $key = trim(fgets(STDIN));
    $result = $auth->license($key);
}

if (!($result['success'] ?? false)) {
    echo "\n[-] " . ($result['message'] ?? 'Unknown error') . "\n";
    exit(1);
}

echo "\n[+] Authenticated!\n";
echo "    Level: {$auth->level}\n";
echo "    Subscription: " . ($auth->subscription ?? 'None') . "\n";
echo "    Expires: " . ($auth->expiresAt ?? 'Lifetime') . "\n";

// ============ USE FEATURES ============
$msg = $auth->getVar('welcome_message');
if ($msg) echo "\n[*] {$msg}\n";

$files = $auth->listFiles();
if (count($files) > 0) {
    echo "\n[*] Available files (" . count($files) . "):\n";
    foreach ($files as $i => $f) {
        echo "    [" . ($i + 1) . "] {$f['name']} (" . round($f['size'] / 1024) . " KB)\n";
    }
}

$auth->log('PHP SDK example executed');

// ============ CLEANUP ============
echo "\n[+] Done. Logging out...\n";
$auth->logout();
