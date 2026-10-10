<?php

declare(strict_types=1);

namespace Slendie\Http\Middleware;

use Closure;
use ReflectionNamedType;
use Slendie\Container\Container;
use Slendie\Contracts\UrlRoutable;
use Slendie\Exceptions\NotFoundHttpException;
use Slendie\Http\Request;
use Slendie\Http\Response;
use Slendie\Routing\Route;

/**
 * Route-model binding: parâmetros de rota tipados com uma classe UrlRoutable passam de valor
 * (`5`) a instância (`Post #5`). Se o registo não existir, devolve 404.
 */
class SubstituteBindings
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        if ($route instanceof Route) {
            foreach ($route->reflector()->getParameters() as $parameter) {
                $name = $parameter->getName();
                $type = $parameter->getType();
                $value = $route->parameter($name);

                if (! $type instanceof ReflectionNamedType || $type->isBuiltin() || $value === null || is_object($value)) {
                    continue;
                }

                $class = $type->getName();

                if (! is_subclass_of($class, UrlRoutable::class)) {
                    continue;
                }

                /** @var UrlRoutable $instance */
                $instance = Container::getInstance()->make($class);
                $model = $instance->resolveRouteBinding($value, null);

                if ($model === null) {
                    throw new NotFoundHttpException('No query results for '.$class.' ['.(is_scalar($value) ? (string) $value : $name).'].');
                }

                $route->setParameter($name, $model);
            }
        }

        /** @var Response */
        return $next($request);
    }
}
