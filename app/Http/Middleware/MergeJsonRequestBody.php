<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures JSON request body is merged into the request input.
 * Fixes environments (e.g. proxy, rewrite) where Laravel's built-in JSON merge
 * doesn't run, so $request->validate() and $request->input() see the body.
 */
class MergeJsonRequestBody
{
    public function handle(Request $request, Closure $next): Response
    {
        $content = $request->getContent();
        if ($content !== null && $content !== '' && $this->isJsonRequest($request)) {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $request->request->replace(array_merge($request->request->all(), $decoded));
            }
        }

        return $next($request);
    }

    private function isJsonRequest(Request $request): bool
    {
        return str_contains($request->header('Content-Type', ''), 'application/json');
    }
}
