<?php

namespace App\Support;

class PageWindow
{
    public const SIZE = 7;

    /**
     * @return array{pages: list<int>, hasStartEllipsis: bool, hasEndEllipsis: bool, current: int, last: int}
     */
    public static function make(int $current, int $last, int $size = self::SIZE): array
    {
        $last = max(1, $last);
        $current = max(1, min($current, $last));
        if ($last <= $size) {
            return [
                'pages' => range(1, $last),
                'hasStartEllipsis' => false,
                'hasEndEllipsis' => false,
                'current' => $current,
                'last' => $last,
            ];
        }

        $start = $current - intdiv($size, 2);
        $end = $start + $size - 1;
        if ($start < 1) {
            $start = 1;
            $end = $size;
        }
        if ($end > $last) {
            $end = $last;
            $start = $last - $size + 1;
        }

        return [
            'pages' => range($start, $end),
            'hasStartEllipsis' => $start > 1,
            'hasEndEllipsis' => $end < $last,
            'current' => $current,
            'last' => $last,
        ];
    }

    public static function perPage(mixed $requested, int $default = 10): int
    {
        $value = (int) $requested;

        return in_array($value, [5, 10, 25, 50], true) ? $value : $default;
    }

    public static function buttonHtml(int $current, int $last, string $attr = 'data-dash-page'): string
    {
        $window = self::make($current, $last);
        $html = $current <= 1
            ? '<span class="page-number disabled">‹</span>'
            : '<button type="button" class="page-number" '.$attr.'="'.($current - 1).'">‹</button>';
        if ($window['hasStartEllipsis']) {
            $html .= '<span class="pager-ellipsis">...</span>';
        }
        foreach ($window['pages'] as $page) {
            if ($page === $current) {
                $html .= '<button type="button" class="page-number active" '.$attr.'="'.$page.'">'.$page.'</button>';
            } else {
                $html .= '<button type="button" class="page-number" '.$attr.'="'.$page.'">'.$page.'</button>';
            }
        }
        if ($window['hasEndEllipsis']) {
            $html .= '<span class="pager-ellipsis">...</span>';
        }
        $html .= $current >= $last
            ? '<span class="page-number disabled">›</span>'
            : '<button type="button" class="page-number" '.$attr.'="'.($current + 1).'">›</button>';

        return $html;
    }
}
