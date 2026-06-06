<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Waitlist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Waitlist>
 */
class WaitlistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Waitlist::class);
    }

    public function save(Waitlist $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Waitlist $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findMaxPriorityBySlot(int $slotId): int
    {
        $result = $this->createQueryBuilder('w')
            ->select('MAX(w.priority) as maxPriority')
            ->where('w.slot = :slotId')
            ->setParameter('slotId', $slotId)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    public function findNextForPromotion(int $slotId): ?Waitlist
    {
        return $this->findOneBy(
            ['slot' => $slotId],
            ['priority' => 'ASC', 'createdAt' => 'DESC']
        );
    }
}
