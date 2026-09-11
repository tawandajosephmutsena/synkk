<?php

namespace App\Data\QA;

readonly class PhaseResult
{
    public function __construct(
        public string $phase,
        public string $status,
        public ?string $errorMessage = null,
        public ?array $data = null,
        public ?float $duration = null,
    ) {
        //
    }

    public static function success(string $phase, array $data = [], ?float $duration = null): self
    {
        return new self(
            phase: $phase,
            status: 'success',
            errorMessage: null,
            data: $data,
            duration: $duration,
        );
    }

    public static function failed(string $phase, string $errorMessage, ?float $duration = null): self
    {
        return new self(
            phase: $phase,
            status: 'failed',
            errorMessage: $errorMessage,
            data: null,
            duration: $duration,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function toArray(): array
    {
        return [
            'phase' => $this->phase,
            'status' => $this->status,
            'errorMessage' => $this->errorMessage,
            'data' => $this->data,
            'duration' => $this->duration,
        ];
    }
}
