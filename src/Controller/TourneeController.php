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
    private const NOMBRE_CHAMPS_DEPOT = 5;
    private const NOMBRE_CHAMPS_VEHICULE = 2;
    private const NOMBRE_CHAMPS_AFFECTATION = 2;

    #[Route('/tournees', name: 'app_tournees', methods: ['GET', 'POST'])]
    public function index(Request $request, RepartitionPaniers $repartition, OrdonnerDepots $ordre): Response
    {
        $formulaire = [
            'depart' => '48.174;6.449',
            'depots' => "1;7;8;48.191;6.459\n2;5;8;48.147;6.439\n3;3;4;48.160;6.418",
            'vehicules' => "1;10\n2;8",
            'affectations' => '',
        ];
        $tournees = [];
        $depotsNonAffectes = [];
        $erreur = null;
        $modeManuel = false;
        $requetePost = $request->isMethod('POST');

        if ($requetePost) {
            $modeManuel = $request->request->getString('action') === 'manuel';
            foreach (array_keys($formulaire) as $champ) {
                $formulaire[$champ] = $request->request->getString($champ);
            }

            try {
                $depart = $this->position(explode(';', $formulaire['depart']), 'Lieu de chargement');
                $depots = [];
                $positions = [];
                foreach ($this->lignes($formulaire['depots'], self::NOMBRE_CHAMPS_DEPOT, 'Dépôt') as $numero => $champs) {
                    $libelle = "Dépôt, ligne $numero";
                    $idDepot = $this->entier($champs[0], 1, $libelle);
                    if (isset($depots[$idDepot])) {
                        throw new InvalidArgumentException("$libelle : l’identifiant $idDepot est déjà utilisé.");
                    }

                    $depots[$idDepot] = [
                        'paniers' => $this->entier($champs[1], 0, $libelle),
                        'capacite' => $this->entier($champs[2], 0, $libelle),
                    ];
                    $positions[$idDepot] = $this->position(array_slice($champs, 3), $libelle);
                }

                if ($depots === []) {
                    throw new InvalidArgumentException('Saisissez au moins un dépôt à livrer.');
                }

                $vehicules = [];
                foreach ($this->lignes($formulaire['vehicules'], self::NOMBRE_CHAMPS_VEHICULE, 'Véhicule') as $numero => $champs) {
                    $libelle = "Véhicule, ligne $numero";
                    $idVehicule = $this->entier($champs[0], 1, $libelle);
                    if (isset($vehicules[$idVehicule])) {
                        throw new InvalidArgumentException("$libelle : l’identifiant $idVehicule est déjà utilisé.");
                    }

                    $vehicules[$idVehicule] = $this->entier($champs[1], 1, $libelle);
                }

                $affectationsManuelles = null;
                if ($modeManuel) {
                    $affectationsManuelles = [];
                    foreach ($this->lignes($formulaire['affectations'], self::NOMBRE_CHAMPS_AFFECTATION, 'Affectation') as $numero => [$depot, $vehicule]) {
                        $libelle = "Affectation, ligne $numero";
                        $affectationsManuelles[] = [
                            'depot' => $this->entier($depot, 1, $libelle),
                            'vehicule' => $this->entier($vehicule, 1, $libelle),
                        ];
                    }
                }

                $resultat = $repartition->calculer($depots, $vehicules, $affectationsManuelles);
                $depotsNonAffectes = $resultat['depotsNonAffectes'];
                foreach ($resultat['affectations'] as $idVehicule => $depotsAffectes) {
                    $tournee = $ordre->calculer($depart, $depotsAffectes, $positions);
                    $tournee['paniers'] = array_sum(array_map(static fn (int $depot): int => $depots[$depot]['paniers'], $depotsAffectes));
                    $tournee['capacite'] = $vehicules[$idVehicule];
                    $tournees[$idVehicule] = $tournee;
                }
                if (!$modeManuel) {
                    $formulaire['affectations'] = $this->texteAffectations($tournees);
                }
            } catch (InvalidArgumentException $exception) {
                $erreur = $exception->getMessage();
            }
        }

        return $this->render('tournee/index.html.twig', [
            'formulaire' => $formulaire,
            'calcule' => $requetePost && $erreur === null,
            'tournees' => $tournees,
            'modeManuel' => $modeManuel,
            'depotsNonAffectes' => $depotsNonAffectes,
            'erreur' => $erreur,
        ], new Response(status: $erreur === null ? 200 : 422));
    }

    /**
     * Préremplit le champ de correction manuelle au format dépôt;véhicule.
     *
     * @param array<int, array{depots: list<int>}> $tournees
     */
    private function texteAffectations(array $tournees): string
    {
        $affectations = [];
        foreach ($tournees as $idVehicule => $tournee) {
            foreach ($tournee['depots'] as $idDepot) {
                $affectations[] = "$idDepot;$idVehicule";
            }
        }

        return implode("\n", $affectations);
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
