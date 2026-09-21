<?php

namespace Tests\Unit;

use App\Support\NaturalSort;
use PHPUnit\Framework\TestCase;

class NaturalSortTest extends TestCase
{
    public function test_padded_keys_put_globe10_after_globe03(): void
    {
        $names = ['GLOBE10', 'GLOBE02', 'GLOBE03', 'GLOBE01'];
        usort($names, [NaturalSort::class, 'compare']);

        $this->assertSame(['GLOBE01', 'GLOBE02', 'GLOBE03', 'GLOBE10'], $names);
        $this->assertSame('globe0000000001', NaturalSort::key('GLOBE01'));
        $this->assertSame('globe0000000010', NaturalSort::key('GLOBE10'));
        $this->assertSame('sip_atome_0000000001', NaturalSort::key('SIP_ATOME_01'));
        $this->assertSame('sip_atome_0000000010', NaturalSort::key('SIP_ATOME_10'));
    }
}
