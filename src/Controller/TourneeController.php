<?php

declare(strict_types=1);

namespace App\Controller;

use App\Tournee\OrdonnerDepots;
use App\Tournee\RepartitionPaniers;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TourneeController extends AbstractController
{
    #[Route('/tournees', name: 'app_tournees', methods: ['GET', 'POST'])]
    public function index(Request $request, RepartitionPaniers $repartition, OrdonnerDepots $ordre): Response
    {
        $formulaire = [
            'depart' => '48.174;6.449',
            'depots' => "1;7;8;48.191;6.459\n2;5;8;48.147;6.439\n3;3;4;48.160;6.418",
            'vehicules' => "1;10\n2;8",
        ];
        $tournees = [];
        $depotsNonAffectes = [];
        $erreur = null;

        if ($request->isMethod('POST')) {
            foreach (array_keys($formulaire) as $champ) {
                $formulaire[$champ] = $request->request->getString($champ);
            }

            try {
                $depart = $this->position(explode(';', $formulaire['depart']), 'Lieu de chargement');
                $depots = [];
                $positions = [];
                foreach ($this->lignes($formulaire['depots'], 5, 'Dépôt') as $numero => $champs) {
                    $libelle = "Dépôt, ligne $numero";
                    $id = $this->entier($champs[0], 1, $libelle);
                    if (isset($depots[$id])) {
                        throw new InvalidArgumentException("$libelle : l’identifiant $id est déjà utilisé.");
                    }

                    $depots[$id] = [
                        'paniers' => $this->entier($champs[1], 0, $libelle),
                        'capacite' => $this->entier($champs[2], 0, $libelle),
                    ];
                    $positions[$id] = $this->position(array_slice($champs, 3), $libelle);
                }

                if ($depots === []) {
                    throw new InvalidArgumentException('Saisissez au moins un dépôt à livrer.');
                }

                $vehicules = [];
                foreach ($this->lignes($formulaire['vehicules'], 2, 'Véhicule') as $numero => $champs) {
                    $libelle = "Véhicule, ligne $numero";
                    $id = $this->entier($champs[0], 1, $libelle);
                    if (isset($vehicules[$id])) {
                        throw new InvalidArgumentException("$libelle : l’identifiant $id est déjà utilisé.");
                    }

                    $vehicules[$id] = $this->entier($champs[1], 1, $libelle);
                }

                $resultat = $repartition->calculer($depots, $vehicules);
                $depotsNonAffectes = $resultat['depotsNonAffectes'];
                foreach ($resultat['affectations'] as $id => $depotsAffectes) {
                    $tournee = $ordre->calculer($depart, $depotsAffectes, $positions);
                    $tournee['paniers'] = array_sum(array_map(static fn (int $depot): int => $depots[$depot]['paniers'], $depotsAffectes));
                    $tournee['capacite'] = $vehicules[$id];
                    $tournees[$id] = $tournee;
                }
            } catch (InvalidArgumentException $exception) {
                $erreur = $exception->getMessage();
            }
        }

        return $this->render('tournee/index.html.twig', [
            'formulaire' => $formulaire,
            'calcule' => $request->isMethod('POST') && $erreur === null,
            'tournees' => $tournees,
            'depotsNonAffectes' => $depotsNonAffectes,
            'erreur' => $erreur,
        ], new Response(status: $erreur === null ? 200 : 422));
    }

    /** @return iterable<int, list<string>> */
    private function lignes(string $texte, int $nombreChamps, string $libelle): iterable
    {
        foreach (preg_split('/\R/', $texte) ?: [] as $indice => $ligne) {
            if (trim($ligne) === '') {
                continue;
            }

            $champs = array_map('trim', explode(';', $ligne));
            if (count($champs) !== $nombreChamps) {
                $numero = $indice + 1;
                throw new InvalidArgumentException("$libelle, ligne $numero : $nombreChamps valeurs séparées par des points-virgules sont attendues.");
            }

            yield $indice + 1 => $champs;
        }
    }

    private function entier(string $valeur, int $minimum, string $libelle): int
    {
        $nombre = filter_var($valeur, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum]]);
        if ($nombre === false) {
            throw new InvalidArgumentException("$libelle : un nombre entier supérieur ou égal à $minimum est attendu.");
        }

        return $nombre;
    }

    /** @return array{float, float} */
    private function position(array $champs, string $libelle): array
    {
        if (count($champs) !== 2 || !is_numeric($champs[0]) || !is_numeric($champs[1])
            || abs((float) $champs[0]) > 90 || abs((float) $champs[1]) > 180) {
            throw new InvalidArgumentException("$libelle : indiquez une latitude entre -90 et 90 et une longitude entre -180 et 180.");
        }

        return [(float) $champs[0], (float) $champs[1]];
    }
}
