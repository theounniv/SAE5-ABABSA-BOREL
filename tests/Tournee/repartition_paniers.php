<?php

declare(strict_types=1);

use App\Tournee\RepartitionPaniers;

require dirname(__DIR__, 2).'/vendor/autoload.php';

function verifier(mixed $attendu, mixed $obtenu, string $scenario): void
{
    if ($obtenu !== $attendu) {
        throw new RuntimeException($scenario."\nAttendu : ".var_export($attendu, true)."\nObtenu : ".var_export($obtenu, true));
    }

    echo "[OK] $scenario\n";
}

$repartition = new RepartitionPaniers();

verifier([
    'affectations' => [1 => [1, 3], 2 => [2, 4]],
    'depotsNonAffectes' => [],
], $repartition->calculer([
    4 => ['paniers' => 3, 'capacite' => 3],
    2 => ['paniers' => 5, 'capacite' => 6],
    3 => ['paniers' => 3, 'capacite' => 5],
    1 => ['paniers' => 5, 'capacite' => 5],
], [2 => 8, 1 => 8]), 'Chaque dépôt est affecté une seule fois et les égalités suivent les identifiants');

verifier([
    'affectations' => [1 => [], 2 => [1]],
    'depotsNonAffectes' => [],
], $repartition->calculer([1 => ['paniers' => 5, 'capacite' => 5]], [1 => 9, 2 => 6]),
    'Le véhicule retenu est celui qui laisse le moins de places libres');

verifier([
    'affectations' => [1 => [1]],
    'depotsNonAffectes' => [
        2 => 'Le nombre de paniers dépasse la capacité du dépôt.',
        3 => 'Aucun véhicule ne dispose encore de suffisamment de places pour ce dépôt.',
    ],
], $repartition->calculer([
    1 => ['paniers' => 6, 'capacite' => 6],
    2 => ['paniers' => 5, 'capacite' => 4],
    3 => ['paniers' => 3, 'capacite' => 3],
    4 => ['paniers' => 0, 'capacite' => 0],
], [1 => 8]), 'Les deux types de capacité sont contrôlés et un dépôt vide est ignoré');

$depotsACorriger = [
    1 => ['paniers' => 6, 'capacite' => 6],
    2 => ['paniers' => 5, 'capacite' => 5],
    3 => ['paniers' => 3, 'capacite' => 3],
    4 => ['paniers' => 2, 'capacite' => 2],
    5 => ['paniers' => 2, 'capacite' => 2],
    6 => ['paniers' => 2, 'capacite' => 2],
];
$deuxVehicules = [1 => 10, 2 => 10];

verifier([
    'affectations' => [1 => [1, 3], 2 => [2, 4, 5]],
    'depotsNonAffectes' => [6 => 'Aucun véhicule ne dispose encore de suffisamment de places pour ce dépôt.'],
], $repartition->calculer($depotsACorriger, $deuxVehicules), 'La méthode signale le dépôt restant même si les capacités totales suffisent');

$depotsAvecDepotVide = $depotsACorriger + [7 => ['paniers' => 0, 'capacite' => 2]];
$correction = [
    ['depot' => 1, 'vehicule' => 1],
    ['depot' => 4, 'vehicule' => 1],
    ['depot' => 5, 'vehicule' => 1],
    ['depot' => 2, 'vehicule' => 2],
    ['depot' => 3, 'vehicule' => 2],
    ['depot' => 6, 'vehicule' => 2],
];

verifier([
    'affectations' => [1 => [1, 4, 5], 2 => [2, 3, 6]],
    'depotsNonAffectes' => [],
], $repartition->calculer($depotsAvecDepotVide, $deuxVehicules, $correction), 'La correction manuelle répartit les 20 paniers entre les deux véhicules');

$depotsEnDepassement = $depotsACorriger;
$depotsEnDepassement[1]['capacite'] = 5;

foreach ([
    'Un dépôt manquant est refusé' => [
        $depotsACorriger, array_slice($correction, 0, -1), 'Dépôts sans affectation dans la correction : 6.',
    ],
    'Une correction vide est refusée si des dépôts restent à livrer' => [
        $depotsACorriger, [], 'Dépôts sans affectation dans la correction : 1, 2, 3, 4, 5, 6.',
    ],
    'Un dépôt affecté deux fois est refusé' => [
        $depotsACorriger, [...$correction, ['depot' => 1, 'vehicule' => 2]], 'Le dépôt 1 est affecté plusieurs fois.',
    ],
    'Un dépôt inconnu est refusé' => [
        $depotsACorriger, [['depot' => 8, 'vehicule' => 1]], 'Le dépôt 8 est inconnu ou sans panier à livrer.',
    ],
    'Un dépôt vide ne peut pas être affecté manuellement' => [
        $depotsAvecDepotVide, [['depot' => 7, 'vehicule' => 1]], 'Le dépôt 7 est inconnu ou sans panier à livrer.',
    ],
    'Un véhicule inconnu est refusé' => [
        $depotsACorriger, [['depot' => 1, 'vehicule' => 3]], 'Le véhicule 3 est inconnu.',
    ],
    'Un dépassement de la capacité du dépôt est refusé' => [
        $depotsEnDepassement, $correction, 'Le nombre de paniers dépasse la capacité du dépôt 1.',
    ],
    'Un dépassement de la capacité du véhicule est refusé' => [
        $depotsACorriger, [['depot' => 1, 'vehicule' => 1], ['depot' => 2, 'vehicule' => 1]], 'La capacité du véhicule 1 est dépassée.',
    ],
] as $scenario => [$depots, $affectations, $attendu]) {
    $message = null;
    try {
        $repartition->calculer($depots, $deuxVehicules, $affectations);
    } catch (InvalidArgumentException $exception) {
        $message = $exception->getMessage();
    }
    verifier($attendu, $message, $scenario);
}

verifier([
    'affectations' => [],
    'depotsNonAffectes' => [1 => 'Aucun véhicule ne dispose encore de suffisamment de places pour ce dépôt.'],
], $repartition->calculer([1 => ['paniers' => 1, 'capacite' => 1]], []), 'Une flotte vide est signalée');

$quantiteRefusee = false;
try {
    $repartition->calculer([1 => ['paniers' => -1, 'capacite' => 5]], [1 => 5]);
} catch (InvalidArgumentException) {
    $quantiteRefusee = true;
}
verifier(true, $quantiteRefusee, 'Une quantité négative est refusée');

$formatRefuse = false;
try {
    $repartition->calculer([1 => new stdClass()], [1 => 5]);
} catch (InvalidArgumentException) {
    $formatRefuse = true;
}
verifier(true, $formatRefuse, 'Un dépôt de format invalide est refusé');
