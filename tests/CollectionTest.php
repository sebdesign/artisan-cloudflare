<?php

namespace Sebdesign\ArtisanCloudflare\Test;

use Illuminate\Support\Collection;

class CollectionTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->registerServiceProvider();
    }

    /**
     * @test
     */
    public function it_transposes_a_collection(): void
    {
        // Arrange

        $zones = [
            'identifiers' => [
                'zone-a',
                'zone-b',
                'zone-c',
            ],
            'files' => [
                ['app.css', 'app.js'],
                ['main.css', 'main.js'],
                ['style.css', 'scripts.css'],
            ],
            'tags' => [
                ['styles', 'scripts'],
                ['images', 'icons'],
                ['fonts', 'media'],
            ],
        ];

        $expected = [
            Collection::make([
                'identifiers' => 'zone-a',
                'files' => ['app.css', 'app.js'],
                'tags' => ['styles', 'scripts'],
            ]),
            Collection::make([
                'identifiers' => 'zone-b',
                'files' => ['main.css', 'main.js'],
                'tags' => ['images', 'icons'],
            ]),
            Collection::make([
                'identifiers' => 'zone-c',
                'files' => ['style.css', 'scripts.css'],
                'tags' => ['fonts', 'media'],
            ]),
        ];

        // Act

        $actual = Collection::make($zones)->_transpose();

        // Assert

        $this->assertEquals(Collection::make($expected), $actual);
    }

    /**
     * @test
     */
    public function it_inserts_a_value_between_items(): void
    {
        // Arrange

        $table = [
            'Row A',
            'Row B',
            'Row C',
        ];

        $expected = [
            'Row A',
            '-----',
            'Row B',
            '-----',
            'Row C',
        ];

        // Act

        $actual = Collection::make($table)->insertBetween('-----');

        // Assert

        $this->assertEquals(Collection::make($expected), $actual);
    }

    /**
     * @test
     */
    public function it_reorders_a_collection(): void
    {
        // Arrange

        $items = [
            'bar' => 'first',
            'baz' => 'second',
            'foo' => 'third',
        ];

        $expected = [
            'foo' => 'third',
            'bar' => 'first',
            'baz' => 'second',
        ];

        $keys = ['foo', 'bar', 'baz'];

        // Act

        $actual = Collection::make($items)->reorder($keys);

        // Assert

        $this->assertEquals(Collection::make($expected), $actual);
    }
}
