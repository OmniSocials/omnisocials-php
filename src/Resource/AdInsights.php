<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

/**
 * Numbers (`$client->ads->insights`): live numbers from the network,
 * campaign and ad numbers, the daily timeline, rankings and alerts. Long
 * reports that run in the background are in `$client->ads->reports`. Money is in major units; `ctr` is a percent. All calls
 * are reads (`ads:read`).
 */
class AdInsights extends AbstractResource
{
    /**
     * `POST /ads/insights` - live numbers from the network: `ad_account_id`,
     * `level` (`account`, `campaign` (default), `ad_set`, `ad`), `ids` (the
     * network's ids of that level), `metrics`, up to 2 `breakdowns`
     * (`country`, `age`, `gender`, `placement`, `device`, `platform`),
     * `from`, `to` and `time_increment` (`day` or `all`). Answers `rows`.
     *
     * @param array<string, mixed> $params
     */
    public function query(array $params): mixed
    {
        return $this->client->post('/ads/insights', $params);
    }

    /**
     * `GET /ads/campaigns/:id/analytics` - numbers of an OmniSocials
     * campaign: `from`, `to`, `breakdown` (`ad` gives one row per ad, or
     * `country`, `age`, `gender`, `placement`, `device`, `platform`),
     * `time_increment` and `metrics` (a list or a comma list).
     *
     * @param array{from?: string, to?: string, breakdown?: string, time_increment?: string, metrics?: string|list<string>} $params
     */
    public function campaign(int|string $campaignId, array $params = []): mixed
    {
        return $this->client->get(
            '/ads/campaigns/' . $this->encodePathSegment((string) $campaignId) . '/analytics',
            $this->queryParams($params)
        );
    }

    /**
     * `GET /ads/campaigns/:id/ads/:adId/analytics` - numbers of one ad
     * (`from`, `to`, `breakdown`).
     *
     * @param array{from?: string, to?: string, breakdown?: string} $params
     */
    public function ad(int|string $campaignId, int|string $adId, array $params = []): mixed
    {
        return $this->client->get(
            '/ads/campaigns/' . $this->encodePathSegment((string) $campaignId)
                . '/ads/' . $this->encodePathSegment((string) $adId) . '/analytics',
            $this->queryParams($params)
        );
    }

    /**
     * `GET /ads/timeline` - daily totals of the workspace, OmniSocials and
     * imported campaigns: `from`, `to`, `platform`, `source` (`all`,
     * `omnisocials`, `external`).
     *
     * @param array{from?: string, to?: string, platform?: string, source?: string} $params
     */
    public function timeline(array $params = []): mixed
    {
        return $this->client->get('/ads/timeline', $this->queryParams($params));
    }

    /**
     * `GET /ads/performance` - campaigns (over a period) or ads (latest
     * numbers) ranked by one metric: `level`, `metric`, `order`, `limit`,
     * `min_impressions`, `from`, `to`, `platform`.
     *
     * @param array<string, mixed> $params
     */
    public function performance(array $params = []): mixed
    {
        return $this->client->get('/ads/performance', $this->queryParams($params));
    }

    /**
     * `GET /ads/alerts` - what needs attention: rejected ads, held
     * campaigns, sign-in or payment problems, no delivery.
     */
    public function alerts(): mixed
    {
        return $this->client->get('/ads/alerts');
    }
}
