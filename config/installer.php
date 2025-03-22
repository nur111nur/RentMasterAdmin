<?php

return [
    'icon' => '/assets/images/logo/mainlogo.svg',

    'background' => '/images/default/background.jpg',

    'support_url' => 'https://join.skype.com/invite/Wy96gZvdk2oW',


    'server' => [
        'php' => [
            'name' => 'PHP Version',
            'version' => '>= 8.1.0',
            'check' => [
                'type' => 'php',
                'value' => 80100
            ]
        ],
        'pdo' => [
            'name' => 'PDO',
            'check' => [
                'type' => 'extension',
                'value' => 'pdo_mysql'
            ]
        ],
        'mbstring' => [
            'name' => 'Mbstring extension',
            'check' => [
                'type' => 'extension',
                'value' => 'mbstring'
            ]
        ],
        'fileinfo' => [
            'name' => 'Fileinfo extension',
            'check' => [
                'type' => 'extension',
                'value' => 'fileinfo'
            ]
        ],
        'openssl' => [
            'name' => 'OpenSSL extension',
            'check' => [
                'type' => 'extension',
                'value' => 'openssl'
            ]
        ],
        'tokenizer' => [
            'name' => 'Tokenizer extension',
            'check' => [
                'type' => 'extension',
                'value' => 'tokenizer'
            ]
        ],
        'json' => [
            'name' => 'Json extension',
            'check' => [
                'type' => 'extension',
                'value' => 'json'
            ]
        ],
        'curl' => [
            'name' => 'Curl extension',
            'check' => [
                'type' => 'extension',
                'value' => 'curl'
            ]
        ]
    ],

    'folders' => [
        'storage.framework' => [
            'name' => '/storage/framework',
            'check' => [
                'type' => 'directory',
                'value' => '../storage/framework'
            ]
        ],
        'storage.logs' => [
            'name' => '/storage/logs',
            'check' => [
                'type' => 'directory',
                'value' => '../storage/logs'
            ],
        ],
        'storage.cache' => [
            'name' => '/bootstrap/cache',
            'check' => [
                'type' => 'directory',
                'value' => '../bootstrap/cache'
            ]
        ],
    ],

    'database' => [
        'seeders' => false
    ],

   'commands' => [
        'db:seed --class=DatabaseSeeder'
    ],

    'admin_area' => [
        'user' => [
            'email' => 'admin@gmail.com',
            'password' => 'admin123'
        ]
    ],

    'login' => '/'
];
