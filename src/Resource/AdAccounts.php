<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

use OmniSocials\Client;

/**
 * One connected ad account (`$client->ads->accounts`): health, money,
 * settings, people, the change log, businesses and identities. List the
 * accounts with `ads->listAccounts()`; `$accountId` is the OmniSocials ad
 * account id.
 */
class AdAccounts extends AbstractResource
{
    /**
     * `GET /ads/accounts/:accountId/health` - check the account before an ad
     * starts (status, payment, spend limit, Page, pixel). A check that cannot
     * be read is unknown; failed checks carry a `fix_url`. Requires `ads:read`.
     */
    public function health(int|string $accountId): mixed
    {
        return $this->client->get($this->accountPath($accountId) . '/health');
    }

    /**
     * `GET /ads/accounts/:accountId/finance` - spend limit, amount spent,
     * balance and payment method. Requires `ads:read`.
     */
    public function finance(int|string $accountId): mixed
    {
        return $this->client->get($this->accountPath($accountId) . '/finance');
    }

    /**
     * `PATCH /ads/accounts/:accountId/settings` - change `spend_cap`,
     * `dsa_beneficiary`, `dsa_payor` or `name`.
     *
     * A HIGHER SPEND CAP lets the account spend more: show the person the new
     * cap and get their yes first, then pass it as `confirm_budget_amount`;
     * removing the cap (`'spend_cap' => null`) needs
     * `'confirm_budget_amount' => 'remove'`. A spend cap change needs a key
     * with the opt-in `ads:spend` scope ("Start ads"); without it, and on
     * Google (`api_draft_only`) always, the call answers `403 start_in_app`
     * with the page where a person does it in the app. The name and the DSA
     * names stay open. Not retried after a 5xx, a timeout or a lost
     * connection: read `finance()` to see what happened. Requires
     * `ads:write`.
     *
     * @param array{spend_cap?: int|float|null, dsa_beneficiary?: string, dsa_payor?: string, name?: string, confirm_budget_amount?: int|float|string} $params
     */
    public function updateSettings(int|string $accountId, array $params): mixed
    {
        return $this->client->patch($this->accountPath($accountId) . '/settings', $params, Client::RETRY_RATE_LIMIT);
    }

    /** `GET /ads/accounts/:accountId/users` - people with access. Requires `ads:read`. */
    public function listUsers(int|string $accountId): mixed
    {
        return $this->client->get($this->accountPath($accountId) . '/users');
    }

    /**
     * `POST /ads/accounts/:accountId/users` - give a person access
     * (LinkedIn): `role` and `email` or `member_id`. Requires `ads:write`.
     *
     * @param array{role: string, email?: string, member_id?: string} $params
     */
    public function addUser(int|string $accountId, array $params): mixed
    {
        return $this->client->post($this->accountPath($accountId) . '/users', $params);
    }

    /**
     * `DELETE /ads/accounts/:accountId/users/:userId` - remove a person's
     * access. Requires `ads:write`.
     */
    public function removeUser(int|string $accountId, string $userId): mixed
    {
        return $this->client->delete($this->accountPath($accountId) . '/users/' . $this->encodePathSegment($userId));
    }

    /**
     * `GET /ads/accounts/:accountId/activity` - the change log (`since`,
     * `until`, `cursor`). Requires `ads:read`.
     *
     * @param array{since?: string, until?: string, cursor?: string} $params
     */
    public function activity(int|string $accountId, array $params = []): mixed
    {
        return $this->client->get($this->accountPath($accountId) . '/activity', $this->queryParams($params));
    }

    /**
     * `GET /ads/accounts/:accountId/businesses` - business portfolios (Meta)
     * and Business Centers (TikTok) of the account's person. Requires
     * `ads:read`.
     */
    public function businesses(int|string $accountId): mixed
    {
        return $this->client->get($this->accountPath($accountId) . '/businesses');
    }

    /**
     * `GET /ads/accounts/:accountId/dsa` - the EU DSA payer and beneficiary
     * (Meta). Requires `ads:read`.
     */
    public function dsa(int|string $accountId): mixed
    {
        return $this->client->get($this->accountPath($accountId) . '/dsa');
    }

    /**
     * `GET /ads/accounts/:accountId/hierarchy` - the manager account
     * hierarchy (Google). Requires `ads:read`.
     */
    public function hierarchy(int|string $accountId): mixed
    {
        return $this->client->get($this->accountPath($accountId) . '/hierarchy');
    }

    /**
     * `GET /ads/accounts/:accountId/identities` - Pages, profiles and
     * identities an ad of this account can run as. Requires `ads:read`.
     */
    public function identities(int|string $accountId): mixed
    {
        return $this->client->get($this->accountPath($accountId) . '/identities');
    }

    private function accountPath(int|string $accountId): string
    {
        return '/ads/accounts/' . $this->encodePathSegment((string) $accountId);
    }
}
