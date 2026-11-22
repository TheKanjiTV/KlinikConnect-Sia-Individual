<?php

return [
    'base_url' => env('PATIENT_APPOINTMENT_BASE_URL', env('APP_URL').'/api/v1'),
    'inbound_api_key' => env('PATIENT_APPOINTMENT_INBOUND_API_KEY'),
    'outbound_api_key' => env('PATIENT_APPOINTMENT_OUTBOUND_API_KEY'),
    'signing_secret' => env('PATIENT_APPOINTMENT_WEBHOOK_SIGNING_SECRET'),
    'verify_secret' => env('PATIENT_APPOINTMENT_WEBHOOK_VERIFY_SECRET'),
    'webhook_url' => env('PATIENT_APPOINTMENT_WEBHOOK_URL'),
    'outbound_webhook_url' => env('INTEGRATION_OUTBOUND_WEBHOOK_URL'),
    'billing' => [
        'base_url' => env('BILLING_BASE_URL', 'http://3.26.55.145/api/v1'),
        'token' => env('BILLING_API_TOKEN'),
    ],
    'emr' => ['base_url' => env('EMR_BASE_URL'), 'token' => env('EMR_API_TOKEN')],
    'inventory' => [
        'base_url' => env('MAUI_MAX_BASE_URL', 'http://13.236.57.159'),
        'token' => env('MAUI_MAX_API_TOKEN'),
    ],
];
