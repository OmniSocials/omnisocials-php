<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

/**
 * Your own numbers per campaign and day (`$client->ads->results`): qualified
 * leads, sales, revenue. Rules read them as `results.<metric>`, formulas as
 * `formula.<key>`.
 */
class AdResults extends AbstractResource
{
    /**
     * `GET /ads/campaign-results` - your numbers (`from`, `to`,
     * `campaign_id`, `metric`). Requires `ads:read`.
     *
     * @param array{from?: string, to?: string, campaign_id?: int|string, metric?: string} $params
     */
    public function list(array $params = []): mixed
    {
        return $this->client->get('/ads/campaign-results', $this->queryParams($params));
    }

    /**
     * `POST /ads/campaign-results` - upload `rows` (each `day`, `metric`,
     * `value` and the campaign as `campaign_id`, `external_campaign_id` +
     * `platform`, `utm_campaign` or `campaign_name`; at most 5,000). Sending
     * a day again replaces it. Answers `upload_id`, `received`, `stored`,
     * `matched_to_campaigns`. Requires `ads:write`.
     *
     * @param array{rows: list<array<string, mixed>>} $params
     */
    public function upload(array $params): mixed
    {
        return $this->client->post('/ads/campaign-results', $params);
    }

    /**
     * `GET /ads/campaign-results/totals` - totals over a period with the
     * formulas (`from`, `to`, `campaign_id`). Requires `ads:read`.
     *
     * @param array{from?: string, to?: string, campaign_id?: int|string} $params
     */
    public function totals(array $params = []): mixed
    {
        return $this->client->get('/ads/campaign-results/totals', $this->queryParams($params));
    }

    /** `GET /ads/campaign-results/metrics` - your metric names and formulas. Requires `ads:read`. */
    public function metrics(): mixed
    {
        return $this->client->get('/ads/campaign-results/metrics');
    }

    /**
     * `PUT /ads/campaign-results/metrics/:key` - name a metric (`label`,
     * `unit` `number`/`money`/`percent`) or define a `formula` with numbers,
     * `+ - * / ( )` and `spend`, `impressions`, `clicks`, `conversions`,
     * `leads`, `results_<key>` (for example `results_revenue / spend`).
     * Requires `ads:write`.
     *
     * @param array{label?: string, unit?: string, formula?: string} $params
     */
    public function putMetric(string $key, array $params): mixed
    {
        return $this->client->put('/ads/campaign-results/metrics/' . $this->encodePathSegment($key), $params);
    }
}
