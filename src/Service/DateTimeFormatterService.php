<?php

namespace App\Service;

class DateTimeFormatterService
{
    public function __construct(
        private readonly string $timezone = 'Asia/Manila',
    ) {
    }

    public function getTimezone(): \DateTimeZone
    {
        return new \DateTimeZone($this->timezone);
    }

    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', $this->getTimezone());
    }

    public function format(?\DateTimeInterface $dateTime, bool $includeSeconds = true): string
    {
        if (!$dateTime instanceof \DateTimeInterface) {
            return '—';
        }

        $converted = \DateTimeImmutable::createFromInterface($dateTime)->setTimezone($this->getTimezone());
        $timeFormat = $includeSeconds ? 'h:i:s A' : 'h:i A';

        return $converted->format('F d, Y').' | '.$converted->format($timeFormat);
    }

    /**
     * @return array{timestamp: int, formatted: string, timezone: string}
     */
    public function getServerTimePayload(): array
    {
        $now = $this->now();

        return [
            'timestamp' => $now->getTimestamp(),
            'formatted' => $this->format($now),
            'timezone' => $this->timezone,
        ];
    }
}
