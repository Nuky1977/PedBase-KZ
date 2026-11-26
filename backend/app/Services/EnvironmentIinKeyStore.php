<?php

namespace App\Services;

class EnvironmentIinKeyStore implements IinKeyStore
{
    public function encryptionKeys(): array
    {
        return [
            'current' => config('identity.encryption_key'),
            'previous' => array_values(config('identity.previous_encryption_keys', [])),
        ];
    }

    public function lookupKey(): mixed
    {
        return config('identity.lookup_key');
    }
}
