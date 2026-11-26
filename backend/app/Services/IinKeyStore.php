<?php

namespace App\Services;

interface IinKeyStore
{
    /** @return array{current: mixed, previous: list<mixed>} */
    public function encryptionKeys(): array;

    public function lookupKey(): mixed;
}
