<?php

declare(strict_types=1);

namespace App\Controller;

use App\Calendrier\CalendrierLivraisons;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CalendrierController extends AbstractController
{
    #[Route('/calendrier', name: 'app_calendrier', methods: ['GET', 'POST'])]
    public function index(Request $request, CalendrierLivraisons $livraisons): Response
    {
        $formulaire = [
            'annee' => (new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')))->format('Y'),
            'tournee' => '',
            'nombre' => '25',
            'jour' => '3',
            'parite' => '1',
            'fermetures' => '',
        ];
        $plan = [];
        $erreur = null;
        $valide = false;

        if ($request->isMethod('POST')) {
            foreach (array_keys($formulaire) as $champ) {
                $formulaire[$champ] = trim($request->request->getString($champ, $formulaire[$champ]));
            }

            try {
                if ($formulaire['tournee'] === '') {
                    throw new InvalidArgumentException('Indiquez la tournée à planifier.');
                }

                $fermetures = [];
                foreach (preg_split('/[\s,;]+/', $formulaire['fermetures'], flags: PREG_SPLIT_NO_EMPTY) as $semaine) {
                    $fermetures[] = $this->entier($semaine, 1, 53);
                }

                $annee = $this->entier($formulaire['annee'], 1583, 9999);
                $plan = $livraisons->generer(
                    $annee,
                    $this->entier($formulaire['nombre'], 1, 53),
                    $this->entier($formulaire['jour'], 1, 5),
                    $this->entier($formulaire['parite'], 0, 1),
                    $fermetures,
                );
                if ($request->request->getString('action') === 'verifier') {
                    $dates = $request->request->all()['dates'] ?? [];
                    if (!is_array($dates)) {
                        throw new InvalidArgumentException('Les dates de livraison sont invalides.');
                    }

                    $plan = $livraisons->valider($plan, $dates, $annee, $fermetures);
                    $valide = true;
                }
            } catch (InvalidArgumentException $exception) {
                $erreur = $exception->getMessage();
            }
        }

        return $this->render('calendrier/index.html.twig', [
            'formulaire' => $formulaire,
            'plan' => $plan,
            'valide' => $valide,
            'erreur' => $erreur,
        ], new Response(status: $erreur === null ? 200 : 422));
    }

    private function entier(string $valeur, int $minimum, int $maximum): int
    {
        $entier = filter_var($valeur, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum, 'max_range' => $maximum]]);
        if ($entier === false) {
            throw new InvalidArgumentException("Saisissez un nombre entier de $minimum à $maximum.");
        }

        return $entier;
    }
}
