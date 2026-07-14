<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

use OmniSocials\Client;

abstract class AbstractResource
{
    public function __construct(protected readonly Client $client)
    {
    }

    /**
     * Percent-encode a path segment (ids in URLs).
     */
    protected function encodePathSegment(string $segment): string
    {
        return rawurlencode($segment);
    }
}
