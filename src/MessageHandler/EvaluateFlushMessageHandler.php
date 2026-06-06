<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Booking;
use App\Entity\ProbeReading;
use App\Message\EvaluateFlushMessage;
use App\Repository\BookingRepository;
use App\Service\FlushSlotManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsMessageHandler]
final readonly class EvaluateFlushMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BookingRepository $bookingRepository,
        private FlushSlotManager $flushSlotManager,
        private LoggerInterface $logger,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(EvaluateFlushMessage $message): void
    {
        $this->entityManager->wrapInTransaction(function () use ($message): void {
            $bookingId = $message->getBookingId();
            $evaluationCount = $message->getEvaluationCount();

            $this->logger->info('Starting flush evaluation', [
                'booking_id' => $bookingId,
                'evaluation_count' => $evaluationCount,
            ]);

            $booking = $this->bookingRepository->find($bookingId);
            if (null === $booking) {
                $this->logger->warning('Booking not found, skipping evaluation', [
                    'booking_id' => $bookingId,
                ]);

                return;
            }

            if (Booking::STATUS_ACTIVE !== $booking->getStatus()) {
                $this->logger->info('Booking is not active, skipping evaluation (idempotency check)', [
                    'booking_id' => $bookingId,
                    'status' => $booking->getStatus(),
                ]);

                return;
            }

            $slot = $booking->getSlot();
            if (null === $slot) {
                $this->logger->error('Booking has no associated slot', [
                    'booking_id' => $bookingId,
                ]);

                return;
            }

            $rack = $slot->getRack();
            if (null === $rack) {
                $this->logger->error('Slot has no associated rack', [
                    'booking_id' => $bookingId,
                    'slot_id' => $slot->getId(),
                ]);

                return;
            }

            $baseline = $rack->getBaselineCo2Ppm();
            if (null === $baseline) {
                $this->logger->error('Rack has no baseline CO2 ppm', [
                    'booking_id' => $bookingId,
                    'rack_id' => $rack->getId(),
                ]);

                return;
            }

            $co2Ppm = (int) ($baseline * (0.9 - 0.03 * $evaluationCount) + random_int(-10, 10));
            $co2Ppm = max(0, $co2Ppm);

            $probeReading = new ProbeReading();
            $probeReading->setBooking($booking);
            $probeReading->setCo2Ppm($co2Ppm);
            $probeReading->setReadAt(new \DateTimeImmutable());

            $this->entityManager->persist($probeReading);
            $this->entityManager->flush();

            $this->logger->info('Probe reading created', [
                'booking_id' => $bookingId,
                'co2_ppm' => $co2Ppm,
                'evaluation_count' => $evaluationCount,
            ]);

            $allReadings = $this->entityManager
                ->getRepository(ProbeReading::class)
                ->findBy(['booking' => $booking], ['readAt' => 'ASC']);

            $targetPpm = (int) ($baseline * 0.6);
            $booking->setTargetCo2Ppm((float) $targetPpm);

            $latestReading = end($allReadings);
            if ($latestReading instanceof ProbeReading && $latestReading->getCo2Ppm() <= $targetPpm) {
                $booking->setStatus(Booking::STATUS_DONE);
                $booking->setCompletedAt(new \DateTimeImmutable());
                $slot->setIsOpen(true);

                $this->entityManager->flush();

                $this->logger->info('Booking marked as done - target CO2 level reached', [
                    'booking_id' => $bookingId,
                    'co2_ppm' => $latestReading->getCo2Ppm(),
                    'target_ppm' => $targetPpm,
                ]);

                return;
            }

            $startTime = $booking->getValveOpenedAt() ?? $booking->getStartedAt();
            if (null === $startTime) {
                $this->logger->error('Booking has no start time', [
                    'booking_id' => $bookingId,
                ]);

                return;
            }

            $now = new \DateTimeImmutable();
            $interval = $now->getTimestamp() - $startTime->getTimestamp();

            if ($interval > 1200) {
                $booking->setStatus(Booking::STATUS_FAILED);
                $booking->setCompletedAt($now);

                $this->logger->warning('Booking failed - timeout exceeded 20 minutes', [
                    'booking_id' => $bookingId,
                    'elapsed_seconds' => $interval,
                ]);

                $this->entityManager->flush();

                $promoted = $this->flushSlotManager->promoteFromWaitlist($slot);

                if (null === $promoted) {
                    $slot->setIsOpen(true);
                    $this->entityManager->flush();
                } else {
                    $this->logger->info('New booking created from waitlist and evaluation dispatched', [
                        'new_booking_id' => $promoted->getId(),
                        'user_name' => $promoted->getUserName(),
                    ]);
                }

                return;
            }

            $this->logger->info('Rescheduling evaluation', [
                'booking_id' => $bookingId,
                'evaluation_count' => $evaluationCount,
                'next_evaluation_count' => $evaluationCount + 1,
                'elapsed_seconds' => $interval,
            ]);

            $this->messageBus->dispatch(
                new EvaluateFlushMessage($bookingId, $evaluationCount + 1),
                [new DelayStamp(120000)]
            );
        });
    }
}
