<?php

$project_root = __DIR__;
for ($i = 0; $i < 10; $i++) {
    $autoload = $project_root . '/includes/external/bx_composer_libs/modified_github/vendor/autoload.php';
    $live_class = $project_root . '/admin/includes/classes/bx_github_repositories_crypto.php';

    if (is_file($autoload) && is_file($live_class)) {
        break;
    }

    $parent = dirname($project_root);
    if ($parent === $project_root) {
        break;
    }

    $project_root = $parent;
}

if (!is_file($project_root . '/includes/external/bx_composer_libs/modified_github/vendor/autoload.php')) {
    fwrite(STDERR, "Live-Root nicht gefunden.\n");
    exit(1);
}

define('_VALID_XTC', true);

require_once($project_root . '/includes/external/bx_composer_libs/modified_github/vendor/autoload.php');

require_once($project_root . '/includes/configure/bx_github_repositories.php');
require_once($project_root . '/admin/includes/classes/bx_github_repositories_crypto.php');
require_once($project_root . '/admin/includes/classes/bx_github_repositories_jwt_provider.php');
require_once($project_root . '/admin/includes/classes/bx_github_repositories_app_manager.php');
require_once($project_root . '/admin/includes/classes/bx_github_repositories_client_factory.php');

function fail($message, $context = [])
{
    fwrite(STDERR, json_encode([
        'ok' => false,
        'message' => $message,
        'context' => $context,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}

try {
    $crypto = new bx_github_repositories_crypto();

    $private_key = $crypto->decryptToken(MODULE_BX_GITHUB_REPOSITORIES_PRIVATE_KEY_ENCRYPTED);
    if ($private_key === '') {
        fail('Private Key konnte nicht entschluesselt werden.');
    }

    $normalized_private_key = bx_github_repositories_jwt_provider::normalizePrivateKey($private_key);
    $jwt = bx_github_repositories_jwt_provider::buildJWT(MODULE_BX_GITHUB_REPOSITORIES_APP_ID, $normalized_private_key);

    if (count(explode('.', $jwt)) !== 3) {
        fail('JWT hat nicht das erwartete 3-teilige Format.');
    }

    $app_manager = new bx_github_repositories_app_manager($crypto);
    $installation_token = $app_manager->getInstallationAccessToken();

    if (!is_string($installation_token) || $installation_token === '') {
        fail('Installation Access Token wurde nicht erzeugt.');
    }

    $factory = new bx_github_repositories_client_factory($app_manager, $crypto);
    $client = $factory->createClient();
    $core_limit = $client->api('rateLimit')->getResource('core');

    if ($core_limit === false) {
        fail('Rate-Limit-Ressource core konnte nicht geladen werden.');
    }

    echo json_encode([
        'ok' => true,
        'message' => 'GitHub App Authentifizierung erfolgreich.',
        'app_id' => MODULE_BX_GITHUB_REPOSITORIES_APP_ID,
        'installation_id' => MODULE_BX_GITHUB_REPOSITORIES_INSTALLATION_ID,
        'jwt_parts' => count(explode('.', $jwt)),
        'installation_token_prefix' => substr($installation_token, 0, 6),
        'rate_limit' => [
            'name' => $core_limit->getName(),
            'limit' => $core_limit->getLimit(),
            'remaining' => $core_limit->getRemaining(),
            'reset' => $core_limit->getReset(),
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $exception) {
    fail($exception->getMessage(), [
        'type' => get_class($exception),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
    ]);
}