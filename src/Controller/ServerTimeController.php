<?php

namespace App\Controller;

use App\Service\DateTimeFormatterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class ServerTimeController extends AbstractController
{
    #[Route('/server-time', name: 'app_server_time', methods: ['GET'])]
    public function serverTime(DateTimeFormatterService $dateTimeFormatter): JsonResponse
    {
        return $this->json($dateTimeFormatter->getServerTimePayload());
    }
}
