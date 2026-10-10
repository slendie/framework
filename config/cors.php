<?php

declare(strict_types=1);

return [
    /* Caminhos (com `*`) a que o CORS se aplica. */
    'paths' => ['api/*'],

    'allowed_origins' => ['*'],

    'allowed_methods' => ['*'],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    /* Segundos que o browser pode guardar a resposta ao preflight. */
    'max_age' => 0,

    'supports_credentials' => false,
];
