<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class ConnexionController extends AbstractController
{
    #[Route('/connexion', name: 'app_connexion', methods: ['GET', 'POST'])]
    public function connexion(AuthenticationUtils $authentification): Response
    {
        return $this->render('connexion/index.html.twig', [
            'email' => $authentification->getLastUsername(),
            'echec' => $authentification->getLastAuthenticationError() !== null,
        ]);
    }

    #[Route('/espace-adherent', name: 'app_espace_adherent', methods: ['GET'])]
    public function espaceAdherent(): Response
    {
        return $this->render('connexion/espace.html.twig');
    }
}
