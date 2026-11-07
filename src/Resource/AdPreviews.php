<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

/**
 * Previews drawn by the network (`$client->ads->previews`). Spends nothing;
 * both calls are reads (`ads:read`).
 */
class AdPreviews extends AbstractResource
{
    /**
     * `POST /ads/previews` - previews of a new ad: `ad_account_id`, `ad`
     * (the shape of a draft's ad), optional `placements` and `objective`.
     * Answers `previews` (`placement`, `html`, `url`).
     *
     * @param array<string, mixed> $params
     */
    public function create(array $params): mixed
    {
        return $this->client->post('/ads/previews', $params);
    }

    /**
     * `GET /ads/campaigns/:id/ads/:adId/previews` - previews of an existing
     * ad, optionally for some `$placements`.
     *
     * @param list<string> $placements
     */
    public function forAd(int|string $campaignId, int|string $adId, array $placements = []): mixed
    {
        return $this->client->get(
            '/ads/campaigns/' . $this->encodePathSegment((string) $campaignId)
                . '/ads/' . $this->encodePathSegment((string) $adId) . '/previews',
            $placements === [] ? [] : ['placements' => implode(',', $placements)]
        );
    }
}
