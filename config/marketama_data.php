<?php

return [
    // Folder berisi CSV hasil tarikan Marketama (satu file per tabel) untuk MarketamaDataSeeder.
    'path' => env('MARKETAMA_DATA_PATH') ?: storage_path('app/marketama'),
];
