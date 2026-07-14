<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

class Media extends AbstractResource
{
    /**
     * `GET /media` - list the media library (newest first).
     *
     * @param array{limit?: int, offset?: int, search?: string, folder_id?: string} $params
     */
    public function list(array $params = []): mixed
    {
        return $this->client->get('/media', [
            'limit' => $params['limit'] ?? null,
            'offset' => $params['offset'] ?? null,
            'search' => $params['search'] ?? null,
            'folder_id' => $params['folder_id'] ?? null,
        ]);
    }

    /**
     * `GET /media/:id` - fetch a single media item.
     */
    public function get(string $id): mixed
    {
        return $this->client->get('/media/' . $this->encodePathSegment($id));
    }

    /**
     * `POST /media/upload` - upload a file as multipart form data (max 50MB
     * for images, 100MB request cap overall; use `uploadFromUrl()` or
     * `createUploadUrl()` for larger videos, up to 1GB).
     *
     * `file` is either a filesystem path or the raw file contents as a
     * string. When it is not an existing file path it is treated as raw
     * bytes (pass `filename` so the API can detect the type). A PDF is
     * rasterized into image slides and returned as a carousel
     * (`slides` + `media_ids`).
     *
     * @param array{file: string, filename?: string, name?: string, folder?: string, folder_id?: string} $params
     */
    public function upload(array $params): mixed
    {
        $file = $params['file'] ?? null;
        if (!is_string($file) || $file === '') {
            throw new \InvalidArgumentException(
                "The 'file' param is required: a filesystem path or the raw file contents as a string."
            );
        }

        $filename = $params['filename'] ?? null;
        if (is_file($file)) {
            $filePart = new \CURLFile($file, null, $filename ?? basename($file));
        } else {
            $filePart = new \CURLStringFile($file, $filename ?? 'upload.bin');
        }

        $fields = ['file' => $filePart];
        foreach (['name', 'folder', 'folder_id'] as $key) {
            if (isset($params[$key])) {
                $fields[$key] = (string) $params[$key];
            }
        }

        return $this->client->postMultipart('/media/upload', $fields);
    }

    /**
     * `POST /media/upload-from-url` - the server fetches a public URL
     * (files up to 1GB; large videos finish processing in the background and
     * come back with status "processing").
     *
     * @param array{url: string, filename?: string, name?: string, folder?: string} $params
     */
    public function uploadFromUrl(array $params): mixed
    {
        return $this->client->post('/media/upload-from-url', $params);
    }

    /**
     * `POST /media/upload-from-base64` - upload base64-encoded file data
     * (no data URI prefix).
     *
     * @param array{data: string, mime_type: string, filename?: string, name?: string, folder?: string} $params
     */
    public function uploadFromBase64(array $params): mixed
    {
        return $this->client->post('/media/upload-from-base64', $params);
    }

    /**
     * `POST /media/create-upload-url` - mint a one-time presigned upload URL
     * for large files (up to 1GB). POST the file as multipart form data
     * (field name "file") to the returned `upload_url` within
     * `expires_in_seconds`; no auth headers are needed on that second request.
     */
    public function createUploadUrl(): mixed
    {
        return $this->client->post('/media/create-upload-url');
    }

    /**
     * `POST /media/check` - preflight a file's compatibility with the
     * workspace's connected platforms BEFORE uploading. Provide one of: a
     * public `url`, an existing `media_id`, or `size_bytes` + `mime`.
     *
     * @param array{url?: string, media_id?: string, size_bytes?: int, mime?: string} $params
     */
    public function check(array $params): mixed
    {
        return $this->client->post('/media/check', $params);
    }

    /**
     * `PATCH /media/:id` - rename a file (`name`) and/or move it into a
     * folder (`folder_id`, or null for the root).
     *
     * @param array<string, mixed> $params
     */
    public function update(string $id, array $params): mixed
    {
        return $this->client->patch('/media/' . $this->encodePathSegment($id), $params);
    }

    /**
     * `DELETE /media/:id` - delete a media file. Returns null (204). Fails
     * with 409 `media_in_use` when the file is attached to a scheduled or
     * publishing post.
     */
    public function delete(string $id): mixed
    {
        return $this->client->delete('/media/' . $this->encodePathSegment($id));
    }
}
