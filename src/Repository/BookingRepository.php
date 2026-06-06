<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Booking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    public function save(Booking $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Booking $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return array<int, \App\Entity\ProbeReading>
     */
    public function findProbeReadingsByBooking(int $bookingId): array
    {
        return $this->createQueryBuilder('b')
            ->select('p')
            ->join('b.probeReadings', 'p')
            ->where('b.id = :bookingId')
            ->setParameter('bookingId', $bookingId)
            ->orderBy('p.readAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
