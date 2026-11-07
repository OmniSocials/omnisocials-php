<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

/**
 * Lead forms of an ad account (`$client->ads->leadForms`). The leads they
 * bring are in `$client->ads->leads`.
 */
class AdLeadForms extends AbstractResource
{
    /**
     * `GET /ads/lead-forms?ad_account_id=` - the lead forms of an ad
     * account. `id` is the OmniSocials form id, `external_id` the network's
     * (use that as `settings.lead_form_id`). Requires `ads:read`.
     */
    public function list(int|string $adAccountId): mixed
    {
        return $this->client->get('/ads/lead-forms', ['ad_account_id' => (string) $adAccountId]);
    }

    /**
     * `POST /ads/lead-forms` - create a lead form: `ad_account_id`, `name`,
     * `privacy_policy_url`, `questions` (each with `type`, `label`,
     * `options`), `thank_you` (`title`, `body`, `button_text`, `url`) and
     * optionally `locale`, `headline`, `description` and `accept_terms`
     * (the person accepts the network's lead ad terms; Pinterest refuses a
     * form without it; only send true after the person said yes).
     * Requires `ads:write`.
     *
     * @param array<string, mixed> $params
     */
    public function create(array $params): mixed
    {
        return $this->client->post('/ads/lead-forms', $params);
    }

    /**
     * `GET /ads/lead-forms/:formId` - one lead form (OmniSocials form id).
     * Requires `ads:read`.
     */
    public function get(int|string $formId): mixed
    {
        return $this->client->get($this->formPath($formId));
    }

    /**
     * `PATCH /ads/lead-forms/:formId` - archive or open a form (`status`
     * `active` or `archived`) or switch its lead sync (`sync_enabled`).
     * Requires `ads:write`.
     *
     * @param array{status?: string, sync_enabled?: bool} $params
     */
    public function update(int|string $formId, array $params): mixed
    {
        return $this->client->patch($this->formPath($formId), $params);
    }

    /**
     * `DELETE /ads/lead-forms/:formId` - archive a lead form; the leads
     * already copied stay. Requires `ads:write`.
     */
    public function delete(int|string $formId): mixed
    {
        return $this->client->delete($this->formPath($formId));
    }

    /**
     * `POST /ads/lead-forms/:formId/test-leads` - send a test lead (optional
     * `fields`); the next sync copies it, marked `is_test`. Requires
     * `ads:write`.
     *
     * @param array{fields?: array<string, mixed>} $params
     */
    public function sendTestLead(int|string $formId, array $params = []): mixed
    {
        return $this->client->post($this->formPath($formId) . '/test-leads', $this->optionalBody($params));
    }

    private function formPath(int|string $formId): string
    {
        return '/ads/lead-forms/' . $this->encodePathSegment((string) $formId);
    }
}
