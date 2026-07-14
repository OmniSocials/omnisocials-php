<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

class Folders extends AbstractResource
{
    /**
     * `GET /folders` - all folders (flat list; build the tree via `parent_id`).
     */
    public function list(): mixed
    {
        return $this->client->get('/folders');
    }

    /**
     * `POST /folders` - create a folder (optionally nested under `parent_id`).
     *
     * @param array{name: string, parent_id?: string} $params
     */
    public function create(array $params): mixed
    {
        return $this->client->post('/folders', $params);
    }

    /**
     * `PATCH /folders/:id` - rename (`name`) and/or move (`parent_id`, or
     * null for the top level). Cycles are rejected.
     *
     * @param array<string, mixed> $params
     */
    public function update(string $id, array $params): mixed
    {
        return $this->client->patch('/folders/' . $this->encodePathSegment($id), $params);
    }

    /**
     * `DELETE /folders/:id` - delete a folder. Media is preserved: files move
     * to the root and subfolders move up to the deleted folder's parent.
     * Returns null (204).
     */
    public function delete(string $id): mixed
    {
        return $this->client->delete('/folders/' . $this->encodePathSegment($id));
    }
}
