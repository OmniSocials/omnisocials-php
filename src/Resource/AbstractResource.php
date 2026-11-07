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

    /**
     * Query params as the API reads them: a list becomes a comma list
     * (`['active', 'paused']` = `active,paused`); the client drops nulls.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    protected function queryParams(array $params): array
    {
        $out = [];
        foreach ($params as $key => $value) {
            $out[$key] = is_array($value) ? implode(',', array_map('strval', $value)) : $value;
        }
        return $out;
    }

    /**
     * A JSON body, or no body when there is nothing to send (an empty PHP
     * array would go out as `[]`, a list).
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    protected function optionalBody(array $params): ?array
    {
        return $params === [] ? null : $params;
    }
}
