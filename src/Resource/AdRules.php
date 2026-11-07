<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

use OmniSocials\Client;

/**
 * Automated rules (`$client->ads->rules`): a rule checks campaigns on a
 * schedule and acts on each one that meets every condition: `notify`
 * (webhook `ad_rule.triggered`), `pause`, `activate` or `adjust_budget`.
 *
 * A rule that notifies, pauses or lowers a budget works with `ads:write`. A
 * rule that can raise spend (`activate`, a positive `adjust_budget`) needs a
 * key with the opt-in `ads:spend` scope (else `403 rule_raises_spend`) and
 * `action.max_daily_budget`, confirmed by the person as
 * `confirm_max_daily_budget`. Never invent or confirm that amount yourself.
 * At run time a rule never acts on Google. `create()`, `update()` and
 * `undo()` are not retried after a 5xx, a timeout or a lost connection: read
 * the rule or the activity to see what happened.
 */
class AdRules extends AbstractResource
{
    /** `GET /ads/rules` - the automated rules of the workspace. Requires `ads:read`. */
    public function list(): mixed
    {
        return $this->client->get('/ads/rules');
    }

    /**
     * `POST /ads/rules` - create a rule: `name`, `conditions` (each
     * `metric`, `op` `gt`/`gte`/`lt`/`lte`, `value`, `window_days`),
     * `action` (`type`, `percent`, `max_daily_budget`, `min_daily_budget`,
     * `currency`) and optionally `ad_account_id`, `enabled` (a new rule is
     * off until true), `scope`, `every_minutes` (15 or more),
     * `cooldown_minutes`, `max_actions_per_run`.
     *
     * A rule that can RAISE AD SPEND needs `confirm_max_daily_budget` =
     * `action.max_daily_budget`, after the person agreed to it, and a key
     * with `ads:spend` (else `403 rule_raises_spend`). Preview it first with
     * `preview()`. Not retried after a 5xx, a timeout or a lost connection:
     * list the rules to see what happened. Requires `ads:write`.
     *
     * @param array<string, mixed> $params
     */
    public function create(array $params): mixed
    {
        return $this->client->post('/ads/rules', $params, Client::RETRY_RATE_LIMIT);
    }

    /** `GET /ads/rules/templates` - ready-made rules. Requires `ads:read`. */
    public function templates(): mixed
    {
        return $this->client->get('/ads/rules/templates');
    }

    /**
     * `POST /ads/rules/preview` - what an unsaved rule (the `create()` body)
     * would match and do now. Changes nothing. Requires `ads:read`.
     *
     * @param array<string, mixed> $params
     */
    public function preview(array $params): mixed
    {
        return $this->client->post('/ads/rules/preview', $params);
    }

    /**
     * `GET /ads/rules/activity` - every change the rules made, with before
     * and after (`rule_id`, `limit`). Requires `ads:read`.
     *
     * @param array{rule_id?: int|string, limit?: int} $params
     */
    public function activity(array $params = []): mixed
    {
        return $this->client->get('/ads/rules/activity', $this->queryParams($params));
    }

    /**
     * `POST /ads/rules/activity/:activityId/undo` - undo a change a rule made.
     *
     * Undoing a pause switches the campaign on again and undoing a budget cut
     * raises the budget back: both START OR RAISE REAL AD SPEND and need
     * `$confirmBudgetAmount` = the budget, after the person agreed to it, and
     * a key with `ads:spend` (else, and on Google always,
     * `403 start_in_app`). Undoing a budget raise needs no amount. Not
     * retried after a 5xx, a timeout or a lost connection: read `activity()`
     * to see what happened. Requires `ads:write`.
     */
    public function undo(int|string $activityId, int|float|null $confirmBudgetAmount = null): mixed
    {
        return $this->client->post(
            '/ads/rules/activity/' . $this->encodePathSegment((string) $activityId) . '/undo',
            $confirmBudgetAmount === null ? null : ['confirm_budget_amount' => $confirmBudgetAmount],
            Client::RETRY_RATE_LIMIT
        );
    }

    /** `GET /ads/rules/:ruleId` - one rule. Requires `ads:read`. */
    public function get(int|string $ruleId): mixed
    {
        return $this->client->get($this->rulePath($ruleId));
    }

    /**
     * `PATCH /ads/rules/:ruleId` - change a rule, also `enabled` true or
     * false. A changed action that can RAISE AD SPEND needs
     * `confirm_max_daily_budget` again, after the person agreed to it, and a
     * key with `ads:spend` (else `403 rule_raises_spend`). Not retried after
     * a 5xx, a timeout or a lost connection: read the rule with `get()` to
     * see what happened. Requires `ads:write`.
     *
     * @param array<string, mixed> $params
     */
    public function update(int|string $ruleId, array $params): mixed
    {
        return $this->client->patch($this->rulePath($ruleId), $params, Client::RETRY_RATE_LIMIT);
    }

    /** `DELETE /ads/rules/:ruleId` - delete a rule. Requires `ads:write`. */
    public function delete(int|string $ruleId): mixed
    {
        return $this->client->delete($this->rulePath($ruleId));
    }

    /**
     * `POST /ads/rules/:ruleId/preview` - what a saved rule would match and
     * do now. Changes nothing. Requires `ads:read`.
     */
    public function previewSaved(int|string $ruleId): mixed
    {
        return $this->client->post($this->rulePath($ruleId) . '/preview');
    }

    /**
     * `GET /ads/rules/:ruleId/runs` - the runs of a rule. Requires `ads:read`.
     *
     * @param array<string, mixed> $params
     */
    public function runs(int|string $ruleId, array $params = []): mixed
    {
        return $this->client->get($this->rulePath($ruleId) . '/runs', $this->queryParams($params));
    }

    private function rulePath(int|string $ruleId): string
    {
        return '/ads/rules/' . $this->encodePathSegment((string) $ruleId);
    }
}
