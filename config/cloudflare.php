<?php

return [

    /*
     * API key generated on the "My Account" page.
     */
    'key' => env('CLOUDFLARE_KEY'),

    /*
     * Email address associated with your account.
     */
    'email' => env('CLOUDFLARE_EMAIL'),

    /*
     * Array of zones.
     *
     * Each zone must have its identifier as a key. The value is an
     * associated array with *optional* arrays of files and/or tags.
     * If nothing is provided, then everything will be purged.
     *
     * E.g.
     *
     * '023e105f4ecef8ad9ca31a8372d0c353' => [
     *      'files' => [
     *          'http://example.com/css/app.css',
     *      ],
     *      'tags' => [
     *          'styles',
     *          'scripts',
     *      ],
     *      'hosts' => [
     *          'www.example.com',
     *          'images.example.com',
     *      ],
     * ],
     */
    'zones' => [
        //
    ],
];
