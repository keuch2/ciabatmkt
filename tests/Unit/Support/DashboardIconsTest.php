<?php

namespace Tests\Unit\Support;

use App\Support\DashboardIcons;
use PHPUnit\Framework\TestCase;

class DashboardIconsTest extends TestCase
{
    public function test_php_catalog_matches_the_frontend_icon_set(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3).'/resources/js/ui/icons.tsx');
        preg_match('/export const ICON_KEYS = \[(.*?)\] as const;/s', $source, $m);
        preg_match_all("/'([a-z-]+)'/", $m[1], $keys);

        $this->assertSame(DashboardIcons::KEYS, $keys[1]);
    }
}
