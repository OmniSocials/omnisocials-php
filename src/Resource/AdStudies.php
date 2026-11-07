<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

/**
 * A/B tests and lift studies on Meta (`$client->ads->studies`).
 */
class AdStudies extends AbstractResource
{
    /**
     * `GET /ads/studies?ad_account_id=` - the studies of an ad account.
     * Requires `ads:read`.
     */
    public function list(int|string $adAccountId): mixed
    {
        return $this->client->get('/ads/studies', ['ad_account_id' => (string) $adAccountId]);
    }

    /**
     * `POST /ads/studies` - create an A/B test between existing campaigns or
     * ad sets (`ad_account_id` and Meta's study fields). Needs a key with the
     * opt-in `ads:spend` scope ("Start ads"); without it, and on Google
     * (`api_draft_only`) always, the call answers `403 start_in_app` with the
     * page where a person does it in the app. Requires `ads:write`.
     *
     * @param array<string, mixed> $params
     */
    public function create(array $params): mixed
    {
        return $this->client->post('/ads/studies', $params);
    }
}
