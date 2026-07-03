<?php

namespace App\Controller;

use App\Repository\DocumentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/search')]
final class SearchController extends AbstractController
{
    #[Route('', name: 'app_search', methods: ['GET'])]
    public function index(Request $request, DocumentRepository $documentRepository): Response
    {
        $query = trim((string) $request->query->get('q', ''));

        if ($query === '') {
            return $this->render('search/_dropdown.html.twig', [
                'query' => '',
                'results' => [],
            ]);
        }

        $result = $documentRepository->findFiltered(
            $query,
            null,
            null,
            null,
            null,
            null,
            null,
            'date_approved',
            'desc',
            1,
            50,
        );

        return $this->render('search/_dropdown.html.twig', [
            'query' => $query,
            'results' => $result['items'],
            'total' => $result['total'],
        ]);
    }
}
