<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

/**
 * Post tags: the workspace's own labels for its posts (a hook style, a
 * format), to filter and compare posts. Tags never go to a platform. Uses
 * the `posts:read` / `posts:write` scopes.
 *
 * Set a post's tags with `tags` on posts->create / posts->update (tag ids or
 * names; a new name becomes a tag; on update `null` or `[]` removes them),
 * filter with `tag` on posts->list and analytics->overview, and compare tags
 * with analytics->tags.
 *
 * Error codes: `tag_not_found` (404), `tag_exists` (409, the existing tag is
 * in `error.tag`), `tag_limit_reached` (409, at most 200
 * tags per workspace), `invalid_tag_name`, `invalid_tag_color`,
 * `invalid_tags`, `invalid_post_ids`, `too_many_posts`, `too_many_tags` (400).
 */
class Tags extends AbstractResource
{
    /**
     * `GET /tags` - every tag of the workspace, by name. Each tag:
     * `{ id, name, color, created_at }`.
     */
    public function list(): mixed
    {
        return $this->client->get('/tags');
    }

    /**
     * `POST /tags` - create a tag. `name` is required (max 40 characters,
     * unique in the workspace, case-insensitive); `color` is one of blue,
     * green, orange, red, purple, pink, teal, yellow, brown or gray (omit for
     * the next colour of the palette). Returns `{ data: Tag }` (201).
     *
     * @param array{name: string, color?: string} $params
     */
    public function create(array $params): mixed
    {
        return $this->client->post('/tags', $params);
    }

    /**
     * `PATCH /tags/:id` - rename (`name`) and/or recolour (`color`). A key you
     * leave out keeps its value; posts keep the tag.
     *
     * @param array{name?: string, color?: string} $params
     */
    public function update(string|int $id, array $params): mixed
    {
        return $this->client->patch('/tags/' . $this->encodePathSegment((string) $id), $params);
    }

    /**
     * `DELETE /tags/:id` - delete the tag and take it off every post of the
     * workspace (the posts stay). Returns `{ data: { tag, posts_untagged } }`.
     */
    public function delete(string|int $id): mixed
    {
        return $this->client->delete('/tags/' . $this->encodePathSegment((string) $id));
    }

    /**
     * `POST /tags/apply` - add, remove or replace the tags of up to 500 posts
     * at once, published ones included. `add`, `remove` and `set` take tag
     * ids or names; a new name in `add` or `set` becomes a tag; `set`
     * replaces a post's tags. Returns
     * `{ data: { updated, posts: [{ id, tags }], not_found } }` (`not_found`
     * = post ids that are not posts of this workspace).
     *
     * @param array{post_ids: string[], add?: array<int, string|int>, remove?: array<int, string|int>, set?: array<int, string|int>} $params
     */
    public function apply(array $params): mixed
    {
        return $this->client->post('/tags/apply', $params);
    }
}
