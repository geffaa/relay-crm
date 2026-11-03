<?php

namespace Tests\Unit;

use App\Support\Phone;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    private Phone $phone;

    protected function setUp(): void
    {
        $this->phone = new Phone('ID');
    }

    public function test_local_indonesian_format_normalizes_to_e164(): void
    {
        $this->assertSame('+6281234567890', $this->phone->toE164('0812-3456-7890'));
        $this->assertSame('+6281234567890', $this->phone->toE164('081234567890'));
    }

    public function test_plus_prefixed_numbers_are_kept_normalized(): void
    {
        $this->assertSame('+6281234567890', $this->phone->toE164('+62 812 3456 7890'));
        $this->assertSame('+447911123456', $this->phone->toE164('+44 7911 123456'));
    }

    public function test_double_zero_international_prefix(): void
    {
        $this->assertSame('+6281234567890', $this->phone->toE164('0062 812 3456 7890'));
    }

    public function test_invalid_numbers_return_null(): void
    {
        $this->assertNull($this->phone->toE164('12345'));
        $this->assertNull($this->phone->toE164('not a phone'));
        $this->assertNull($this->phone->toE164(''));
        $this->assertNull($this->phone->toE164(null));
    }

    public function test_whitespaces_and_dashes_are_ignored(): void
    {
        $this->assertSame($this->phone->toE164('081234567890'), $this->phone->toE164(' 0812 3456-7890 '));
    }
}
