<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Unit\Infrastructure\Media;

use Innis\Hubstr\Blossom\Infrastructure\Media\ImagePixelBudget;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ImagePixelBudgetTest extends TestCase
{
    public function testAdmitsImagesWithinTheBudget(): void
    {
        $budget = new ImagePixelBudget(1_000_000);

        self::assertTrue($budget->admits(1000, 1000));
        self::assertTrue($budget->admits(1, 1));
    }

    public function testRejectsImagesOverTheBudget(): void
    {
        $budget = new ImagePixelBudget(1_000_000);

        self::assertFalse($budget->admits(1001, 1000));
        self::assertFalse($budget->admits(30_000, 30_000));
    }

    public function testRejectsDegenerateDimensions(): void
    {
        $budget = new ImagePixelBudget(1_000_000);

        self::assertFalse($budget->admits(0, 500));
        self::assertFalse($budget->admits(500, 0));
        self::assertFalse($budget->admits(-1, -1));
    }

    public function testRejectsANonPositiveBudget(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ImagePixelBudget(0);
    }
}
