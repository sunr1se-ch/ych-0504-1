<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Booking;
use App\Entity\FlushSlot;
use App\Entity\ProbeReading;
use App\Entity\Rack;
use App\Entity\Waitlist;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable();

        $racks = $this->createRacks($manager);
        $manager->flush();

        $allSlots = $this->createFlushSlots($manager, $racks, $now);
        $manager->flush();

        $rackASlots = array_values(array_filter(
            $allSlots,
            static fn(FlushSlot $slot): bool => $slot->getRack()?->getName() === 'Rack-A'
        ));

        $activeBooking = $this->createActiveBooking($manager, $rackASlots[0], $now);
        $manager->flush();

        $this->createWaitlistEntries($manager, $rackASlots[1], $now);
        $this->createProbeReadings($manager, $activeBooking, $now);

        $manager->flush();
    }

    /**
     * @return array<int, Rack>
     */
    private function createRacks(ObjectManager $manager): array
    {
        $rackConfigs = [
            ['name' => 'Rack-A', 'volume' => 100.0, 'baseline' => 1500],
            ['name' => 'Rack-B', 'volume' => 80.0, 'baseline' => 1200],
            ['name' => 'Rack-C', 'volume' => 120.0, 'baseline' => 1800],
        ];

        $racks = [];
        foreach ($rackConfigs as $config) {
            $rack = (new Rack())
                ->setName($config['name'])
                ->setVolumeM3($config['volume'])
                ->setBaselineCo2Ppm($config['baseline']);

            $manager->persist($rack);
            $racks[] = $rack;
        }

        return $racks;
    }

    /**
     * @param array<int, Rack> $racks
     * @return array<int, FlushSlot>
     */
    private function createFlushSlots(ObjectManager $manager, array $racks, \DateTimeImmutable $now): array
    {
        $allSlots = [];
        $startTime = $now->modify('+1 hour');

        foreach ($racks as $rack) {
            for ($i = 0; $i < 5; ++$i) {
                $slotStartTime = $startTime->modify(sprintf('+%d minutes', $i * 30));

                $slot = (new FlushSlot())
                    ->setRack($rack)
                    ->setStartTime($slotStartTime)
                    ->setDurationMinutes(20)
                    ->setIsOpen(true);

                $manager->persist($slot);
                $allSlots[] = $slot;
            }
        }

        return $allSlots;
    }

    private function createActiveBooking(ObjectManager $manager, FlushSlot $slot, \DateTimeImmutable $now): Booking
    {
        $booking = (new Booking())
            ->setSlot($slot)
            ->setUserName('John Doe')
            ->setStatus(Booking::STATUS_ACTIVE)
            ->setStartedAt($now);

        $manager->persist($booking);

        return $booking;
    }

    private function createWaitlistEntries(ObjectManager $manager, FlushSlot $slot, \DateTimeImmutable $now): void
    {
        $waitlistConfigs = [
            ['userName' => 'Jane Smith', 'priority' => 1],
            ['userName' => 'Bob Johnson', 'priority' => 2],
        ];

        foreach ($waitlistConfigs as $config) {
            $waitlist = (new Waitlist())
                ->setSlot($slot)
                ->setUserName($config['userName'])
                ->setPriority($config['priority'])
                ->setCreatedAt($now);

            $manager->persist($waitlist);
        }
    }

    private function createProbeReadings(ObjectManager $manager, Booking $booking, \DateTimeImmutable $now): void
    {
        $readings = [
            ['ppm' => 1400, 'offsetMinutes' => 0],
            ['ppm' => 1100, 'offsetMinutes' => 2],
            ['ppm' => 950, 'offsetMinutes' => 4],
        ];

        foreach ($readings as $reading) {
            $probeReading = (new ProbeReading())
                ->setBooking($booking)
                ->setCo2Ppm($reading['ppm'])
                ->setReadAt($now->modify(sprintf('+%d minutes', $reading['offsetMinutes'])));

            $manager->persist($probeReading);
        }
    }
}
