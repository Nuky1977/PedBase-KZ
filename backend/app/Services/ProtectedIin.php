<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;

class ProtectedIin
{
    public function __construct(private IinKeyStore $keys) {}

    public function fingerprint(#[\SensitiveParameter] string $iin): string
    {
        return $this->digest($iin, $this->validatedLookupKey($this->encryptionKeys()));
    }

    public function ensureWritable(): void
    {
        $this->validatedLookupKey($this->encryptionKeys());
    }

    /** @return array{iin_ciphertext: string, iin_lookup: string} */
    public function protect(#[\SensitiveParameter] string $iin): array
    {
        $keys = $this->encryptionKeys();
        $lookup = $this->digest($iin, $this->validatedLookupKey($keys));

        return [
            'iin_ciphertext' => $this->encrypter($keys)->encryptString($iin),
            'iin_lookup' => $lookup,
        ];
    }

    public function encrypt(#[\SensitiveParameter] string $iin): string
    {
        return $this->encrypter($this->encryptionKeys())->encryptString($iin);
    }

    public function decrypt(#[\SensitiveParameter] string $ciphertext): string
    {
        try {
            return $this->encrypter($this->encryptionKeys())->decryptString($ciphertext);
        } catch (DecryptException) {
            abort(503, 'Қорғалған жеке деректі оқу уақытша қолжетімсіз.');
        }
    }

    /** @param list<string> $keys */
    private function encrypter(#[\SensitiveParameter] array $keys): Encrypter
    {
        $key = array_shift($keys);

        return (new Encrypter($key, 'aes-256-gcm'))->previousKeys($keys);
    }

    /** @return list<string> */
    private function encryptionKeys(): array
    {
        $keys = $this->keys->encryptionKeys();

        return array_map($this->key(...), [$keys['current'], ...$keys['previous']]);
    }

    /** @param list<string> $encryptionKeys */
    private function validatedLookupKey(#[\SensitiveParameter] array $encryptionKeys): string
    {
        $lookup = $this->key($this->keys->lookupKey());
        abort_if(in_array($lookup, $encryptionKeys, true), 503, __('identity.unavailable'));

        return $lookup;
    }

    private function digest(#[\SensitiveParameter] string $iin, #[\SensitiveParameter] string $key): string
    {
        abort_unless(preg_match('/^[0-9]{12}$/D', $iin), 422, 'ЖСН 12 цифрдан тұруы керек.');

        return hash_hmac('sha256', 'pedbase:teacher-iin:v1:'.$iin, $key);
    }

    private function key(#[\SensitiveParameter] mixed $encoded): string
    {
        $key = is_string($encoded) ? base64_decode($encoded, true) : false;
        abort_unless(is_string($key) && strlen($key) === 32, 503, __('identity.unavailable'));

        return $key;
    }
}
