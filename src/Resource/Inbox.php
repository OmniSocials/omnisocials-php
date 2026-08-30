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
     * Threads conversations are `type` "comment" (replies people leave on
     * the user's Threads posts; conversation ids look like
     * `threads_comment_<rootPostId>`) and "mention"
     * (`threads_mention_<postId>`); there are no Threads DMs. Threads inbox
     * is currently rolling out: until Meta approves the permissions it is
     * disabled on production and calls return a clear error, and it needs a
     * Threads connection with the reply permission.
     *
     * @param array{
     *     platform?: 'instagram'|'facebook'|'linkedin'|'tiktok'|'youtube'|'x'|'threads',
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
     * On a Threads conversation the reply publishes as a native Threads
     * reply. Threads inbox is currently rolling out (disabled on production
     * until Meta App Review) and needs a Threads connection with the reply
     * permission: a 401 `reauth_required` means the connection lacks that
     * permission (reconnect Threads).
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
     *     text?: string,
     *     attachment_url?: string,
     *     attachment_type?: 'image'|'video'|'audio'|'file'
     * } $params On Facebook and Instagram DMs, pass `attachment_url` (with
     *           `attachment_type`) to include media; `text` is optional
     *           when `attachment_url` is set (an attachment-only reply is
     *           allowed). Other platforms are text-only, and `text` is
     *           required for them. The returned message's `attachment` key
     *           carries the same shape when the message has media.
     */
    public function reply(string $conversationId, array $params): mixed
    {
        return $this->client->post(
            '/inbox/conversations/' . $this->encodePathSegment($conversationId) . '/reply',
            $params
        );
    }

    /**
     * `POST /inbox/messages/:messageId/hide` - hide or unhide a reply someone
     * left on one of the user's Threads posts, as the post owner (Threads
     * only for now). Returns the updated message as `{ data: InboxMessage }`
     * with its `hidden` flag flipped.
     *
     * Only incoming top-level replies can be hidden (Threads does not allow
     * hiding nested replies); the message keeps its place in the
     * conversation.
     *
     * Errors: 400 `unsupported_platform` (not an incoming Threads reply, or
     * Threads inbox not available yet), 400 `not_hideable` (nested reply or
     * Threads refused), 401 `reauth_required` (connection lacks the reply
     * permission; reconnect Threads), 404 `not_found` (message not in this
     * workspace) or `account_not_connected` (no Threads account).
     *
     * `$messageId` is URL-encoded for you.
     *
     * @param array{hide?: bool} $params Optional. `hide` defaults to true
     *                                   (true = hide, false = unhide).
     */
    public function hide(string $messageId, array $params = []): mixed
    {
        return $this->client->post(
            '/inbox/messages/' . $this->encodePathSegment($messageId) . '/hide',
            $params === [] ? null : $params
        );
    }
}
