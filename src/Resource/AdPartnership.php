<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

/**
 * Partnership ads with creators on Meta (`$client->ads->partnership`). Run a
 * creator's post through `network.meta.partnership` on an ad.
 */
class AdPartnership extends AbstractResource
{
    /**
     * `GET /ads/partnership/content?ad_account_id=` - creator posts you may
     * run as partnership ads. Requires `ads:read`.
     */
    public function listContent(int|string $adAccountId): mixed
    {
        return $this->client->get('/ads/partnership/content', ['ad_account_id' => (string) $adAccountId]);
    }

    /**
     * `GET /ads/partnership/permissions?ad_account_id=` - the partnership ad
     * permissions. Requires `ads:read`.
     */
    public function listPermissions(int|string $adAccountId): mixed
    {
        return $this->client->get('/ads/partnership/permissions', ['ad_account_id' => (string) $adAccountId]);
    }

    /**
     * `POST /ads/partnership/permissions` - ask for or set a partnership ad
     * permission (`ad_account_id` and Meta's permission fields). Requires
     * `ads:write`.
     *
     * @param array<string, mixed> $params
     */
    public function setPermission(array $params): mixed
    {
        return $this->client->post('/ads/partnership/permissions', $params);
    }
}
