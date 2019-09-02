<?php

namespace Sebdesign\ArtisanCloudflare;

use JsonSerializable;

class Zone implements JsonSerializable
{
    /** @var array */
    private $parameters;

    public function __construct(array $parameters = [])
    {
        $this->parameters = $parameters;
    }

    public function replace(array $parameters)
    {
        $this->parameters = $parameters;
    }

    public function jsonSerialize()
    {
        if (empty($this->parameters)) {
            return ['purge_everything' => true];
        }

        return $this->parameters;
    }

    public function get($key, $default = null)
    {
        if (isset($this->parameters[$key])) {
            return $this->parameters[$key];
        }

        return $default;
    }
}
