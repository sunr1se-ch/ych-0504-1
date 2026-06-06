<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

class BookRackRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 100)]
    private ?string $userName = null;

    #[Assert\NotNull]
    #[Assert\Positive]
    private ?int $slotId = null;

    #[Assert\NotNull]
    private ?DateTimeImmutable $startTime = null;

    #[Assert\NotNull]
    #[Assert\Min(5)]
    #[Assert\Max(120)]
    private ?int $durationMinutes = null;

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function setUserName(?string $userName): self
    {
        $this->userName = $userName;
        return $this;
    }

    public function getSlotId(): ?int
    {
        return $this->slotId;
    }

    public function setSlotId(?int $slotId): self
    {
        $this->slotId = $slotId;
        return $this;
    }

    public function getStartTime(): ?DateTimeImmutable
    {
        return $this->startTime;
    }

    public function setStartTime(?DateTimeImmutable $startTime): self
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(?int $durationMinutes): self
    {
        $this->durationMinutes = $durationMinutes;
        return $this;
    }
}
