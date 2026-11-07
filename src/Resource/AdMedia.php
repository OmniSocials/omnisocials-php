<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

/**
 * The network's own image and video library of an ad account
 * (`$client->ads->media`). Use an uploaded file in an ad as
 * `network.<network>.media_ref`.
 */
class AdMedia extends AbstractResource
{
    /**
     * `GET /ads/media` - the library of `ad_account_id`, optionally by
     * `type` (`image` or `video`), `cursor` for the next page. Requires
     * `ads:read`.
     *
     * @param array{ad_account_id: int|string, type?: string, cursor?: string} $params
     */
    public function list(array $params): mixed
    {
        return $this->client->get('/ads/media', $this->queryParams($params));
    }

    /**
     * `POST /ads/media` - upload an image or video to the network ahead of
     * an ad: `ad_account_id`, `type`, `url` and optional `name`. Requires
     * `ads:write`.
     *
     * @param array{ad_account_id: int|string, type: string, url: string, name?: string} $params
     */
    public function upload(array $params): mixed
    {
        return $this->client->post('/ads/media', $params);
    }

    /**
     * `GET /ads/media/:mediaId` - the processing status of an uploaded file
     * (`ad_account_id`, optional `type`). Requires `ads:read`.
     *
     * @param array{ad_account_id: int|string, type?: string} $params
     */
    public function get(string $mediaId, array $params): mixed
    {
        return $this->client->get($this->mediaPath($mediaId), $this->queryParams($params));
    }

    /**
     * `DELETE /ads/media/:mediaId` - delete a file from the network's
     * library (`ad_account_id` and `type`). Requires `ads:write`.
     *
     * @param array{ad_account_id: int|string, type: string} $params
     */
    public function delete(string $mediaId, array $params): mixed
    {
        return $this->client->delete($this->mediaPath($mediaId), $this->queryParams($params));
    }

    private function mediaPath(string $mediaId): string
    {
        return '/ads/media/' . $this->encodePathSegment($mediaId);
    }
}
