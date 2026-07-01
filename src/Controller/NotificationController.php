<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/notifications')]
final class NotificationController extends AbstractController
{
    #[Route('', name: 'app_notifications', methods: ['GET'])]
    public function index(NotificationService $notificationService): Response
    {
        return $this->render('notifications/_dropdown.html.twig', [
            'notifications' => $notificationService->getRecent(10),
            'unreadCount' => $notificationService->countUnread(),
        ]);
    }

    #[Route('/{id}/read', name: 'app_notifications_read', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function markRead(Notification $notification, NotificationService $notificationService): JsonResponse
    {
        $notificationService->markAsRead($notification);

        return $this->json([
            'success' => true,
            'unreadCount' => $notificationService->countUnread(),
        ]);
    }

    #[Route('/read-all', name: 'app_notifications_read_all', methods: ['POST'])]
    public function markAllRead(Request $request, NotificationService $notificationService): JsonResponse
    {
        if (!$this->isCsrfTokenValid('notifications_read_all', (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Invalid security token.'], Response::HTTP_FORBIDDEN);
        }

        $notificationService->markAllAsRead();

        return $this->json([
            'success' => true,
            'unreadCount' => 0,
        ]);
    }
}
