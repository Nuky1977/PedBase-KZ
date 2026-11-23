<?php

return [
    'encryption_key' => env('PEDBASE_IIN_ENCRYPTION_KEY'),
    'lookup_key' => env('PEDBASE_IIN_LOOKUP_KEY'),
    'previous_encryption_keys' => array_filter(explode(',', (string) env('PEDBASE_IIN_PREVIOUS_ENCRYPTION_KEYS', ''))),
];
