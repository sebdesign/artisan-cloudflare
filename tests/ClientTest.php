<?php

namespace Sebdesign\ArtisanCloudflare\Test;

use Sebdesign\ArtisanCloudflare\Client;

class ClientTest extends TestCase
{
    use GuzzleHelpers;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->mockClient();
    }

    /**
     * @test
     */
    public function it_purges_a_zone_with_success()
    {
        // Arrange

        $client = $this->app[Client::class];
        $this->mockResponse(200, [], ['success' => true]);

        // Act

        $results = $client->purge(collect(['foo' => ['bar' => 'baz']]));

        // Assert

        $this->assertCount(1, $this->transactions);
        $this->assertCount(1, $results);
        $this->seeRequestWithBody($this->transactions->first(), ['bar' => 'baz']);
        $this->seeRequestContainsPath($this->transactions->first(), 'foo');
        $this->assertEquals((object) ['success' => true], $results->get('foo'));
    }

    /**
     * @test
     */
    public function it_handles_client_errors()
    {
        // Arrange

        $client = $this->app[Client::class];
        $this->mockClientErrorResponse(['error']);

        // Act

        $results = $client->purge(collect(['foo' => ['bar' => 'baz']]));

        // Assert

        $this->assertCount(1, $this->transactions);
        $this->assertCount(1, $results);
        $this->seeRequestWithBody($this->transactions->first(), ['bar' => 'baz']);
        $this->seeRequestContainsPath($this->transactions->first(), 'foo');
        $this->assertEquals((object) [
            'success' => false,
            'errors' => ['error'],
        ], $results->get('foo'));
    }

    /**
     * @test
     */
    public function it_handles_server_errors()
    {
        // Arrange

        $client = $this->app[Client::class];
        $this->mockRequestException('Connection error', 'foo');
        $this->mockServerErrorResponse('Fatal error');

        // Act

        $results = $client->purge(collect([
            'foo' => ['bar' => 'baz'],
            'bar' => ['baz' => 'qux'],
        ]));

        // Assert

        $this->assertCount(2, $this->transactions);
        $this->assertCount(2, $results);
        $this->seeRequestWithBody($this->transactions->get(0), ['bar' => 'baz']);
        $this->seeRequestContainsPath($this->transactions->get(0), 'foo');
        $this->assertEquals((object) [
            'success' => false,
            'errors' => [
                (object) [
                    'code' => 0,
                    'message' => 'Connection error',
                ],
            ],
        ], $results->get('foo'));

        $this->seeRequestWithBody($this->transactions->get(1), ['baz' => 'qux']);
        $this->seeRequestContainsPath($this->transactions->get(1), 'bar');
        $this->assertEquals((object) [
            'success' => false,
            'errors' => [
                (object) [
                    'code' => 500,
                    'message' => 'Fatal error',
                ],
            ],
        ], $results->get('bar'));
    }
}
