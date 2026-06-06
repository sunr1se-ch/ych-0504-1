<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FlushSlotRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FlushSlotRepository::class)]
class FlushSlot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'slots')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Rack $rack = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $startTime = null;

    #[ORM\Column]
    private ?int $durationMinutes = null;

    #[ORM\Column]
    private bool $isOpen = true;

    #[ORM\OneToOne(mappedBy: 'slot', cascade: ['persist', 'remove'])]
    private ?Booking $booking = null;

    /**
     * @var Collection<int, Waitlist>
     */
    #[ORM\OneToMany(targetEntity: Waitlist::class, mappedBy: 'slot')]
    private Collection $waitlist;

    public function __construct()
    {
        $this->waitlist = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRack(): ?Rack
    {
        return $this->rack;
    }

    public function setRack(?Rack $rack): static
    {
        $this->rack = $rack;

        return $this;
    }

    public function getStartTime(): ?\DateTimeImmutable
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeImmutable $startTime): static
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(int $durationMinutes): static
    {
        $this->durationMinutes = $durationMinutes;

        return $this;
    }

    public function isIsOpen(): ?bool
    {
        return $this->isOpen;
    }

    public function setIsOpen(bool $isOpen): static
    {
        $this->isOpen = $isOpen;

        return $this;
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(?Booking $booking): static
    {
        if ($booking === null && $this->booking !== null) {
            $this->booking->setSlot(null);
        }

        if ($booking !== null && $booking->getSlot() !== $this) {
            $booking->setSlot($this);
        }

        $this->booking = $booking;

        return $this;
    }

    /**
     * @return Collection<int, Waitlist>
     */
    public function getWaitlist(): Collection
    {
        return $this->waitlist;
    }

    public function addWaitlist(Waitlist $waitlist): static
    {
        if (!$this->waitlist->contains($waitlist)) {
            $this->waitlist->add($waitlist);
            $waitlist->setSlot($this);
        }

        return $this;
    }

    public function removeWaitlist(Waitlist $waitlist): static
    {
        if ($this->waitlist->removeElement($waitlist)) {
            if ($waitlist->getSlot() === $this) {
                $waitlist->setSlot(null);
            }
        }

        return $this;
    }
}
