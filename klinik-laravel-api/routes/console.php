<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('integrations:send-webhook {file : Path to a JSON event payload}', function () {
    $path = $this->argument('file');
    if (!is_file($path) || !is_readable($path)) {
        $this->error('Event payload file could not be read.');
        return 1;
    }
    try {
        $payload = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        app(\App\Services\SendIntegrationWebhook::class)->send($payload);
        $this->info('Webhook delivered.');
        return 0;
    } catch (\Throwable $e) {
        $this->error('Webhook delivery failed. Check configuration and recipient availability.');
        return 1;
    }
})->purpose('Send a signed integration event to the configured recipient');
