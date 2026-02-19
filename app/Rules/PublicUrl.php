<?php

namespace App\Rules;

use App\Exceptions\UnsafeUrlException;
use App\Services\UrlSafetyService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PublicUrl implements ValidationRule
{
    /**
     * @param  Closure(string): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Invalid URL.');

            return;
        }

        try {
            UrlSafetyService::assertSafeForScanning($value);
        } catch (UnsafeUrlException $e) {
            $fail($e->getMessage());
        }
    }
}

