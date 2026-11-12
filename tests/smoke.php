<?php

declare(strict_types=1);

/**
 * @param list<string> $fragments
 * @param array<string, string|list<string>>|null $formulaire
 * @param list<string> $fragmentsAbsents
 */
function verifierReponse(
    string $url,
    int $statutAttendu,
    array $fragments,
    string $scenario,
    ?array $formulaire = null,
    array $fragmentsAbsents = [],
): string {
    $options = ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5];
    if ($formulaire !== null) {
        $options += [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($formulaire),
        ];
    }

    $html = @file_get_contents($url, false, stream_context_create(['http' => $options]));
    $ligneStatut = $http_response_header[0] ?? 'aucune réponse';
    if ($html === false || !str_contains($ligneStatut, " $statutAttendu ")) {
        throw new RuntimeException("$scenario ($url) — HTTP $statutAttendu attendu, reçu $ligneStatut");
    }

    foreach ($fragments as $fragment) {
        if (!str_contains($html, $fragment)) {
            throw new RuntimeException("$scenario — texte attendu introuvable : $fragment");
        }
    }

    foreach ($fragmentsAbsents as $fragment) {
        if (str_contains($html, $fragment)) {
            throw new RuntimeException("$scenario — texte inattendu : $fragment");
        }
    }

    echo "[OK] $scenario\n";

    return $html;
}

$base = rtrim(getenv('BASE_URL') ?: 'http://127.0.0.1:8000', '/');
$tournees = $base.'/tournees';
$exemple = [
    'depart' => '48.174;6.449',
    'depots' => "1;5;5;48.184;6.449\n2;5;5;48.164;6.449\n3;5;5;48.194;6.449\n4;5;5;48.154;6.449",
    'vehicules' => "1;10\n2;10",
];

verifierReponse($base.'/', 200, ['Rayane ABABSA', 'Théo BOREL', 'href="/tournees"', 'href="/calendrier"'], 'Accueil et accès aux tournées');
verifierReponse($tournees, 200, ['Calculer les tournées', 'name="affectations"', 'Vérifier la répartition manuelle'], 'Formulaire des tournées');
$html = verifierReponse($tournees, 200, ['Répartition par véhicule', '10 / 10'], 'Calcul automatique de l’exemple', $exemple);
if (preg_match('/<textarea[^>]*name="affectations"[^>]*>([^<]*)<\/textarea>/s', $html, $proposition) !== 1
    || count(explode("\n", trim($proposition[1]))) !== 4) {
    throw new RuntimeException('Le calcul doit proposer les quatre affectations dans le formulaire manuel.');
}
echo "[OK] La proposition automatique est reprise dans le formulaire manuel\n";

verifierReponse(
    $tournees,
    422,
    ['indiquez une latitude'],
    'Coordonnée invalide refusée',
    ['depart' => '92;6.449'] + $exemple,
);

$casLimite = [
    'depots' => "1;6;6;48.184;6.449\n2;5;5;48.164;6.449\n3;3;3;48.194;6.449\n4;2;2;48.154;6.449\n5;2;2;48.204;6.449\n6;2;2;48.144;6.449",
] + $exemple;
verifierReponse(
    $tournees,
    200,
    ['Dépôts sans affectation', 'Dépôt 6'],
    'Le calcul automatique signale le dépôt laissé de côté',
    $casLimite,
);

$correction = [
    'action' => 'manuel',
    'affectations' => "1;1\n4;1\n5;1\n2;2\n3;2\n6;2",
];
$html = verifierReponse(
    $tournees,
    200,
    ['La répartition manuelle est valide.'],
    'La correction manuelle affecte tous les dépôts',
    $correction + $casLimite,
    ['Dépôts sans affectation'],
);
if (substr_count($html, '<td>10 / 10</td>') !== 2) {
    throw new RuntimeException('La correction manuelle doit charger les deux véhicules à 10 paniers.');
}

$repartitionManuelle = ['action' => 'manuel', 'affectations' => "1;1\n2;1\n3;2\n4;2"];
$html = verifierReponse(
    $tournees,
    200,
    ['La répartition manuelle est valide.'],
    'La répartition manuelle garde les dépôts choisis',
    $repartitionManuelle + $exemple,
);
if (preg_match('/<th scope="row">1<\/th>\s*<td>10 \/ 10<\/td>\s*<td>(?:1, 2|2, 1)<\/td>/', $html) !== 1) {
    throw new RuntimeException('Les dépôts 1 et 2 doivent rester dans le véhicule 1 en mode manuel.');
}

verifierReponse(
    $tournees,
    422,
    ['Dépôts sans affectation dans la correction : 6.'],
    'Correction manuelle incomplète refusée',
    ['affectations' => "1;1\n4;1\n5;1\n2;2\n3;2"] + $correction + $casLimite,
);
verifierReponse(
    $tournees,
    422,
    ['La capacité du véhicule 1 est dépassée.'],
    'Correction manuelle qui surcharge un véhicule refusée',
    ['affectations' => "1;1\n2;1"] + $correction + $casLimite,
);

$calendrier = $base.'/calendrier';
$exempleCalendrier = [
    'action' => 'generer',
    'tournee' => 'Mercredi matin',
    'annee' => '2027',
    'jour' => '3',
    'parite' => '1',
    'fermetures' => '1,31,32',
];

verifierReponse($calendrier, 200, ['Générer le calendrier'], 'Formulaire du calendrier');

foreach ([25, 20, 17] as $nombre) {
    $html = verifierReponse(
        $calendrier,
        200,
        ["$nombre livraisons placées sur $nombre demandées.", '2027-01-20'],
        "Génération de $nombre livraisons",
        ['nombre' => (string) $nombre] + $exempleCalendrier,
    );
}

preg_match_all('/name="dates\[\]" value="([^"]+)"/', $html, $datesRetenues);
$dates = $datesRetenues[1];
if (count($dates) !== 17) {
    throw new RuntimeException('Le formulaire doit contenir les 17 dates générées.');
}
$dates[0] = '2027-01-21';
$verification = ['action' => 'verifier', 'nombre' => '17'] + $exempleCalendrier;
verifierReponse(
    $calendrier,
    200,
    ['Les dates de livraison sont valides.', 'value="2027-01-21"'],
    'Déplacement manuel accepté',
    ['dates' => $dates] + $verification,
);
verifierReponse(
    $calendrier,
    422,
    ['exactement le nombre de livraisons demandé'],
    'Calendrier incomplet refusé',
    ['dates' => array_slice($dates, 1)] + $verification,
);
