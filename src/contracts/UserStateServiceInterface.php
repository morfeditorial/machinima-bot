<?php

declare(strict_types=1);

namespace morfeditorial\contracts;

interface UserStateServiceInterface
{
    public function setState(int $user_id, mixed $value, string $key = 'default'): void;
    public function getState(int $user_id, string $key = 'default'): mixed;
    public function clearState(int $user_id, ?string $key = null): void;
}
