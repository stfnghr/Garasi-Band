<?php

namespace Tests\Unit;

use App\Enums\GarageType;
use App\Services\GarageCodeGenerator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit test generator ID garasi (Blackbox), skenario C.5.
 */
class GarageCodeGeneratorTest extends TestCase
{
    private GarageCodeGenerator $gen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gen = new GarageCodeGenerator;
    }

    #[Test]
    public function ut_35_format_prefix_dan_tiga_digit(): void
    {
        $this->assertSame('S001', $this->gen->format(GarageType::Sepeda, 1));
        $this->assertSame('C010', $this->gen->format(GarageType::Mobil, 10));
        $this->assertSame('E999', $this->gen->format(GarageType::MobilListrik, 999));
    }

    #[Test]
    public function nomor_urut_melanjutkan_nomor_terakhir(): void
    {
        $this->assertSame([1, 2, 3], $this->gen->sequencesAfter(0, 3));
        $this->assertSame([10, 11], $this->gen->sequencesAfter(9, 2));
        $this->assertSame([999], $this->gen->sequencesAfter(998, 1));
    }

    #[Test]
    public function nomor_urut_lewat_999_atau_jumlah_nol_ditolak(): void
    {
        foreach ([[998, 2], [0, 1000], [0, 0]] as [$last, $count]) {
            try {
                $this->gen->sequencesAfter($last, $count);
                $this->fail("last={$last}, count={$count} seharusnya ditolak");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
