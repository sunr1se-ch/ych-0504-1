<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProbeReading;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProbeReading>
 */
class ProbeReadingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProbeReading::class);
    }

    public function save(ProbeReading $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ProbeReading $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findLatestByBooking(int $bookingId): ?ProbeReading
    {
        return $this->createQueryBuilder('p')
            ->where('p.booking = :bookingId')
            ->setParameter('bookingId', $bookingId)
            ->orderBy('p.readAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
