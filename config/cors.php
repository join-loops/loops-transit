<?php

return [

    'paths' => ['api/*', '.well-known/*'],

    'allowed_methods' => ['GET', 'POST'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Content-Type'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,

];
