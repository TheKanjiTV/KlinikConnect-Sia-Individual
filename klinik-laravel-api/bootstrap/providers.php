<?php

$providers = [
    App\Providers\AppServiceProvider::class,
];

// The SIA integration package is optional. It lives beside this repository in
// the full SIA workspace and is not required to run KlinikConnect on its own.
$integrationBootstrap = __DIR__.'/../../integration/bootstrap.php';
if (is_file($integrationBootstrap)) {
    require_once $integrationBootstrap;
    $providers[] = Sia\Integration\IntegrationServiceProvider::class;
}

return $providers;
