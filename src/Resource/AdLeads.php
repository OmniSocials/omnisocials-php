<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

/**
 * The leads of the workspace (`$client->ads->leads`). New leads are copied
 * from the networks every 15 minutes (webhook `lead.received` per new lead).
 * The answers are personal data; OmniSocials stores them encrypted. Lead
 * forms are in `$client->ads->leadForms`.
 */
class AdLeads extends AbstractResource
{
    /**
     * `GET /ads/leads` - the leads of the workspace, newest first, by
     * `form_id`, `status` (`new`, `contacted`, `qualified`, `won`, `lost`,
     * `spam`), `since`, `until`; `limit` up to 200 and `cursor`. Requires
     * `ads:read`.
     *
     * @param array{form_id?: int|string, status?: string, since?: string, until?: string, limit?: int, cursor?: string} $params
     */
    public function list(array $params = []): mixed
    {
        return $this->client->get('/ads/leads', $this->queryParams($params));
    }

    /**
     * `GET /ads/leads/counts` - lead counts by status (`total`,
     * `by_status`), optionally of one `form_id`. Requires `ads:read`.
     *
     * @param array{form_id?: int|string} $params
     */
    public function counts(array $params = []): mixed
    {
        return $this->client->get('/ads/leads/counts', $this->queryParams($params));
    }

    /**
     * `PATCH /ads/leads/:leadId` - set a lead's `status` or `note`. Requires
     * `ads:write`.
     *
     * @param array{status?: string, note?: string} $params
     */
    public function update(int|string $leadId, array $params): mixed
    {
        return $this->client->patch('/ads/leads/' . $this->encodePathSegment((string) $leadId), $params);
    }
}
