<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

/**
 * Targeting options of a network (`$client->ads->targeting`). The ids go into
 * `targeting.networks.<network>.include` / `exclude` of a draft, or
 * `targeting.network` of a campaign. `ads->capabilities()` lists the
 * `targeting_types` per network. All four calls are reads (`ads:read`).
 */
class AdTargeting extends AbstractResource
{
    /**
     * `GET /ads/targeting/search` - search interests, places, languages, job
     * titles and more: `ad_account_id`, `type`, `q`, optional `limit` (up to
     * 100) and `country`. Answers `items` with `id`, `name`, `type`, `path`
     * and the audience size range.
     *
     * @param array{ad_account_id: int|string, type: string, q: string, limit?: int, country?: string} $params
     */
    public function search(array $params): mixed
    {
        return $this->client->get('/ads/targeting/search', $this->queryParams($params));
    }

    /**
     * `GET /ads/targeting/browse` - the targeting categories of one `type`
     * as a tree.
     *
     * @param array{ad_account_id: int|string, type: string} $params
     */
    public function browse(array $params): mixed
    {
        return $this->client->get('/ads/targeting/browse', $this->queryParams($params));
    }

    /**
     * `POST /ads/targeting/reach-estimate` - the audience size of a
     * targeting (`ad_account_id`, `targeting` with `countries`, `age_min`,
     * `age_max`, `genders` and `network`, optional `objective`). Answers
     * `lower`, `upper` and `daily`.
     *
     * @param array<string, mixed> $params
     */
    public function reachEstimate(array $params): mixed
    {
        return $this->client->post('/ads/targeting/reach-estimate', $params);
    }

    /**
     * `POST /ads/targeting/bid-suggestions` - a suggested bid and daily
     * budget for a targeting (`ad_account_id`, `targeting`, optional
     * `objective` and `budget`). Spends nothing.
     *
     * @param array<string, mixed> $params
     */
    public function bidSuggestions(array $params): mixed
    {
        return $this->client->post('/ads/targeting/bid-suggestions', $params);
    }
}
