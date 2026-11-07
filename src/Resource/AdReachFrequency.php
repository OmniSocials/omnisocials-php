<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

use OmniSocials\Client;

/**
 * Reach and frequency buying on Meta (`$client->ads->reachFrequency`). A
 * prediction spends nothing; a reservation commits the budget.
 */
class AdReachFrequency extends AbstractResource
{
    /**
     * `POST /ads/reach-frequency` - create a prediction: `ad_account_id`,
     * `objective` (`awareness`, `engagement` or `video_views`), `targeting`
     * (exactly one country in `countries`, optional `age_min`, `age_max`,
     * `genders`), `start_at`, `end_at`, and `budget` (major units) or
     * `reach` (people to reach), optional `frequency_cap` and
     * `frequency_interval_days`. Meta needs the reach and frequency
     * capability on the ad account. Spends nothing. Requires `ads:write`.
     *
     * @param array{ad_account_id: int|string, objective: string, targeting: array<string, mixed>, start_at: string, end_at: string, budget?: array<string, mixed>, reach?: int, frequency_cap?: int, frequency_interval_days?: int} $params
     */
    public function create(array $params): mixed
    {
        return $this->client->post('/ads/reach-frequency', $params);
    }

    /**
     * `GET /ads/reach-frequency/:predictionId?ad_account_id=` - read a
     * prediction. Requires `ads:read`.
     */
    public function get(string $predictionId, int|string $adAccountId): mixed
    {
        return $this->client->get($this->predictionPath($predictionId), ['ad_account_id' => (string) $adAccountId]);
    }

    /**
     * `DELETE /ads/reach-frequency/:predictionId?ad_account_id=` - cancel a
     * prediction or a reservation. Requires `ads:write`.
     */
    public function cancel(string $predictionId, int|string $adAccountId): mixed
    {
        return $this->client->delete($this->predictionPath($predictionId), ['ad_account_id' => (string) $adAccountId]);
    }

    /**
     * `POST /ads/reach-frequency/:predictionId/reserve` - reserve a
     * prediction: it COMMITS REAL AD SPEND. Show the person the prediction's
     * budget and get their yes first; `$confirmBudgetAmount` must equal it.
     * Needs a key with the opt-in `ads:spend` scope ("Start ads"); without
     * it, and on Google (`api_draft_only`) always, the call answers
     * `403 start_in_app` with the page where a person does it in the app. Not
     * retried after a 5xx, a timeout or a lost connection: read the
     * prediction with `get()` to see what happened. Requires `ads:write`.
     */
    public function reserve(string $predictionId, int|string $adAccountId, int|float $confirmBudgetAmount): mixed
    {
        return $this->client->post(
            $this->predictionPath($predictionId) . '/reserve',
            ['ad_account_id' => $adAccountId, 'confirm_budget_amount' => $confirmBudgetAmount],
            Client::RETRY_RATE_LIMIT
        );
    }

    private function predictionPath(string $predictionId): string
    {
        return '/ads/reach-frequency/' . $this->encodePathSegment($predictionId);
    }
}
