# Authon PHP SDK

<p align="center">
  <img src="https://authon.pro/logo.png" alt="Authon" width="80" />
  <br/>
  <strong>Official PHP SDK for Authon — Software Licensing & Authentication Platform</strong>
</p>

<p align="center">
  <a href="https://authon.pro">Website</a> •
  <a href="https://authon.pro/docs">Docs</a> •
  <a href="https://discord.gg/jMZCTKPsmE">Discord</a> •
  <a href="https://authon.pro/status">Status</a>
</p>

---

## Requirements

- PHP 7.4+
- cURL extension (usually installed by default)

## Installation

Copy `authon.php` into your project:
```php
require_once 'authon.php';
```

## Quick Start

```php
$auth = new Authon('your-app-id', 'your-api-key');
$auth->init();

$result = $auth->login('username', 'password');
if ($result['success']) {
    echo "Level: " . $auth->level;
}
```

## Run Example

```bash
php example.php
```

## Links

- 🌐 Website: https://authon.pro
- 📖 Docs: https://authon.pro/docs
- 💬 Discord: https://discord.gg/jMZCTKPsmE
- 📊 Status: https://authon.pro/status
- 🔗 API Health: https://api.authon.pro/health

## License

MIT
