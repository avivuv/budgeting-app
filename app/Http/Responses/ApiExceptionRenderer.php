<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Error bodies for the REST API in Bahasa Indonesia (docs/api-contracts/README.md).
 * Messages given on purpose (e.g. abort(403, 'Keluarga ini sedang ditangguhkan.'))
 * are kept; framework defaults in English are replaced.
 */
final class ApiExceptionRenderer
{
    public static function handles(Request $request): bool
    {
        return $request->is('api/*');
    }

    public function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! self::handles($request)) {
            return null;
        }

        return match (true) {
            $e instanceof DomainException => $this->domain($e),
            $e instanceof AuthenticationException => $this->message(__('auth.unauthenticated'), 401),
            $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => $this->message(
                $this->custom($e->getMessage()) ?? __('auth.forbidden'),
                403,
            ),
            $e instanceof ModelNotFoundException => $this->message(__('auth.not_found'), 404),
            $e instanceof NotFoundHttpException => $this->message(
                $e->getPrevious() instanceof ModelNotFoundException ? __('auth.not_found') : ($this->custom($e->getMessage()) ?? __('auth.not_found')),
                404,
            ),
            $e instanceof ThrottleRequestsException => $this->throttled($e),
            default => null,
        };
    }

    private function domain(DomainException $e): JsonResponse
    {
        $body = [
            'message' => $e->userMessage(),
            'errors' => [$e->field() ?? 'general' => [$e->userMessage()]],
        ];

        if ($e instanceof BusinessRuleException) {
            $body['rule'] = $e->rule;
        }

        return new JsonResponse($body, $e->status());
    }

    private function throttled(ThrottleRequestsException $e): JsonResponse
    {
        $headers = $e->getHeaders();
        $seconds = (int) ($headers['Retry-After'] ?? 60);

        return new JsonResponse(
            ['message' => __('auth.too_many_requests', ['seconds' => $seconds])],
            429,
            $headers,
        );
    }

    private function message(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status);
    }

    /**
     * The exception message if it was written for the user, null for framework defaults.
     */
    private function custom(string $message): ?string
    {
        $defaults = ['', 'Not Found', 'This action is unauthorized.', 'Invalid ability provided.', 'Forbidden'];

        if (in_array($message, $defaults, true) || str_starts_with($message, 'The route ') || str_starts_with($message, 'No query results')) {
            return null;
        }

        return $message;
    }
}
