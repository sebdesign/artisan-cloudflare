<?php

namespace Sebdesign\ArtisanCloudflare\Test;

use PHPUnit\Framework\Attributes\Test;
use Sebdesign\ArtisanCloudflare\Client;
use Sebdesign\ArtisanCloudflare\Commands\Cache\Purge;
use Sebdesign\ArtisanCloudflare\ServiceProvider;

class ServiceProviderTest extends TestCase
{
    #[Test]
    public function it_publishes_the_configuration(): void
    {
        // Act

        $this->registerServiceProvider();

        // Assert

        $path = $this->getConfigurationPath();

        $this->assertFileExists($path);
    }

    #[Test]
    public function it_merges_the_configuration(): void
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

    #[Test]
    public function is_deferred(): void
    {
        // Act

        $provider = new ServiceProvider($this->app);

        // Assert

        $this->assertTrue(
            $provider->isDeferred(),
            'Failed asserting that the service provider is deferred.'
        );
    }

    #[Test]
    public function it_registers_the_api_wrapper(): void
    {
        // Arrange

        $this->registerServiceProvider();

        // Act

        $client = $this->app[Client::class];

        // Assert

        $this->assertInstanceOf(Client::class, $client);

        $base_uri = $client->getClient()->getConfig('base_uri');

        $this->assertEquals(Client::BASE_URI, $base_uri);
    }

    #[Test]
    public function it_authenticates_with_an_api_token(): void
    {
        // Arrange

        $this->app['config']->set(['cloudflare.token' => 'API_TOKEN']);

        $this->registerServiceProvider();

        // Act

        $client = $this->app[Client::class];

        // Assert

        $headers = $client->getClient()->getConfig('headers');

        $this->assertEquals('Bearer API_TOKEN', $headers['Authorization']);
    }

    #[Test]
    public function it_authenticates_with_an_api_key(): void
    {
        // Arrange

        $this->app['config']->set([
            'cloudflare.key' => 'API_KEY',
            'cloudflare.enauk' => 'email@example.com',
        ]);

        $this->registerServiceProvider();

        // Act

        $client = $this->app[Client::class];

        // Assert

        $headers = $client->getClient()->getConfig('headers');

        $this->assertEquals('API_KEY', $headers['X-Auth-Key']);
        $this->assertEquals('email@example.com', $headers['X-Auth-Email']);
    }

    #[Test]
    public function it_registers_the_purge_command(): void
    {
        // Arrange

        $this->registerServiceProvider();

        // Act

        $command = $this->app[Purge::class];

        // Assert

        $this->assertInstanceOf(Purge::class, $command);
    }

    #[Test]
    public function it_provides_the_api_client(): void
    {
        $provider = new ServiceProvider($this->app);

        $this->assertContains(Client::class, $provider->provides());
    }

    #[Test]
    public function it_provides_the_purge_command(): void
    {
        $provider = new ServiceProvider($this->app);

        $this->assertContains(Purge::class, $provider->provides());
    }

    /**
     * Get the path of the configuration file to be published.
     */
    protected function getConfigurationPath(): string
    {
        return key(ServiceProvider::pathsToPublish(null, 'config'));
    }
}
