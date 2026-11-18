<?php

declare(strict_types=1);

/** @return array{statut: int, destination: ?string, html: string} */
function requeteAdherent(string $url, array &$cookies, ?array $formulaire = null): array
{
    $entetes = [];
    if ($cookies !== []) {
        $paires = [];
        foreach ($cookies as $nom => $valeur) {
            $paires[] = "$nom=$valeur";
        }
        $entetes[] = 'Cookie: '.implode('; ', $paires);
    }

    $options = ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5];
    if ($formulaire !== null) {
        $entetes[] = 'Content-Type: application/x-www-form-urlencoded';
        $options['method'] = 'POST';
        $options['content'] = http_build_query($formulaire);
    }
    $options['header'] = $entetes;

    $html = @file_get_contents($url, false, stream_context_create(['http' => $options]));
    $reponse = $http_response_header ?? [];
    if ($html === false || preg_match('/^HTTP\/\S+ (\d{3})/', $reponse[0] ?? '', $statut) !== 1) {
        throw new RuntimeException("Aucune réponse HTTP pour $url.");
    }

    $destination = null;
    foreach ($reponse as $entete) {
        if (preg_match('/^Set-Cookie:\s*([^=;]+)=([^;]*)/i', $entete, $cookie) === 1) {
            $cookies[$cookie[1]] = $cookie[2];
        } elseif (str_starts_with(strtolower($entete), 'location:')) {
            $destination = parse_url(trim(substr($entete, 9)), PHP_URL_PATH) ?: null;
        }
    }

    return ['statut' => (int) $statut[1], 'destination' => $destination, 'html' => $html];
}

function verifierParcoursAdherent(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

function jetonConnexion(string $base, array &$cookies): string
{
    $page = requeteAdherent($base.'/connexion', $cookies);
    verifierParcoursAdherent($page['statut'] === 200, 'Le formulaire de connexion doit répondre HTTP 200, reçu '.$page['statut'].'.');
    verifierParcoursAdherent(
        preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $page['html'], $jeton) === 1,
        'Le formulaire doit fournir un jeton CSRF.',
    );

    return html_entity_decode($jeton[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

$base = rtrim(getenv('BASE_URL') ?: 'http://127.0.0.1:8000', '/');
$motDePasse = getenv('SAE5_R17_DEMO_PASSWORD');
if ($motDePasse === false || $motDePasse === '') {
    throw new RuntimeException('Fournir SAE5_R17_DEMO_PASSWORD pour le compte adhérent de test.');
}

$cookies = [];
$anonyme = requeteAdherent($base.'/espace-adherent', $cookies);
verifierParcoursAdherent(
    $anonyme['statut'] === 302 && $anonyme['destination'] === '/connexion',
    'Un visiteur doit être redirigé vers la connexion, reçu HTTP '.$anonyme['statut'].'.',
);
echo "[OK] Espace adhérent protégé\n";

$jeton = jetonConnexion($base, $cookies);
$sessionAvant = $cookies;
$identifiants = ['_username' => 'adherent@example.test', '_password' => $motDePasse];
$succes = requeteAdherent($base.'/connexion', $cookies, $identifiants + ['_csrf_token' => $jeton]);
verifierParcoursAdherent($succes['statut'] === 302 && $succes['destination'] === '/espace-adherent', 'Les bons identifiants doivent ouvrir l’espace adhérent.');
verifierParcoursAdherent($sessionAvant !== [] && $cookies !== $sessionAvant, 'La session doit être renouvelée après la connexion.');
$espace = requeteAdherent($base.'/espace-adherent', $cookies);
verifierParcoursAdherent($espace['statut'] === 200 && str_contains($espace['html'], 'adherent@example.test'), 'La session doit appartenir à l’adhérent de test.');
echo "[OK] Connexion et renouvellement de session\n";

$tentativesRefusees = [
    'Mot de passe incorrect' => ['_password' => 'incorrect-'.$motDePasse],
    'Compte inconnu' => ['_username' => 'inconnu@example.test'],
    'Jeton CSRF absent' => null,
];
foreach ($tentativesRefusees as $scenario => $differences) {
    $cookies = [];
    $jeton = jetonConnexion($base, $cookies);
    $donnees = $differences === null
        ? $identifiants
        : $differences + $identifiants + ['_csrf_token' => $jeton];
    $refus = requeteAdherent($base.'/connexion', $cookies, $donnees);
    verifierParcoursAdherent($refus['statut'] === 302 && $refus['destination'] === '/connexion', "$scenario : la connexion doit être refusée.");
    $page = requeteAdherent($base.'/connexion', $cookies);
    verifierParcoursAdherent($page['statut'] === 200 && str_contains($page['html'], 'Identifiants invalides.'), "$scenario : afficher le même message d’échec.");
    $espace = requeteAdherent($base.'/espace-adherent', $cookies);
    verifierParcoursAdherent($espace['statut'] === 302 && $espace['destination'] === '/connexion', "$scenario : aucune session authentifiée ne doit être ouverte.");
    echo "[OK] $scenario refusé\n";
}
