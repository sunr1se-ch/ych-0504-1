<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use App\Entity\FlushSlot;
use App\Entity\Waitlist;
use App\Message\EvaluateFlushMessage;
use App\Repository\WaitlistRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class FlushSlotManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WaitlistRepository $waitlistRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function openValve(Booking $booking): void
    {
        $this->logger->info('Opening valve for booking', [
            'booking_id' => $booking->getId(),
        ]);

        $booking->setValveOpenedAt(new \DateTimeImmutable());
        $booking->setStatus(Booking::STATUS_ACTIVE);

        $slot = $booking->getSlot();
        if (null !== $slot) {
            $slot->setIsOpen(false);
        }

        $this->entityManager->flush();

        $this->messageBus->dispatch(new EvaluateFlushMessage($booking->getId()));

        $this->logger->info('Valve opened and first evaluation dispatched', [
            'booking_id' => $booking->getId(),
        ]);
    }

    public function cancelBooking(Booking $booking): void
    {
        $this->logger->info('Cancelling booking', [
            'booking_id' => $booking->getId(),
            'current_status' => $booking->getStatus(),
        ]);

        $booking->setStatus(Booking::STATUS_FAILED);
        $booking->setCompletedAt(new \DateTimeImmutable());

        $slot = $booking->getSlot();
        if (null !== $slot) {
            $this->promoteFromWaitlist($slot);
        }

        $this->entityManager->flush();

        $this->logger->info('Booking cancelled', [
            'booking_id' => $booking->getId(),
        ]);
    }

    public function promoteFromWaitlist(FlushSlot $slot): ?Booking
    {
        $waitlist = $this->waitlistRepository->findNextForPromotion($slot->getId());

        if (null === $waitlist) {
            $this->logger->info('No waitlist entries found for slot', [
                'slot_id' => $slot->getId(),
            ]);

            $slot->setIsOpen(true);

            return null;
        }

        $this->logger->info('Promoting waitlist entry to booking', [
            'slot_id' => $slot->getId(),
            'waitlist_id' => $waitlist->getId(),
            'user_name' => $waitlist->getUserName(),
        ]);

        $newBooking = new Booking();
        $newBooking->setUserName($waitlist->getUserName() ?? '');
        $newBooking->setStatus(Booking::STATUS_PENDING);

        $this->entityManager->persist($newBooking);
        $this->entityManager->remove($waitlist);
        $slot->setIsOpen(false);

        $newBooking->setSlot($slot);

        $this->messageBus->dispatch(new EvaluateFlushMessage($newBooking->getId()));

        $this->logger->info('New booking created from waitlist', [
            'slot_id' => $slot->getId(),
            'new_booking_id' => $newBooking->getId(),
            'user_name' => $newBooking->getUserName(),
        ]);

        return $newBooking;
    }
}
