<?php

namespace App\Support;

use Propaganistas\LaravelPhone\PhoneNumber;

class SyrianPhone
{
    public static function e164(string $number): string
    {
        return (new PhoneNumber($number, 'SY'))->formatE164();
    }

    public static function telUri(string $number): string
    {
        return 'tel:'.self::e164($number);
    }
}
