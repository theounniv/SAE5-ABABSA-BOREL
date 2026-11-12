<?php

declare(strict_types=1);

use App\Calendrier\CalendrierLivraisons;

require dirname(__DIR__, 2).'/vendor/autoload.php';

function verifier(mixed $attendu, mixed $obtenu, string $scenario): void
{
    if ($obtenu !== $attendu) {
        throw new RuntimeException($scenario."\nAttendu : ".var_export($attendu, true)."\nObtenu : ".var_export($obtenu, true));
    }

    echo "[OK] $scenario\n";
}

function verifierRefus(callable $action, string $scenario): void
{
    try {
        $action();
    } catch (InvalidArgumentException) {
        echo "[OK] $scenario\n";

        return;
    }

    throw new RuntimeException($scenario);
}

$calendrier = new CalendrierLivraisons();
$fermetures = [1, 31, 32];

foreach ([25, 20, 17] as $nombre) {
    $plan = $calendrier->generer(2027, $nombre, 3, 1, $fermetures);
    $dates = array_column($plan, 'date');

    verifier(
        [$nombre, $nombre, '2027-01-20', '2027-12-29'],
        [count($dates), count(array_unique($dates)), reset($dates), end($dates)],
        "Le cas R3 à $nombre livraisons est complet et commence en semaine 3",
    );
}

$plan = $calendrier->generer(2027, 48, 3, 1, $fermetures);
$dates = array_column($plan, 'date');
verifier(48, count(array_unique($dates)), 'Le cas R3 comporte 48 dates disponibles');
verifier(
    true,
    in_array(['prevue' => '2027-07-14', 'date' => '2027-07-15'], $plan, true),
    'Le mercredi 14 juillet férié est reporté au jeudi 15',
);
verifierRefus(
    fn () => $calendrier->generer(2027, 49, 3, 1, $fermetures),
    'Le calendrier refuse 49 livraisons pour 48 dates disponibles',
);

$indiceReport = array_search('2027-07-15', $dates, true);
$datesDeplacees = $dates;
$datesDeplacees[$indiceReport] = '2027-07-13';
$planValide = $calendrier->valider($plan, $datesDeplacees, 2027, $fermetures);
verifier(
    true,
    in_array(['prevue' => '2027-07-14', 'date' => '2027-07-13'], $planValide, true),
    'Un report manuel autorisé conserve la date prévue',
);

verifierRefus(
    fn () => $calendrier->valider($plan, array_slice($dates, 1), 2027, $fermetures),
    'La validation refuse une livraison manquante',
);

$datesFermees = $dates;
$datesFermees[array_search('2027-07-28', $dates, true)] = '2027-08-04';
verifierRefus(
    fn () => $calendrier->valider($plan, $datesFermees, 2027, $fermetures),
    'La validation refuse un déplacement dans une semaine fermée',
);

$datesDupliquees = $dates;
$datesDupliquees[1] = $datesDupliquees[0];
verifierRefus(
    fn () => $calendrier->valider($plan, $datesDupliquees, 2027, $fermetures),
    'La validation refuse deux livraisons le même jour',
);

$lundis = $calendrier->generer(2027, 52, 1, 1, []);
verifier(
    true,
    in_array(['prevue' => '2027-03-29', 'date' => '2027-03-30'], $lundis, true),
    'Le lundi de Pâques est reporté au mardi',
);
$datesLundi = array_column($lundis, 'date');
$datesLundi[array_search('2027-03-30', $datesLundi, true)] = '2027-03-31';
verifierRefus(
    fn () => $calendrier->valider($lundis, $datesLundi, 2027, []),
    'Un lundi férié ne peut pas être reporté au mercredi',
);

$vendredis = $calendrier->generer(2023, 52, 5, 1, []);
verifier(
    true,
    in_array(['prevue' => '2023-07-14', 'date' => '2023-07-13'], $vendredis, true),
    'Le vendredi 14 juillet férié est reporté au jeudi',
);
$datesVendredi = array_column($vendredis, 'date');
$datesVendredi[array_search('2023-07-13', $datesVendredi, true)] = '2023-07-12';
verifierRefus(
    fn () => $calendrier->valider($vendredis, $datesVendredi, 2023, []),
    'Un vendredi férié ne peut pas être reporté au mercredi',
);
