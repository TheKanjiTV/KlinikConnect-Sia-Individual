<?php

require_once __DIR__.'/../../integration/bootstrap.php';

return [
    App\Providers\AppServiceProvider::class,
    Sia\Integration\IntegrationServiceProvider::class,
];
