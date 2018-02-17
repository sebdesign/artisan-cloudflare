<?php

namespace Sebdesign\ArtisanCloudflare\Test;

class PurgeCommandTest extends TestCase
{
    use ConsoleHelpers, GuzzleHelpers;

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
    public function it_fails_if_no_zone_identifier_is_found()
    {
        // Arrange

        $this->app['config']->set('cloudflare.zones', []);

        // Act

        $this->artisan('cloudflare:cache:purge');

        // Assert

        $this->assertEmpty($this->transactions);

        $this->seeInConsole('Please supply a valid zone identifier')
            ->withoutSuccessCode();
    }

    /**
     * @test
     */
    public function it_outputs_an_error_if_the_api_throws_an_exception()
    {
        // Arrange

        $this->mockRequestException('Connection error!', 'foo');
        $this->mockServerErrorResponse('Fatal error!');

        // Act

        $this->artisan('cloudflare:cache:purge');

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
    public function it_outputs_an_error_if_the_api_returns_an_error()
    {
        // Arrange

        $this->mockClientErrorResponse([[
            'code' => 42,
            'message' => 'Error message',
        ]]);

        $this->mockClientErrorResponse([[
            'code' => 43,
            'message' => 'Another error message',
        ]]);

        // Act

        $this->artisan('cloudflare:cache:purge');

        // Assert

        $this->assertCount(2, $this->transactions);

        $this->seeInConsole('zone-identifier')
            ->seeInConsole('42')
            ->seeInConsole('Error message')
            ->seeInConsole('another-identifier')
            ->seeInConsole('43')
            ->seeInConsole('Another error message')
            ->withoutSuccessCode();
    }

    /**
     * @test
     */
    public function it_runs_without_arguments_and_options()
    {
        // Arrange

        $options = $this->app['config']['cloudflare.zones.zone-identifier'];

        $this->mockSuccessResponse();
        $this->mockSuccessResponse();

        // Act

        $this->artisan('cloudflare:cache:purge');

        // Assert

        $this->assertCount(2, $this->transactions);

        $zoneA = $this->transactions->get(0);
        $zoneB = $this->transactions->get(1);

        $this->seeRequestContainsPath($zoneA, 'zone-identifier');
        $this->seeRequestWithBody($zoneA, $options);

        $this->seeRequestContainsPath($zoneB, 'another-identifier');
        $this->seeRequestWithBody($zoneB, ['purge_everything' => true]);

        $this->seeInConsole('zone-identifier')
            ->seeInConsole('another-identifier')
            ->seeInConsole('app.css')
            ->seeInConsole('scripts')
            ->withSuccessCode();
    }

    /**
     * @test
     */
    public function it_accepts_an_existing_zone()
    {
        // Arrange

        $this->mockSuccessResponse();

        // Act

        $this->artisan('cloudflare:cache:purge', ['zone' => 'another-identifier']);

        // Assert

        $this->assertCount(1, $this->transactions);

        $zoneB = $this->transactions->first();

        $this->seeRequestContainsPath($zoneB, 'another-identifier');
        $this->seeRequestWithBody($zoneB, ['purge_everything' => true]);

        $this->seeInConsole('another-identifier')
            ->dontSeeInConsole('zone-identifier')
            ->dontSeeInConsole('app.css')
            ->withSuccessCode();
    }

    /**
     * @test
     */
    public function it_accepts_a_custom_zone()
    {
        // Arrange

        $this->mockSuccessResponse();

        // Act

        $this->artisan('cloudflare:cache:purge', ['zone' => 'my-zone']);

        // Assert

        $this->assertCount(1, $this->transactions);

        $customZone = $this->transactions->first();

        $this->seeRequestContainsPath($customZone, 'my-zone');
        $this->seeRequestWithBody($customZone, ['purge_everything' => true]);

        $this->seeInConsole('my-zone')
            ->dontSeeInConsole('zone-identifier')
            ->dontSeeInConsole('app.css')
            ->dontSeeInConsole('scripts')
            ->withSuccessCode();
    }

    /**
     * @test
     */
    public function it_accepts_files()
    {
        // Arrange

        $files = [url('app.css')];

        $this->mockSuccessResponse();
        $this->mockSuccessResponse();

        // Act

        $this->artisan('cloudflare:cache:purge', ['--file' => $files]);

        // Asssert

        $this->assertCount(2, $this->transactions);

        $zoneA = $this->transactions->get(0);
        $zoneB = $this->transactions->get(1);

        $this->seeRequestContainsPath($zoneA, 'zone-identifier');
        $this->seeRequestWithBody($zoneA, compact('files'));

        $this->seeRequestContainsPath($zoneB, 'another-identifier');
        $this->seeRequestWithBody($zoneB, compact('files'));

        $this->seeInConsole('zone-identifier')
            ->seeInConsole('another-identifier')
            ->seeInConsole('app.css')
            ->dontSeeInConsole('scripts')
            ->withSuccessCode();
    }

    /**
     * @test
     */
    public function it_accepts_tags()
    {
        // Arrange

        $tags = ['scripts', 'images'];

        $this->mockSuccessResponse();
        $this->mockSuccessResponse();

        // Act

        $this->artisan('cloudflare:cache:purge', ['--tag' => $tags]);

        // Assert

        $this->assertCount(2, $this->transactions);

        $zoneA = $this->transactions->get(0);
        $zoneB = $this->transactions->get(1);

        $this->seeRequestContainsPath($zoneA, 'zone-identifier');
        $this->seeRequestWithBody($zoneA, compact('tags'));

        $this->seeRequestContainsPath($zoneB, 'another-identifier');
        $this->seeRequestWithBody($zoneB, compact('tags'));

        $this->seeInConsole('zone-identifier')
            ->seeInConsole('another-identifier')
            ->seeInConsole('scripts')
            ->dontSeeInConsole('app.css')
            ->withSuccessCode();
    }

    /**
     * @test
     */
    public function it_accepts_hosts()
    {
        // Arrange

        $hosts = ['images.example.com'];

        $this->mockSuccessResponse();
        $this->mockSuccessResponse();

        // Act

        $this->artisan('cloudflare:cache:purge', ['--host' => $hosts]);

        // Assert

        $this->assertCount(2, $this->transactions);

        $zoneA = $this->transactions->get(0);
        $zoneB = $this->transactions->get(1);

        $this->seeRequestContainsPath($zoneA, 'zone-identifier');
        $this->seeRequestWithBody($zoneA, compact('hosts'));

        $this->seeRequestContainsPath($zoneB, 'another-identifier');
        $this->seeRequestWithBody($zoneB, compact('hosts'));

        $this->seeInConsole('zone-identifier')
            ->seeInConsole('another-identifier')
            ->seeInConsole('images.example.com')
            ->dontSeeInConsole('www.example.com')
            ->withSuccessCode();
    }

    /**
     * @test
     */
    public function it_accepts_files_and_tags_and_hosts()
    {
        // Arrange

        $files = [url('app.css'), url('logo.svg')];
        $tags = ['images'];
        $hosts = ['www.example.com'];

        $this->mockSuccessResponse();
        $this->mockSuccessResponse();

        // Act

        $this->artisan('cloudflare:cache:purge', [
            '--file' => $files,
            '--tag' => $tags,
            '--host' => $hosts,
        ]);

        // Assert

        $this->assertCount(2, $this->transactions);

        $zoneA = $this->transactions->get(0);
        $zoneB = $this->transactions->get(1);

        $this->seeRequestContainsPath($zoneA, 'zone-identifier');
        $this->seeRequestWithBody($zoneA, compact('files', 'tags', 'hosts'));

        $this->seeRequestContainsPath($zoneB, 'another-identifier');
        $this->seeRequestWithBody($zoneB, compact('files', 'tags', 'hosts'));

        $this->seeInConsole('zone-identifier')
            ->seeInConsole('another-identifier')
            ->seeInConsole('app.css')
            ->seeInConsole('logo.svg')
            ->seeInConsole('images')
            ->dontSeeInConsole('scripts')
            ->seeInConsole('www.example.com')
            ->dontSeeInConsole('images.example.com')
            ->withSuccessCode();
    }

    /**
     * @test
     */
    public function it_accepts_a_zone_with_files_and_files_and_tags()
    {
        // Arrange

        $files = [url('app.css'), url('logo.svg')];
        $tags = ['scripts'];
        $hosts = ['images.example.com'];

        $this->mockSuccessResponse();

        // Act

        $this->artisan('cloudflare:cache:purge', [
            'zone' => 'my-zone',
            '--file' => $files,
            '--tag' => $tags,
            '--host' => $hosts,
        ]);

        // Assert

        $this->assertCount(1, $this->transactions);

        $this->seeRequestContainsPath($this->transactions->first(), 'my-zone');
        $this->seeRequestWithBody($this->transactions->first(), compact('files', 'tags', 'hosts'));

        $this->seeInConsole('my-zone')
            ->seeInConsole('app.css')
            ->seeInConsole('logo.svg')
            ->seeInConsole('scripts')
            ->dontSeeInConsole('zone-identifier')
            ->dontSeeInConsole('another-identifier')
            ->seeInConsole('images.example.com')
            ->dontSeeInConsole('www.example.com')
            ->withSuccessCode();
    }
}
