<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

use OmniSocials\Client;

/**
 * Planned budget increases of a campaign for high-demand periods on Meta
 * (`$client->ads->budgetSchedules`).
 */
class AdBudgetSchedules extends AbstractResource
{
    /**
     * `GET /ads/campaigns/:id/budget-schedules` - the planned increases.
     * Requires `ads:read`.
     */
    public function list(int|string $campaignId): mixed
    {
        return $this->client->get($this->schedulesPath($campaignId));
    }

    /**
     * `POST /ads/campaigns/:id/budget-schedules` - plan a budget increase:
     * `start_at`, `end_at`, `budget_increase` (`type` `absolute` = the extra
     * per day, or `multiplier` = a whole percentage on top, 50 = 50% more;
     * `value`) and `confirm_budget_amount`.
     *
     * It is NEW AD SPEND in that period: show the person the amount and get
     * their yes first; `confirm_budget_amount` = the daily total during the
     * period (the daily budget plus the increase), under the OmniSocials
     * daily cap. Needs a key with the opt-in `ads:spend` scope ("Start ads");
     * without it, and on Google (`api_draft_only`) always, the call answers
     * `403 start_in_app` with the page where a person does it in the app. Not
     * retried after a 5xx, a timeout or a lost connection: read `list()` to
     * see what happened. Requires `ads:write`.
     *
     * @param array{start_at: string, end_at: string, budget_increase: array{type: string, value: int|float}, confirm_budget_amount: int|float} $params
     */
    public function create(int|string $campaignId, array $params): mixed
    {
        return $this->client->post($this->schedulesPath($campaignId), $params, Client::RETRY_RATE_LIMIT);
    }

    /**
     * `DELETE /ads/campaigns/:id/budget-schedules/:scheduleId` - remove a
     * planned increase. Requires `ads:write`.
     */
    public function delete(int|string $campaignId, string $scheduleId): mixed
    {
        return $this->client->delete($this->schedulesPath($campaignId) . '/' . $this->encodePathSegment($scheduleId));
    }

    private function schedulesPath(int|string $campaignId): string
    {
        return '/ads/campaigns/' . $this->encodePathSegment((string) $campaignId) . '/budget-schedules';
    }
}
