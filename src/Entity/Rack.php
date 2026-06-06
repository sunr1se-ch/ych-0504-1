<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RackRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RackRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Rack
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column]
    private ?float $volume_m3 = null;

    #[ORM\Column]
    private ?int $baseline_co2_ppm = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, FlushSlot>
     */
    #[ORM\OneToMany(targetEntity: FlushSlot::class, mappedBy: 'rack')]
    private Collection $slots;

    public function __construct()
    {
        $this->slots = new ArrayCollection();
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getVolumeM3(): ?float
    {
        return $this->volume_m3;
    }

    public function setVolumeM3(float $volume_m3): static
    {
        $this->volume_m3 = $volume_m3;

        return $this;
    }

    public function getBaselineCo2Ppm(): ?int
    {
        return $this->baseline_co2_ppm;
    }

    public function setBaselineCo2Ppm(int $baseline_co2_ppm): static
    {
        $this->baseline_co2_ppm = $baseline_co2_ppm;

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

    /**
     * @return Collection<int, FlushSlot>
     */
    public function getSlots(): Collection
    {
        return $this->slots;
    }

    public function addSlot(FlushSlot $slot): static
    {
        if (!$this->slots->contains($slot)) {
            $this->slots->add($slot);
            $slot->setRack($this);
        }

        return $this;
    }

    public function removeSlot(FlushSlot $slot): static
    {
        if ($this->slots->removeElement($slot)) {
            if ($slot->getRack() === $this) {
                $slot->setRack(null);
            }
        }

        return $this;
    }
}
