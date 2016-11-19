<?php

namespace Sebdesign\ArtisanCloudflare\Test;

use Sebdesign\ArtisanCloudflare\ServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application   $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('cloudflare', [
            'key' => 'API_KEY',
            'email' => 'email@example.com',
            'zones' => [
                'zone-identifier' => [
                    'files' => [url('css/app.css')],
                    'tags' => ['scripts'],
                ],
                'another-identifier' => [
                ],
            ],
        ]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    protected function registerServiceProvider()
    {
        $this->app->registerDeferredProvider(ServiceProvider::class);
    }
}
