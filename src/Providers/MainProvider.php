<?php

namespace Leolnid\Common\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Leolnid\Common\Console\Kernel;
use Leolnid\Common\Events\DeploymentSuccessEvent;
use Leolnid\Common\Http\Middleware\Request\AddToContextMiddleware;
use Leolnid\Common\Http\Middleware\Request\LogAllMiddleware;
use Leolnid\Common\Http\Middleware\Response\ForceJson;
use Leolnid\Common\Http\Middleware\TildaConfirmTestMiddleware;
use Leolnid\Common\Listeners\LogDeploymentListener;
use Opcodes\LogViewer\Facades\LogViewer;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Spatie\CpuLoadHealthCheck\CpuLoadCheck;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\OptimizedAppCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Health;

class MainProvider extends ServiceProvider
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function boot(): void
    {
        Event::listen(DeploymentSuccessEvent::class, LogDeploymentListener::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../../config/amocrm/common.php' => config_path('amocrm/common.php')]);
            $this->loadMigrationsFrom([__DIR__ . '/../../database/migrations']);

            $console = new Kernel();
            $console->commands($this);
            $this->callAfterResolving(Schedule::class, fn(Schedule $schedule) => $console->schedule($schedule));
        }

        $this->loadRoutesFrom(__DIR__ . '/../../routes/main.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'leolnid.common');
        $this->registerMiddlewares();

        LogViewer::auth(function ($request) {
            return $request->user() && in_array($request->user()->email, [
                    'john@example.com',
                    'leonid.dyukov@gmail.com',
                ]);
        });
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function registerMiddlewares(): void
    {
        $router = $this->app->get('router');
        $router->aliasMiddleware('response-force-json', ForceJson::class);
        $router->aliasMiddleware('response-format-to-json', ForceJson::class);
        $router->aliasMiddleware('request-add-to-contest', AddToContextMiddleware::class);
        $router->aliasMiddleware('request-log-all', LogAllMiddleware::class);
        $router->aliasMiddleware('tilda-confirm-text', TildaConfirmTestMiddleware::class);
    }

    public function register(): void
    {
        $this->callAfterResolving(Health::class, fn(Health $health) => $health->checks($this->getChecks()));
    }

    protected function getChecks(): array
    {
        return [
            EnvironmentCheck::new(),
            DebugModeCheck::new(),
            CacheCheck::new(),

            OptimizedAppCheck::new(),
            DatabaseCheck::new(),

            QueueCheck::new(),

            ScheduleCheck::new(),

            CpuLoadCheck::new()
                ->failWhenLoadIsHigherInTheLast5Minutes(2.0)
                ->failWhenLoadIsHigherInTheLast15Minutes(1.5),

            UsedDiskSpaceCheck::new()
                ->warnWhenUsedSpaceIsAbovePercentage(60)
                ->failWhenUsedSpaceIsAbovePercentage(80),
        ];
    }
}
