<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

/**
 * Google Ads reports (`$client->ads->google`). Every call takes
 * `ad_account_id` and is a read (`ads:read`). Every API caller stops at the
 * draft on Google, also a key with `ads:spend` (`403 start_in_app`).
 */
class AdGoogle extends AbstractResource
{
    /**
     * `GET /ads/google/search-terms` - the search terms report
     * (`campaign_id` or `external_campaign_id`, `from`, `to`, `limit`).
     *
     * @param array<string, mixed> $params
     */
    public function searchTerms(array $params): mixed
    {
        return $this->read('search-terms', $params);
    }

    /**
     * `GET /ads/google/recommendations` - Google's recommendations
     * (`types`, a list or a comma list).
     *
     * @param array<string, mixed> $params
     */
    public function recommendations(array $params): mixed
    {
        return $this->read('recommendations', $params);
    }

    /**
     * `GET /ads/google/keywords` - search keywords and negative keywords
     * (`external_campaign_id`, `external_ad_set_id`).
     *
     * @param array<string, mixed> $params
     */
    public function keywords(array $params): mixed
    {
        return $this->read('keywords', $params);
    }

    /**
     * `GET /ads/google/assets` - sitelinks, callouts and structured snippets
     * (`type`).
     *
     * @param array<string, mixed> $params
     */
    public function assets(array $params): mixed
    {
        return $this->read('assets', $params);
    }

    /**
     * `GET /ads/google/shared-budgets` - shared budgets.
     *
     * @param array{ad_account_id: int|string} $params
     */
    public function sharedBudgets(array $params): mixed
    {
        return $this->read('shared-budgets', $params);
    }

    /**
     * `GET /ads/google/bid-strategies` - portfolio bid strategies.
     *
     * @param array{ad_account_id: int|string} $params
     */
    public function bidStrategies(array $params): mixed
    {
        return $this->read('bid-strategies', $params);
    }

    /**
     * `GET /ads/google/ad-schedule` - the days and hours of a campaign
     * (`external_campaign_id`).
     *
     * @param array{ad_account_id: int|string, external_campaign_id: string} $params
     */
    public function adSchedule(array $params): mixed
    {
        return $this->read('ad-schedule', $params);
    }

    /**
     * `GET /ads/google/labels` - labels.
     *
     * @param array{ad_account_id: int|string} $params
     */
    public function labels(array $params): mixed
    {
        return $this->read('labels', $params);
    }

    /**
     * `GET /ads/google/local-services-leads` - Local Services leads
     * (`since`).
     *
     * @param array{ad_account_id: int|string, since?: string} $params
     */
    public function localServicesLeads(array $params): mixed
    {
        return $this->read('local-services-leads', $params);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function read(string $report, array $params): mixed
    {
        return $this->client->get('/ads/google/' . $report, $this->queryParams($params));
    }
}
