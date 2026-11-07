<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

use OmniSocials\Client;

/**
 * The campaigns, ad sets and ads of an ad account as the network has them,
 * also the ones made outside OmniSocials (`$client->ads->objects`), and the
 * import of those campaigns.
 *
 * `level` is `campaign`, `ad_set` or `ad`; `externalId` is the network's own
 * id. Objects of OmniSocials campaigns answer `409 use_campaign_routes`: change
 * those through `$client->ads`. A network without the feature answers
 * `400 not_supported_on_network` (`ads->capabilities()` lists `features`).
 */
class AdObjects extends AbstractResource
{
    /**
     * `GET /ads/accounts/:accountId/tree` - campaigns, ad sets and ads of the
     * ad account, read live from the network with the numbers of the period
     * (`from`, `to`: at most 90 days, default the last 30), `status` (a list
     * or a comma list), `limit` (up to 100) and `cursor`. Each campaign says
     * `source` (`omnisocials` or `external`). Requires `ads:read`.
     *
     * @param array{from?: string, to?: string, status?: string|list<string>, limit?: int, cursor?: string} $params
     */
    public function tree(int|string $accountId, array $params = []): mixed
    {
        return $this->client->get($this->accountPath($accountId) . '/tree', $this->queryParams($params));
    }

    /**
     * `GET /ads/accounts/:accountId/objects/:level/:externalId` - live
     * details of one campaign, ad set or ad. Requires `ads:read`.
     */
    public function get(int|string $accountId, string $level, string $externalId): mixed
    {
        return $this->client->get($this->objectPath($accountId, $level, $externalId));
    }

    /**
     * `PATCH /ads/accounts/:accountId/objects/:level/:externalId` - change
     * the `name`, `budget` or `end_at` of a campaign or ad set made outside
     * OmniSocials.
     *
     * A higher budget RAISES REAL AD SPEND: show the person the new amount
     * and get their yes first, then pass it again as `confirm_budget_amount`.
     * It changes the campaign on the network, so it needs a key with the
     * opt-in `ads:spend` scope ("Start ads"); without it, and on Google
     * (`api_draft_only`) always, the call answers `403 start_in_app` with the
     * page where a person does it in the app. Not retried after a 5xx, a
     * timeout or a lost connection: read the object with `get()` to see what
     * happened. Requires `ads:write`.
     *
     * @param array{name?: string, budget?: array<string, mixed>, end_at?: string|null, confirm_budget_amount?: int|float} $params
     */
    public function update(int|string $accountId, string $level, string $externalId, array $params): mixed
    {
        return $this->client->patch(
            $this->objectPath($accountId, $level, $externalId),
            $params,
            Client::RETRY_RATE_LIMIT
        );
    }

    /**
     * `PUT /ads/accounts/:accountId/objects/:level/:externalId/status` -
     * pause (`paused`) or switch on (`active`) one campaign, ad set or ad.
     *
     * Switching on STARTS REAL AD SPEND: show the person the budget that pays
     * (the object's own or its parent's) and get their yes first, then pass
     * it as `$confirmBudgetAmount`. Switching on needs a key with the opt-in
     * `ads:spend` scope ("Start ads"); without it, and on Google
     * (`api_draft_only`) always, the call answers `403 start_in_app` with the
     * page where a person does it in the app. Pausing needs no amount and
     * stays open (Google: `start_in_app`). Not retried after a 5xx, a timeout
     * or a lost connection: read the object with `get()` to see what
     * happened. Requires `ads:write`.
     */
    public function setStatus(
        int|string $accountId,
        string $level,
        string $externalId,
        string $status,
        int|float|null $confirmBudgetAmount = null
    ): mixed {
        $body = ['status' => $status];
        if ($confirmBudgetAmount !== null) {
            $body['confirm_budget_amount'] = $confirmBudgetAmount;
        }
        return $this->client->put(
            $this->objectPath($accountId, $level, $externalId) . '/status',
            $body,
            Client::RETRY_RATE_LIMIT
        );
    }

    /**
     * `POST /ads/accounts/:accountId/objects/:level/:externalId/duplicate` -
     * copy a campaign, ad set or ad made outside OmniSocials; the copy is
     * paused. `deep` (default true) copies what is under it, `rename` names
     * the copy. Meta only for now. Needs a key with the opt-in `ads:spend`
     * scope ("Start ads"); without it, and on Google (`api_draft_only`)
     * always, the call answers `403 start_in_app` with the page where a
     * person does it in the app. Copy an OmniSocials campaign as a draft with
     * `ads->duplicate()`. Requires `ads:write`.
     *
     * @param array{deep?: bool, rename?: string} $params
     */
    public function duplicate(int|string $accountId, string $level, string $externalId, array $params = []): mixed
    {
        return $this->client->post(
            $this->objectPath($accountId, $level, $externalId) . '/duplicate',
            $this->optionalBody($params)
        );
    }

    /**
     * `GET /ads/accounts/:accountId/import` - the import state of the ad
     * account's campaigns made outside OmniSocials: `status` (`not_started`,
     * `pending`, `done` or `failed`) and `campaigns_imported`. Requires
     * `ads:read`.
     */
    public function importStatus(int|string $accountId): mixed
    {
        return $this->client->get($this->accountPath($accountId) . '/import');
    }

    /**
     * `POST /ads/accounts/:accountId/import` - read the ad account again at
     * the next sync (within 15 minutes). The first import reads the last 90
     * days; webhook `ads.initial_sync_completed` when it is done. Answers
     * 202. Requires `ads:write`.
     */
    public function startImport(int|string $accountId): mixed
    {
        return $this->client->post($this->accountPath($accountId) . '/import');
    }

    /**
     * `GET /ads/external-campaigns` - imported campaigns (made outside
     * OmniSocials), optionally by `ad_account_id`, `platform` or `status`;
     * `limit` up to 200 and `offset`. Requires `ads:read`.
     *
     * @param array{ad_account_id?: int|string, platform?: string, status?: string, limit?: int, offset?: int} $params
     */
    public function listExternal(array $params = []): mixed
    {
        return $this->client->get('/ads/external-campaigns', $this->queryParams($params));
    }

    /**
     * `GET /ads/external-campaigns/:id` - one imported campaign with its
     * daily numbers (`from`, `to`). Requires `ads:read`.
     *
     * @param array{from?: string, to?: string} $params
     */
    public function getExternal(int|string $id, array $params = []): mixed
    {
        return $this->client->get(
            '/ads/external-campaigns/' . $this->encodePathSegment((string) $id),
            $this->queryParams($params)
        );
    }

    private function accountPath(int|string $accountId): string
    {
        return '/ads/accounts/' . $this->encodePathSegment((string) $accountId);
    }

    private function objectPath(int|string $accountId, string $level, string $externalId): string
    {
        return $this->accountPath($accountId) . '/objects/' . $this->encodePathSegment($level)
            . '/' . $this->encodePathSegment($externalId);
    }
}
