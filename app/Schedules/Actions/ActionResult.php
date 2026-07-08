<?php

namespace App\Schedules\Actions;

class ActionResult
{
    public function __construct(
        public bool $success,
        public mixed $output = null,
        public ?string $error = null,
    ) {}

    public static function success(mixed $output = null): self
    {
        return new self(true, $output);
    }

    public static function failure(string $error, mixed $output = null): self
    {
        return new self(false, $output, $error);
    }
}