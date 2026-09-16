<?php

namespace App\Support;

/**
 * Catálogo de íconos disponibles para el menú. Debe coincidir con ICON_KEYS de
 * resources/js/ui/icons.tsx (hay un test que compara ambas listas).
 */
final class DashboardIcons
{
    public const KEYS = [
        'chart-bar', 'chart-line', 'chart-pie', 'trending-up', 'table', 'grid',
        'dollar', 'cart', 'tag', 'users', 'user', 'building',
        'factory', 'truck', 'package', 'map-pin', 'calendar', 'clock',
        'target', 'flag', 'bell', 'clipboard', 'briefcase', 'globe',
    ];
}
