<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Booking;
use App\Repository\BookingRepository;
use App\Repository\FlushSlotRepository;
use App\Repository\RackRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\UX\Turbo\TurboBundle;

#[Route('/admin/slots', name: 'admin_slot_')]
class SlotCalendarController extends AbstractController
{
    public function __construct(
        private readonly RackRepository $rackRepository,
        private readonly FlushSlotRepository $flushSlotRepository,
        private readonly BookingRepository $bookingRepository,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $startDateParam = $request->query->get('startDate');
        $endDateParam = $request->query->get('endDate');

        $today = new \DateTimeImmutable();
        $weekStart = $today->modify('monday this week');
        $weekEnd = $today->modify('sunday this week');

        if ($startDateParam !== null) {
            try {
                $startDate = new \DateTimeImmutable($startDateParam);
            } catch (\Exception) {
                $startDate = $weekStart;
            }
        } else {
            $startDate = $weekStart;
        }

        if ($endDateParam !== null) {
            try {
                $endDate = new \DateTimeImmutable($endDateParam);
            } catch (\Exception) {
                $endDate = $weekEnd;
            }
        } else {
            $endDate = $weekEnd;
        }

        $startDate = $startDate->setTime(0, 0, 0);
        $endDate = $endDate->setTime(23, 59, 59);

        $racks = $this->rackRepository->findAll();
        $slots = $this->flushSlotRepository->findByDateRange($startDate, $endDate);

        $groupedSlots = [];
        foreach ($racks as $rack) {
            $rackId = $rack->getId();
            $groupedSlots[$rackId] = [];

            $currentDate = $startDate;
            while ($currentDate <= $endDate) {
                $dateKey = $currentDate->format('Y-m-d');
                $groupedSlots[$rackId][$dateKey] = [];
                $currentDate = $currentDate->modify('+1 day');
            }
        }

        foreach ($slots as $slot) {
            $rackId = $slot->getRack()->getId();
            $dateKey = $slot->getStartTime()->format('Y-m-d');

            if (isset($groupedSlots[$rackId][$dateKey])) {
                $groupedSlots[$rackId][$dateKey][] = $slot;
            }
        }

        foreach ($groupedSlots as $rackId => $dates) {
            foreach ($dates as $dateKey => $daySlots) {
                usort($daySlots, fn($a, $b) => $a->getStartTime() <=> $b->getStartTime());
                $groupedSlots[$rackId][$dateKey] = $daySlots;
            }
        }

        $days = [];
        $currentDate = $startDate;
        while ($currentDate <= $endDate) {
            $days[] = $currentDate;
            $currentDate = $currentDate->modify('+1 day');
        }

        $prevWeekStart = $startDate->modify('-7 days');
        $nextWeekStart = $startDate->modify('+7 days');
        $prevWeekEnd = $prevWeekStart->modify('+6 days');
        $nextWeekEnd = $nextWeekStart->modify('+6 days');

        return $this->render('admin/slot/index.html.twig', [
            'racks' => $racks,
            'days' => $days,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'groupedSlots' => $groupedSlots,
            'prevWeekStart' => $prevWeekStart,
            'prevWeekEnd' => $prevWeekEnd,
            'nextWeekStart' => $nextWeekStart,
            'nextWeekEnd' => $nextWeekEnd,
        ]);
    }

    #[Route('/{id}/show', name: 'show', methods: ['GET'])]
    public function show(int $id, Request $request): Response
    {
        $slot = $this->flushSlotRepository->find($id);

        if ($slot === null) {
            throw $this->createNotFoundException('Slot not found');
        }

        $booking = $slot->getBooking();
        $probeReadings = [];
        $waitlist = $slot->getWaitlist()->toArray();

        usort($waitlist, fn($a, $b) => $b->getPriority() <=> $a->getPriority() ?: $a->getCreatedAt() <=> $b->getCreatedAt());

        if ($booking !== null) {
            $probeReadings = $this->bookingRepository->findProbeReadingsByBooking($booking->getId());
        }

        $co2Data = [];
        $maxCo2 = 0;
        foreach ($probeReadings as $reading) {
            $co2 = $reading->getCo2Ppm();
            $co2Data[] = [
                'time' => $reading->getReadAt()->format('H:i'),
                'value' => $co2,
            ];
            if ($co2 > $maxCo2) {
                $maxCo2 = $co2;
            }
        }

        $template = 'admin/slot/show.html.twig';
        $context = [
            'slot' => $slot,
            'booking' => $booking,
            'probeReadings' => $probeReadings,
            'co2Data' => $co2Data,
            'maxCo2' => $maxCo2,
            'waitlist' => $waitlist,
        ];

        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            return $this->renderBlock($template, 'slot_content', $context);
        }

        return $this->render($template, $context);
    }
}
