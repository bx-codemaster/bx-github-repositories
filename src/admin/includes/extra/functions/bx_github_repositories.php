<?php
/** BX Github Repositiries - Funktions */

/**
 * Validiere den PEM-Inhalt auf Private-Key-Block.
 */
function bx_github_repositories_is_valid_pem_private_key(string $pem): bool
{
  $pattern = '/-----BEGIN [A-Z0-9 ]*PRIVATE KEY-----[\s\S]+-----END [A-Z0-9 ]*PRIVATE KEY-----/';
  return (bool)preg_match($pattern, trim($pem));
}

/**
 * Teste App-Authentifizierung direkt gegen GitHub.
 */
function bx_github_repositories_test_connection(int $app_id, int $installation_id, string $private_key_pem): array
{
  try {
    $normalized_key = bx_github_repositories_jwt_provider::normalizePrivateKey($private_key_pem);
    $jwt = bx_github_repositories_jwt_provider::buildJWT($app_id, $normalized_key);
  } catch (Exception $e) {
    return [
      'success' => false,
      'message' => sprintf(BX_GITHUB_REPOSITORIES_ERROR_JWT_CREATION_FAILED, $e->getMessage()),
    ];
  }

  $endpoint = 'https://api.github.com/app/installations/' . $installation_id . '/access_tokens';
  $ch = curl_init($endpoint);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => '{}',
    CURLOPT_HTTPHEADER => [
      'Authorization: Bearer ' . $jwt,
      'Accept: application/vnd.github+json',
      'X-GitHub-Api-Version: 2022-11-28',
      'Content-Type: application/json',
      'User-Agent: bx-github-repositories-admin',
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
  ]);

  $response_raw = curl_exec($ch);
  $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

  $response = is_string($response_raw) ? json_decode($response_raw, true) : null;

  if ($http_code !== 201 || !is_array($response) || empty($response['token'])) {
    $details = is_array($response) && isset($response['message']) ? (string)$response['message'] : BX_GITHUB_REPOSITORIES_TEXT_UNKNOWN_GITHUB_RESPONSE;
    return [
      'success' => false,
      'message' => sprintf(BX_GITHUB_REPOSITORIES_ERROR_CONNECTION_TEST_FAILED, $http_code, $details),
    ];
  }

  return [
    'success' => true,
    'message' => BX_GITHUB_REPOSITORIES_SUCCESS_CONNECTION_TEST,
  ];
}

/**
 * Erzeuge Installation-Token per App-JWT.
 */
function bx_github_repositories_create_installation_token(int $app_id, int $installation_id, string $private_key_pem): array
{
  $normalized_key = bx_github_repositories_jwt_provider::normalizePrivateKey($private_key_pem);
  $jwt = bx_github_repositories_jwt_provider::buildJWT($app_id, $normalized_key);

  $endpoint = 'https://api.github.com/app/installations/' . $installation_id . '/access_tokens';
  $ch = curl_init($endpoint);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => '{}',
    CURLOPT_HTTPHEADER => [
      'Authorization: Bearer ' . $jwt,
      'Accept: application/vnd.github+json',
      'X-GitHub-Api-Version: 2022-11-28',
      'Content-Type: application/json',
      'User-Agent: bx-github-repositories-admin',
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
  ]);

  $response_raw = curl_exec($ch);
  $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $response = is_string($response_raw) ? json_decode($response_raw, true) : null;

  if ($http_code !== 201 || !is_array($response) || empty($response['token'])) {
    $details = is_array($response) && isset($response['message']) ? (string)$response['message'] : BX_GITHUB_REPOSITORIES_TEXT_UNKNOWN_GITHUB_RESPONSE;
    throw new Exception(sprintf(BX_GITHUB_REPOSITORIES_ERROR_CONNECTION_TEST_FAILED, $http_code, $details));
  }

  return $response;
}

/**
 * Lade alle Repositories der Installation.
 */
function bx_github_repositories_fetch_installation_repositories(string $installation_token): array
{
  $endpoint = 'https://api.github.com/installation/repositories?per_page=100';
  $ch = curl_init($endpoint);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPGET => true,
    CURLOPT_HTTPHEADER => [
      'Authorization: Bearer ' . $installation_token,
      'Accept: application/vnd.github+json',
      'X-GitHub-Api-Version: 2022-11-28',
      'User-Agent: bx-github-repositories-admin',
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
  ]);

  $response_raw = curl_exec($ch);
  $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $response = is_string($response_raw) ? json_decode($response_raw, true) : null;

  if ($http_code !== 200 || !is_array($response)) {
    $details = is_array($response) && isset($response['message']) ? (string)$response['message'] : BX_GITHUB_REPOSITORIES_TEXT_UNKNOWN_GITHUB_RESPONSE;
    throw new Exception(sprintf(BX_GITHUB_REPOSITORIES_ERROR_REPOSITORY_LOAD_FAILED, $http_code, $details));
  }

  return isset($response['repositories']) && is_array($response['repositories'])
    ? $response['repositories']
    : [];
}

/**
 * Erzeuge stabilen Dateinamen fuer ein Repository.
 */
function bx_github_repositories_build_stable_filename(string $owner, string $repo): string
{
  $base = strtolower(trim($owner . '-' . $repo));
  $base = preg_replace('/[^a-z0-9._-]+/', '-', $base);
  $base = trim((string)$base, '-._');
  if ($base === '') {
    $base = 'github-release';
  }
  return $base . '.zip';
}

/**
 * Lade neuesten Tag von GitHub (per Tags-API, neuester zuerst).
 * Gibt ['tag_name' => string, 'zipball_url' => string] zurück.
 */
function bx_github_repositories_fetch_latest_tag(string $owner, string $repo, string $installation_token): array
{
  // GitHub sortiert Tags nicht zuverlässig nach semantischer Version.
  // Wir laden bis zu 30 Tags und ermitteln den höchsten via version_compare().
  $endpoint = 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/tags?per_page=30';
  $ch = curl_init($endpoint);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPGET        => true,
    CURLOPT_HTTPHEADER     => [
      'Authorization: Bearer ' . $installation_token,
      'Accept: application/vnd.github+json',
      'X-GitHub-Api-Version: 2022-11-28',
      'User-Agent: bx-github-repositories-admin',
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
  ]);

  $response_raw = curl_exec($ch);
  $http_code    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $response     = is_string($response_raw) ? json_decode($response_raw, true) : null;

  if ($http_code !== 200 || !is_array($response)) {
    $details = is_array($response) && isset($response['message']) ? (string)$response['message'] : BX_GITHUB_REPOSITORIES_TEXT_UNKNOWN_GITHUB_RESPONSE;
    throw new Exception(sprintf(BX_GITHUB_REPOSITORIES_ERROR_CONNECTION_TEST_FAILED, $http_code, $details));
  }

  if (empty($response) || !isset($response[0]['name'])) {
    throw new Exception(BX_GITHUB_REPOSITORIES_ERROR_NO_RELEASE_FOUND);
  }

  // Höchsten Tag per version_compare ermitteln (führendes 'v' tolerieren)
  $best = null;
  foreach ($response as $candidate) {
    if (!isset($candidate['name'])) {
      continue;
    }
    if ($best === null) {
      $best = $candidate;
      continue;
    }
    $a = ltrim((string)$best['name'], 'v');
    $b = ltrim((string)$candidate['name'], 'v');
    if (version_compare($b, $a, '>')) {
      $best = $candidate;
    }
  }

  $tag_name    = (string)$best['name'];
  $zipball_url = isset($best['zipball_url']) ? (string)$best['zipball_url'] : '';

  if ($zipball_url === '') {
    $zipball_url = 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/zipball/' . rawurlencode($tag_name);
  }

  return [
    'tag_name'    => $tag_name,
    'zipball_url' => $zipball_url,
  ];
}

/**
 * Lade Asset-Datei atomar in das Zielverzeichnis herunter.
 */
function bx_github_repositories_download_asset(string $asset_url, string $installation_token, string $target_path): void
{
  $tmp_path = $target_path . '.tmp.' . bin2hex(random_bytes(8));
  $fp = fopen($tmp_path, 'wb');
  if ($fp === false) {
    throw new Exception(sprintf(BX_GITHUB_REPOSITORIES_ERROR_TEMP_FILE_CREATE, $tmp_path));
  }

  $ch = curl_init($asset_url);
  curl_setopt_array($ch, [
    CURLOPT_FILE            => $fp,
    CURLOPT_FOLLOWLOCATION  => true,
    CURLOPT_MAXREDIRS       => 5,
    CURLOPT_TIMEOUT         => 120,
    CURLOPT_HTTPHEADER      => [
      'Authorization: Bearer ' . $installation_token,
      'Accept: application/vnd.github+json',
      'X-GitHub-Api-Version: 2022-11-28',
      'User-Agent: bx-github-repositories-admin',
    ],
    CURLOPT_SSL_VERIFYPEER  => true,
    CURLOPT_SSL_VERIFYHOST  => 2,
  ]);

  curl_exec($ch);
  $http_code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curl_error = curl_error($ch);
  fclose($fp);

  if ($curl_error !== '' || ($http_code !== 200 && $http_code !== 0)) {
    @unlink($tmp_path);
    throw new Exception(sprintf(BX_GITHUB_REPOSITORIES_ERROR_DOWNLOAD_FAILED, $http_code, $curl_error !== '' ? $curl_error : '-'));
  }

  if (!file_exists($tmp_path) || filesize($tmp_path) === 0) {
    @unlink($tmp_path);
    throw new Exception(BX_GITHUB_REPOSITORIES_ERROR_DOWNLOAD_EMPTY);
  }

  if (!rename($tmp_path, $target_path)) {
    @unlink($tmp_path);
    throw new Exception(sprintf(BX_GITHUB_REPOSITORIES_ERROR_FILE_RENAME, $target_path));
  }
}

/**
 * Lade moduleinfo.json eines Repositories über die GitHub Contents API.
 *
 * Gibt das dekodierte Array zurück oder null wenn die Datei nicht gefunden wurde
 * oder das JSON ungültig ist. Wirft eine Exception bei Netzwerk-/Auth-Fehlern.
 *
 * @param string $owner             GitHub-Owner
 * @param string $repo              Repository-Name
 * @param string $installation_token GitHub Installation Access Token
 * @return array|null               Dekodiertes JSON-Array oder null
 * @throws Exception                Bei Netzwerk- oder Authentifizierungsfehlern
 */
function bx_github_repositories_fetch_moduleinfo_json(
  string $owner,
  string $repo,
  string $installation_token
): ?array {
  $endpoint = 'https://api.github.com/repos/'
    . rawurlencode($owner) . '/'
    . rawurlencode($repo)
    . '/contents/moduleinfo.json';
  $ch = curl_init($endpoint);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPGET        => true,
    CURLOPT_HTTPHEADER     => [
      'Authorization: Bearer ' . $installation_token,
      'Accept: application/vnd.github+json',
      'X-GitHub-Api-Version: 2022-11-28',
      'User-Agent: bx-github-repositories-admin',
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
  ]);

  $response_raw = curl_exec($ch);
  $http_code    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curl_error   = curl_error($ch);

  if ($curl_error !== '') {
    throw new Exception(sprintf(BX_GITHUB_REPOSITORIES_ERROR_DOWNLOAD_FAILED, $http_code, $curl_error));
  }

  if ($http_code === 404) {
    return null;
  }

  if ($http_code !== 200) {
    $response = is_string($response_raw) ? json_decode($response_raw, true) : null;
    $details  = is_array($response) && isset($response['message']) ? (string)$response['message'] : '-';
    throw new Exception(sprintf(BX_GITHUB_REPOSITORIES_ERROR_DOWNLOAD_FAILED, $http_code, $details));
  }

  $response = is_string($response_raw) ? json_decode($response_raw, true) : null;

  if (!is_array($response) || !isset($response['content']) || $response['encoding'] !== 'base64') {
    return null;
  }

  $decoded = base64_decode(str_replace(["\n", "\r"], '', (string)$response['content']), true);
  if ($decoded === false) {
    return null;
  }

  $data = json_decode($decoded, true);
  return is_array($data) ? $data : null;
}

/**
 * Normalisiert den Preiswert aus moduleinfo.json auf float oder null.
 *
 * Regeln:
 * - "free" (case-insensitive) → 0.0
 * - numerischer Wert          → float (>= 0)
 * - alles andere              → null (Template-Preis behalten)
 *
 * @param mixed $raw Rohwert aus moduleinfo.json
 * @return float|null
 */
function bx_github_repositories_parse_price(mixed $raw): ?float
{
  if (is_string($raw) && strtolower(trim($raw)) === 'free') {
    return 0.0;
  }
  if (is_numeric($raw)) {
    return max(0.0, (float)$raw);
  }
  return null;
}
