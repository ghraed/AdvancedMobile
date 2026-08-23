<?php

return [
    'low_margin_threshold' => 10,
    'default_range' => 'last_30_days',
    'product_page_size' => 20,
    'timezone' => env('ANALYTICS_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
];
