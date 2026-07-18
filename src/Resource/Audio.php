<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

class Audio extends AbstractResource
{
    /**
     * `GET /audio/search?q=&type=` - search Meta's licensed audio catalog for
     * Instagram Reels. Omit `$query` for trending audio; `$type` is `music`
     * (default) or `original_sound`. Use a result's `audio_id` as
     * `instagram.audio_id` on a reel post (with optional
     * `instagram.audio_volume` / `instagram.video_volume`, integers 0-100).
     * `preview_url` is a temporary URL (~1.5 days); never persist it.
     */
    public function search(?string $query = null, ?string $type = null): mixed
    {
        return $this->client->get('/audio/search', ['q' => $query, 'type' => $type]);
    }
}
