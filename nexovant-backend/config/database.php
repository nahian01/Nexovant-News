<?php

return [
    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE') === ':memory:'
                ? ':memory:'
                : (env('DB_DATABASE') && file_exists(env('DB_DATABASE'))
                    ? env('DB_DATABASE')
                    : database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'nexovant_production'),
            'username' => env('DB_USERNAME', 'nexovant_app'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],
    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],
];
