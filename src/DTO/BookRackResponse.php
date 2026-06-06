<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Serializer\Annotation\Groups;

class BookRackResponse
{
    #[Groups(['booking:read'])]
    private ?int $bookingId = null;

    #[Groups(['booking:read'])]
    private ?string $status = null;

    #[Groups(['booking:read'])]
    private ?int $slotId = null;

    #[Groups(['booking:read'])]
    private ?string $userName = null;

    #[Groups(['booking:read'])]
    private ?string $message = null;

    public function getBookingId(): ?int
    {
        return $this->bookingId;
    }

    public function setBookingId(?int $bookingId): self
    {
        $this->bookingId = $bookingId;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;
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

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function setUserName(?string $userName): self
    {
        $this->userName = $userName;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }
}
