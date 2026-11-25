<?php

declare(strict_types=1);

namespace App\Controller;

use JsonException;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;
use PDO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/espace-adherent/passkey', host: 'localhost', env: 'dev')]
#[IsGranted('ROLE_ADHERENT')]
final class PasskeyController extends AbstractController
{
    private const ORIGINE = 'http://localhost:8000';
    private const DUREE_DEFI = 120;
    private const CLE_SESSION = 'passkey.inscription';
    private const DEJA_INSCRITE = 'Une passkey est déjà enregistrée pour ce compte.';

    #[Route('/options', name: 'app_passkey_options', methods: ['POST'])]
    public function options(
        Request $requete,
        #[Autowire('%kernel.secret%')] string $secret,
        #[Autowire('%kernel.project_dir%')] string $projet,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('ajouter_passkey', $requete->headers->get('X-CSRF-Token'))) {
            return $this->json(['erreur' => 'Jeton de formulaire invalide.'], 403);
        }

        $email = $this->getUser()->getUserIdentifier();
        $recherche = $this->base($projet)->prepare('SELECT 1 FROM passkeys WHERE email = ?');
        $recherche->execute([$email]);
        if ($recherche->fetchColumn() !== false) {
            return $this->json(['erreur' => self::DEJA_INSCRITE], 409);
        }

        $webauthn = new WebAuthn('Jardins de Cocagne', 'localhost', ['none'], true);
        $identifiant = hash_hmac('sha256', $email, $secret, true);
        $options = $webauthn->getCreateArgs($identifiant, $email, $email, self::DUREE_DEFI, true, true);

        $requete->getSession()->set(self::CLE_SESSION, [
            'email' => $email,
            'identifiant_adherent' => base64_encode($identifiant),
            'defi' => $webauthn->getChallenge()->getBinaryString(),
            'expiration' => time() + self::DUREE_DEFI,
        ]);

        return new JsonResponse($options->publicKey, headers: ['Cache-Control' => 'no-store']);
    }

    #[Route('', name: 'app_passkey_enregistrer', methods: ['POST'])]
    public function enregistrer(Request $requete, #[Autowire('%kernel.project_dir%')] string $projet): JsonResponse
    {
        if (!$this->isCsrfTokenValid('ajouter_passkey', $requete->headers->get('X-CSRF-Token'))) {
            return $this->json(['erreur' => 'Jeton de formulaire invalide.'], 403);
        }

        $email = $this->getUser()->getUserIdentifier();
        $inscription = $requete->getSession()->remove(self::CLE_SESSION);
        if ($inscription === null || $inscription['email'] !== $email || $inscription['expiration'] <= time()) {
            return $this->json(['erreur' => 'Recommencez l’ajout de la passkey.'], 400);
        }

        try {
            $donnees = $requete->getContent();
            if (strlen($donnees) > 32768) {
                return $this->json(['erreur' => 'Réponse de passkey trop volumineuse.'], 413);
            }
            $reponse = json_decode($donnees, true, 16, JSON_THROW_ON_ERROR);
            $client = $this->decoder($reponse['clientDataJSON'] ?? null);
            $attestation = $this->decoder($reponse['attestationObject'] ?? null);
            $contexte = json_decode($client, true, 16, JSON_THROW_ON_ERROR);
            if (($contexte['origin'] ?? null) !== self::ORIGINE || ($contexte['crossOrigin'] ?? false) !== false) {
                return $this->json(['erreur' => 'Origine de la passkey invalide.'], 400);
            }

            $webauthn = new WebAuthn('Jardins de Cocagne', 'localhost', ['none'], true);
            $cle = $webauthn->processCreate($client, $attestation, $inscription['defi'], requireUserVerification: true);
        } catch (JsonException|WebAuthnException) {
            return $this->json(['erreur' => 'Réponse de passkey invalide.'], 400);
        }

        $ajout = $this->base($projet)->prepare('INSERT INTO passkeys (email, identifiant_adherent, identifiant, cle_publique, compteur)
            VALUES (?, ?, ?, ?, ?) ON CONFLICT DO NOTHING');
        $ajout->execute([
            $email,
            $inscription['identifiant_adherent'],
            base64_encode($cle->credentialId),
            $cle->credentialPublicKey,
            $cle->signatureCounter ?? 0,
        ]);

        if ($ajout->rowCount() === 0) {
            return $this->json(['erreur' => self::DEJA_INSCRITE], 409);
        }

        return $this->json(['enregistree' => true], 201);
    }

    private function base(string $projet): PDO
    {
        $base = new PDO('sqlite:'.$projet.'/var/passkeys/adherents.sqlite');
        $base->exec('CREATE TABLE IF NOT EXISTS passkeys (
            email TEXT PRIMARY KEY NOT NULL,
            identifiant_adherent TEXT NOT NULL,
            identifiant TEXT UNIQUE NOT NULL,
            cle_publique TEXT NOT NULL,
            compteur INTEGER NOT NULL
        )');

        return $base;
    }

    private function decoder(mixed $valeur): string
    {
        $octets = is_string($valeur) ? base64_decode(strtr($valeur, '-_', '+/'), true) : false;
        if ($octets === false || $octets === '') {
            throw new JsonException('Donnée de passkey invalide.');
        }

        return $octets;
    }
}
