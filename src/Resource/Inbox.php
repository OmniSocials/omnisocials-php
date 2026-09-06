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
     *     unanswered?: bool,
     *     limit?: int,
     *     cursor?: string
     * } $params All optional. `limit` is clamped to 1-100. `unanswered`
     *           returns only conversations that still need an answer: the
     *           customer's latest DM has no reply after it (Instagram/Facebook
     *           DMs within the 24-hour messaging window only), or a
     *           comment/mention that has not been replied to and is not
     *           hidden. Replies typed in the native apps count as answers.
     *           Read state is ignored here; use `next()` for a work queue.
     */
    public function listConversations(array $params = []): mixed
    {
        return $this->client->get('/inbox/conversations', [
            'platform' => $params['platform'] ?? null,
            'type' => $params['type'] ?? null,
            'unread' => $params['unread'] ?? null,
            'unanswered' => $params['unanswered'] ?? null,
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
     * Pass `include_next` => true to also get `next` (the next conversation
     * that needs an answer, the same object `next()` returns under `data`,
     * using its default queue order and filters; null when nothing is
     * waiting) and `remaining` in the response. Saves the extra call when
     * working through the inbox.
     *
     * @param array{
     *     text?: string,
     *     attachment_url?: string,
     *     attachment_type?: 'image'|'video'|'audio'|'file',
     *     include_next?: bool
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
     * `POST /inbox/messages/:messageId/hide` - hide or unhide a comment
     * someone left on one of the user's posts, on the platform, as the post
     * owner. Facebook, Instagram, TikTok, YouTube and Threads comments
     * (Threads: incoming top-level replies only; Threads does not allow
     * hiding nested replies). On YouTube, hide sets the comment's moderation
     * status to rejected, which removes it and its replies from public view;
     * unhide publishes it again. Returns the updated message as
     * `{ data: InboxMessage }` with its `hidden` flag flipped; the message
     * keeps its place in the conversation, and a hidden comment no longer
     * counts as unanswered. The account must have been connected with the
     * moderation permission (Facebook `pages_manage_engagement`, Instagram
     * `instagram_business_manage_comments`).
     *
     * Errors: 400 `unsupported_platform` (not an incoming comment on a
     * supported platform), 400 `not_hideable` (Threads nested reply, or
     * Threads refused), 401 `reauth_required` (the Threads reply permission
     * or the TikTok comments authorization is missing or expired), 403
     * `reconnect_required` (the account was connected without the
     * comment-moderation permission; reconnect it in the dashboard), 404
     * `not_found` (message not in this workspace) or `account_not_connected`,
     * 429 `quota_exceeded` (YouTube's daily API quota is used up; retry after
     * midnight Pacific), 502 `platform_error` (the platform rejected the
     * call). Threads inbox is currently rolling out; until Meta approves the
     * permissions it is disabled on production and Threads calls return a
     * clear error.
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

    /**
     * `DELETE /inbox/messages/:messageId` - delete a comment someone left on
     * one of the user's posts, on the platform and from the inbox. Facebook,
     * Instagram and TikTok comments only: YouTube's API does not let a
     * channel delete other people's comments, hide those instead (`hide()`).
     * Replies under the deleted comment go with it (the platforms cascade the
     * delete and the inbox mirrors that); their inbox ids come back as
     * `removed_reply_ids`. A comment that is already gone on the platform is
     * still removed from the inbox. This cannot be undone. Returns
     * `{ data: { id: string, conversation_id: string, removed_reply_ids: string[] } }`.
     *
     * Errors: 400 `unsupported_platform` (not an incoming Facebook, Instagram
     * or TikTok comment), 401 `reauth_required` (the TikTok comments
     * authorization expired), 403 `reconnect_required` (the account was
     * connected without the comment-moderation permission; reconnect it in
     * the dashboard), 404 `not_found` (message not in this workspace) or
     * `account_not_connected`, 502 `platform_error` (the platform rejected
     * the call).
     *
     * `$messageId` is URL-encoded for you.
     */
    public function deleteMessage(string $messageId): mixed
    {
        return $this->client->delete(
            '/inbox/messages/' . $this->encodePathSegment($messageId)
        );
    }

    /**
     * `GET /inbox/next` - the next conversation that needs an answer: a work
     * queue for answering the inbox. Returns the oldest (by default) item
     * that still needs a reply, together with its conversation so far and
     * the post it belongs to, so a reply can be drafted from one call. An
     * item needs an answer when it is the customer's latest DM with no reply
     * after it (Instagram/Facebook DMs within the 24-hour messaging window
     * only, since Meta refuses replies outside it), or a comment/mention
     * that has not been replied to and is not hidden. Replies typed in the
     * native apps count as answers (they are mirrored into the inbox), so a
     * thread a colleague answered on their phone is not served again.
     * Instagram mentions are skipped (no reply path). Looks at the last 30
     * days of activity.
     *
     * Only unread items are served by default: marking a conversation read
     * (`markRead()`) is how to skip one for good; pass `include_read` =>
     * true to include read-but-unanswered items.
     *
     * Returns `{ data: ?InboxNextUnanswered, remaining: int }`. `data` is
     * `{ conversation: InboxConversation, message: InboxMessage, messages: InboxMessage[] }`,
     * or null when nothing is waiting. `message` is the unanswered incoming
     * item itself (the customer's latest DM, or the specific comment): its
     * `id` is what `hide()` and `deleteMessage()` take, its
     * `conversation_id` is what `reply()` takes. `messages` is the
     * conversation so far, oldest first (the most recent 50 messages for
     * long DM threads). `remaining` is the number of unanswered items still
     * waiting after this one (capped at 500), 0 when `data` is null. To
     * chain the queue, pass `include_next` => true to `reply()` and it
     * returns the next item in the same response. Errors: 400
     * `validation_error` (unknown platform, type or order).
     *
     * @param array{
     *     platform?: 'instagram'|'facebook'|'linkedin'|'tiktok'|'youtube'|'x'|'threads',
     *     type?: 'dm'|'comment'|'mention',
     *     order?: 'oldest'|'newest',
     *     include_read?: bool,
     *     exclude?: string[]|string
     * } $params All optional. `order` is `oldest` (default: the item that
     *           has waited longest first) or `newest`. `exclude` is a
     *           session-local skip: conversation ids to leave out of this
     *           call (an array, or a comma-separated string; up to 100).
     */
    public function next(array $params = []): mixed
    {
        $exclude = $params['exclude'] ?? null;
        if (is_array($exclude)) {
            $exclude = $exclude === [] ? null : implode(',', $exclude);
        }

        return $this->client->get('/inbox/next', [
            'platform' => $params['platform'] ?? null,
            'type' => $params['type'] ?? null,
            'order' => $params['order'] ?? null,
            'include_read' => $params['include_read'] ?? null,
            'exclude' => $exclude,
        ]);
    }
}
