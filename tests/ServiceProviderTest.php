<?php

namespace Sebdesign\ArtisanCloudflare\Test;

use Sebdesign\ArtisanCloudflare\Client;
use Sebdesign\ArtisanCloudflare\ServiceProvider;
use Sebdesign\ArtisanCloudflare\Commands\Cache\Purge;

class ServiceProviderTest extends TestCase
{
    /**
     * @test
     */
    public function it_publishes_the_configuration()
    {
        // Act

        $this->registerServiceProvider();

        // Assert

        $path = $this->getConfigurationPath();

        $this->assertFileExists($path);
    }

    /**
     * @test
     */
    public function it_merges_the_configuration()
    {
        // Arrange

        // Empty the configuration so that it can be merged when the provider loads.
        $this->app['config']->set('cloudflare', []);

        // Act

        $this->registerServiceProvider();

        // Assert

        // Load the configuration from the file
        $config = require $this->getConfigurationPath();

        $this->assertEquals($this->app['config']['cloudflare'], $config);
    }

    /**
     * @test
     */
    public function is_deferred()
    {
        // Act

        $provider = new ServiceProvider($this->app);

        // Assert

        $this->assertTrue($provider->isDeferred());
    }

    /**
     * @test
     */
    public function it_registers_the_api_wrapper()
    {
        // Arrange

        $this->registerServiceProvider();

        // Act

        $client = $this->app[Client::class];

        // Assert

        $this->assertInstanceOf(Client::class, $client);

        $base_uri = $client->getClient()->getConfig('base_uri');
        $headers = $client->getClient()->getConfig('headers');

        $this->assertEquals(Client::BASE_URI, $base_uri);
        $this->assertEquals($this->app['config']['cloudflare.key'], $headers['X-Auth-Key']);
        $this->assertEquals($this->app['config']['cloudflare.email'], $headers['X-Auth-Email']);
    }

    /**
     * @test
     */
    public function it_registers_the_purge_command()
    {
        // Arrange

        $this->registerServiceProvider();

        // Act

        $command = $this->app[Purge::class];

        // Assert

        $this->assertInstanceOf(Purge::class, $command);
    }

    /**
     * @test
     */
    public function it_provides_the_api_client()
    {
        $provider = new ServiceProvider($this->app);

        $this->assertContains(Client::class, $provider->provides());
    }

    /**
     * @test
     */
    public function it_provides_the_purge_command()
    {
        $provider = new ServiceProvider($this->app);

        $this->assertContains(Purge::class, $provider->provides());
    }

    /**
     * Get the path of the configuration file to be published.
     *
     * @return string
     */
    protected function getConfigurationPath()
    {
        return key(ServiceProvider::pathsToPublish(null, 'config'));
    }

}
