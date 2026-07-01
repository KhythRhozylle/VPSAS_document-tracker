<?php

namespace App\Controller;

use App\Repository\DocumentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(DocumentRepository $documentRepository): Response
    {
        $monthlyCounts = $documentRepository->countByMonth(12);
        $chartLabels = [];
        $chartValues = [];

        for ($i = 11; $i >= 0; --$i) {
            $date = new \DateTime(sprintf('first day of -%d months', $i));
            $key = $date->format('Y-m');
            $chartLabels[] = $date->format('M');
            $chartValues[] = $monthlyCounts[$key] ?? 0;
        }

        return $this->render('dashboard/index.html.twig', [
            'totalDocuments' => $documentRepository->countTotal(),
            'thisMonth' => $documentRepository->countThisMonth(),
            'today' => $documentRepository->countToday(),
            'totalAmount' => $documentRepository->sumTotalAmount(),
            'recentDocuments' => $documentRepository->findRecent(5),
            'chartLabels' => $chartLabels,
            'chartValues' => $chartValues,
        ]);
    }
}
