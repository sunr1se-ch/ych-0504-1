<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\BookRackRequest;
use App\DTO\BookRackResponse;
use App\Entity\Booking;
use App\Entity\Waitlist;
use App\Repository\BookingRepository;
use App\Repository\FlushSlotRepository;
use App\Repository\RackRepository;
use App\Repository\WaitlistRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/racks')]
class RackBookingController extends AbstractController
{
    public function __construct(
        private readonly RackRepository $rackRepository,
        private readonly FlushSlotRepository $flushSlotRepository,
        private readonly BookingRepository $bookingRepository,
        private readonly WaitlistRepository $waitlistRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly SerializerInterface $serializer,
    ) {
    }

    #[Route('/{id}/book', name: 'api_rack_book', methods: ['POST'])]
    public function book(int $id, Request $request): JsonResponse
    {
        try {
            $bookRackRequest = $this->serializer->deserialize(
                $request->getContent(),
                BookRackRequest::class,
                'json'
            );

            $errors = $this->validator->validate($bookRackRequest);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[$error->getPropertyPath()] = $error->getMessage();
                }
                return $this->json([
                    'error' => 'Validation failed',
                    'details' => $errorMessages,
                ], Response::HTTP_BAD_REQUEST);
            }

            $rack = $this->rackRepository->find($id);
            if ($rack === null) {
                return $this->json([
                    'error' => 'Rack not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $slot = $this->flushSlotRepository->find($bookRackRequest->getSlotId());
            if ($slot === null || $slot->getRack()?->getId() !== $rack->getId()) {
                return $this->json([
                    'error' => 'Slot not found or does not belong to this rack',
                ], Response::HTTP_NOT_FOUND);
            }

            if (!$slot->isIsOpen()) {
                return $this->json([
                    'error' => 'Slot is not open for booking',
                ], Response::HTTP_CONFLICT);
            }

            $existingBooking = $slot->getBooking();
            if ($existingBooking !== null && in_array(
                $existingBooking->getStatus(),
                [Booking::STATUS_PENDING, Booking::STATUS_ACTIVE, Booking::STATUS_DONE],
                true
            )) {
                return $this->json([
                    'error' => 'Slot already has an active or completed booking',
                ], Response::HTTP_CONFLICT);
            }

            $waitlistEntries = $slot->getWaitlist();
            if ($waitlistEntries->count() > 0) {
                $maxPriority = $this->waitlistRepository->findMaxPriorityBySlot($slot->getId());
                $nextPriority = $maxPriority > 0 ? $maxPriority - 1 : 1;

                $waitlist = new Waitlist();
                $waitlist->setSlot($slot);
                $waitlist->setUserName($bookRackRequest->getUserName());
                $waitlist->setPriority($nextPriority);

                $this->waitlistRepository->save($waitlist, true);

                $response = new BookRackResponse();
                $response->setBookingId(null);
                $response->setStatus('waitlisted');
                $response->setSlotId($slot->getId());
                $response->setUserName($bookRackRequest->getUserName());
                $response->setMessage(sprintf('Added to waitlist with priority %d', $nextPriority));

                return $this->json($response, Response::HTTP_ACCEPTED, [], [
                    'groups' => ['booking:read'],
                ]);
            }

            $booking = new Booking();
            $booking->setSlot($slot);
            $booking->setStatus(Booking::STATUS_PENDING);
            $booking->setUserName($bookRackRequest->getUserName());
            $booking->setTargetCo2Ppm((float) $rack->getBaselineCo2Ppm() * 0.6);

            $slot->setIsOpen(false);

            $this->entityManager->persist($booking);
            $this->entityManager->persist($slot);
            $this->entityManager->flush();

            $response = new BookRackResponse();
            $response->setBookingId($booking->getId());
            $response->setStatus($booking->getStatus());
            $response->setSlotId($slot->getId());
            $response->setUserName($bookRackRequest->getUserName());
            $response->setMessage('Booking created successfully');

            return $this->json($response, Response::HTTP_CREATED, [], [
                'groups' => ['booking:read'],
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
