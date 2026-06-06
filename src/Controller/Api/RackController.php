<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\RackRepository;
use App\Repository\FlushSlotRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/racks', name: 'api_racks_')]
class RackController extends AbstractController
{
    public function __construct(
        private readonly RackRepository $rackRepository,
        private readonly FlushSlotRepository $flushSlotRepository,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $racks = $this->rackRepository->findAll();

        $data = array_map(function ($rack) {
            return [
                'id' => $rack->getId(),
                'name' => $rack->getName(),
                'volumeM3' => $rack->getVolumeM3(),
                'baselineCo2Ppm' => $rack->getBaselineCo2Ppm(),
                'createdAt' => $rack->getCreatedAt()?->format('Y-m-d\TH:i:sP'),
                'slotCount' => $rack->getSlots()->count(),
            ];
        }, $racks);

        return $this->json($data, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $rack = $this->rackRepository->find($id);
        if ($rack === null) {
            return $this->json([
                'error' => 'Rack not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $availableSlots = [];
        foreach ($rack->getSlots() as $slot) {
            if ($slot->isIsOpen() && $slot->getBooking() === null) {
                $availableSlots[] = [
                    'id' => $slot->getId(),
                    'startTime' => $slot->getStartTime()?->format('Y-m-d\TH:i:sP'),
                    'durationMinutes' => $slot->getDurationMinutes(),
                ];
            }
        }

        $data = [
            'id' => $rack->getId(),
            'name' => $rack->getName(),
            'volumeM3' => $rack->getVolumeM3(),
            'baselineCo2Ppm' => $rack->getBaselineCo2Ppm(),
            'createdAt' => $rack->getCreatedAt()?->format('Y-m-d\TH:i:sP'),
            'availableSlots' => $availableSlots,
        ];

        return $this->json($data, Response::HTTP_OK);
    }

    #[Route('/{id}/slots', name: 'slots', methods: ['GET'])]
    public function slots(int $id): JsonResponse
    {
        $rack = $this->rackRepository->find($id);
        if ($rack === null) {
            return $this->json([
                'error' => 'Rack not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $slots = [];
        foreach ($rack->getSlots() as $slot) {
            $booking = $slot->getBooking();
            $bookingStatus = null;
            if ($booking !== null) {
                $bookingStatus = [
                    'id' => $booking->getId(),
                    'status' => $booking->getStatus(),
                    'userName' => $booking->getUserName(),
                    'targetCo2Ppm' => $booking->getTargetCo2Ppm(),
                    'createdAt' => $booking->getCreatedAt()?->format('Y-m-d\TH:i:sP'),
                ];
            }

            $slots[] = [
                'id' => $slot->getId(),
                'startTime' => $slot->getStartTime()?->format('Y-m-d\TH:i:sP'),
                'durationMinutes' => $slot->getDurationMinutes(),
                'isOpen' => $slot->isIsOpen(),
                'booking' => $bookingStatus,
                'waitlistCount' => $slot->getWaitlist()->count(),
            ];
        }

        return $this->json($slots, Response::HTTP_OK);
    }
}
