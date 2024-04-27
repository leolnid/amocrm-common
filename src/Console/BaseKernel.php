<?php

namespace Leolnid\Common\Console;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Finder\Finder;

abstract class BaseKernel
{
    public abstract function schedule(Schedule $schedule): void;

    public abstract function commands(ServiceProvider $provider): void;

    protected function load(ServiceProvider $provider, string $namespace, $root): void
    {
        $paths = array_unique(Arr::wrap($root . DIRECTORY_SEPARATOR . 'Commands'));
        $paths = array_filter($paths, fn($path) => is_dir($path));

        if (empty($paths)) return;

        $dir = str_replace('/', "\\", $root);
        foreach ((new Finder())->in($paths)->files() as $file) {
            $command = str_replace(
                [DIRECTORY_SEPARATOR, $dir],
                ['\\', $namespace],
                ucfirst(Str::replaceLast('.php', '', $file->getRealPath())),
            );

            try {
                if (is_subclass_of($command, Command::class) && !(new ReflectionClass($command))->isAbstract()) {
                    $provider->commands($command);
                }
            } catch (ReflectionException $e) {
                dump($e);
            }
        }
    }
}
