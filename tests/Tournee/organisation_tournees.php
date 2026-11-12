<?php

declare(strict_types=1);

use App\Tournee\OrdonnerDepots;

require dirname(__DIR__, 2).'/vendor/autoload.php';

function verifier(bool $condition, string $scenario): void
{
    if (!$condition) {
        throw new RuntimeException($scenario);
    }

    echo "[OK] $scenario\n";
}

$depart = [0.0, 0.0];
$positions = [
    1 => [0.01, 0.0],
    2 => [-0.01, 0.0],
    3 => [0.02, 0.0],
    4 => [-0.02, 0.0],
];
$ordre = new OrdonnerDepots();

verifier(
    $ordre->calculer($depart, [], $positions) === ['depots' => [], 'distanceKm' => 0.0],
    'Un véhicule sans dépôt ne parcourt aucune distance',
);

$tournee = $ordre->calculer($depart, [4, 3, 2, 1], $positions);
verifier($tournee['depots'] === [1, 3, 2, 4], 'Le plus proche est visité en premier et le plus petit identifiant départage une égalité');
verifier(abs($tournee['distanceKm'] - 8.895594) < 0.00001, 'La distance inclut le retour au lieu de chargement');
