<?php

declare(strict_types=1);

namespace App\Controller;

use lbuchs\WebAuthn\WebAuthn;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route('/connexion/passkey/options', name: 'app_connexion_passkey_options', host: 'localhost', methods: ['POST'], env: 'dev')]
    public function optionsPasskey(Request $requete): JsonResponse
    {
        if (!$this->isCsrfTokenValid('authenticate', $requete->headers->get('X-CSRF-Token'))) {
            return $this->json(['erreur' => 'Jeton de formulaire invalide.'], 403);
        }

        $duree = 120;
        $webauthn = new WebAuthn('Jardins de Cocagne', 'localhost', ['none'], true);
        $options = $webauthn->getGetArgs(timeout: $duree, requireUserVerification: true);

        $requete->getSession()->set('passkey.connexion', [
            'defi' => $webauthn->getChallenge()->getBinaryString(),
            'expiration' => time() + $duree,
        ]);

        return new JsonResponse($options->publicKey, headers: ['Cache-Control' => 'no-store']);
    }

    #[Route('/espace-adherent', name: 'app_espace_adherent', methods: ['GET'])]
    public function espaceAdherent(): Response
    {
        return $this->render('connexion/espace.html.twig');
    }
}
