<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TourneeController extends AbstractController
{
    #[Route('/tournees', name: 'app_tournees', methods: ['GET'])]
    public function index(): Response
    {
        $formulaire = [
            'depart' => '48.174;6.449',
            'depots' => "1;7;8;48.191;6.459\n2;5;8;48.147;6.439\n3;3;4;48.160;6.418",
            'vehicules' => "1;10\n2;8",
        ];

        return $this->render('tournee/index.html.twig', ['formulaire' => $formulaire]);
    }
}
