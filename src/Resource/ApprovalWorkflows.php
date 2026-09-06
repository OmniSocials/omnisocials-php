<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

/**
 * Approval workflows are configured in the OmniSocials dashboard (Approvals).
 * List them here and route a post through one at create time by passing
 * `approval_workflow_id` to `$client->posts->create()`.
 */
class ApprovalWorkflows extends AbstractResource
{
    /**
     * `GET /approval-workflows` - the workflows this workspace can use
     * (company-wide plus workspace-bound), with steps and named approvers.
     */
    public function list(): mixed
    {
        return $this->client->get('/approval-workflows');
    }
}
