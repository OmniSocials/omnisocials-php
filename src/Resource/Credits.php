<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

/**
 * The organisation's credit balance and what X statistics cost. Top-ups are
 * made in the OmniSocials app (Settings -> Organisation -> Billing ->
 * Credits).
 */
class Credits extends AbstractResource
{
    /**
     * `GET /credits` - the balance (1 credit = EUR 0.01), `auto_refill`
     * (`{ enabled }`), `this_month` (credits used per reason) and
     * `x_statistics`: `state`, `paused_at`, `charging` (`{ active, from }`),
     * `prices` (`{ post_read, media_read, follower_read }`),
     * `total_credits_month`, `this_month` (`{ post_reads, media_reads,
     * follower_reads, refreshes, credits }`) and `accounts` (`[{ account_id,
     * workspace_id, handle, posts, followers, tweets_30d, credits_month }]`).
     * Needs the opt-in scope `credits:read` and billing rights
     * (manage_billing) for the key's creator; OmniSocials Connect keys get
     * `403 not_available_for_connect`.
     */
    public function get(): mixed
    {
        return $this->client->get('/credits');
    }
}
