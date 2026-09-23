<?php

namespace App\Http\Responses;

use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse as FailedPasswordResetResponseContract;
use Symfony\Component\HttpFoundation\Response;

class FailedPasswordResetResponse implements FailedPasswordResetResponseContract
{
    public function __construct(private readonly string $status) {}

    /**
     * Return the failed reset response while preserving the token for a retry.
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            throw ValidationException::withMessages([
                'email' => [trans($this->status)],
            ]);
        }

        return back()
            ->withInput($request->only('email', 'token'))
            ->withErrors(['email' => trans($this->status)]);
    }
}
