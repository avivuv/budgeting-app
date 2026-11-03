<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Http\Controllers\Api\V1\Auth\Concerns\RespondsWithMe;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Laravel\Passkeys\Http\Responses\PasskeyLoginResponse as DefaultPasskeyLoginResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Passkey login on /api/v1/auth/passkeys/login answers with `Me` like every
 * other login; the legacy web routes keep the package's redirect answer.
 */
final class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    use RespondsWithMe;

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $user = $request->user();

        if (ApiExceptionRenderer::handles($request) && $user instanceof User) {
            return $this->me($user)->response();
        }

        return (new DefaultPasskeyLoginResponse)->toResponse($request);
    }
}
