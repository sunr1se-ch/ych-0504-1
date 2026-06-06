<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Booking;
use App\Repository\BookingRepository;
use App\Service\FlushSlotManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class BookingEventSubscriber implements EventSubscriberInterface
{
    private bool $processed = false;

    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly FlushSlotManager $flushSlotManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'onKernelTerminate',
        ];
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if ($this->processed) {
            return;
        }

        $this->processed = true;

        $now = new \DateTimeImmutable();

        $bookings = $this->bookingRepository->createQueryBuilder('b')
            ->join('b.slot', 's')
            ->where('b.status = :status')
            ->andWhere('s.startTime <= :now')
            ->andWhere('b.startedAt IS NULL')
            ->andWhere('NOT EXISTS (
                SELECT 1 FROM App\Entity\Booking prev
                WHERE prev.slot = s AND prev.status = :failedStatus AND prev.id != b.id
            )')
            ->setParameter('status', Booking::STATUS_PENDING)
            ->setParameter('failedStatus', Booking::STATUS_FAILED)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        foreach ($bookings as $booking) {
            if (!$booking instanceof Booking) {
                continue;
            }

            $this->logger->info('Booking start time reached, opening valve', [
                'booking_id' => $booking->getId(),
                'user_name' => $booking->getUserName(),
            ]);

            $booking->setStartedAt($now);
            $this->entityManager->flush();

            $this->flushSlotManager->openValve($booking);
        }
    }
}
