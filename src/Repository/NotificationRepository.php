<?php

namespace App\Repository;

use App\Entity\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function countUnread(): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.isRead = false')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Notification>
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('n')
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function markAllAsRead(): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', ':read')
            ->where('n.isRead = false')
            ->setParameter('read', true)
            ->getQuery()
            ->execute();
    }
}
