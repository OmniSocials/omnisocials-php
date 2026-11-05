<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

use OmniSocials\Client;

/**
 * "Create an ad" drafts (`$client->ads->drafts`): one campaign per network
 * in `platforms`, each with the same ads. A draft never spends; `launch()`
 * starts it.
 */
class AdDrafts extends AbstractResource
{
    /**
     * `GET /ads/drafts/defaults` - suggested countries (where the Instagram
     * followers are), the currency and the workspace's ad account per
     * network. Requires `ads:read`.
     */
    public function defaults(): mixed
    {
        return $this->client->get('/ads/drafts/defaults');
    }

    /**
     * `POST /ads/drafts` - save a new ad as a draft group. It needs no ad
     * account and spends nothing.
     *
     * `objective` is `traffic`, `engagement`, `awareness` or `video_views`.
     * `platforms` lists network keys (`meta`, `pinterest`, `tiktok`,
     * `linkedin`, `x`, `google`) or `['platform' => ..., 'budget_amount' =>
     * ..., 'ad_account_id' => ...]` per network. `budget` is the daily amount
     * per network, `['amount' => 10, 'currency' => 'EUR']`. `schedule` is
     * `['duration_days' => 7]` (null runs until it is stopped). `targeting`
     * needs `countries`; `'automatic_audience' => true` lets the network find
     * the audience. Each of `ads` has `media`, `primary_text`, `link` and
     * optionally `headline`, `description`, `cta`, `primary_text_b` (an A/B
     * test) and, on Meta, `'placement_media' => ['stories_reels' => [...]]`
     * (an upright 9:16 file for Stories and Reels).
     *
     * Read `ads->capabilities()` first for what each network accepts. The
     * answer carries `review_url` (the builder in the dashboard) and per
     * network `readiness`. Requires `ads:write`.
     *
     * @param array<string, mixed> $params
     */
    public function create(array $params): mixed
    {
        return $this->client->post('/ads/drafts', $params);
    }

    /**
     * `GET /ads/drafts/:group_id` - one draft group. Requires `ads:read`.
     */
    public function get(string $groupId): mixed
    {
        return $this->client->get($this->draftPath($groupId));
    }

    /**
     * `PUT /ads/drafts/:group_id` - replace the draft with the full body (the
     * same fields as `create()`). Networks that already started are left
     * alone (`campaigns[].editable`). Requires `ads:write`.
     *
     * @param array<string, mixed> $params
     */
    public function update(string $groupId, array $params): mixed
    {
        return $this->client->put($this->draftPath($groupId), $params);
    }

    /**
     * `DELETE /ads/drafts/:group_id` - delete a draft group that never
     * started. Requires `ads:write`.
     */
    public function delete(string $groupId): mixed
    {
        return $this->client->delete($this->draftPath($groupId));
    }

    /**
     * `POST /ads/drafts/:group_id/account` - set the ad account of one
     * network in the draft (`platform` and `ad_account_id`, from
     * `ads->listAccounts()`, same network). Requires `ads:write`.
     *
     * @param array{platform: string, ad_account_id: int|string} $params
     */
    public function attachAccount(string $groupId, array $params): mixed
    {
        return $this->client->post($this->draftPath($groupId) . '/account', $params);
    }

    /**
     * `POST /ads/drafts/:group_id/launch` - START REAL AD SPEND for every
     * network of the draft that never started.
     *
     * Show the person the daily amount per network and get their yes first,
     * then pass it as `confirm_budget_amount` (or per campaign id as
     * `confirm_budget_amounts`). A wrong or missing amount answers
     * `400 budget_confirmation_required` and writes nothing. The answer is
     * `data` with `group` and a result per network in `results`; one failed
     * network never stops the others. Not retried after a 5xx, a timeout or
     * a lost connection: read the draft with `get()` to see what started.
     * Requires `ads:write`.
     *
     * @param array{confirm_budget_amount?: int|float, confirm_budget_amounts?: array<string, int|float>} $params
     */
    public function launch(string $groupId, array $params): mixed
    {
        if (isset($params['confirm_budget_amounts']) && is_array($params['confirm_budget_amounts'])) {
            // Keyed by campaign id: always a JSON object, never a list.
            $params['confirm_budget_amounts'] = (object) $params['confirm_budget_amounts'];
        }
        return $this->client->post($this->draftPath($groupId) . '/launch', $params, Client::RETRY_RATE_LIMIT);
    }

    private function draftPath(string $groupId): string
    {
        return '/ads/drafts/' . $this->encodePathSegment($groupId);
    }
}
