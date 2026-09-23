<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The API is consumed by the React frontend, which lives in its own project
    | (../sms-frontend) and runs on a different origin, for example
    | http://localhost:5173 while Vite is serving it. The api/* routes must
    | therefore answer pre-flight requests.
    |
    | Tokens travel in the Authorization header, so cookies are not required:
    | keeping `supports_credentials` false allows the wildcard origin above to
    | stay valid for any dev port. To authenticate the frontend with Sanctum
    | cookies instead, list the frontend origins explicitly and set
    | `supports_credentials` to true.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
