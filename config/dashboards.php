<?php

return [
    /*
     * Hosts desde los que un dashboard puede cargar <script src> y hojas de estilo,
     * y a los que puede hacer fetch. También alimenta la CSP que se inyecta en el iframe.
     */
    'cdn_allowlist' => array_values(array_filter(array_map('trim', explode(',', env(
        'DASHBOARD_CDN_ALLOWLIST',
        'cdn.jsdelivr.net,cdnjs.cloudflare.com,unpkg.com,fonts.googleapis.com,fonts.gstatic.com',
    ))))),

    // Tamaño máximo del HTML de un dashboard.
    'max_html_bytes' => (int) env('DASHBOARD_MAX_HTML_BYTES', 2 * 1024 * 1024),

    // Topes para las colecciones de registros compartidos (un manifiesto puede pedir menos, nunca más).
    'max_records_per_collection' => (int) env('DASHBOARD_MAX_RECORDS', 5000),
    'max_record_bytes' => (int) env('DASHBOARD_MAX_RECORD_BYTES', 256 * 1024),
];
