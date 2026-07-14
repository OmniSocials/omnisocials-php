<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

class Accounts extends AbstractResource
{
    /**
     * `GET /accounts` - the workspace's connected social accounts.
     */
    public function list(): mixed
    {
        return $this->client->get('/accounts');
    }

    /**
     * `GET /accounts/:id` - a single connected account.
     */
    public function get(string $id): mixed
    {
        return $this->client->get('/accounts/' . $this->encodePathSegment($id));
    }
}
