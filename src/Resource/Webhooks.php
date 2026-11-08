<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

class Webhooks extends AbstractResource
{
    /**
     * `GET /webhooks` - list the webhooks of the key's workspace and the
     * organisation-wide webhooks of its organisation (`workspace_id` null,
     * made in the OmniSocials dashboard; they receive the events of every
     * workspace). A webhook whose creator lost access to the organisation
     * or workspace is switched off
     * automatically: `is_active` is false and `last_failure['error']` says
     * why.
     */
    public function list(): mixed
    {
        return $this->client->get('/webhooks');
    }

    /**
     * `GET /webhooks/:id` - fetch a webhook of the key's workspace, or an
     * organisation-wide webhook of its organisation (`workspace_id` null).
     */
    public function get(string $id): mixed
    {
        return $this->client->get('/webhooks/' . $this->encodePathSegment($id));
    }

    /**
     * `POST /webhooks` - register an endpoint for event deliveries
     * (post.scheduled, post.published, post.failed, post.approved,
     * post.rejected, and for ads ad.status_changed, ad.created, ad.updated,
     * ad.review, ad.delivery_issue, campaign.created, campaign.updated,
     * campaign.budget, lead.received, ads.sync_failed, ads.sync_recovered,
     * ads.initial_sync_completed and ad_rule.triggered). The `data` of an
     * ad.status_changed delivery is the campaign, not a post: `campaign_id`,
     * `workspace_id`, `name`, `platform`, `campaign_type`, `status`,
     * `previous_status`, `external_campaign_id` and `budget`; the other
     * campaign events carry the campaign plus `details`, the rest their own
     * `data`. Any Ads event (ad.*, campaign.*, lead.*, ads.*, ad_rule.*)
     * needs the `ads:read` scope on the key and `access_ads` for its person
     * (403 `insufficient_scope` / 403 `insufficient_permission`). The
     * response includes the signing `secret`; save it, it is only shown once.
     *
     * @param array{url: string, events: string[]} $params
     */
    public function create(array $params): mixed
    {
        return $this->client->post('/webhooks', $params);
    }

    /**
     * `PATCH /webhooks/:id` - change the URL, events, or active state.
     * Works on organisation-wide webhooks too. `['is_active' => true]`
     * switches on a webhook that was switched off (also an automatic
     * switch-off); setting `url`, `events` or switching it on makes the
     * key's creator the person behind the webhook. Setting `url` on an
     * organisation-wide webhook (even its current one) moves it to the
     * key's workspace: from then on it gets only that workspace's events,
     * and the answer shows the new `workspace_id`. When the webhook has Ads
     * events after the change, setting `url` or `events` or switching it on
     * needs the `ads:read` scope and `access_ads` for the key's creator (403
     * otherwise, nothing changed).
     *
     * @param array{url?: string, events?: string[], is_active?: bool} $params
     */
    public function update(string $id, array $params): mixed
    {
        return $this->client->patch('/webhooks/' . $this->encodePathSegment($id), $params);
    }

    /**
     * `DELETE /webhooks/:id` - delete a webhook of the key's workspace or
     * an organisation-wide webhook of its organisation. Returns null (204).
     */
    public function delete(string $id): mixed
    {
        return $this->client->delete('/webhooks/' . $this->encodePathSegment($id));
    }

    /**
     * `POST /webhooks/:id/rotate-secret` - generate a new signing secret.
     * Save the returned secret; it is only shown once. The old secret keeps
     * signing for 24 hours: every delivery then carries
     * `X-OmniSocials-Signature` (new secret) and
     * `X-OmniSocials-Signature-Previous` (old secret, same format, same
     * timestamp and body). Verify the first with the new secret and, when
     * that fails, the second with the old one (see
     * `Webhooks::verifySignature()`). Rotating again within the 24 hours
     * replaces the previous secret with the one just rotated out. Works on
     * organisation-wide webhooks too.
     */
    public function rotateSecret(string $id): mixed
    {
        return $this->client->post('/webhooks/' . $this->encodePathSegment($id) . '/rotate-secret');
    }
}
