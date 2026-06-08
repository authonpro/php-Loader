<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  Authon PHP SDK — Software Licensing & Authentication                      ║
 * ║  Version: 1.0.0                                                            ║
 * ║  Dependencies: cURL extension (built-in)                                   ║
 * ║                                                                            ║
 * ║  Website: https://authon.pro                                               ║
 * ║  Docs:    https://authon.pro/docs                                          ║
 * ║  Discord: https://discord.gg/jMZCTKPsmE                                    ║
 * ║  Status:  https://authon.pro/status                                        ║
 * ║  Health:  https://api.authon.pro/health                                    ║
 * ║  GitHub:  https://github.com/authonpro                                     ║
 * ║                                                                            ║
 * ║  Usage:                                                                    ║
 * ║    require_once 'authon.php';                                              ║
 * ║    $auth = new Authon('your-app-id', 'your-api-key');                      ║
 * ║    $auth->init();                                                          ║
 * ║    $result = $auth->login('username', 'password');                         ║
 * ║    if ($result['success']) echo "Welcome " . $auth->username;              ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 *
 * @package  Authon
 * @version  1.0.0
 * @author   Authon Team
 * @license  MIT
 * @link     https://authon.pro
 */

class Authon
{
    /** @var string SDK version */
    const VERSION = '1.0.0';

    /** @var string Default API endpoint */
    const DEFAULT_API_URL = 'https://api.authon.pro/v1';

    /** @var int Default HTTP timeout in seconds */
    const DEFAULT_TIMEOUT = 15;

    // ═══════════════════════════════════════════════════════════════════════════
    // CONFIGURATION
    // ═══════════════════════════════════════════════════════════════════════════

    /** @var string Application ID */
    private $appId;

    /** @var string API Key */
    private $apiKey;

    /** @var string API URL */
    private $apiUrl;

    // ═══════════════════════════════════════════════════════════════════════════
    // SESSION STATE
    // ═══════════════════════════════════════════════════════════════════════════

    /** @var string|null Current session token */
    public $sessionToken = null;

    /** @var string|null Authenticated username */
    public $username = null;

    /** @var int User's access level */
    public $level = 0;

    /** @var string|null Subscription plan name */
    public $subscription = null;

    /** @var string|null Subscription expiration date */
    public $expiresAt = null;

    // ═══════════════════════════════════════════════════════════════════════════
    // APP INFO
    // ═══════════════════════════════════════════════════════════════════════════

    /** @var string|null Application name (from init) */
    public $appName = null;

    /** @var string|null Application version (from init) */
    public $appVersion = null;

    /** @var bool Whether HWID lock is enabled */
    public $hwidLock = false;

    /** @var bool Whether hash check is enabled */
    public $hashCheck = false;

    /** @var bool Whether init() was called successfully */
    public $initialized = false;

    // ═══════════════════════════════════════════════════════════════════════════
    // CONSTRUCTOR
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Creates a new Authon client instance.
     *
     * @param string $appId  Your Application ID from the Authon dashboard.
     * @param string $apiKey Your API Key from the Authon dashboard.
     * @param string $apiUrl Custom API URL (default: https://api.authon.pro/v1).
     *
     * @throws \InvalidArgumentException If appId or apiKey is empty.
     */
    public function __construct(string $appId, string $apiKey, string $apiUrl = self::DEFAULT_API_URL)
    {
        if (empty(trim($appId))) {
            throw new \InvalidArgumentException('appId is required');
        }
        if (empty(trim($apiKey))) {
            throw new \InvalidArgumentException('apiKey is required');
        }

        $this->appId = trim($appId);
        $this->apiKey = trim($apiKey);
        $this->apiUrl = rtrim($apiUrl, '/');
    }

    /**
     * Check if user has an active session.
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return !empty($this->sessionToken);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HWID GENERATION
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Generates a hardware ID unique to the current machine.
     *
     * Windows: Uses disk serial number + computer name.
     * Linux:   Uses /etc/machine-id.
     * macOS:   Uses system_profiler hardware UUID.
     *
     * @return string 32-character lowercase hex MD5 hash.
     */
    public static function getHWID(): string
    {
        $raw = '';

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows: disk serial + computer name
                $output = shell_exec('wmic diskdrive get serialnumber 2>NUL');
                if ($output) {
                    $lines = explode("\n", $output);
                    $raw = isset($lines[1]) ? trim($lines[1]) : '';
                }
                $raw .= gethostname();
            } elseif (PHP_OS_FAMILY === 'Darwin') {
                // macOS: hardware UUID
                $output = shell_exec('system_profiler SPHardwareDataType 2>/dev/null');
                if ($output) {
                    foreach (explode("\n", $output) as $line) {
                        if (strpos($line, 'UUID') !== false) {
                            $parts = explode(':', $line, 2);
                            $raw = isset($parts[1]) ? trim($parts[1]) : '';
                            break;
                        }
                    }
                }
                if (empty($raw)) {
                    $raw = gethostname() . php_uname('m');
                }
            } else {
                // Linux: machine-id
                if (file_exists('/etc/machine-id')) {
                    $raw = trim(file_get_contents('/etc/machine-id'));
                } else {
                    $raw = gethostname() . php_uname('m') . php_uname('r');
                }
            }
        } catch (\Exception $e) {
            $raw = gethostname() . php_uname('m');
        }

        if (empty($raw)) {
            $raw = 'fallback-' . gethostname();
        }

        return md5($raw);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // INITIALIZATION
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Initializes the connection to the Authon API.
     * Must be called before any other API method.
     *
     * @return array{success: bool, message?: string, data?: array}
     */
    public function init(): array
    {
        $result = $this->request(['type' => 'init']);

        if (!empty($result['success'])) {
            $data = $result['data'] ?? [];
            $this->appName = $data['name'] ?? null;
            $this->appVersion = $data['version'] ?? null;
            $this->hwidLock = $data['hwidLock'] ?? false;
            $this->hashCheck = $data['hashCheck'] ?? false;
            $this->initialized = true;
        }

        return $result;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // AUTHENTICATION
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Authenticates with username and password.
     *
     * On success, sets sessionToken, username, level, subscription, expiresAt.
     *
     * @param string      $username User's username.
     * @param string      $password User's password.
     * @param string|null $hwid     Hardware ID (null to auto-generate).
     *
     * @return array{success: bool, message?: string, data?: array}
     *
     * Possible error messages:
     * - "Invalid credentials"
     * - "Account banned"
     * - "Hardware ID mismatch"
     * - "Subscription expired"
     * - "Account is frozen"
     * - "VPN/Proxy connections are not allowed"
     */
    public function login(string $username, string $password, ?string $hwid = null): array
    {
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Username and password are required'];
        }

        $result = $this->request([
            'type'     => 'login',
            'username' => $username,
            'password' => $password,
            'hwid'     => $hwid ?: self::getHWID(),
        ]);

        if (!empty($result['success'])) {
            $this->extractSession($result['data'] ?? []);
        }

        return $result;
    }

    /**
     * Authenticates using a license key only (no username/password).
     *
     * @param string      $licenseKey The license key to validate/activate.
     * @param string|null $hwid       Hardware ID (null to auto-generate).
     *
     * @return array{success: bool, message?: string, data?: array}
     */
    public function license(string $licenseKey, ?string $hwid = null): array
    {
        if (empty($licenseKey)) {
            return ['success' => false, 'message' => 'License key is required'];
        }

        $result = $this->request([
            'type'       => 'license',
            'licenseKey' => $licenseKey,
            'hwid'       => $hwid ?: self::getHWID(),
        ]);

        if (!empty($result['success'])) {
            $this->extractSession($result['data'] ?? []);
        }

        return $result;
    }

    /**
     * Registers a new user account with a license key.
     *
     * @param string      $username   Desired username.
     * @param string      $password   Desired password.
     * @param string      $licenseKey A valid, unused license key.
     * @param string|null $hwid       Hardware ID (null to auto-generate).
     *
     * @return array{success: bool, message?: string}
     */
    public function register(string $username, string $password, string $licenseKey, ?string $hwid = null): array
    {
        if (empty($username) || empty($password) || empty($licenseKey)) {
            return ['success' => false, 'message' => 'Username, password, and licenseKey are required'];
        }

        return $this->request([
            'type'       => 'register',
            'username'   => $username,
            'password'   => $password,
            'licenseKey' => $licenseKey,
            'hwid'       => $hwid ?: self::getHWID(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SESSION MANAGEMENT
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Validates the current session (heartbeat).
     *
     * @return bool True if session is still valid.
     */
    public function check(): bool
    {
        if (empty($this->sessionToken)) return false;

        $result = $this->request([
            'type'         => 'check',
            'sessionToken' => $this->sessionToken,
        ]);

        return !empty($result['success']);
    }

    /**
     * Ends the current session and clears local state.
     *
     * @return bool True if logout was successful.
     */
    public function logout(): bool
    {
        if (empty($this->sessionToken)) return false;

        $result = $this->request([
            'type'         => 'logout',
            'sessionToken' => $this->sessionToken,
        ]);

        if (!empty($result['success'])) {
            $this->sessionToken = null;
            $this->username = null;
            $this->level = 0;
            $this->subscription = null;
            $this->expiresAt = null;
        }

        return !empty($result['success']);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // VARIABLES
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Gets an application-level variable (shared across all users).
     *
     * @param string $key Variable name.
     *
     * @return string|null Variable value, or null if not found.
     */
    public function getVar(string $key): ?string
    {
        $result = $this->request([
            'type'         => 'var',
            'key'          => $key,
            'sessionToken' => $this->sessionToken,
        ]);

        if (!empty($result['success'])) {
            return $result['data']['value'] ?? null;
        }
        return null;
    }

    /**
     * Sets a user-level variable (stored per authenticated user).
     *
     * @param string $key   Variable name.
     * @param string $value Variable value.
     *
     * @return bool True if saved successfully.
     */
    public function setVar(string $key, string $value): bool
    {
        $result = $this->request([
            'type'         => 'setvar',
            'key'          => $key,
            'value'        => $value,
            'sessionToken' => $this->sessionToken,
        ]);

        return !empty($result['success']);
    }

    /**
     * Gets a user-level variable.
     *
     * @param string $key Variable name.
     *
     * @return string|null Variable value, or null if not found.
     */
    public function getUserVar(string $key): ?string
    {
        $result = $this->request([
            'type'         => 'getvar',
            'key'          => $key,
            'sessionToken' => $this->sessionToken,
        ]);

        if (!empty($result['success'])) {
            return $result['data']['value'] ?? null;
        }
        return null;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // FILES
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Lists all files available to the authenticated user.
     *
     * @return array List of file objects [{id, name, size, minLevel}].
     */
    public function listFiles(): array
    {
        $result = $this->request([
            'type'         => 'list_files',
            'sessionToken' => $this->sessionToken,
        ]);

        if (!empty($result['success'])) {
            return $result['data'] ?? [];
        }
        return [];
    }

    /**
     * Downloads a file by its ID and returns raw content.
     *
     * @param string $fileId File ID from listFiles().
     *
     * @return string|null Raw file content, or null on failure.
     */
    public function downloadFile(string $fileId): ?string
    {
        if (empty($this->sessionToken) || empty($fileId)) return null;

        // Try POST endpoint
        $response = $this->requestRaw([
            'type'         => 'file',
            'appId'        => $this->appId,
            'apiKey'       => $this->apiKey,
            'fileId'       => $fileId,
            'sessionToken' => $this->sessionToken,
        ]);

        if ($response !== null) return $response;

        // Fallback: GET endpoint
        $url = $this->apiUrl . '/files/download/' . urlencode($fileId) . '?token=' . urlencode($this->sessionToken);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => ['User-Agent: Authon-PHP-SDK/' . self::VERSION],
        ]);
        $body = curl_exec($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if (strpos($contentType, 'octet-stream') !== false) {
            return $body;
        }

        return null;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // LOGGING & ANALYTICS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Sends an activity log message to the dashboard.
     *
     * @param string $message Log message (max 500 chars).
     *
     * @return bool True if logged successfully.
     */
    public function log(string $message): bool
    {
        $result = $this->request([
            'type'         => 'log',
            'message'      => mb_substr($message, 0, 500),
            'sessionToken' => $this->sessionToken,
        ]);

        return !empty($result['success']);
    }

    /**
     * Gets the list of currently online users.
     *
     * @return array{count: int, users: array}
     */
    public function fetchOnline(): array
    {
        $result = $this->request([
            'type'         => 'fetch_online',
            'sessionToken' => $this->sessionToken,
        ]);

        if (!empty($result['success'])) {
            return $result['data'] ?? ['count' => 0, 'users' => []];
        }
        return ['count' => 0, 'users' => []];
    }

    /**
     * Gets application statistics.
     *
     * @return array{totalUsers: int, onlineUsers: int, totalKeys: int, appVersion: string}
     */
    public function fetchStats(): array
    {
        $result = $this->request([
            'type'         => 'fetch_stats',
            'sessionToken' => $this->sessionToken,
        ]);

        if (!empty($result['success'])) {
            return $result['data'] ?? [];
        }
        return [];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SECURITY
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Checks if an IP address or HWID is blacklisted.
     *
     * @param string|null $ip   IP address to check (null to skip).
     * @param string|null $hwid HWID to check (null to skip).
     *
     * @return array{blacklisted: bool, reason: string|null}
     */
    public function checkBlacklist(?string $ip = null, ?string $hwid = null): array
    {
        $payload = ['type' => 'check_blacklist'];
        if (!empty($ip)) $payload['ip'] = $ip;
        if (!empty($hwid)) $payload['hwid'] = $hwid;

        $result = $this->request($payload);

        if (!empty($result['success'])) {
            return $result['data'] ?? ['blacklisted' => false, 'reason' => null];
        }
        return ['blacklisted' => false, 'reason' => null];
    }

    /**
     * Redeems a referral code for bonus subscription days.
     *
     * @param string $code Referral code.
     *
     * @return array{success: bool, message?: string, data?: array}
     */
    public function redeemReferral(string $code): array
    {
        return $this->request([
            'type'         => 'redeem_referral',
            'code'         => $code,
            'sessionToken' => $this->sessionToken,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // INTERNAL
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Sends a POST request to the Authon API and returns parsed JSON.
     *
     * @param array $data Request payload.
     *
     * @return array Decoded JSON response.
     */
    private function request(array $data): array
    {
        $data['appId'] = $this->appId;
        $data['apiKey'] = $this->apiKey;

        $json = json_encode($data, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::DEFAULT_TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'User-Agent: Authon-PHP-SDK/' . self::VERSION,
                'Content-Length: ' . strlen($json),
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($error)) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $error . '. Check https://authon.pro/status',
            ];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'message' => 'Invalid response from server'];
        }

        return $decoded;
    }

    /**
     * Sends a POST request for binary file downloads.
     *
     * @param array $data Request payload.
     *
     * @return string|null Raw binary content, or null if not a binary response.
     */
    private function requestRaw(array $data): ?string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'User-Agent: Authon-PHP-SDK/' . self::VERSION,
            ],
        ]);

        $response = curl_exec($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if (strpos($contentType, 'octet-stream') !== false) {
            return $response;
        }

        return null;
    }

    /**
     * Extracts session data from a response data array.
     *
     * @param array $data Response data.
     */
    private function extractSession(array $data): void
    {
        $this->sessionToken = $data['sessionToken'] ?? null;
        $this->username = $data['username'] ?? null;
        $this->level = (int)($data['level'] ?? 0);
        $this->subscription = $data['subscription'] ?? null;
        $this->expiresAt = $data['expiresAt'] ?? null;
    }
}
