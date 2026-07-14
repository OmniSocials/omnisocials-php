<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

class Webhooks extends AbstractResource
{
    /**
     * `GET /webhooks` - list the workspace's webhook endpoints.
     */
    public function list(): mixed
    {
        return $this->client->get('/webhooks');
    }

    /**
     * `GET /webhooks/:id` - fetch a single webhook.
     */
    public function get(string $id): mixed
    {
        return $this->client->get('/webhooks/' . $this->encodePathSegment($id));
    }

    /**
     * `POST /webhooks` - register an endpoint for event deliveries
     * (post.scheduled, post.published, post.failed). The response includes
     * the signing `secret`; save it, it is only shown once.
     *
     * @param array{url: string, events: string[]} $params
     */
    public function create(array $params): mixed
    {
        return $this->client->post('/webhooks', $params);
    }

    /**
     * `PATCH /webhooks/:id` - change the URL, events, or active state.
     *
     * @param array{url?: string, events?: string[], is_active?: bool} $params
     */
    public function update(string $id, array $params): mixed
    {
        return $this->client->patch('/webhooks/' . $this->encodePathSegment($id), $params);
    }

    /**
     * `DELETE /webhooks/:id` - delete a webhook. Returns null (204).
     */
    public function delete(string $id): mixed
    {
        return $this->client->delete('/webhooks/' . $this->encodePathSegment($id));
    }

    /**
     * `POST /webhooks/:id/rotate-secret` - generate a new signing secret.
     * Save the returned secret; it is only shown once.
     */
    public function rotateSecret(string $id): mixed
    {
        return $this->client->post('/webhooks/' . $this->encodePathSegment($id) . '/rotate-secret');
    }
}
