<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

class Inbox extends AbstractResource
{
    /**
     * `GET /inbox/conversations` - list social inbox conversations (DMs,
     * comments, and mentions) across connected platforms, newest activity
     * first.
     *
     * Unlike the offset-paginated list endpoints, the inbox uses CURSOR
     * pagination: the response `pagination` is
     * `{ next_cursor: ?string, has_more: bool, limit: int }`. Pass
     * `pagination.next_cursor` back as `cursor` to fetch the next page; stop
     * when `has_more` is false.
     *
     * @param array{
     *     platform?: 'instagram'|'facebook'|'linkedin'|'tiktok'|'youtube'|'x',
     *     type?: 'dm'|'comment'|'mention',
     *     unread?: bool,
     *     limit?: int,
     *     cursor?: string
     * } $params All optional. `limit` is clamped to 1-100.
     */
    public function listConversations(array $params = []): mixed
    {
        return $this->client->get('/inbox/conversations', [
            'platform' => $params['platform'] ?? null,
            'type' => $params['type'] ?? null,
            'unread' => $params['unread'] ?? null,
            'limit' => $params['limit'] ?? null,
            'cursor' => $params['cursor'] ?? null,
        ]);
    }

    /**
     * `GET /inbox/conversations/:conversationId/messages` - fetch the message
     * history for a single conversation, newest first. Cursor-paginated, same
     * `{ next_cursor, has_more, limit }` shape as `listConversations()`.
     *
     * `$conversationId` is URL-encoded for you: LinkedIn conversation ids
     * contain `:` and `()` (e.g. `linkedin_comment_urn:li:activity:123`).
     *
     * @param array{limit?: int, cursor?: string} $params All optional.
     */
    public function getMessages(string $conversationId, array $params = []): mixed
    {
        return $this->client->get(
            '/inbox/conversations/' . $this->encodePathSegment($conversationId) . '/messages',
            [
                'limit' => $params['limit'] ?? null,
                'cursor' => $params['cursor'] ?? null,
            ]
        );
    }

    /**
     * `POST /inbox/conversations/:conversationId/read` - mark every message in
     * the conversation as read. No request body. Returns
     * `{ conversation_id: string, marked_read: int }`.
     *
     * `$conversationId` is URL-encoded for you.
     */
    public function markRead(string $conversationId): mixed
    {
        return $this->client->post(
            '/inbox/conversations/' . $this->encodePathSegment($conversationId) . '/read'
        );
    }

    /**
     * `POST /inbox/conversations/:conversationId/reply` - send a reply into the
     * conversation. Returns the created message as `{ data: InboxMessage }`.
     *
     * `$conversationId` is URL-encoded for you.
     *
     * X DM replies cost 2 prepaid credits per send (X's send fee, passed
     * through at cost), debited before the send and auto-refunded if the
     * send fails. Two 402 error codes are specific to this endpoint, thrown
     * as an `ApiException` with `getErrorCode()`:
     * - `insufficient_credits` - the company balance can't cover the 2
     *   credits.
     * - `x_inbox_suspended` - the workspace's X inbox auto-suspended after
     *   the balance hit zero; top up and re-enable it in the dashboard to
     *   resume. DMs that arrived while suspended are not recovered.
     *
     * @param array{
     *     text: string,
     *     attachment_url?: string,
     *     attachment_type?: 'image'|'video'|'audio'|'file'
     * } $params `text` is required; pass `attachment_url` (with an optional
     *           `attachment_type`) to include media.
     */
    public function reply(string $conversationId, array $params): mixed
    {
        return $this->client->post(
            '/inbox/conversations/' . $this->encodePathSegment($conversationId) . '/reply',
            $params
        );
    }
}
