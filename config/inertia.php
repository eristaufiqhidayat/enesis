<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | Direktori komponen halaman Inertia memakai nama folder "Pages" (huruf
    | besar), sesuai resolver import.meta.glob di resources/js/app.js.
    | Default paket ini memakai "js/pages" (huruf kecil); di filesystem yang
    | case-sensitive (Linux/CI) ini membuat assertInertia()->component()
    | gagal menemukan file meski halamannya ada. Override di sini agar
    | path-nya cocok dengan folder sebenarnya.
    |
    */

    'pages' => [

        'paths' => [

            resource_path('js/Pages'),

        ],

        'extensions' => [

            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',

        ],

    ],

];
