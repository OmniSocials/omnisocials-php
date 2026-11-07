<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

use OmniSocials\Client;

/**
 * The ads of an OmniSocials campaign (`$client->ads->campaignAds`): list,
 * add, read, pause or switch on, and the network's review.
 */
class AdCampaignAds extends AbstractResource
{
    /**
     * `GET /ads/campaigns/:id/ads` - the ads of an OmniSocials campaign with
     * their status, review and latest numbers. Requires `ads:read`.
     */
    public function list(int|string $campaignId): mixed
    {
        return $this->client->get($this->adsPath($campaignId));
    }

    /**
     * `POST /ads/campaigns/:id/ads` - add up to 6 new ads to a campaign that
     * runs, in the shape of a draft's ads (`primary_text`, `headline`,
     * `media`, `cta`, `link`, `network`). The new ads are created PAUSED on
     * the network; switch each one on with `setStatus()`. Adding ads to a
     * running campaign needs a key with the opt-in `ads:spend` scope ("Start
     * ads"); without it, and on Google (`api_draft_only`) always, the call
     * answers `403 start_in_app` with the page where a person does it in the
     * app. Webhook `ad.created`. Requires `ads:write`.
     *
     * @param list<array<string, mixed>> $ads
     */
    public function add(int|string $campaignId, array $ads): mixed
    {
        return $this->client->post($this->adsPath($campaignId), ['ads' => array_values($ads)]);
    }

    /**
     * `GET /ads/campaigns/:id/ads/:adId` - one ad of an OmniSocials
     * campaign. Requires `ads:read`.
     */
    public function get(int|string $campaignId, int|string $adId): mixed
    {
        return $this->client->get($this->adPath($campaignId, $adId));
    }

    /**
     * `PUT /ads/campaigns/:id/ads/:adId/status` - pause (`paused`) or switch
     * on (`active`) one ad.
     *
     * Switching on STARTS REAL AD SPEND: show the person the campaign budget
     * and get their yes first, then pass it as `$confirmBudgetAmount`.
     * Switching on needs a key with the opt-in `ads:spend` scope ("Start
     * ads"); without it, and on Google (`api_draft_only`) always, the call
     * answers `403 start_in_app` with the page where a person does it in the
     * app. Pausing needs no amount and stays open (Google: `start_in_app`).
     * Webhook `ad.updated`. Not retried after a 5xx, a timeout or a lost
     * connection: read the ad with `get()` to see what happened. Requires
     * `ads:write`.
     */
    public function setStatus(
        int|string $campaignId,
        int|string $adId,
        string $status,
        int|float|null $confirmBudgetAmount = null
    ): mixed {
        $body = ['status' => $status];
        if ($confirmBudgetAmount !== null) {
            $body['confirm_budget_amount'] = $confirmBudgetAmount;
        }
        return $this->client->put(
            $this->adPath($campaignId, $adId) . '/status',
            $body,
            Client::RETRY_RATE_LIMIT
        );
    }

    /**
     * `GET /ads/campaigns/:id/ads/:adId/review` - the network's review
     * verdict of one ad: `in_review`, `approved` or `rejected` with reasons.
     * Requires `ads:read`.
     */
    public function review(int|string $campaignId, int|string $adId): mixed
    {
        return $this->client->get($this->adPath($campaignId, $adId) . '/review');
    }

    private function adsPath(int|string $campaignId): string
    {
        return '/ads/campaigns/' . $this->encodePathSegment((string) $campaignId) . '/ads';
    }

    private function adPath(int|string $campaignId, int|string $adId): string
    {
        return $this->adsPath($campaignId) . '/' . $this->encodePathSegment((string) $adId);
    }
}
