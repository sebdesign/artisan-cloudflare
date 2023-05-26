<?php

namespace Sebdesign\ArtisanCloudflare\Test;

class BlockIpCommandTest extends TestCase
{
    use ConsoleHelpers;
    use GuzzleHelpers;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->mockClient();
    }

    /**
     * @test
     */
    public function it_fails_if_no_zone_identifier_is_found(): void
    {
        // Arrange

        $this->app['config']->set('cloudflare.zones', []);

        // Act

        $this->artisan('cloudflare:waf:block-ip', ['ip' => '6.6.6.6']);

        // Assert

        $this->assertEmpty($this->transactions);

        $this->seeInConsole('Please supply a valid zone identifier')
            ->withoutSuccessCode();
    }

    /**
     * @test
     */
    public function it_fails_if_ip_is_not_valid(): void
    {
        // Act

        $this->artisan('cloudflare:waf:block-ip', ['ip' => '6.6.6']);

        // Assert

        $this->assertEmpty($this->transactions);

        $this->seeInConsole('Please supply a valid IP address')
            ->withoutSuccessCode();
    }

    /**
     * @test
     */
    public function it_outputs_an_error_if_the_api_throws_an_exception(): void
    {
        // Arrange

        $this->mockRequestException('Connection error!', 'foo');
        $this->mockServerErrorResponse('Fatal error!');

        // Act

        $this->artisan('cloudflare:waf:block-ip', ['ip' => '6.6.6.6']);

        // Assert

        $this->assertCount(2, $this->transactions);

        $this->seeInConsole('zone-identifier')
            ->seeInConsole('500')
            ->seeInConsole('Connection error!')
            ->seeInConsole('another-identifier')
            ->seeInConsole('Fatal error!')
            ->withoutSuccessCode();
    }

    /**
     * @test
     */
    public function it_outputs_an_error_if_the_api_returns_an_error(): void
    {
        // Arrange

        $this->mockClientErrorResponse([[
            'code' => 42,
            'message' => 'Error message',
        ]]);

        $this->mockClientErrorResponse([[
            'message' => 'Another error message',
        ]]);

        // Act

        $this->artisan('cloudflare:waf:block-ip', ['ip' => '6.6.6.6']);

        // Assert

        $this->assertCount(2, $this->transactions);

        $this->seeInConsole('zone-identifier')
            ->seeInConsole('42')
            ->seeInConsole('Error message')
            ->seeInConsole('another-identifier')
            ->seeInConsole('Another error message')
            ->withoutSuccessCode();
    }

    /**
     * @test
     */
    public function it_runs_without_zone_argument(): void
    {
        // Arrange

        $options = [
            'mode' => 'block',
            'configuration' => [
                'target' => 'ip', 'value' => '6.6.6.6',
            ],
            'notes' => 'Blocked by artisan command.',
        ];

        $this->mockSuccessResponse();
        $this->mockSuccessResponse();

        // Act

        $this->artisan('cloudflare:waf:block-ip', ['ip' => '6.6.6.6']);

        // Assert

        $this->assertCount(2, $this->transactions);

        $zoneA = $this->transactions->get(0);
        $zoneB = $this->transactions->get(1);

        $this->seeRequestContainsPath($zoneA, 'zone-identifier');
        $this->seeRequestWithBody($zoneA, $options);

        $this->seeRequestContainsPath($zoneB, 'another-identifier');
        $this->seeRequestWithBody($zoneB, $options);

        $this->seeInConsole('zone-identifier')
            ->seeInConsole('another-identifier')
            ->seeInConsole('6.6.6.6')
            ->withSuccessCode();
    }

    /**
     * @test
     */
    public function it_runs_with_ipv6_address(): void
    {
        // Arrange

        $this->mockSuccessResponse();
        $this->mockSuccessResponse();

        // Act

        $this->artisan('cloudflare:waf:block-ip', ['ip' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334']);

        // Assert

        $this->assertCount(2, $this->transactions);

        $zoneA = $this->transactions->get(0);

        $this->seeRequestContainsPath($zoneA, 'zone-identifier');
        $this->seeRequestWithBody($zoneA, [
            'mode' => 'block',
            'configuration' => [
                'target' => 'ip6', 'value' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
            ],
            'notes' => 'Blocked by artisan command.',
        ]);

        $this->seeInConsole('zone-identifier')
            ->seeInConsole('2001:0db8:85a3:0000:0000:8a2e:0370:7334')
            ->withSuccessCode();
    }

    /**
     * @test
     */
    public function it_runs_with_notes_argument(): void
    {
        // Arrange

        $this->mockSuccessResponse();
        $this->mockSuccessResponse();

        // Act

        $this->artisan('cloudflare:waf:block-ip', ['ip' => '6.6.6.6', '--notes' => 'foo']);

        // Assert

        $this->assertCount(2, $this->transactions);

        $zoneA = $this->transactions->get(0);

        $this->seeRequestContainsPath($zoneA, 'zone-identifier');
        $this->seeRequestWithBody($zoneA, [
            'mode' => 'block',
            'configuration' => [
                'target' => 'ip', 'value' => '6.6.6.6',
            ],
            'notes' => 'foo',
        ]);

        $this->seeInConsole('zone-identifier')
            ->withSuccessCode();
    }

    /**
     * @test
     */
    public function it_accepts_an_existing_zone(): void
    {
        // Arrange

        $this->mockSuccessResponse();

        // Act

        $this->artisan('cloudflare:waf:block-ip', ['ip' => '6.6.6.6', 'zone' => 'another-identifier']);

        // Assert

        $this->assertCount(1, $this->transactions);

        $zoneB = $this->transactions->first();

        $this->seeRequestContainsPath($zoneB, 'another-identifier');
        $this->seeRequestWithBody($zoneB, [
            'mode' => 'block',
            'configuration' => [
                'target' => 'ip', 'value' => '6.6.6.6',
            ],
            'notes' => 'Blocked by artisan command.',
        ]);

        $this->seeInConsole('another-identifier')
            ->dontSeeInConsole('zone-identifier')
            ->seeInConsole('6.6.6.6')
            ->withSuccessCode();
    }

    /**
     * @test
     */
    public function it_accepts_a_custom_zone(): void
    {
        // Arrange

        $this->mockSuccessResponse();

        // Act

        $this->artisan('cloudflare:waf:block-ip', ['ip' => '6.6.6.6', 'zone' => 'my-zone']);

        // Assert

        $this->assertCount(1, $this->transactions);

        $customZone = $this->transactions->first();

        $this->seeRequestContainsPath($customZone, 'my-zone');
        $this->seeRequestWithBody($customZone, [
            'mode' => 'block',
            'configuration' => [
                'target' => 'ip','value' => '6.6.6.6',
            ],
            'notes' => 'Blocked by artisan command.',
        ]);

        $this->seeInConsole('my-zone')
            ->dontSeeInConsole('zone-identifier')
            ->SeeInConsole('6.6.6.6')
            ->withSuccessCode();
    }
}
