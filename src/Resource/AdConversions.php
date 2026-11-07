<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

/**
 * Conversion tracking (`$client->ads->conversions`): pixels and datasets,
 * the events a campaign can optimise for, custom conversions and events sent
 * from your server.
 */
class AdConversions extends AbstractResource
{
    /**
     * `GET /ads/conversions/destinations?ad_account_id=` - pixels, datasets,
     * conversion tags and conversion actions of an ad account. Requires
     * `ads:read`.
     */
    public function destinations(int|string $adAccountId): mixed
    {
        return $this->client->get('/ads/conversions/destinations', ['ad_account_id' => (string) $adAccountId]);
    }

    /**
     * `GET /ads/conversions/goals?ad_account_id=` - the conversion events a
     * campaign can optimise for (`settings.optimization.conversion`).
     * Requires `ads:read`.
     */
    public function goals(int|string $adAccountId): mixed
    {
        return $this->client->get('/ads/conversions/goals', ['ad_account_id' => (string) $adAccountId]);
    }

    /**
     * `POST /ads/conversions/custom` - create a custom conversion:
     * `ad_account_id`, `name`, `destination_id` and an `event` and/or
     * `url_contains` rule. Requires `ads:write`.
     *
     * @param array{ad_account_id: int|string, name: string, destination_id: string, event?: string, url_contains?: string} $params
     */
    public function createCustom(array $params): mixed
    {
        return $this->client->post('/ads/conversions/custom', $params);
    }

    /**
     * `POST /ads/conversions/events` - send conversion events from your
     * server (Meta Conversions API, TikTok Events API, Pinterest, LinkedIn,
     * X, Google click conversions): `ad_account_id`, `destination_id` and up
     * to 1,000 `events` (`event_name`, `event_time`, `event_id`,
     * `action_source`, `value`, `currency`, `user` with `email`, `phone`,
     * `click_ids` ...). Send the raw email and phone: OmniSocials normalizes
     * and SHA-256 hashes them before they leave and stores nothing. Answers
     * `received`, `accepted` and `errors`. Requires `ads:write`.
     *
     * @param array<string, mixed> $params
     */
    public function sendEvents(array $params): mixed
    {
        return $this->client->post('/ads/conversions/events', $params);
    }
}
