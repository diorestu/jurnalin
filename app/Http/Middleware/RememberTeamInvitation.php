<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RememberTeamInvitation
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('login', 'register')) {
            $code = $request->query('invitation');
            if (is_string($code) && preg_match('/\A[a-zA-Z0-9]{64}\z/', $code)) {
                $request->session()->put('team_invitation_code', $code);
            }
        }

        return $next($request);
    }
}
