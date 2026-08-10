<?php

return [
    'currency' => env('INSTALLMENT_CURRENCY', 'USD'),
    // Fees are basis points, so 250 means 2.50%. Keep zero until pricing policy is defined.
    'durations' => [
        3 => ['fee_basis_points' => 0],
        6 => ['fee_basis_points' => 0],
        9 => ['fee_basis_points' => 0],
    ],
];
