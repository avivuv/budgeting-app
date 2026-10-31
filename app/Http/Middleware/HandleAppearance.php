<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\User\AppearanceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    public function __construct(
        private readonly AppearanceService $appearance,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        View::share('appearance', $this->appearance->resolve($user, $request->cookie('appearance'))->value);

        return $next($request);
    }
}
