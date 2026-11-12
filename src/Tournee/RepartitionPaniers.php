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
     * @param list<array{depot: int, vehicule: int}>|null $affectationsManuelles
     * @return array{affectations: array<int, list<int>>, depotsNonAffectes: array<int, string>}
     */
    public function calculer(array $depots, array $capacitesVehicules, ?array $affectationsManuelles = null): array
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
                if ($affectationsManuelles !== null) {
                    throw new InvalidArgumentException("Le nombre de paniers dépasse la capacité du dépôt $idDepot.");
                }

                $depotsNonAffectes[$idDepot] = 'Le nombre de paniers dépasse la capacité du dépôt.';
            } elseif ($paniers > 0) {
                $paniersParDepot[$idDepot] = $paniers;
            }
        }

        if ($affectationsManuelles !== null) {
            return [
                'affectations' => $this->repartirManuellement($affectationsManuelles, $paniersParDepot, $placesRestantes, $affectations),
                'depotsNonAffectes' => [],
            ];
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

    /**
     * @param list<array{depot: int, vehicule: int}> $correction
     * @param array<int, int> $paniersParDepot
     * @param array<int, int> $placesRestantes
     * @param array<int, list<int>> $affectations
     * @return array<int, list<int>>
     */
    private function repartirManuellement(array $correction, array $paniersParDepot, array $placesRestantes, array $affectations): array
    {
        $depotsAffectes = [];
        foreach ($correction as $ligne) {
            if (!is_array($ligne) || !is_int($ligne['depot'] ?? null) || !is_int($ligne['vehicule'] ?? null)) {
                throw new InvalidArgumentException('Chaque affectation manuelle doit indiquer un dépôt et un véhicule.');
            }

            $depot = $ligne['depot'];
            $vehicule = $ligne['vehicule'];
            if (!isset($paniersParDepot[$depot])) {
                throw new InvalidArgumentException("Le dépôt $depot est inconnu ou sans panier à livrer.");
            }
            if (isset($depotsAffectes[$depot])) {
                throw new InvalidArgumentException("Le dépôt $depot est affecté plusieurs fois.");
            }
            if (!isset($placesRestantes[$vehicule])) {
                throw new InvalidArgumentException("Le véhicule $vehicule est inconnu.");
            }
            if ($paniersParDepot[$depot] > $placesRestantes[$vehicule]) {
                throw new InvalidArgumentException("La capacité du véhicule $vehicule est dépassée.");
            }

            $affectations[$vehicule][] = $depot;
            $placesRestantes[$vehicule] -= $paniersParDepot[$depot];
            $depotsAffectes[$depot] = true;
        }

        $manquants = array_diff_key($paniersParDepot, $depotsAffectes);
        if ($manquants !== []) {
            throw new InvalidArgumentException('Dépôts sans affectation dans la correction : '.implode(', ', array_keys($manquants)).'.');
        }

        return $affectations;
    }
}
