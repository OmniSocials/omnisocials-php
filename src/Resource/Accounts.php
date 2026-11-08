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
     * `GET /accounts/:id` - a single connected account. An X account also
     * carries `x_statistics`: posts, followers, post_schedule, source,
     * billing, state and paused_reason (null when it could not be read).
     */
    public function get(string $id): mixed
    {
        return $this->client->get('/accounts/' . $this->encodePathSegment($id));
    }

    /**
     * `PATCH /accounts/:id` - change how often the X statistics of an X
     * account are read: `['x_statistics' => ['posts' => 'light', 'followers' => 'daily']]`.
     * Send one key or more of posts, followers and post_schedule; only the
     * keys you give are sent. `posts` is `full` (5 checks), `light` (2
     * checks) or `off`; `followers` is `daily`, `weekly` or `off`. X bills
     * every read and OmniSocials passes X's price on as credits (half a
     * credit per post check, plus half for a video or GIF, 1 credit per
     * follower count). Needs the opt-in scope `accounts:write` and billing
     * rights (manage_billing) for the key's creator. OmniSocials Connect keys
     * (the partner's own X app pays) also take `posts` `custom` with
     * `post_schedule` (`['at' => ['1h', '1d'], 'then_every' => '7d', 'until' => '90d']`),
     * `followers` `hourly` / `6h`, and `['x_statistics' => null]` (sent as
     * null): follow the customer's setting again.
     *
     * Returns `{ data: account }` with the new `x_statistics`. Errors:
     * `400 validation_error` / `schedule_invalid` / `not_supported` (not an X
     * account), `403 forbidden`, `404 not_found`.
     *
     * @param array{x_statistics: array{posts?: string, followers?: string, post_schedule?: array<string, mixed>}|null} $params
     */
    public function update(string $id, array $params): mixed
    {
        $body = $params;
        if (array_key_exists('x_statistics', $body) && is_array($body['x_statistics'])) {
            // An object on the wire even when empty (an empty PHP array would go out as `[]`).
            $body['x_statistics'] = (object) $body['x_statistics'];
        }
        return $this->client->patch('/accounts/' . $this->encodePathSegment($id), $body);
    }
}
