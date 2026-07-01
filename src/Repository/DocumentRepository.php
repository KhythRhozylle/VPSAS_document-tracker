<?php

namespace App\Repository;

use App\Entity\Document;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    private const ALLOWED_SORT_FIELDS = [
        'date_approved' => 'd.dateApproved',
        'campus' => 'd.campus',
        'amount' => 'd.amount',
        'created_at' => 'd.createdAt',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    public function countTotal(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countThisMonth(): int
    {
        $start = new \DateTime('first day of this month 00:00:00');
        $end = new \DateTime('last day of this month 23:59:59');

        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countToday(): int
    {
        $start = new \DateTime('today 00:00:00');
        $end = new \DateTime('today 23:59:59');

        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumTotalAmount(): float
    {
        $result = $this->createQueryBuilder('d')
            ->select('COALESCE(SUM(d.amount), 0)')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $result;
    }

    /**
     * @return list<Document>
     */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('d')
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<string, int> Month key (Y-m) => count
     */
    public function countByMonth(int $months = 12): array
    {
        $start = new \DateTime(sprintf('first day of -%d months 00:00:00', $months - 1));

        $rows = $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT DATE_FORMAT(created_at, \'%Y-%m\') AS month_key, COUNT(id) AS doc_count
             FROM documents
             WHERE created_at >= :start
             GROUP BY month_key
             ORDER BY month_key ASC',
            ['start' => $start->format('Y-m-d H:i:s')],
        )->fetchAllAssociative();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['month_key']] = (int) $row['doc_count'];
        }

        return $counts;
    }

    /**
     * @return array{items: list<Document>, total: int}
     */
    public function findFiltered(
        string $search = '',
        ?string $campus = null,
        ?string $documentType = null,
        ?string $status = null,
        ?string $userRole = null,
        ?\DateTimeInterface $dateFrom = null,
        ?\DateTimeInterface $dateTo = null,
        string $sort = 'date_approved',
        string $direction = 'desc',
        int $page = 1,
        int $limit = 10,
    ): array {
        $qb = $this->createQueryBuilder('d');
        $this->applyFilters($qb, $search, $campus, $documentType, $status, $userRole, $dateFrom, $dateTo);

        $total = (int) (clone $qb)
            ->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $sortField = self::ALLOWED_SORT_FIELDS[$sort] ?? self::ALLOWED_SORT_FIELDS['date_approved'];
        $direction = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';

        $qb->select('d')->orderBy($sortField, $direction);

        if ($limit > 0) {
            $qb
                ->setFirstResult(max(0, ($page - 1) * $limit))
                ->setMaxResults($limit);
        }

        return ['items' => $qb->getQuery()->getResult(), 'total' => $total];
    }

    /**
     * @return list<Document>
     */
    public function findAllFiltered(
        string $search = '',
        ?string $campus = null,
        ?string $documentType = null,
        ?string $status = null,
        ?string $userRole = null,
        ?\DateTimeInterface $dateFrom = null,
        ?\DateTimeInterface $dateTo = null,
        string $sort = 'date_approved',
        string $direction = 'desc',
    ): array {
        $qb = $this->createQueryBuilder('d');
        $this->applyFilters($qb, $search, $campus, $documentType, $status, $userRole, $dateFrom, $dateTo);

        $sortField = self::ALLOWED_SORT_FIELDS[$sort] ?? self::ALLOWED_SORT_FIELDS['date_approved'];
        $direction = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';

        return $qb
            ->select('d')
            ->orderBy($sortField, $direction)
            ->getQuery()
            ->getResult();
    }

    private function applyFilters(
        \Doctrine\ORM\QueryBuilder $qb,
        string $search,
        ?string $campus,
        ?string $documentType,
        ?string $status,
        ?string $userRole,
        ?\DateTimeInterface $dateFrom,
        ?\DateTimeInterface $dateTo,
    ): void {
        $search = trim($search);
        if ($search !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('d.campus', ':search'),
                    $qb->expr()->like('d.documentType', ':search'),
                    $qb->expr()->like('d.particulars', ':search'),
                    $qb->expr()->like('d.nature', ':search'),
                    $qb->expr()->like('d.status', ':search'),
                )
            )->setParameter('search', '%'.$search.'%');
        }

        if ($campus !== null && $campus !== '') {
            $qb->andWhere('d.campus = :campus')->setParameter('campus', $campus);
        }

        if ($documentType !== null && $documentType !== '') {
            $qb->andWhere('d.documentType = :documentType')->setParameter('documentType', $documentType);
        }

        if ($status !== null && $status !== '') {
            $qb->andWhere('d.status = :status')->setParameter('status', $status);
        }

        if ($userRole !== null && $userRole !== '') {
            $qb->leftJoin('d.createdBy', 'creator');
            if ($userRole === 'Admin') {
                $qb->andWhere('creator.roles LIKE :adminRole')->setParameter('adminRole', '%ROLE_ADMIN%');
            } elseif ($userRole === 'Staff') {
                $qb->andWhere('creator.roles LIKE :staffRole')
                    ->andWhere('creator.roles NOT LIKE :excludeAdmin')
                    ->setParameter('staffRole', '%ROLE_STAFF%')
                    ->setParameter('excludeAdmin', '%ROLE_ADMIN%');
            }
        }

        if ($dateFrom instanceof \DateTimeInterface) {
            $qb->andWhere('d.dateApproved >= :dateFrom')->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo instanceof \DateTimeInterface) {
            $qb->andWhere('d.dateApproved <= :dateTo')->setParameter('dateTo', $dateTo);
        }
    }
}
