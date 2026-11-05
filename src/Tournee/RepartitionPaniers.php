<?php

declare(strict_types=1);

namespace App\Tournee;

use InvalidArgumentException;

final class RepartitionPaniers
{
    /**
     * Les dépôts sans panier ne nécessitent aucun véhicule.
     *
     * @param array<int, array{paniers: int, capacite: int}> $depots
     * @param array<int, int> $capacitesVehicules
     * @return array{affectations: array<int, list<int>>, depotsNonAffectes: array<int, string>}
     */
    public function calculer(array $depots, array $capacitesVehicules): array
    {
        ksort($depots);
        ksort($capacitesVehicules);
        $placesRestantes = $capacitesVehicules;
        $affectations = [];

        foreach ($capacitesVehicules as $idVehicule => $capacite) {
            if (!is_int($idVehicule) || $idVehicule < 1 || !is_int($capacite) || $capacite < 1) {
                throw new InvalidArgumentException('Chaque véhicule doit avoir un identifiant et une capacité entiers positifs.');
            }

            $affectations[$idVehicule] = [];
        }

        $paniersParDepot = [];
        $depotsNonAffectes = [];
        foreach ($depots as $idDepot => $depot) {
            if (!is_array($depot)) {
                throw new InvalidArgumentException('Un dépôt doit indiquer son nombre de paniers et sa capacité.');
            }

            $paniers = $depot['paniers'] ?? null;
            $capacite = $depot['capacite'] ?? null;
            if (!is_int($idDepot) || $idDepot < 1 || !is_int($paniers) || $paniers < 0 || !is_int($capacite) || $capacite < 0) {
                throw new InvalidArgumentException('Chaque dépôt doit avoir un identifiant entier positif et des quantités entières positives ou nulles.');
            }

            if ($paniers > $capacite) {
                $depotsNonAffectes[$idDepot] = 'Le nombre de paniers dépasse la capacité du dépôt.';
            } elseif ($paniers > 0) {
                $paniersParDepot[$idDepot] = $paniers;
            }
        }

        arsort($paniersParDepot);
        foreach ($paniersParDepot as $idDepot => $paniers) {
            $vehiculeChoisi = null;
            $meilleurReste = PHP_INT_MAX;

            foreach ($placesRestantes as $idVehicule => $places) {
                $reste = $places - $paniers;
                if ($reste >= 0 && $reste < $meilleurReste) {
                    $vehiculeChoisi = $idVehicule;
                    $meilleurReste = $reste;
                }
            }

            if ($vehiculeChoisi === null) {
                $depotsNonAffectes[$idDepot] = 'Aucun véhicule ne dispose encore de suffisamment de places pour ce dépôt.';
                continue;
            }

            $affectations[$vehiculeChoisi][] = $idDepot;
            $placesRestantes[$vehiculeChoisi] = $meilleurReste;
        }

        return [
            'affectations' => $affectations,
            'depotsNonAffectes' => $depotsNonAffectes,
        ];
    }
}
