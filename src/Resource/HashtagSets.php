<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

class HashtagSets extends AbstractResource
{
    /**
     * `GET /hashtag-sets` - list the workspace's saved hashtag sets.
     */
    public function list(): mixed
    {
        return $this->client->get('/hashtag-sets');
    }

    /**
     * `GET /hashtag-sets/:id` - fetch a single hashtag set.
     */
    public function get(string $id): mixed
    {
        return $this->client->get('/hashtag-sets/' . $this->encodePathSegment($id));
    }

    /**
     * `POST /hashtag-sets` - create a hashtag set. `hashtags` is an array of
     * tags, or a single string of tags. Apply the set on posts->create via
     * `hashtag_set` (name, case-insensitive) or `hashtag_set_id`.
     *
     * @param array{name: string, hashtags: string[]|string} $params
     */
    public function create(array $params): mixed
    {
        return $this->client->post('/hashtag-sets', $params);
    }

    /**
     * `PATCH /hashtag-sets/:id` - rename (`name`) and/or replace the tags
     * (`hashtags` replaces the FULL list).
     *
     * @param array{name?: string, hashtags?: string[]|string} $params
     */
    public function update(string $id, array $params): mixed
    {
        return $this->client->patch('/hashtag-sets/' . $this->encodePathSegment($id), $params);
    }

    /**
     * `DELETE /hashtag-sets/:id` - delete a hashtag set. Returns null (204).
     */
    public function delete(string $id): mixed
    {
        return $this->client->delete('/hashtag-sets/' . $this->encodePathSegment($id));
    }
}
