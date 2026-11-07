<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

use OmniSocials\Client;

/**
 * Boosts, campaigns and "Create an ad" drafts on Meta, Pinterest, TikTok,
 * LinkedIn, X and Google.
 *
 * Ads spend real money once a campaign starts. Boosts and drafts are created
 * as drafts (no spend). Starting or raising spend needs a key with the opt-in
 * `ads:spend` scope ("Start ads", a second switch under "Ads"; its creator
 * needs `manage_ads`): `activate()`, `activateMulti()`, `resume()`,
 * `drafts->launch()`, a budget raise or any change of a campaign that reached
 * the network through `update()`, and the parity calls that switch something
 * on or raise a budget: `bulkStatus()` (resuming), `campaignAds->setStatus()`
 * (switching on), `campaignAds->add()` on a running campaign,
 * `objects->update()`, `objects->setStatus()` (switching on),
 * `objects->duplicate()`, `rules->create()`, `rules->update()` and
 * `rules->undo()` (a rule or undo that switches on or raises),
 * `accounts->updateSettings()` (a spend cap change),
 * `reachFrequency->reserve()`, `studies->create()` and
 * `budgetSchedules->create()`. Each repeats the amount. Without `ads:spend`
 * these calls answer `403 start_in_app` with the page in the app where a
 * person does it (rules: `403 rule_raises_spend`); on Google
 * (`api_draft_only` on `capabilities()`) they always do, also with
 * `ads:spend`. `pause()` and `delete()` stay open, except on the networks in
 * `api_stop_in_app` (Google).
 *
 * Take the amount from the person's explicit yes; never invent or confirm it
 * yourself. The calls that start or raise spend are never retried after a
 * 5xx, a timeout or a lost connection (only a 429), because the first
 * attempt may have started the ad. Pausing never needs an amount. An ads
 * error may carry `param` (the field it is about) in the exception's
 * `getBody()`.
 *
 * Budgets are in major currency units (10 = 10 EUR) in the ad account's
 * currency. Ad accounts are connected and picked in the OmniSocials
 * dashboard. The scopes `ads:read` and `ads:write` are opt-in on the API key
 * (the Ads switch); every write also needs `manage_ads` for the person
 * behind the key. A network without a feature answers
 * `400 not_supported_on_network`; `capabilities()` lists `features` per
 * network.
 */
class Ads extends AbstractResource
{
    /** "Create an ad" drafts. */
    public readonly AdDrafts $drafts;

    /** Campaigns, ad sets and ads as the network has them, and the import of campaigns made outside OmniSocials. */
    public readonly AdObjects $objects;

    /** Targeting search, browse, reach estimate and bid suggestions. */
    public readonly AdTargeting $targeting;

    /** Custom audiences: customer lists, lookalikes, company lists. */
    public readonly AdAudiences $audiences;

    /** The ads of an OmniSocials campaign: list, add, read, status, review. */
    public readonly AdCampaignAds $campaignAds;

    /** Lead forms. */
    public readonly AdLeadForms $leadForms;

    /** The leads the lead forms bring. */
    public readonly AdLeads $leads;

    /** Pixels, conversion goals, custom conversions and server events. */
    public readonly AdConversions $conversions;

    /** Live numbers, campaign and ad numbers, timeline, rankings and alerts. */
    public readonly AdInsights $insights;

    /** Long reports that run in the background. */
    public readonly AdReports $reports;

    /** Automated rules. */
    public readonly AdRules $rules;

    /** Your own numbers per campaign and day. */
    public readonly AdResults $results;

    /** The network's own image and video library. */
    public readonly AdMedia $media;

    /** Previews drawn by the network. */
    public readonly AdPreviews $previews;

    /** Partnership ads with creators (Meta). */
    public readonly AdPartnership $partnership;

    /** Comments on ads. */
    public readonly AdComments $comments;

    /** One ad account: identities, health, finance, settings, people, activity. */
    public readonly AdAccounts $accounts;

    /** Reach and frequency buying (Meta). */
    public readonly AdReachFrequency $reachFrequency;

    /** A/B tests and lift studies (Meta). */
    public readonly AdStudies $studies;

    /** Planned budget increases (Meta). */
    public readonly AdBudgetSchedules $budgetSchedules;

    /** Google Ads reports. */
    public readonly AdGoogle $google;

    public function __construct(Client $client)
    {
        parent::__construct($client);
        $this->drafts = new AdDrafts($client);
        $this->objects = new AdObjects($client);
        $this->targeting = new AdTargeting($client);
        $this->audiences = new AdAudiences($client);
        $this->campaignAds = new AdCampaignAds($client);
        $this->leadForms = new AdLeadForms($client);
        $this->leads = new AdLeads($client);
        $this->conversions = new AdConversions($client);
        $this->insights = new AdInsights($client);
        $this->reports = new AdReports($client);
        $this->rules = new AdRules($client);
        $this->results = new AdResults($client);
        $this->media = new AdMedia($client);
        $this->previews = new AdPreviews($client);
        $this->partnership = new AdPartnership($client);
        $this->comments = new AdComments($client);
        $this->accounts = new AdAccounts($client);
        $this->reachFrequency = new AdReachFrequency($client);
        $this->studies = new AdStudies($client);
        $this->budgetSchedules = new AdBudgetSchedules($client);
        $this->google = new AdGoogle($client);
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
     * (goals, media, text limits, buttons, minimum daily budget, and per
     * network `bid_strategies`, `settings` and `ad_fields`: the keys its
     * module reads), `create_networks`, `network_states` (`live`, `preview`
     * or `coming_soon`), `features` (per network, the feature keys its API
     * offers here) with `feature_labels`, `targeting_types` (per network, the
     * types of `targeting->search()` and of `targeting.networks`),
     * `api_goals` (`lead_generation`, `conversions`, `messages`, `calls`: API
     * only; each network's goal entry says what it `needs`), `api_draft_only`
     * (networks where every API caller stops at the draft, also a key with
     * `ads:spend`: Google) and `api_stop_in_app` (networks where the API may
     * not even pause or delete a campaign that reached the network: Google).
     * Requires `ads:read`.
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
     * `POST /ads/boost` - boost a published post. Creates a DRAFT boost that
     * spends nothing; the person starts it in the app. Takes `post_id`,
     * `ad_account_id`, `budget`
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
     * `PATCH /ads/campaigns/:id` - change the `name`, `objective`, `budget`,
     * `targeting` (also `targeting.network`), `schedule`, or the `settings` of a
     * campaign that never started (after the start `400 settings_locked`:
     * make a copy with `duplicate()`).
     *
     * A draft changes with `ads:write`. A campaign that reached the network
     * changes only with a key that has the opt-in `ads:spend` scope ("Start
     * ads"); without it, and on Google (`api_draft_only`) always, the call
     * answers `403 start_in_app` with the page where a person does it in the
     * app. A higher budget on a running campaign RAISES REAL AD SPEND: show
     * the person the new amount and get their yes first, then pass it again
     * as `confirm_budget_amount`. Not retried after a 5xx, a timeout or a
     * lost connection: read the campaign with `get()` to see what happened.
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
     * Needs a key with the opt-in `ads:spend` scope ("Start ads"); without
     * it, and on Google (`api_draft_only`) always, the call answers
     * `403 start_in_app` with the page where a person does it in the app.
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
     * Needs a key with the opt-in `ads:spend` scope ("Start ads"); without
     * it, and on Google (`api_draft_only`) always, a campaign comes back
     * `ok: false` with code `start_in_app` and the page where a person starts
     * it in the app. Every campaign's budget must equal
     * `$confirmBudgetAmount`; show the person the total (amount x campaigns)
     * and get their yes first. Answers `results`. Not retried after a 5xx, a
     * timeout or a lost connection: read the campaigns with `get()` to see
     * what happened. Requires `ads:write`.
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
     * review). Open with `ads:write`, except on the networks in
     * `api_stop_in_app` (Google): there a campaign that reached the network
     * answers `403 start_in_app`. Requires `ads:write`.
     */
    public function pause(int|string $id): mixed
    {
        return $this->client->post($this->campaignPath($id) . '/pause');
    }

    /**
     * `POST /ads/campaigns/:id/resume` - resume a paused campaign: it STARTS
     * REAL AD SPEND again. Needs a key with the opt-in `ads:spend` scope
     * ("Start ads"); without it, and on Google (`api_draft_only`) always, the
     * call answers `403 start_in_app` with the page where a person does it in
     * the app. Only a paused campaign resumes (`409 not_paused`). Not retried
     * after a 5xx, a timeout or a lost connection: read the campaign with
     * `get()` to see what happened. Requires `ads:write`.
     */
    public function resume(int|string $id): mixed
    {
        return $this->client->post($this->campaignPath($id) . '/resume', null, Client::RETRY_RATE_LIMIT);
    }

    /**
     * `DELETE /ads/campaigns/:id` - delete a campaign on the network too. A
     * campaign that spent is archived: its spend stays in the totals. Open
     * with `ads:write`, except on the networks in `api_stop_in_app` (Google):
     * there a campaign that reached the network answers `403 start_in_app`.
     * Requires `ads:write`.
     */
    public function delete(int|string $id): mixed
    {
        return $this->client->delete($this->campaignPath($id));
    }

    /**
     * `GET /ads/overview` - accounts, campaigns per status and the totals.
     * `totals.spend` is null when the campaigns use more than one currency
     * (`totals.currency` names the one currency); read `totals_by_currency`,
     * one row per currency with `currency`, `spend`, `impressions` and
     * `clicks`. Requires `ads:read`.
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

    /**
     * `POST /ads/campaigns/bulk-status` - pause (`paused`) or resume
     * (`active`) up to 50 OmniSocials campaigns.
     *
     * Resuming STARTS REAL AD SPEND again: every campaign's budget must equal
     * `$confirmBudgetAmount` (one amount for all, checked before any change);
     * show the person the total and get their yes first. Resuming needs a key
     * with `ads:spend`: without it, and on Google always, a result answers
     * `start_in_app`. Pausing needs no amount (Google: `start_in_app`).
     * Answers `results` per campaign. Not retried after a 5xx, a timeout or a
     * lost connection: read the campaigns with `get()` to see what happened.
     * Requires `ads:write`.
     *
     * @param list<int|string> $campaignIds
     */
    public function bulkStatus(array $campaignIds, string $status, int|float|null $confirmBudgetAmount = null): mixed
    {
        $body = ['campaign_ids' => array_values($campaignIds), 'status' => $status];
        if ($confirmBudgetAmount !== null) {
            $body['confirm_budget_amount'] = $confirmBudgetAmount;
        }
        return $this->client->post('/ads/campaigns/bulk-status', $body, Client::RETRY_RATE_LIMIT);
    }

    /**
     * `POST /ads/campaigns/:id/duplicate` - copy an OmniSocials campaign as
     * a new DRAFT (same ad account, goal, budget, targeting, settings and
     * ads; optional `name`). A draft never spends. Requires `ads:write`.
     *
     * @param array{name?: string} $params
     */
    public function duplicate(int|string $id, array $params = []): mixed
    {
        return $this->client->post($this->campaignPath($id) . '/duplicate', $this->optionalBody($params));
    }

    private function campaignPath(int|string $id): string
    {
        return '/ads/campaigns/' . $this->encodePathSegment((string) $id);
    }

}
