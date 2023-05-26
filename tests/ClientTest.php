<?php

namespace Sebdesign\ArtisanCloudflare\Test;

use Illuminate\Support\Collection;
use Sebdesign\ArtisanCloudflare\Client;
use Sebdesign\ArtisanCloudflare\Zone;

class ClientTest extends TestCase
{
    use GuzzleHelpers;

    /**
     * Setup the test environment.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->mockClient();
    }

    /**
     * @test
     */
    public function it_purges_a_zone_with_success(): void
    {
        // Arrange

        $client = $this->app[Client::class];
        $this->mockResponse(200, [], ['success' => true]);

        // Act

        $results = $client->purge(Collection::make([
            'foo' => new Zone(['bar' => 'baz']),
        ]));

        // Assert

        $this->assertCount(1, $this->transactions);
        $this->assertCount(1, $results);
        $this->seeRequestWithBody($this->transactions->first(), ['bar' => 'baz']);
        $this->seeRequestContainsPath($this->transactions->first(), 'foo');
        $this->assertEquals(new Zone(['success' => true]), $results->get('foo'));
    }

    /**
     * @test
     */
    public function it_blocks_ip_address_with_success(): void
    {
        // Arrange

        $client = $this->app[Client::class];
        $this->mockResponse(200, [], ['success' => true]);

        // Act

        $results = $client->blockIP(Collection::make([
            'foo' => new Zone(['bar' => 'baz']),
        ]));

        // Assert

        $this->assertCount(1, $this->transactions);
        $this->assertCount(1, $results);
        $this->seeRequestWithBody($this->transactions->first(), ['bar' => 'baz']);
        $this->seeRequestContainsPath($this->transactions->first(), 'foo');
        $this->assertEquals(new Zone(['success' => true]), $results->get('foo'));
    }

    /**
     * @test
     */
    public function it_handles_client_errors(): void
    {
        // Arrange

        $client = $this->app[Client::class];
        $this->mockClientErrorResponse(['error']);

        // Act

        $results = $client->purge(Collection::make([
            'foo' => new Zone(['bar' => 'baz']),
        ]));

        // Assert

        $this->assertCount(1, $this->transactions);
        $this->assertCount(1, $results);
        $this->seeRequestWithBody($this->transactions->first(), ['bar' => 'baz']);
        $this->seeRequestContainsPath($this->transactions->first(), 'foo');
        $this->assertEquals(new Zone([
            'success' => false,
            'errors' => ['error'],
        ]), $results->get('foo'));
    }

    /**
     * @test
     */
    public function it_handles_server_errors(): void
    {
        // Arrange

        $client = $this->app[Client::class];
        $this->mockRequestException('Connection error', 'foo');
        $this->mockServerErrorResponse('Fatal error');

        // Act

        $results = $client->purge(Collection::make([
            'foo' => new Zone(['bar' => 'baz']),
            'bar' => new Zone(['baz' => 'qux']),
        ]));

        // Assert

        $this->assertCount(2, $this->transactions);
        $this->assertCount(2, $results);
        $this->seeRequestWithBody($this->transactions->get(0), ['bar' => 'baz']);
        $this->seeRequestContainsPath($this->transactions->get(0), 'foo');
        $this->assertEquals(new Zone([
            'success' => false,
            'errors' => [
                [
                    'code' => 0,
                    'message' => 'Connection error',
                ],
            ],
        ]), $results->get('foo'));

        $this->seeRequestWithBody($this->transactions->get(1), ['baz' => 'qux']);
        $this->seeRequestContainsPath($this->transactions->get(1), 'bar');
        $this->assertEquals(new Zone([
            'success' => false,
            'errors' => [
                [
                    'code' => 500,
                    'message' => 'Fatal error',
                ],
            ],
        ]), $results->get('bar'));
    }
}
