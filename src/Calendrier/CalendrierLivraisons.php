<?php

declare(strict_types=1);

namespace App\Calendrier;

use DateTimeImmutable;
use InvalidArgumentException;

final class CalendrierLivraisons
{
    /** @return list<array{prevue: string, date: string}> */
    public function generer(int $annee, int $nombre, int $jour, int $parite, array $fermetures): array
    {
        if ($annee < 1583 || $annee > 9999 || $nombre < 1 || $jour < 1 || $jour > 5 || !in_array($parite, [0, 1], true)) {
            throw new InvalidArgumentException('Vérifiez l’année, le nombre de livraisons, le jour et la semaine de départ.');
        }

        $debut = new DateTimeImmutable(sprintf('%04d-01-01', $annee));
        $decalage = ($jour - (int) $debut->format('N') + 7) % 7;
        $feries = $this->joursFeries($annee);
        $possibles = [];

        for ($date = $debut->modify("+$decalage days"); (int) $date->format('Y') === $annee; $date = $date->modify('+7 days')) {
            $semaine = (int) $date->format('W');
            if (in_array($semaine, $fermetures, true) || ($possibles === [] && $semaine % 2 !== $parite)) {
                continue;
            }

            $report = $this->report($date, $annee, $fermetures, $feries);
            if ($report !== null) {
                $possibles[] = ['prevue' => $date->format('Y-m-d'), 'date' => $report->format('Y-m-d')];
            }
        }

        $disponibles = count($possibles);
        if ($nombre > $disponibles) {
            throw new InvalidArgumentException("Il reste $disponibles dates possibles pour $nombre livraisons demandées.");
        }
        if ($nombre === 1) {
            return [$possibles[0]];
        }

        $plan = [];
        for ($i = 0; $i < $nombre; ++$i) {
            $plan[] = $possibles[(int) round($i * ($disponibles - 1) / ($nombre - 1))];
        }

        return $plan;
    }

    /** @return list<array{prevue: string, date: string}> */
    public function valider(array $plan, array $dates, int $annee, array $fermetures): array
    {
        if (array_keys($dates) !== array_keys($plan)) {
            throw new InvalidArgumentException('Le calendrier doit contenir exactement le nombre de livraisons demandé.');
        }

        $feries = $this->joursFeries($annee);
        $utilisees = [];
        foreach ($plan as $i => $livraison) {
            $date = is_string($dates[$i]) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $dates[$i])
                ? DateTimeImmutable::createFromFormat('!Y-m-d', $dates[$i])
                : false;
            $prevue = new DateTimeImmutable($livraison['prevue']);
            $jour = (int) $prevue->format('N');
            $reportImpose = in_array($prevue->format('Y-m-d'), $feries, true) && in_array($jour, [1, 5], true);

            if (!$date || $date->format('Y-m-d') !== $dates[$i] || !$this->disponible($date, $annee, $fermetures, $feries)) {
                throw new InvalidArgumentException("Date indisponible pour la livraison du {$livraison['prevue']}.");
            }
            if (isset($utilisees[$dates[$i]])) {
                throw new InvalidArgumentException('Deux livraisons ont la même date.');
            }
            if ($date->format('o-W') !== $prevue->format('o-W') || ($reportImpose && $dates[$i] !== $livraison['date'])) {
                throw new InvalidArgumentException("Report non autorisé pour la livraison du {$livraison['prevue']}.");
            }

            $utilisees[$dates[$i]] = true;
            $plan[$i]['date'] = $dates[$i];
        }

        return $plan;
    }

    private function report(DateTimeImmutable $date, int $annee, array $fermetures, array $feries): ?DateTimeImmutable
    {
        if ($this->disponible($date, $annee, $fermetures, $feries)) {
            return $date;
        }

        $decalages = match ((int) $date->format('N')) {
            1 => [1],
            5 => [-1],
            default => [1, -1, 2, -2, 3, -3],
        };
        foreach ($decalages as $decalage) {
            $report = $date->modify(sprintf('%+d days', $decalage));
            if ($report->format('o-W') === $date->format('o-W') && $this->disponible($report, $annee, $fermetures, $feries)) {
                return $report;
            }
        }

        return null;
    }

    private function disponible(DateTimeImmutable $date, int $annee, array $fermetures, array $feries): bool
    {
        return (int) $date->format('Y') === $annee
            && (int) $date->format('N') <= 5
            && !in_array((int) $date->format('W'), $fermetures, true)
            && !in_array($date->format('Y-m-d'), $feries, true);
    }

    /** @return list<string> Jours fériés nationaux en France métropolitaine. */
    private function joursFeries(int $annee): array
    {
        // Calcul grégorien de Pâques, indépendant de l’extension PHP calendar.
        $cycle = $annee % 19;
        $siecle = intdiv($annee, 100);
        $reste = $annee % 100;
        $lune = (19 * $cycle + $siecle - intdiv($siecle, 4) - intdiv($siecle - intdiv($siecle + 8, 25) + 1, 3) + 15) % 30;
        $semaine = (32 + 2 * ($siecle % 4) + 2 * intdiv($reste, 4) - $lune - $reste % 4) % 7;
        $correction = intdiv($cycle + 11 * $lune + 22 * $semaine, 451);
        $position = $lune + $semaine - 7 * $correction + 114;
        $paques = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $annee, intdiv($position, 31), $position % 31 + 1));

        $fixes = ['01-01', '05-01', '05-08', '07-14', '08-15', '11-01', '11-11', '12-25'];

        return [
            ...array_map(static fn (string $date): string => sprintf('%04d-%s', $annee, $date), $fixes),
            $paques->modify('+1 day')->format('Y-m-d'),
            $paques->modify('+39 days')->format('Y-m-d'),
            $paques->modify('+50 days')->format('Y-m-d'),
        ];
    }
}
