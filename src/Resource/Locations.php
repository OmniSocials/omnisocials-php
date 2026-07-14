<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

class Locations extends AbstractResource
{
    /**
     * `GET /locations/search?q=` - search Facebook Places for Instagram
     * location tagging. Use a result's `id` as `location_id` on a post.
     */
    public function search(string $query): mixed
    {
        return $this->client->get('/locations/search', ['q' => $query]);
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
