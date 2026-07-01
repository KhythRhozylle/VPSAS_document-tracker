<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationRepository $notificationRepository,
    ) {
    }

    public function notify(string $title, string $message, string $type = Notification::TYPE_INFO, ?string $link = null): Notification
    {
        $notification = (new Notification())
            ->setTitle($title)
            ->setMessage($message)
            ->setType($type)
            ->setLink($link);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }

    public function notifyDocumentCreated(Document $document): void
    {
        $this->notify(
            'New document added',
            sprintf('Document for %s (%s) was created.', $document->getCampus(), $document->getDocumentType()),
            Notification::TYPE_SUCCESS,
            '/reports?q='.urlencode($document->getCampus() ?? ''),
        );
    }

    public function notifyDocumentUpdated(Document $document): void
    {
        $this->notify(
            'Document updated',
            sprintf('Document #%d for %s was updated.', $document->getId(), $document->getCampus()),
            Notification::TYPE_INFO,
            '/reports?q='.urlencode($document->getCampus() ?? ''),
        );
    }

    public function notifyDocumentDeleted(string $campus, int $documentId): void
    {
        $this->notify(
            'Document deleted',
            sprintf('Document #%d for %s was removed.', $documentId, $campus),
            Notification::TYPE_WARNING,
            '/reports',
        );
    }

    public function countUnread(): int
    {
        return $this->notificationRepository->countUnread();
    }

    /**
     * @return list<Notification>
     */
    public function getRecent(int $limit = 10): array
    {
        return $this->notificationRepository->findRecent($limit);
    }

    public function markAsRead(Notification $notification): void
    {
        if ($notification->isRead()) {
            return;
        }

        $notification->setIsRead(true);
        $this->entityManager->flush();
    }

    public function markAllAsRead(): void
    {
        $this->notificationRepository->markAllAsRead();
    }
}
