<?php

namespace Sebdesign\ArtisanCloudflare;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;

class ServiceProvider extends IlluminateServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/cloudflare.php' => config_path('cloudflare.php'),
            ], 'config');
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cloudflare.php', 'cloudflare');

        $this->registerClient();
        $this->registerCommands();
        $this->registerMacros();
    }

    protected function registerClient()
    {
        $this->app->bind(Client::class, function () {
            return new Client(
                $this->bootGuzzleClient(),
                $this->app['log']
            );
        });
    }

    protected function bootGuzzleClient()
    {
        $config = $this->app['config']['cloudflare'];

        $authorization = isset($config['token']) ? [
            'Authorization' => "Bearer {$config['token']}",
        ] : [
            'X-Auth-Key' => $config['key'],
            'X-Auth-Email' => $config['email'],
        ];

        return new GuzzleClient([
            'base_uri' => Client::BASE_URI,
            \GuzzleHttp\RequestOptions::HEADERS => $authorization,
        ]);
    }

    protected function registerCommands()
    {
        $this->app->bind(Commands\Cache\Purge::class, function () {
            return new Commands\Cache\Purge(
                $this->app['config']['cloudflare.zones'] ?? []
            );
        });

        $this->commands([
            Commands\Cache\Purge::class,
        ]);
    }

    protected function registerMacros()
    {
        /*
         * Transpose with keys.
         *
         * Implementation for PHP < 5.6 and Laravel ~5.1.
         *
         * @return \Illuminate\Support\Collection
         */
        Collection::macro('_transpose', function () {
            $keys = $this->keys()->all();

            $params = array_merge([function () use ($keys) {
                return new static(array_combine($keys, func_get_args()));
            }], $this->values()->toArray());

            return new static(call_user_func_array('array_map', $params));
        });

        /*
         * Add a value between each item.
         *
         * @param  mixed $value
         * @return \Illuminate\Support\Collection
         */
        Collection::macro('insertBetween', function ($value) {
            return $this->values()->flatMap(function ($item, $index) use ($value) {
                return [$index ? $value : null, $item];
            })->forget(0)->values();
        });

        /*
         * Reorder the collection according to an array of keys
         *
         * @param  mixed $keys
         * @return \Illuminate\Support\Collection
         */
        Collection::macro('reorder', function ($keys) {
            $order = $this->getArrayableItems($keys);

            return $this->sortBy(function ($item, $key) use ($order) {
                return array_search($key, $order);
            });
        });
    }

    /**
     * Determine if the provider is deferred.
     *
     * @return bool
     */
    public function isDeferred()
    {
        return true;
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            Client::class,
            Commands\Cache\Purge::class,
        ];
    }
}
