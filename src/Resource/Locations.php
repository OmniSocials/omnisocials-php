<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

class Locations extends AbstractResource
{
    /**
     * `GET /locations/search` - search locations for place tagging.
     *
     * `platform` is `instagram` (default) or `threads`, and the two sources
     * use DIFFERENT ids: a Facebook Place ID is not a Threads location id.
     *
     * Instagram: pass `$query` to search Facebook Places; use a result's
     * `id` as `location_id` on a post. Response:
     * `{ data: [...], error?, needsPermission? }` (`error` is a plain string
     * on the degraded path).
     *
     * Threads: pass `$query`, or `latitude` (-90..90) plus `longitude`
     * (-180..180) in `$params` to search around a point instead of a
     * keyword. Response: `{ locations: [{ id, name, address, city, country,
     * latitude, longitude }] }` (all fields but `id` nullable), or
     * `{ error: { code, message } }` with `code` one of `not_available`
     * (Threads location tagging not enabled in this environment yet),
     * `threads_not_connected`, `threads_reauth_required` (the connection
     * lacks the `threads_location_tagging` permission; reconnect Threads),
     * or `platform_error`. Validation problems (neither `q` nor lat+lng,
     * `q` under 2 chars, coordinates out of range) throw a 400
     * `ValidationException`. Use a result's `id` as `threads.location_id`
     * on a post.
     *
     * Threads location tagging is currently rolling out; until Meta
     * approves the permissions it is disabled on production and calls
     * return a clear error.
     *
     * @param array{platform?: 'instagram'|'threads', latitude?: float, longitude?: float} $params
     */
    public function search(?string $query = null, array $params = []): mixed
    {
        return $this->client->get('/locations/search', [
            'q' => $query,
            'platform' => $params['platform'] ?? null,
            'latitude' => $params['latitude'] ?? null,
            'longitude' => $params['longitude'] ?? null,
        ]);
    }

    /**
     * `GET /locations/validate?id=` - check whether a Facebook Place id is a
     * valid Instagram location before using it as `location_id`.
     */
    public function validate(string $id): mixed
    {
        return $this->client->get('/locations/validate', ['id' => $id]);
    }
}
