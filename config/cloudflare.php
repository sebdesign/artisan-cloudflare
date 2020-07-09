<?php

return [

    /*
     * API token generated from the User Profile 'My Profile > Api Tokens > API Tokens' page.
     * create token > Edit zone DNS > "Permissions" Zone:Cache Purge:Purge
     */
    'token' => env('CLOUDFLARE_TOKEN'),

    /*
     * Global API Key on the "My Profile > Api Tokens > API Keys" page.
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
     *
     * you can find your zoneId under 'Account Home > site > Api'.
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
