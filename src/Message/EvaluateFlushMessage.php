<?php

declare(strict_types=1);

namespace App\Message;

final readonly class EvaluateFlushMessage
{
    public function __construct(
        private int $bookingId,
        private int $evaluationCount = 0,
    ) {
    }

    public function getBookingId(): int
    {
        return $this->bookingId;
    }

    public function getEvaluationCount(): int
    {
        return $this->evaluationCount;
    }
}
