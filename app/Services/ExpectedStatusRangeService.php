<?php

namespace App\Services;

class ExpectedStatusRangeService
{
    /**
     * @return array{0: int, 1: int}
     */
    public function resolve(?int $inputMin, ?int $inputMax, int $fallbackMin, int $fallbackMax): array
    {
        $min = $inputMin ?? $fallbackMin;
        $max = $inputMax ?? $fallbackMax;

        $min = min(599, max(100, $min));
        $max = min(599, max(100, $max));

        if ($min > $max) {
            throw new \InvalidArgumentException('Expected status max must be greater than or equal to expected status min.');
        }

        return [$min, $max];
    }
}
