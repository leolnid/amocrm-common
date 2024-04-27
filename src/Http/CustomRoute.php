<?php

namespace Leolnid\Common\Http;

use Closure;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Leolnid\Common\Http\Middleware\Request\AddToContextMiddleware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request as RequestAlias;
use Throwable;

class CustomRoute
{
    protected string $route;
    protected string|Closure $callable;

    protected string $method = RequestAlias::METHOD_POST;
    protected null|string|Closure $cast = null;
    protected bool $sync = false;

    protected function __construct(string $route, string|Closure $callable)
    {
        $this->route = $route;
        $this->callable = $callable;
    }

    public static function make(string $route, string|Closure $callable): static
    {
        return new static($route, $callable);
    }

    public function method(string $method): CustomRoute
    {
        $this->method = $method;
        return $this;
    }

    public function cast(string|Closure|null $cast): CustomRoute
    {
        $this->cast = $cast;
        return $this;
    }

    public function build(): \Illuminate\Routing\Route
    {
        $method = strtolower($this->method);

        return Route::middleware(AddToContextMiddleware::class)
            ->{$method}($this->route, function (Request $request) {
                try {
                    $data = self::castRequest($this->cast, $request);
                } catch (Throwable $e) {
                    report($e);
                    return ['message' => 'Не смогли преобразовать входные данные', 'error' => $e->getMessage()];
                }

                $callable = match (true) {
                    is_callable($this->callable) => function () use ($data) {
                        return call_user_func($this->callable, $data);
                    },
                    is_subclass_of($this->callable, ShouldQueue::class) => new $this->callable($data),
                    default => function () use ($data) {
                        $this->callable->call($this, $data);
                        throw new RuntimeException('Unimplemented');
                    }
                };


                if ($this->sync) dispatch_sync($callable);
                else dispatch($callable);

                return response()->json('Поставили вебхук в очередь на обработку.');
            })
            ->name(Str::slug(Str::replace('/', '.', $this->route)));
    }

    public static function castRequest($cast, Request $request)
    {
        if (is_null($cast))
            return $request->all();

        if (is_callable($cast))
            return call_user_func($cast, $request);

        if (is_callable([$cast, 'factory']))
            return call_user_func([$cast, 'factory'], $request);

        return new $cast($request);
    }
}
