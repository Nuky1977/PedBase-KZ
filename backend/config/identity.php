<?php

return [
    'key_store' => env('PEDBASE_IIN_KEY_STORE', 'environment'),
    'vault' => [
        'address' => env('PEDBASE_VAULT_ADDRESS'),
        'token_file' => env('PEDBASE_VAULT_TOKEN_FILE'),
        'aes_path' => env('PEDBASE_VAULT_AES_PATH', 'secret/data/pedbase/iin-aes'),
        'hmac_path' => env('PEDBASE_VAULT_HMAC_PATH', 'secret/data/pedbase/iin-hmac'),
    ],
    'encryption_key' => env('PEDBASE_IIN_ENCRYPTION_KEY'),
    'lookup_key' => env('PEDBASE_IIN_LOOKUP_KEY'),
    'previous_encryption_keys' => array_filter(explode(',', (string) env('PEDBASE_IIN_PREVIOUS_ENCRYPTION_KEYS', ''))),
];
