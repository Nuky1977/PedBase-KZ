<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class VaultIinKeyStore implements IinKeyStore
{
    public function encryptionKeys(): array
    {
        $data = $this->read(config('identity.vault.aes_path'));
        $previous = $data['previous'] ?? [];
        abort_unless(is_array($previous) && array_is_list($previous), 503, __('identity.unavailable'));

        return ['current' => $data['current'] ?? null, 'previous' => $previous];
    }

    public function lookupKey(): mixed
    {
        return $this->read(config('identity.vault.hmac_path'))['current'] ?? null;
    }

    /** @return array<string, mixed> */
    private function read(mixed $path): array
    {
        $address = config('identity.vault.address');
        $parts = is_string($address) ? parse_url($address) : false;
        abort_unless(is_array($parts) && ($parts['scheme'] ?? null) === 'https'
            && isset($parts['host']) && ! isset($parts['user']) && ! isset($parts['pass'])
            && ! isset($parts['query']) && ! isset($parts['fragment'])
            && in_array($parts['path'] ?? '', ['', '/'], true)
            && is_string($path) && preg_match('#^[a-zA-Z0-9_-]+/data/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]+$#D', $path)
            && config('identity.vault.aes_path') !== config('identity.vault.hmac_path'), 503, __('identity.unavailable'));

        $file = config('identity.vault.token_file');
        $contents = is_string($file) && is_file($file) ? @file_get_contents($file, length: 4096) : false;
        $token = is_string($contents) ? trim($contents) : '';
        abort_unless($token !== '' && preg_match('/^[!-~]+$/D', $token), 503, __('identity.unavailable'));

        try {
            $response = Http::acceptJson()
                ->withHeaders(['X-Vault-Token' => $token])
                ->withoutRedirecting()
                ->connectTimeout(2)
                ->timeout(5)
                ->get(rtrim($address, '/').'/v1/'.$path);
        } catch (ConnectionException) {
            abort(503, __('identity.unavailable'));
        }

        $data = $response->successful() ? $response->json('data.data') : null;
        abort_unless(is_array($data), 503, __('identity.unavailable'));

        return $data;
    }
}
