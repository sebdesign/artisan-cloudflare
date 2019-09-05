# Laravel artisan commands for Cloudflare

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sebdesign/artisan-cloudflare.svg?style=flat-square)](https://packagist.org/packages/sebdesign/artisan-cloudflare)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/sebdesign/artisan-cloudflare/master.svg?style=flat-square)](https://travis-ci.org/sebdesign/artisan-cloudflare)
[![Quality Score](https://img.shields.io/scrutinizer/g/sebdesign/artisan-cloudflare.svg?style=flat-square)](https://scrutinizer-ci.com/g/sebdesign/artisan-cloudflare)
[![Scrutinizer Coverage](https://img.shields.io/scrutinizer/coverage/g/sebdesign/artisan-cloudflare.svg?style=flat-square)]()
[![StyleCI](https://styleci.io/repos/74228812/shield?style=flat-square)](https://styleci.io/repos/74228812)

Laravel artisan commands for interacting with the CloudFlare API.

![artisan-cloudflare.png](https://s13.postimg.cc/6cukzjf53/artisan-cloudflare.png)

## Installation

You can install the package via composer. This package requires Laravel 5.5 or higher.

``` bash
composer require sebdesign/artisan-cloudflare
```

Since version 5.5, Laravel uses package auto-discovery, so doesn't require you to manually add the ServiceProvider. If you don't use auto-discovery or you are using an older version, add the service provider in `config/app.php`.

``` php
<?php

'providers' => [
    Sebdesign\ArtisanCloudflare\ServiceProvider::class,
],
```

## Configuration

Publish the config file in `config/cloudflare.php` and set your `CLOUDFLARE_KEY` and `CLOUDFLARE_EMAIL` in the `.env`.

``` bash
php artisan vendor:publish --provider="Sebdesign\ArtisanCloudflare\ServiceProvider"
```

The following options are available:

``` php
<?php

return [

    /**
     * API key generated on the "My Account" page.
     */
    'key' => env('CLOUDFLARE_KEY'),

    /**
     * Email address associated with your account.
     */
    'email' => env('CLOUDFLARE_EMAIL'),

    /**
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

```

## Usage

> Read more about [Purging cached resources from Cloudflare
](https://support.cloudflare.com/hc/en-us/articles/200169246-Purging-cached-resources-from-Cloudflare) on the support article.

Execute the `cloudflare:cache:purge` command in your console or integrate it in your deployment workflow.

#### Purge all the zones with their files and tags.

``` bash
php artisan cloudflare:cache:purge
```

#### Purge a single zone.

If the zone exists in the config, then its files and tags will be purged. Otherwise everything will be purged from the given zone.

``` bash
php artisan cloudflare:cache:purge 023e105f4ecef8ad9ca31a8372d0c353
```

#### Purge individual files from all the zones.

``` bash
php artisan cloudflare:cache:purge --file="http://example.com/css/app.css" --file="http://example.com/img/logo.svg"
```

#### Purge individual tags from all the zones.

Purging tags is available for **Enterprise** accounts only.

``` bash
php artisan cloudflare:cache:purge --tag=styles --tag=scripts
```

#### Purge individual hosts from all the zones.

Purging hosts is available for **Enterprise** accounts only.

``` bash
php artisan cloudflare:cache:purge --host=www.example.com --host=images.example.com
```

#### Purge individual files, tags, and hosts from all the zones.

``` bash
php artisan cloudflare:cache:purge --file="http://example.com/css/app.css" --tag=scripts --tag=images --host=www.example.com
```

#### Purge individual files, tags, and hosts from a single zone.

``` bash
php artisan cloudflare:cache:purge 023e105f4ecef8ad9ca31a8372d0c353 --file="http://example.com/css/app.css" --tag=scripts --tag=images --host=www.example.com
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Testing

``` bash
composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email info@sebdesign.eu instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
