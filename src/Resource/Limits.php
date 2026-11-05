<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

/**
 * What OmniSocials cannot do, why, and what to do instead: platform rules,
 * features not built yet, features that work in the OmniSocials app only.
 * Read it before you tell a person that something is not possible, so you
 * can give the reason (`says`) and the way around it (`instead`). When the
 * person still wants a `requestable` entry, send it with
 * `featureRequests->create(['summary' => ..., 'limit_id' => ...])`. No scope
 * needed: every key may call it.
 *
 * Error codes: `invalid_platform`, `invalid_area` (400).
 */
class Limits extends AbstractResource
{
    /**
     * `GET /limits` - the things OmniSocials cannot do. `platform` keeps the
     * entries for one platform (entries for every channel stay in); `area` is
     * one of posts, media, inbox, analytics, automations, accounts, ads, team,
     * other. Returns `{ data: Limit[], kinds: { kind: label } }`; a limit is
     * `{ id, kind, kind_label, area, platforms, title, says, instead,
     * requestable }`. `kind` is platform, by_design, not_built, beta,
     * dashboard_only or needs_setup; `requestable` is false for platform and
     * by_design.
     *
     * @param array{platform?: string, area?: string} $params
     */
    public function list(array $params = []): mixed
    {
        return $this->client->get('/limits', [
            'platform' => $params['platform'] ?? null,
            'area' => $params['area'] ?? null,
        ]);
    }
}
