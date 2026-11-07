<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

/**
 * Comments on the ads of an OmniSocials campaign (`$client->ads->comments`).
 * `$commentId` is the network's comment id.
 */
class AdComments extends AbstractResource
{
    /**
     * `GET /ads/campaigns/:id/ads/:adId/comments` - comments on an ad
     * (`cursor` for the next page). Requires `ads:read`.
     *
     * @param array{cursor?: string} $params
     */
    public function list(int|string $campaignId, int|string $adId, array $params = []): mixed
    {
        return $this->client->get($this->commentsPath($campaignId, $adId), $this->queryParams($params));
    }

    /**
     * `POST /ads/campaigns/:id/ads/:adId/comments/:commentId/reply` - answer
     * a comment. Requires `ads:write`.
     */
    public function reply(int|string $campaignId, int|string $adId, string $commentId, string $text): mixed
    {
        return $this->client->post(
            $this->commentsPath($campaignId, $adId) . '/' . $this->encodePathSegment($commentId) . '/reply',
            ['text' => $text]
        );
    }

    /**
     * `POST /ads/campaigns/:id/ads/:adId/comments/:commentId/hide` - hide a
     * comment, or show it again with `$hidden = false`. Requires `ads:write`.
     */
    public function hide(int|string $campaignId, int|string $adId, string $commentId, bool $hidden = true): mixed
    {
        return $this->client->post(
            $this->commentsPath($campaignId, $adId) . '/' . $this->encodePathSegment($commentId) . '/hide',
            ['hidden' => $hidden]
        );
    }

    /**
     * `DELETE /ads/campaigns/:id/ads/:adId/comments/:commentId` - delete a
     * comment. Requires `ads:write`.
     */
    public function delete(int|string $campaignId, int|string $adId, string $commentId): mixed
    {
        return $this->client->delete(
            $this->commentsPath($campaignId, $adId) . '/' . $this->encodePathSegment($commentId)
        );
    }

    private function commentsPath(int|string $campaignId, int|string $adId): string
    {
        return '/ads/campaigns/' . $this->encodePathSegment((string) $campaignId)
            . '/ads/' . $this->encodePathSegment((string) $adId) . '/comments';
    }
}
