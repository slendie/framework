<?php

declare(strict_types=1);

namespace Slendie\Http\Middleware;

use Closure;
use Slendie\Contracts\ViewFactory;
use Slendie\Foundation\Application;
use Slendie\Http\Request;
use Slendie\Http\Response;

/**
 * Disponibiliza `$errors` às views a partir do flash `errors` da sessão.
 */
class ShareErrorsFromSession
{
    public function __construct(protected Application $app)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $errors = $request->hasSession() ? $request->session()->get('errors', []) : [];
        $errors = is_array($errors) ? $errors : [];

        // Peça 08: converter em MessageBag.
        $request->setAttribute('errors', $errors);

        if ($this->app->bound('view')) {
            $view = $this->app->make('view');

            if ($view instanceof ViewFactory) {
                $view->share('errors', $errors);
            }
        }

        /** @var Response */
        return $next($request);
    }
}
