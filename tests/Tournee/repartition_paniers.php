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
