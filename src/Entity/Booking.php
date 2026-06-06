<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BookingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Booking
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DONE = 'done';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'booking', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?FlushSlot $slot = null;

    #[ORM\Column(length: 20, options: ['default' => self::STATUS_PENDING, 'enum' => [self::STATUS_PENDING, self::STATUS_ACTIVE, self::STATUS_FAILED, self::STATUS_DONE]])]
    private ?string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 100)]
    private ?string $userName = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $valveOpenedAt = null;

    #[ORM\Column(nullable: true)]
    private ?float $target_co2_ppm = null;

    /**
     * @var Collection<int, ProbeReading>
     */
    #[ORM\OneToMany(targetEntity: ProbeReading::class, mappedBy: 'booking')]
    private Collection $probeReadings;

    public function __construct()
    {
        $this->probeReadings = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlot(): ?FlushSlot
    {
        return $this->slot;
    }

    public function setSlot(?FlushSlot $slot): static
    {
        $this->slot = $slot;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        if (!in_array($status, [self::STATUS_PENDING, self::STATUS_ACTIVE, self::STATUS_FAILED, self::STATUS_DONE], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid status: %s', $status));
        }

        $this->status = $status;

        return $this;
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function setUserName(string $userName): static
    {
        $this->userName = $userName;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    public function getValveOpenedAt(): ?\DateTimeImmutable
    {
        return $this->valveOpenedAt;
    }

    public function setValveOpenedAt(?\DateTimeImmutable $valveOpenedAt): static
    {
        $this->valveOpenedAt = $valveOpenedAt;

        return $this;
    }

    public function getTargetCo2Ppm(): ?float
    {
        return $this->target_co2_ppm;
    }

    public function setTargetCo2Ppm(?float $target_co2_ppm): static
    {
        $this->target_co2_ppm = $target_co2_ppm;

        return $this;
    }

    /**
     * @return Collection<int, ProbeReading>
     */
    public function getProbeReadings(): Collection
    {
        return $this->probeReadings;
    }

    public function addProbeReading(ProbeReading $probeReading): static
    {
        if (!$this->probeReadings->contains($probeReading)) {
            $this->probeReadings->add($probeReading);
            $probeReading->setBooking($this);
        }

        return $this;
    }

    public function removeProbeReading(ProbeReading $probeReading): static
    {
        if ($this->probeReadings->removeElement($probeReading)) {
            if ($probeReading->getBooking() === $this) {
                $probeReading->setBooking(null);
            }
        }

        return $this;
    }
}
