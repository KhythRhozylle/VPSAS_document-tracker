<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    private const ALLOWED_SORT_FIELDS = [
        'created_at' => 'a.createdAt',
        'full_name' => 'a.fullName',
        'email' => 'a.email',
        'action' => 'a.action',
        'module' => 'a.module',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * @return array{items: list<ActivityLog>, total: int}
     */
    public function findFiltered(
        string $search = '',
        ?string $role = null,
        ?string $action = null,
        ?\DateTimeInterface $dateFrom = null,
        ?\DateTimeInterface $dateTo = null,
        string $sort = 'created_at',
        string $direction = 'desc',
        int $page = 1,
        int $limit = 15,
    ): array {
        $qb = $this->createQueryBuilder('a');
        $this->applyFilters($qb, $search, $role, $action, $dateFrom, $dateTo);

        $total = (int) (clone $qb)
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $sortField = self::ALLOWED_SORT_FIELDS[$sort] ?? self::ALLOWED_SORT_FIELDS['created_at'];
        $direction = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';

        $qb->select('a')->orderBy($sortField, $direction);

        if ($limit > 0) {
            $qb
                ->setFirstResult(max(0, ($page - 1) * $limit))
                ->setMaxResults($limit);
        }

        $items = $qb->getQuery()->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return list<ActivityLog>
     */
    public function findAllFiltered(
        string $search = '',
        ?string $role = null,
        ?string $action = null,
        ?\DateTimeInterface $dateFrom = null,
        ?\DateTimeInterface $dateTo = null,
        string $sort = 'created_at',
        string $direction = 'desc',
    ): array {
        $qb = $this->createQueryBuilder('a');
        $this->applyFilters($qb, $search, $role, $action, $dateFrom, $dateTo);

        $sortField = self::ALLOWED_SORT_FIELDS[$sort] ?? self::ALLOWED_SORT_FIELDS['created_at'];
        $direction = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';

        return $qb
            ->select('a')
            ->orderBy($sortField, $direction)
            ->getQuery()
            ->getResult();
    }

    private function applyFilters(
        \Doctrine\ORM\QueryBuilder $qb,
        string $search,
        ?string $role,
        ?string $action,
        ?\DateTimeInterface $dateFrom,
        ?\DateTimeInterface $dateTo,
    ): void {
        $search = trim($search);
        if ($search !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('a.fullName', ':search'),
                    $qb->expr()->like('a.email', ':search'),
                    $qb->expr()->like('a.role', ':search'),
                    $qb->expr()->like('a.action', ':search'),
                    $qb->expr()->like('a.module', ':search'),
                    $qb->expr()->like('a.description', ':search'),
                )
            )->setParameter('search', '%'.$search.'%');
        }

        if ($role !== null && $role !== '') {
            $qb->andWhere('a.role = :role')->setParameter('role', $role);
        }

        if ($action !== null && $action !== '') {
            $qb->andWhere('a.action = :action')->setParameter('action', $action);
        }

        if ($dateFrom instanceof \DateTimeInterface) {
            $qb->andWhere('a.createdAt >= :dateFrom')->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo instanceof \DateTimeInterface) {
            $qb->andWhere('a.createdAt <= :dateTo')->setParameter('dateTo', $dateTo);
        }
    }
}
