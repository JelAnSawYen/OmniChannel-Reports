<?php

namespace Tests\Unit;

use App\Support\PageWindow;
use PHPUnit\Framework\TestCase;

class PageWindowTest extends TestCase
{
    public function test_shows_all_pages_when_there_are_seven_or_fewer(): void
    {
        $window = PageWindow::make(1, 4);

        $this->assertSame([1, 2, 3, 4], $window['pages']);
        $this->assertFalse($window['hasStartEllipsis']);
        $this->assertFalse($window['hasEndEllipsis']);
    }

    public function test_starts_with_seven_pages_and_end_ellipsis(): void
    {
        $window = PageWindow::make(1, 20);

        $this->assertSame([1, 2, 3, 4, 5, 6, 7], $window['pages']);
        $this->assertFalse($window['hasStartEllipsis']);
        $this->assertTrue($window['hasEndEllipsis']);
    }

    public function test_shifts_the_seven_page_window_and_uses_ellipsis_on_both_sides(): void
    {
        $window = PageWindow::make(10, 20);

        $this->assertSame([7, 8, 9, 10, 11, 12, 13], $window['pages']);
        $this->assertTrue($window['hasStartEllipsis']);
        $this->assertTrue($window['hasEndEllipsis']);
    }

    public function test_ends_with_seven_pages_and_start_ellipsis(): void
    {
        $window = PageWindow::make(20, 20);

        $this->assertSame([14, 15, 16, 17, 18, 19, 20], $window['pages']);
        $this->assertTrue($window['hasStartEllipsis']);
        $this->assertFalse($window['hasEndEllipsis']);
    }

    public function test_per_page_keeps_only_existing_dropdown_sizes(): void
    {
        $this->assertSame(5, PageWindow::perPage(5));
        $this->assertSame(10, PageWindow::perPage(10));
        $this->assertSame(25, PageWindow::perPage(25));
        $this->assertSame(50, PageWindow::perPage(50));
        $this->assertSame(10, PageWindow::perPage(15));
        $this->assertSame(10, PageWindow::perPage('all'));
    }
}
