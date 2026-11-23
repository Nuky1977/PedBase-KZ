<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;

class ProtectedIin
{
    public function fingerprint(#[\SensitiveParameter] string $iin): string
    {
        if (! preg_match('/^[0-9]{12}$/D', $iin)) {
            abort(422, 'ЖСН 12 цифрдан тұруы керек.');
        }

        $lookup = $this->key(config('identity.lookup_key'));
        $encryptionKeys = array_map($this->key(...), [config('identity.encryption_key'), ...config('identity.previous_encryption_keys', [])]);
        abort_if(in_array($lookup, $encryptionKeys, true), 503, 'Жеке деректерді қорғау баптауы жарамсыз.');

        return hash_hmac('sha256', 'pedbase:teacher-iin:v1:'.$iin, $lookup);
    }

    public function encrypt(#[\SensitiveParameter] string $iin): string
    {
        return $this->encrypter()->encryptString($iin);
    }

    public function decrypt(#[\SensitiveParameter] string $ciphertext): string
    {
        try {
            return $this->encrypter()->decryptString($ciphertext);
        } catch (DecryptException) {
            abort(503, 'Қорғалған жеке деректі оқу уақытша қолжетімсіз.');
        }
    }

    private function encrypter(): Encrypter
    {
        $key = $this->key(config('identity.encryption_key'));
        $previous = array_map($this->key(...), config('identity.previous_encryption_keys', []));

        return (new Encrypter($key, 'aes-256-gcm'))->previousKeys($previous);
    }

    private function key(#[\SensitiveParameter] mixed $encoded): string
    {
        $key = is_string($encoded) ? base64_decode($encoded, true) : false;
        abort_unless(is_string($key) && strlen($key) === 32, 503, 'Жеке деректерді қорғау баптауы жарамсыз.');

        return $key;
    }
}
