<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Booking;
use App\Repository\BookingRepository;
use App\Repository\FlushSlotRepository;
use App\Repository\ProbeReadingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class SlotEventController extends AbstractController
{
    public function __construct(
        private readonly FlushSlotRepository $flushSlotRepository,
        private readonly BookingRepository $bookingRepository,
        private readonly ProbeReadingRepository $probeReadingRepository,
        private readonly SerializerInterface $serializer,
        private readonly ?HubInterface $mercureHub = null,
    ) {
    }

    #[Route('/api/slots/{id}/events', name: 'api_slot_events', methods: ['GET'])]
    public function events(int $id, Request $request): StreamedResponse
    {
        $slot = $this->flushSlotRepository->find($id);
        if ($slot === null) {
            return new StreamedResponse(function () {
                echo json_encode(['error' => 'Slot not found'], JSON_THROW_ON_ERROR);
            }, 404, [
                'Content-Type' => 'application/json',
            ]);
        }

        $response = new StreamedResponse(function () use ($id) {
            $startTime = time();
            $maxExecutionTime = 300;

            ob_start();

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                if (time() - $startTime >= $maxExecutionTime) {
                    break;
                }

                $this->flushSlotRepository->getEntityManager()->refresh(
                    $slot = $this->flushSlotRepository->find($id)
                );

                $booking = $slot->getBooking();
                $bookingData = null;
                if ($booking !== null) {
                    $this->flushSlotRepository->getEntityManager()->refresh($booking);
                    $bookingData = [
                        'id' => $booking->getId(),
                        'status' => $booking->getStatus(),
                        'userName' => $booking->getUserName(),
                        'targetCo2Ppm' => $booking->getTargetCo2Ppm(),
                        'startedAt' => $booking->getStartedAt()?->format('Y-m-d\TH:i:sP'),
                        'completedAt' => $booking->getCompletedAt()?->format('Y-m-d\TH:i:sP'),
                    ];
                }

                $latestReading = null;
                if ($booking !== null) {
                    $reading = $this->probeReadingRepository->findLatestByBooking($booking->getId());
                    if ($reading !== null) {
                        $latestReading = [
                            'co2Ppm' => $reading->getCo2Ppm(),
                            'readAt' => $reading->getReadAt()?->format('Y-m-d\TH:i:sP'),
                        ];
                    }
                }

                $waitlistCount = $slot->getWaitlist()->count();

                $eventData = [
                    'slotId' => $slot->getId(),
                    'isOpen' => $slot->isIsOpen(),
                    'booking' => $bookingData,
                    'latestCo2Reading' => $latestReading,
                    'occupancy' => [
                        'isOccupied' => $booking !== null && in_array(
                            $booking->getStatus(),
                            [Booking::STATUS_ACTIVE, Booking::STATUS_PENDING],
                            true
                        ),
                        'waitlistCount' => $waitlistCount,
                    ],
                    'timestamp' => date('Y-m-d\TH:i:sP'),
                ];

                echo "event: update\n";
                echo 'data: ' . json_encode($eventData, JSON_THROW_ON_ERROR) . "\n\n";

                ob_flush();
                flush();

                sleep(2);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
