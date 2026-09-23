<?php
return [
    'app' => [
        'name' => 'Patient Medical Record Management System',
        'version' => '3.0.1',
        'base_url' => 'https://ibd-gastro-dmch-bd.com', // CHANGE THIS to your actual URL
        'debug' => true, // Set to true temporarily to see errors
        'timezone' => 'Asia/Dhaka',
        'session_timeout' => 7200
    ],
    
    'database' => [
        'host' => 'localhost',
        'name' => 'ibdgastrodmchbd_pmrms',
        'user' => 'ibdgastrodmchbd_pmrms',
        'pass' => '<Admin123!@#>',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci'
    ],
    
    'upload' => [
        'max_size' => 10485760,
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
        'path' => __DIR__ . '/../uploads/'
    ],
    
    'security' => [
        'password_cost' => 10,
        'csrf_token_name' => '_csrf',
        'session_name' => 'pmrms_session'
    ]
];