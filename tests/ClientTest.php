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
    public function it_makes_a_delete_request_to_a_uri_with_a_body()
    {
        // Arrange

        $client = $this->app[Client::class];
        $this->mockResponse(200, [], ['success' => true]);

        // Act

        $response = $client->delete('foo', ['bar' => 'baz']);

        // Assert

        $this->assertCount(1, $this->transactions);
        $this->seeRequestWithBody($this->transactions->first(), ['bar' => 'baz']);
        $this->seeRequestContainsPath($this->transactions->first(), 'foo');
        $this->assertEquals((object) ['success' => true], $response);
    }

    /**
     * @test
     */
    public function it_handles_client_errors()
    {
        // Arrange

        $client = $this->app[Client::class];
        $this->mockErrorResponse(['error']);

        // Act

        $response = $client->delete('foo', ['bar' => 'baz']);

        // Assert

        $this->assertCount(1, $this->transactions);
        $this->assertEquals((object) [
            'success' => false,
            'errors' => ['error'],
        ], $response);
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

        $responseA = $client->delete('foo', ['bar' => 'baz']);
        $responseB = $client->delete('foo', ['bar' => 'baz']);

        // Assert

        $this->assertCount(2, $this->transactions);

        $this->assertEquals((object) [
            'success' => false,
            'errors' => [
                (object) [
                    'code' => 0,
                    'message' => 'Connection error',
                ]
            ],
        ], $responseA);

        $this->assertEquals((object) [
            'success' => false,
            'errors' => [
                (object) [
                    'code' => 500,
                    'message' => 'Fatal error',
                ]
            ],
        ], $responseB);
    }
}
