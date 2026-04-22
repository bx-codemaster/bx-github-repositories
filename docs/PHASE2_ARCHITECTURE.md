# BX GitHub Repositories - Phase 2 Redesign

## Übersicht: KnpLabs-basierte Architektur

**Datum**: 22. April 2026  
**Status**: Phase 2 Architecture Redesign Complete  
**Nächster Schritt**: Phase 3 (File Download/Sync)

---

## Architektur-Änderungen

### Vorher (Chaotisch, 1000+ Z.):
```
bx_github_repositories_crypto.php     (155 Z.) → Token Encryption
bx_github_repositories_api.php        (550+ Z.) → Entfernt (war monolithisch)
  └─ Enthielt vorher JWT, App-Auth, Token-Caching und API-Calls gemischt
```

### Nachher (Sauber, Delegiert):
```
bx_github_repositories_jwt_provider.php       (85 Z.)  ← RS256 JWT nur
bx_github_repositories_app_manager.php        (120 Z.) ← Token Exchange + Caching
bx_github_repositories_client_factory.php     (100 Z.) ← KnpLabs Wrapper
bx_github_repositories_crypto.php             (155 Z.) ← AES-256-CBC (unverändert)
bx_github_repositories_helpers.php            (100 Z.) ← Validation (unverändert)
+ KnpLabs Client (externe Library)                      ← HTTP, API, Routing
```

**Ersparnis**: 1000+ Zeilen → ~450 Zeilen custom Code  
**Vorteil**: Wartbar, testbar, delegiert HTTP zu Industry-Standard

---

## Komponenten (Neue Phase 2)

### 1. JWT Provider (`bx_github_repositories_jwt_provider.php`)

**Aufgabe**: RS256-signed JWT für GitHub App Auth generieren

**Public API**:
```php
// JWT erzeugen
$jwt = bx_github_repositories_jwt_provider::buildJWT(
    $app_id,              // int: GitHub App ID
    $private_key_pem,     // string: RSA Private Key (PEM)
    $iss_buffer = 60,     // int: Clock Skew Buffer (Sekunden)
    $exp_offset = 540     // int: Token Gültigkeit (9 Min, max 10 Min)
);
// Returns: "eyJhbGciOiJSUzI1NiIs..."

// Private Key normalisieren (escaped \n, Base64 dekodiert)
$key = bx_github_repositories_jwt_provider::normalizePrivateKey($encrypted_or_escaped_key);
```

**JWT-Struktur** (GitHub Spec):
- Header: `{ "alg": "RS256", "typ": "JWT" }`
- Payload: `{ "iat": now-60s, "exp": now+540s, "iss": app_id }`
- Signature: OpenSSL RS256

---

### 2. App Manager (`bx_github_repositories_app_manager.php`)

**Aufgabe**: Installation Access Token austauschen + cachen

**Public API**:
```php
$app_manager = new bx_github_repositories_app_manager($crypto);

// Token holen (auto-refresh bei Ablauf)
$token = $app_manager->getInstallationAccessToken();
// Returns: "ghu_16C7e42F292c6912E7710c838347Ae178B4a" (~1h gültig)

// Cache-Status
$status = bx_github_repositories_app_manager::getCacheStatus();
// Returns: ['installation_123' => ['token' => '...', 'expires_at' => 1724342400]]

// Cache leeren
bx_github_repositories_app_manager::clearCache();
```

**Ablauf**:
1. Prüfe Runtime Cache (static $token_cache)
2. Falls abgelaufen/leer:
   - Lade Private Key aus Config
   - Entschlüssele mit bx_github_repositories_crypto
   - Generiere JWT mit JWT Provider
   - Tausche gegen Installation Token (POST /app/installations/{id}/access_tokens)
   - Cachen (55 Min, 60 Min Gültigkeit - 5 Min Buffer)

**Token Caching (Runtime Memory)**:
```php
$GLOBALS['bx_github_token_cache'] = [
    'installation_123' => [
        'token' => 'ghu_16C7e42F...',
        'expires_at' => 1724342400,
        'cached_at' => 1724255200
    ]
];
```

---

### 3. Client Factory (`bx_github_repositories_client_factory.php`)

**Aufgabe**: KnpLabs GitHub Client mit korrekter Authentifizierung erstellen

**Public API**:
```php
$factory = new bx_github_repositories_client_factory($app_manager, $crypto);

// Client erstellen (auto-auth)
$client = $factory->createClient($fallback_pat = null);
// Returns: Github\Client (authenticated & ready)

// Validiere Auth
$valid = $factory->validateAuthentication();
// Returns: true/false

// Rate-Limit Status
$limit = $factory->getRateLimitStatus();
// Returns: ['name' => 'core', 'limit' => 5000, 'remaining' => 4999, 'reset' => 1724255200]
```

**Auth-Hierarchie**:
1. **App Auth** (Installation Token) - wenn konfiguriert
2. **PAT** (Personal Access Token) - Fallback
3. **Unauthenticated** - Wenn beide fehlen (60 req/hr)

---

## Konfiguration

### Speicherung: `src/includes/configure/bx_github_repositories.php`

```php
// Crypto Key (für Token-Verschlüsselung)
defined('BX_GITHUB_REPOSITORIES_CRYPTO_KEY') 
  or define('BX_GITHUB_REPOSITORIES_CRYPTO_KEY', 'c91d7e4a8f...');

// App Credentials
defined('BX_GITHUB_REPOSITORIES_APP_ID') 
  or define('BX_GITHUB_REPOSITORIES_APP_ID', null); // int: z.B. 3456729

defined('BX_GITHUB_REPOSITORIES_INSTALLATION_ID') 
  or define('BX_GITHUB_REPOSITORIES_INSTALLATION_ID', null); // int: z.B. 12345678

defined('BX_GITHUB_REPOSITORIES_PRIVATE_KEY_ENCRYPTED') 
  or define('BX_GITHUB_REPOSITORIES_PRIVATE_KEY_ENCRYPTED', null);
// string: RSA Private Key, verschlüsselt mit BX_GITHUB_REPOSITORIES_CRYPTO_KEY
// Format: "-----BEGIN RSA PRIVATE KEY-----\nMIIE..." (mit \n als echte Newlines)
// Speichern: $crypto->encryptToken($private_key_pem) vor dem Speichern
```

### Token-Caching (Runtime Memory)

```php
$GLOBALS['bx_github_token_cache'] = []; // Init automatisch
```

**Lifecycle**:
- **Generierung**: Bei erstem `getInstallationAccessToken()` Call
- **Speicher**: `$GLOBALS['bx_github_token_cache']` (Klassenstatic)
- **Gültigkeit**: ~1h (GitHub Spec)
- **Refresh**: Auto bei `> expires_at - 60s`
- **Ablauf**: Mit Request-Ende (kein persistent Cache)

---

## Nutzungsbeispiel (Für Phase 3+)

```php
<?php
// Lade Module
require_once(DIR_FS_CATALOG . 'includes/classes/bx_dependency_resolver.php');
bx_dependency_resolver::require('modified_github');

// Initialisiere Manager
$crypto = new bx_github_repositories_crypto();
$app_manager = new bx_github_repositories_app_manager($crypto);
$factory = new bx_github_repositories_client_factory($app_manager, $crypto);

// Erstelle Client
$client = $factory->createClient();

// Nutze KnpLabs API
$repo = $client->api('repo')->show('owner', 'repo');
$releases = $client->api('repo')->releases()->all('owner', 'repo');
$latest_release = $releases[0]; // Neueste Release

// Rate-Limit Check
$limit_status = $factory->getRateLimitStatus();
echo 'Remaining Requests: ' . $limit_status['remaining'];
```

---

## Abhängigkeiten & Voraussetzungen

### Externe Libraries:
- **KnpLabs** (`php-github-api` v3+)
  - Abhängigkeiten: PSR-18 HTTP Client (z.B. Symfony HttpClient, Guzzle)
  - Geladen via: `bx_dependency_resolver::require('modified_github')`

### Framework/PHP:
- **OpenSSL**: Für RSA-Signing (JWT), AES-256-CBC (Crypto)
- **PHP 7.4+**: Type Hints, null coalescing
- **Modified eCommerce 3.1.x**: Framework-Konstanten (DIR_FS_*, etc.)

---

## Fehlerbehandlung

### JWT Provider:
```php
try {
    $jwt = bx_github_repositories_jwt_provider::buildJWT($app_id, $key);
} catch (Exception $e) {
    // "Private Key ungültig oder nicht im PEM-Format"
    // "JWT Signatur fehlgeschlagen (openssl_sign)"
}
```

### App Manager:
```php
try {
    $token = $app_manager->getInstallationAccessToken();
} catch (Exception $e) {
    // "GitHub App ist nicht konfiguriert"
    // "GitHub App Token Exchange fehlgeschlagen (HTTP 401): ..."
}
```

### Client Factory:
```php
try {
    $client = $factory->createClient();
} catch (Exception $e) {
    // "KnpLabs Github\Client nicht verfügbar"
    // "Keine GitHub Authentifizierung verfügbar"
}
```

---

## Roadmap: Nächste Schritte

### Phase 3 (Nicht sofort):
- [ ] File Downloader (Stream-based, atomic rename)
- [ ] Filesystem Helper (Path safety, locking)
- [ ] Scheduled Task Worker (`github_repositories_check.php`)
- [ ] Nutzt: `$factory->createClient()` → Asset-Download

### Phase 4-7:
- [ ] 2-Jahres-Berechtigung Gate
- [ ] Kundenbenachrichtigung Queue
- [ ] Admin-UI (Credentials Setup, History, Testing)
- [ ] Security Hardening (Monitoring, Audit Logs)

---

## Validation Checklist

- ✅ JWT Provider: Kompiliert, Tests bestanden
- ✅ App Manager: Token Exchange + Caching funktional
- ✅ Client Factory: KnpLabs Integration
- ✅ Crypto: AES-256-CBC unverändert
- ✅ Configure: App-Credentials-Konstanten
- ⏳ Phase 3: Downloader (pending)

---

**Kontakt/Fragen**: Siehe Session Memory `/memories/session/phase2-redesign.md`
