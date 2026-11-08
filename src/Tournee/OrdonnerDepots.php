<?php

declare(strict_types=1);

namespace App\Tournee;

final class OrdonnerDepots
{
    /**
     * @param array{0: float, 1: float} $depart
     * @param list<int> $depots
     * @param array<int, array{0: float, 1: float}> $positions
     * @return array{depots: list<int>, distanceKm: float}
     */
    public function calculer(array $depart, array $depots, array $positions): array
    {
        sort($depots);
        $position = $depart;
        $ordre = [];
        $distance = 0.0;

        while ($depots !== []) {
            $plusProche = null;
            $meilleureDistance = INF;

            foreach ($depots as $indice => $idDepot) {
                $distanceDepot = $this->distance($position, $positions[$idDepot]);
                if ($distanceDepot < $meilleureDistance) {
                    $plusProche = $indice;
                    $meilleureDistance = $distanceDepot;
                }
            }

            $idDepot = $depots[$plusProche];
            unset($depots[$plusProche]);
            $ordre[] = $idDepot;
            $distance += $meilleureDistance;
            $position = $positions[$idDepot];
        }

        return [
            'depots' => $ordre,
            'distanceKm' => $distance + $this->distance($position, $depart),
        ];
    }

    private function distance(array $origine, array $destination): float
    {
        [$latitudeOrigine, $longitudeOrigine] = $origine;
        [$latitudeDestination, $longitudeDestination] = $destination;
        $ecartLatitude = deg2rad($latitudeDestination - $latitudeOrigine);
        $ecartLongitude = deg2rad($longitudeDestination - $longitudeOrigine);

        $a = sin($ecartLatitude / 2) ** 2
            + cos(deg2rad($latitudeOrigine)) * cos(deg2rad($latitudeDestination)) * sin($ecartLongitude / 2) ** 2;

        return 2 * 6371 * asin(sqrt(min(1.0, max(0.0, $a))));
    }
}
