<?php

namespace Sebdesign\ArtisanCloudflare\Test;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Sebdesign\ArtisanCloudflare\ServiceProvider;

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
                    'hosts' => ['www.example.com'],
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
        if (version_compare($this->app->version(), '5.7.0', '>=')) {
            $this->app->register(new ServiceProvider($this->app), true);
        } else {
            $this->app->register(new ServiceProvider($this->app), [], true);
        }
    }
}
