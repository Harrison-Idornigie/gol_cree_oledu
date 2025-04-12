<?php

return [
    'mailgun'  => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses'      => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google'   => [
        'client_id'        => env('GOOGLE_CLIENT_ID'),
        'client_secret'    => env('GOOGLE_CLIENT_SECRET'),
        'redirect'         => env('GOOGLE_CALLBACK_URI'),
        'mobile_redirect'  => env('GOOGLE_MOBILE_CALLBACK_URI', env('GOOGLE_CALLBACK_URI')),
        'allowed_domains'  => env('GOOGLE_ALLOWED_DOMAINS', 'gmail.com,keyin.com'),
        'admin_domains'    => env('GOOGLE_ADMIN_DOMAINS', ''),
        'validate_domains' => env('GOOGLE_VALIDATE_DOMAINS', true),
    ],

    'frontend' => [
        'url' => env('FRONTEND_URL', 'http://localhost:3000'),
    ],

    'mobile'   => [
        'app_scheme'           => env('MOBILE_APP_SCHEME', 'myapp'),
        'ios_bundle_id'        => env('MOBILE_IOS_BUNDLE_ID', 'com.example.myapp'),
        'android_package_name' => env('MOBILE_ANDROID_PACKAGE_NAME', 'com.example.myapp'),
    ],
];
