<?php
/**
 * Kryptographische Funktionen für Token-Verschlüsselung
 * 
 * Nutzt den modul-spezifischen Key für AES-256-CBC Verschlüsselung
 * Token werden niemals im Klartext gespeichert
 */

defined('_VALID_XTC') or die('Direct Access to this location is not allowed.');

class bx_github_repositories_crypto {

  /** @var string Encryption Algorithm */
  private const CIPHER = 'AES-256-CBC';

  /** @var string Secret Key Quelle */
  private string $secret_key;

  public function __construct() {
    // Bevorzuge den modul-spezifischen Key aus includes/extra/configure.
    if (defined('BX_GITHUB_REPOSITORIES_CRYPTO_KEY')) {
      $this->secret_key = constant('BX_GITHUB_REPOSITORIES_CRYPTO_KEY');
    } else {
      // Fallback: Generiere deterministischen Key aus Installation
      $this->secret_key = hash('sha256', DIR_FS_DOCUMENT_ROOT . 'bx_github_repositories');
    }

    // Prüfe ob openssl verfügbar ist
    if (!extension_loaded('openssl')) {
      throw new Exception('OpenSSL extension not available for token encryption');
    }
  }

  /**
   * Verschlüssele GitHub Token
   * 
   * @param string $token Klartext-Token
   * @return string Base64-codierte verschlüsselte Daten
   */
  public function encryptToken(string $token): string {
    if (empty($token)) {
      return '';
    }

    $iv  = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER));
    $key = hash('sha256', $this->secret_key, true);
    
    $encrypted = openssl_encrypt(
      $token,
      self::CIPHER,
      $key,
      OPENSSL_RAW_DATA,
      $iv
    );

    if ($encrypted === false) {
      throw new Exception('Token encryption failed');
    }

    // Speichere IV + Encrypted Data
    return base64_encode($iv . $encrypted);
  }

  /**
   * Entschlüssele GitHub Token
   * 
   * @param string $encrypted_token Base64-codierte Verschlüsselung
   * @return string Klartext-Token
   */
  public function decryptToken(string $encrypted_token): string {
    if (empty($encrypted_token)) {
      return '';
    }

    try {
      $decoded = base64_decode($encrypted_token, true);
      if ($decoded === false) {
        throw new Exception('Invalid base64 encoding');
      }

      $iv_length = openssl_cipher_iv_length(self::CIPHER);
      $iv        = substr($decoded, 0, $iv_length);
      $encrypted = substr($decoded, $iv_length);

      if (strlen($iv) !== $iv_length) {
        throw new Exception('Invalid IV length');
      }

      $key = hash('sha256', $this->secret_key, true);

      $decrypted = openssl_decrypt(
        $encrypted,
        self::CIPHER,
        $key,
        OPENSSL_RAW_DATA,
        $iv
      );

      if ($decrypted === false) {
        throw new Exception('Token decryption failed');
      }

      return $decrypted;

    } catch (Exception $e) {
      // Log error aber gebe leeren String zurück
      error_log('GitHub Token Decryption Error: ' . $e->getMessage());
      return '';
    }
  }

  /**
   * Prüfe ob String verschlüsselt aussieht (beginnt nicht mit gh_)
   */
  public static function isEncrypted(string $token): bool {
    // GitHub Tokens beginnen mit gh_ oder ghp_
    return strpos($token, 'gh') !== 0;
  }

  /**
   * Sichere Token-Speicherung (wird in Repository-Config verwendet)
   */
  public function prepareForStorage(string $token): string {
    if (self::isEncrypted($token)) {
      return $token; // Bereits verschlüsselt
    }
    return $this->encryptToken($token);
  }

  /**
   * Token zum Verwenden vorbereiten (wird bei API-Zugriff verwendet)
   */
  public function prepareForUse(string $stored_token): string {
    if (!self::isEncrypted($stored_token)) {
      return $stored_token; // Nicht verschlüsselt (sollte nicht vorkommen)
    }
    return $this->decryptToken($stored_token);
  }

  /**
   * Generate deterministic hash für Token-Validierung
   * (ohne den Token selbst zu speichern)
   */
  public function hashToken(string $token): string {
    return hash('sha256', $token . $this->secret_key);
  }

  /**
   * Verify Token gegen gespeicherten Hash
   */
  public function verifyToken(string $token, string $stored_hash): bool {
    return hash_equals($this->hashToken($token), $stored_hash);
  }
}
