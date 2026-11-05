<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

use OmniSocials\Client;

/**
 * Boosts, campaigns and "Create an ad" drafts on Meta, Pinterest, TikTok,
 * LinkedIn, X and Google.
 *
 * Ads spend real money once a campaign starts. Boosts, campaigns and drafts
 * are created as drafts (no spend); `activate()`, `activateMulti()`,
 * `drafts->launch()`, `resume()` and a budget raise through `update()` start
 * or raise spend, and each needs the amount again as a confirmation. These
 * calls are never retried after a 5xx, a timeout or a lost connection (only
 * a 429), because the first attempt may have started the ad. Budgets are in
 * major currency units (10 = 10 EUR) in the ad account's currency. Ad
 * accounts are connected and picked in the OmniSocials dashboard. The scopes
 * `ads:read` and `ads:write` are opt-in on the API key (the Ads switch).
 */
class Ads extends AbstractResource
{
    /** "Create an ad" drafts. */
    public readonly AdDrafts $drafts;

    public function __construct(Client $client)
    {
        parent::__construct($client);
        $this->drafts = new AdDrafts($client);
    }

    /**
     * `GET /ads/accounts` - the connected ad accounts, as `data` and
     * `platforms_available`. Accounts are connected and picked in the
     * dashboard; only `is_selected` accounts can run campaigns. Requires
     * `ads:read` (opt-in on the API key).
     */
    public function listAccounts(): mixed
    {
        return $this->client->get('/ads/accounts');
    }

    /**
     * `GET /ads/capabilities` - what each network accepts for a new ad
     * (goals, media, text limits, buttons, minimum daily budget),
     * `create_networks` and `network_states` (`live`, `preview` or
     * `coming_soon`). Requires `ads:read`.
     */
    public function capabilities(): mixed
    {
        return $this->client->get('/ads/capabilities');
    }

    /**
     * `GET /ads/campaigns` - list campaigns and boosts, optionally by
     * `status`, `platform` or `campaign_type` (`campaign` or `boost`);
     * `limit` is 1 to 100 (default 25). Requires `ads:read`.
     *
     * @param array{status?: string, platform?: string, campaign_type?: string, limit?: int, offset?: int} $params
     */
    public function list(array $params = []): mixed
    {
        return $this->client->get('/ads/campaigns', [
            'status' => $params['status'] ?? null,
            'platform' => $params['platform'] ?? null,
            'campaign_type' => $params['campaign_type'] ?? null,
            'limit' => $params['limit'] ?? null,
            'offset' => $params['offset'] ?? null,
        ]);
    }

    /**
     * `GET /ads/campaigns/:id` - one campaign with its latest numbers.
     * Requires `ads:read`.
     */
    public function get(int|string $id): mixed
    {
        return $this->client->get($this->campaignPath($id));
    }

    /**
     * `POST /ads/boost` - boost a published post. Creates a DRAFT boost: no
     * spend until `activate()`. Takes `post_id`, `ad_account_id`, `budget`
     * (`['type' => 'daily', 'amount' => 5, 'currency' => 'EUR']` in the ad
     * account's currency) and optionally `objective`, `targeting`,
     * `schedule`, `creative` and `name`. TikTok needs
     * `creative.spark_auth_code`, Google needs `headlines`, `descriptions`
     * and `logo_url`. Requires `ads:write`.
     *
     * @param array<string, mixed> $params
     */
    public function boost(array $params): mixed
    {
        return $this->client->post('/ads/boost', $params);
    }

    /**
     * `POST /ads/boost-multi` - one DRAFT boost per ad account in
     * `ad_account_ids` (up to 10), each with the same budget, so the total
     * is amount x accounts. Answers `results`; one failed account never
     * stops the others. Requires `ads:write`.
     *
     * @param array<string, mixed> $params
     */
    public function boostMulti(array $params): mixed
    {
        return $this->client->post('/ads/boost-multi', $params);
    }

    /**
     * `POST /ads/campaigns` - a DRAFT campaign without ads (`ad_account_id`,
     * `name`, `budget`, optionally `objective`, `targeting`, `schedule`). It
     * cannot start (`campaign_has_no_ads`): use `ads->drafts->create()` for
     * a new ad. Requires `ads:write`.
     *
     * @param array<string, mixed> $params
     */
    public function create(array $params): mixed
    {
        return $this->client->post('/ads/campaigns', $params);
    }

    /**
     * `PATCH /ads/campaigns/:id` - change the `name`, `budget`, `targeting`
     * or `schedule`.
     *
     * A higher budget on a running campaign RAISES REAL AD SPEND: show the
     * person the new amount and get their yes first, then pass it again as
     * `confirm_budget_amount`. Not retried after a 5xx, a timeout or a lost
     * connection: read the campaign with `get()` to see what happened.
     * Requires `ads:write`.
     *
     * @param array<string, mixed> $params
     */
    public function update(int|string $id, array $params): mixed
    {
        return $this->client->patch($this->campaignPath($id), $params, Client::RETRY_RATE_LIMIT);
    }

    /**
     * `POST /ads/campaigns/:id/activate` - START REAL AD SPEND.
     *
     * Show the person the budget (amount, currency, daily or lifetime) and
     * get their yes first; `$confirmBudgetAmount` must equal the campaign's
     * budget amount. A campaign of a draft group answers
     * `409 use_draft_launch`: use `ads->drafts->launch()`. Not retried after
     * a 5xx, a timeout or a lost connection: read the campaign with `get()`
     * to see what happened. Requires `ads:write`.
     */
    public function activate(int|string $id, int|float $confirmBudgetAmount): mixed
    {
        return $this->client->post(
            $this->campaignPath($id) . '/activate',
            ['confirm_budget_amount' => $confirmBudgetAmount],
            Client::RETRY_RATE_LIMIT
        );
    }

    /**
     * `POST /ads/campaigns/activate-multi` - START REAL AD SPEND on up to 10
     * campaigns.
     *
     * Every campaign's budget must equal `$confirmBudgetAmount`; show the
     * person the total (amount x campaigns) and get their yes first.
     * Answers `results`. Not retried after a 5xx, a timeout or a lost
     * connection: read the campaigns with `get()` to see what happened.
     * Requires `ads:write`.
     *
     * @param list<int|string> $campaignIds
     */
    public function activateMulti(array $campaignIds, int|float $confirmBudgetAmount): mixed
    {
        return $this->client->post(
            '/ads/campaigns/activate-multi',
            ['campaign_ids' => array_values($campaignIds), 'confirm_budget_amount' => $confirmBudgetAmount],
            Client::RETRY_RATE_LIMIT
        );
    }

    /**
     * `POST /ads/campaigns/:id/pause` - pause a campaign (also while in
     * review). Requires `ads:write`.
     */
    public function pause(int|string $id): mixed
    {
        return $this->client->post($this->campaignPath($id) . '/pause');
    }

    /**
     * `POST /ads/campaigns/:id/resume` - resume a paused campaign: it STARTS
     * REAL AD SPEND again. Show the person the budget and get their yes
     * first. Not retried after a 5xx, a timeout or a lost connection: read
     * the campaign with `get()` to see what happened. Requires `ads:write`.
     */
    public function resume(int|string $id): mixed
    {
        return $this->client->post($this->campaignPath($id) . '/resume', null, Client::RETRY_RATE_LIMIT);
    }

    /**
     * `DELETE /ads/campaigns/:id` - delete a campaign on the network too. A
     * campaign that spent is archived: its spend stays in the totals.
     * Requires `ads:write`.
     */
    public function delete(int|string $id): mixed
    {
        return $this->client->delete($this->campaignPath($id));
    }

    /**
     * `GET /ads/overview` - accounts, campaigns per status and total spend.
     * Requires `ads:read`.
     */
    public function overview(): mixed
    {
        return $this->client->get('/ads/overview');
    }

    /**
     * `GET /ads/suggestions` - organic posts that do better than the
     * workspace median: boost candidates. Read-only. Requires `ads:read`.
     */
    public function suggestions(): mixed
    {
        return $this->client->get('/ads/suggestions');
    }

    /**
     * `POST /ads/research/keywords` - keyword ideas from seed `keywords` (up
     * to 20), a `url` or both, through the connected Google Ads account.
     * `country` defaults to `US`, `language` to `en`, `limit` is 1 to 500
     * (default 100); `ad_account_id` only when the workspace has more than
     * one Google Ads account. Read-only and free. Requires `ads:read`.
     *
     * @param array{keywords?: list<string>, url?: string, country?: string, language?: string, limit?: int, ad_account_id?: string} $params
     */
    public function researchKeywords(array $params): mixed
    {
        return $this->client->post('/ads/research/keywords', $params);
    }

    /**
     * `POST /ads/research/keywords/metrics` - search numbers for an exact
     * `keywords` list. Read-only. Requires `ads:read`.
     *
     * @param array{keywords: list<string>, country?: string, language?: string, ad_account_id?: string} $params
     */
    public function keywordMetrics(array $params): mixed
    {
        return $this->client->post('/ads/research/keywords/metrics', $params);
    }

    private function campaignPath(int|string $id): string
    {
        return '/ads/campaigns/' . $this->encodePathSegment((string) $id);
    }
}
