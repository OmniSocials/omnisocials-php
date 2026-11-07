<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

/**
 * Long reports that run in the background (`$client->ads->reports`). All
 * calls are reads (`ads:read`).
 */
class AdReports extends AbstractResource
{
    /**
     * `GET /ads/insights/reports` - the background reports of the workspace.
     */
    public function list(): mixed
    {
        return $this->client->get('/ads/insights/reports');
    }

    /**
     * `POST /ads/insights/reports` - start a long report (up to 730 days),
     * with the same body as `insights->query()`. Answers 202 with the run;
     * poll it with `get()`.
     *
     * @param array<string, mixed> $params
     */
    public function start(array $params): mixed
    {
        return $this->client->post('/ads/insights/reports', $params);
    }

    /**
     * `GET /ads/insights/reports/:reportId` - poll a report: `status`
     * `running`, `done` (with `rows`) or `failed`.
     */
    public function get(string $reportId): mixed
    {
        return $this->client->get('/ads/insights/reports/' . $this->encodePathSegment($reportId));
    }
}
