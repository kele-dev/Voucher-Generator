<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the baseline security headers on every response.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // The Vite dev server serves assets from another origin (the app host on
        // port 5173) and streams HMR over a websocket, so 'self' alone blocks
        // both. Allow that origin only in local; production serves built assets
        // from 'self' and keeps the strict policy.
        $viteHttp = '';
        $viteWs = '';
        if (app()->environment('local')) {
            $viteHttp = ' http://localhost:5173 http://127.0.0.1:5173';
            $viteWs = ' ws://localhost:5173 ws://127.0.0.1:5173';
        }

        // Alpine evaluates directive expressions (x-data, @click, ...) with the
        // Function constructor, which a policy without 'unsafe-eval' blocks.
        //
        // The layout also runs one inline script (the pre-paint theme resolve, so
        // the page never flashes light before Alpine boots). Inline script is
        // blocked by script-src, so that exact block is allowed by hash instead
        // of weakening the policy with 'unsafe-inline'. If the inline script in
        // components/layouts/app.blade.php changes, this hash must be recomputed.
        $themeScriptHash = "'sha256-7L20/RYlOIsJCJxbWc6mNMHK34VffVTtDBOaGI3dsDQ='";
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'unsafe-eval' {$themeScriptHash}{$viteHttp}; style-src 'self' 'unsafe-inline'{$viteHttp}; "
            ."img-src 'self' data: blob:{$viteHttp}; font-src 'self' data:{$viteHttp}; connect-src 'self'{$viteHttp}{$viteWs}; "
            ."object-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'"
        );

        return $response;
    }
}
