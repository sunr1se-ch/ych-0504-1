<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class DateTimeNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public const string FORMAT = 'Y-m-d\TH:i:sP';

    public function normalize(mixed $object, string $format = null, array $context = []): string
    {
        return $object->format(self::FORMAT);
    }

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return $data instanceof DateTimeImmutable;
    }

    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat(self::FORMAT, (string) $data) ?: new DateTimeImmutable((string) $data);
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return $type === DateTimeImmutable::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            DateTimeInterface::class => true,
            DateTimeImmutable::class => true,
        ];
    }
}
