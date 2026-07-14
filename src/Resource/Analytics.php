<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

class Analytics extends AbstractResource
{
    /**
     * `GET /analytics/posts/:id` - latest per-platform metrics for one post.
     */
    public function post(string $postId): mixed
    {
        return $this->client->get('/analytics/posts/' . $this->encodePathSegment($postId));
    }

    /**
     * `GET /analytics/posts?ids=a,b,c` - batch metrics for up to 100 posts in
     * one call instead of one request per post.
     *
     * @param string[] $postIds
     */
    public function posts(array $postIds): mixed
    {
        return $this->client->get('/analytics/posts', ['ids' => implode(',', $postIds)]);
    }

    /**
     * `GET /analytics/overview` - workspace-wide totals for a period.
     *
     * @param array{period?: string, start_date?: string, end_date?: string} $params
     */
    public function overview(array $params = []): mixed
    {
        return $this->client->get('/analytics/overview', [
            'period' => $params['period'] ?? null,
            'start_date' => $params['start_date'] ?? null,
            'end_date' => $params['end_date'] ?? null,
        ]);
    }

    /**
     * `GET /analytics/accounts` - account-level stats (followers etc).
     *
     * @param array{platform?: string, date?: string} $params
     */
    public function accounts(array $params = []): mixed
    {
        return $this->client->get('/analytics/accounts', [
            'platform' => $params['platform'] ?? null,
            'date' => $params['date'] ?? null,
        ]);
    }

    /**
     * `GET /analytics/best-times` - recommended posting time slots for a
     * platform, based on when the account's audience engages.
     *
     * @param array{platform: string, timezone?: string} $params
     */
    public function bestTimes(array $params): mixed
    {
        return $this->client->get('/analytics/best-times', [
            'platform' => $params['platform'] ?? null,
            'timezone' => $params['timezone'] ?? null,
        ]);
    }
}
