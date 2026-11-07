<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

/**
 * Custom audiences of an ad account (`$client->ads->audiences`): customer
 * lists, lookalikes and company lists. `$audienceId` is the network's own id.
 * Use an audience in `targeting.networks.<network>.audiences.include`.
 */
class AdAudiences extends AbstractResource
{
    /**
     * `GET /ads/audiences` - the custom audiences of `ad_account_id`
     * (`cursor` for the next page). Requires `ads:read`.
     *
     * @param array{ad_account_id: int|string, cursor?: string} $params
     */
    public function list(array $params): mixed
    {
        return $this->client->get('/ads/audiences', $this->queryParams($params));
    }

    /**
     * `POST /ads/audiences` - create a custom audience: `ad_account_id`,
     * `name`, optional `description`, `kind` (`customer_list`, `lookalike`
     * or `company_list`), `lookalike` (`source_audience_id`, `country`,
     * `ratio` 0.01 to 0.2), `customer_file_source` (Meta customer lists:
     * `user_provided`, `partner_provided` or `both`, as the person says) and
     * the first members of a customer list as `schema` + `rows` (the same
     * shape as `addMembers()`; Pinterest makes a list only together with its
     * first people). Without `rows` a customer list starts empty. Requires
     * `ads:write`.
     *
     * @param array<string, mixed> $params
     */
    public function create(array $params): mixed
    {
        return $this->client->post('/ads/audiences', $params);
    }

    /**
     * `GET /ads/audiences/:audienceId?ad_account_id=` - one custom audience.
     * Requires `ads:read`.
     */
    public function get(int|string $audienceId, int|string $adAccountId): mixed
    {
        return $this->client->get($this->audiencePath($audienceId), ['ad_account_id' => (string) $adAccountId]);
    }

    /**
     * `PATCH /ads/audiences/:audienceId` - rename a custom audience
     * (`ad_account_id`, `name`, `description`). Requires `ads:write`.
     *
     * @param array{ad_account_id: int|string, name?: string, description?: string} $params
     */
    public function update(int|string $audienceId, array $params): mixed
    {
        return $this->client->patch($this->audiencePath($audienceId), $params);
    }

    /**
     * `DELETE /ads/audiences/:audienceId?ad_account_id=` - delete a custom
     * audience. Requires `ads:write`.
     */
    public function delete(int|string $audienceId, int|string $adAccountId): mixed
    {
        return $this->client->delete($this->audiencePath($audienceId), ['ad_account_id' => (string) $adAccountId]);
    }

    /**
     * `POST /ads/audiences/:audienceId/members` - add people to a customer
     * list: `ad_account_id`, `schema` (the column names: `email`, `phone`
     * with the country code, `first_name`, `last_name`, `city`, `state`,
     * `zip`, `country`, `external_id`, `dob_year`, `gender`, `maid`) and
     * `rows` (lists of values in that order, at most 10,000). Send the raw
     * values: OmniSocials normalizes and SHA-256 hashes them before they
     * leave and stores nothing. Answers `received`, `accepted`, `invalid`.
     * Requires `ads:write`.
     *
     * @param array{ad_account_id: int|string, schema: list<string>, rows: list<list<string>>} $params
     */
    public function addMembers(int|string $audienceId, array $params): mixed
    {
        return $this->client->post($this->audiencePath($audienceId) . '/members', $params);
    }

    /**
     * `DELETE /ads/audiences/:audienceId/members` - remove people from a
     * customer list (the same `ad_account_id`, `schema` and `rows` as
     * `addMembers()`, sent as the JSON body). Requires `ads:write`.
     *
     * @param array{ad_account_id: int|string, schema: list<string>, rows: list<list<string>>} $params
     */
    public function removeMembers(int|string $audienceId, array $params): mixed
    {
        return $this->client->delete($this->audiencePath($audienceId) . '/members', [], $params);
    }

    /**
     * `POST /ads/audiences/:audienceId/companies` - add companies to a
     * company list (LinkedIn): `ad_account_id` and `companies` (each with
     * `name`, `domain` or `linkedin_url`). Requires `ads:write`.
     *
     * @param array{ad_account_id: int|string, companies: list<array<string, string>>} $params
     */
    public function addCompanies(int|string $audienceId, array $params): mixed
    {
        return $this->client->post($this->audiencePath($audienceId) . '/companies', $params);
    }

    private function audiencePath(int|string $audienceId): string
    {
        return '/ads/audiences/' . $this->encodePathSegment((string) $audienceId);
    }
}
