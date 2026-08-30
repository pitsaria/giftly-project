<?php
/**
 * Supabase Storage helper — product / catalog images.
 *
 * Render's container filesystem is ephemeral: anything written to uploads/ at
 * runtime disappears on the next deploy or restart. So new uploads go to a
 * public Supabase Storage bucket instead, and the FULL public URL is stored in
 * the products.image column.
 *
 * This file talks to Supabase over plain HTTPS only — it does NOT touch the
 * database and has no other dependencies, so it is safe to include anywhere.
 *
 * Required environment variables (set these on Render):
 *   SUPABASE_URL          e.g. https://abcdefgh.supabase.co   (no trailing slash needed)
 *   SUPABASE_SERVICE_KEY  the service_role secret key         (server-side only!)
 *   SUPABASE_BUCKET       bucket name, defaults to "product-images"
 */

if (!function_exists('supabase_storage_config')) {

    function supabase_storage_config(): array
    {
        $url = rtrim((string) getenv('SUPABASE_URL'), '/');
        // Tolerate someone pasting the REST or Storage endpoint instead of the
        // project root — we only want https://<ref>.supabase.co here.
        $url = preg_replace('#/(rest|storage|auth|realtime)/v1/?$#', '', $url);
        return [
            'url'    => $url,
            'key'    => (string) getenv('SUPABASE_SERVICE_KEY'),
            'bucket' => getenv('SUPABASE_BUCKET') ?: 'product-images',
        ];
    }

    /**
     * True when the env vars needed for uploading are present.
     */
    function supabase_storage_ready(): bool
    {
        $c = supabase_storage_config();
        return $c['url'] !== '' && $c['key'] !== '';
    }

    /**
     * Upload a PHP file-upload array ($_FILES['image']) to Supabase Storage.
     *
     * @return string|null  Full public URL on success, null on any failure.
     */
    function supabase_upload_image(array $file): ?string
    {
        if (!supabase_storage_ready()) {
            error_log('supabase_upload_image: SUPABASE_URL / SUPABASE_SERVICE_KEY not set');
            return null;
        }
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return null;
        }
        if (!empty($file['error'])) {
            error_log('supabase_upload_image: upload error code ' . $file['error']);
            return null;
        }

        $c = supabase_storage_config();

        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!preg_match('/^[a-z0-9]{2,5}$/', $ext)) {
            $ext = 'jpg';
        }
        $object = 'product_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

        $bytes = file_get_contents($file['tmp_name']);
        if ($bytes === false) {
            return null;
        }

        $mime = 'application/octet-stream';
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($file['tmp_name']);
            if ($detected) {
                $mime = $detected;
            }
        }

        $endpoint = $c['url'] . '/storage/v1/object/' . $c['bucket'] . '/' . rawurlencode($object);
        $headers  = [
            'Authorization: Bearer ' . $c['key'],
            'apikey: ' . $c['key'],
            'Content-Type: ' . $mime,
            'x-upsert: true',
        ];

        $ok = false;

        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $bytes,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
            ]);
            $resp = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($code >= 200 && $code < 300) {
                $ok = true;
            } else {
                error_log("supabase_upload_image: HTTP $code $err $resp");
            }
        } else {
            // Fallback: streams
            $ctx = stream_context_create(['http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $headers),
                'content'       => $bytes,
                'timeout'       => 30,
                'ignore_errors' => true,
            ]]);
            $resp = @file_get_contents($endpoint, false, $ctx);
            $code = 0;
            if (isset($http_response_header[0]) &&
                preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
                $code = (int) $m[1];
            }
            if ($code >= 200 && $code < 300) {
                $ok = true;
            } else {
                error_log("supabase_upload_image (stream): HTTP $code $resp");
            }
        }

        if (!$ok) {
            return null;
        }

        // Public URL for a public bucket
        return $c['url'] . '/storage/v1/object/public/' . $c['bucket'] . '/' . $object;
    }

    /**
     * Best-effort delete of a previously uploaded image, given the value stored
     * in products.image (a full Supabase URL). Local filenames are ignored here.
     */
    function supabase_delete_image(?string $stored): void
    {
        $stored = (string) $stored;
        if ($stored === '' || !supabase_storage_ready()) {
            return;
        }
        $c = supabase_storage_config();
        $needle = '/storage/v1/object/public/' . $c['bucket'] . '/';
        $pos = strpos($stored, $needle);
        if ($pos === false) {
            return; // not one of ours / not a Supabase URL
        }
        $object   = substr($stored, $pos + strlen($needle));
        $endpoint = $c['url'] . '/storage/v1/object/' . $c['bucket'] . '/' . $object;

        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST  => 'DELETE',
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $c['key'],
                    'apikey: ' . $c['key'],
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
            ]);
            curl_exec($ch);
            curl_close($ch);
        }
    }

    /**
     * Resolve a stored image value to something usable in an <img src>.
     *
     *   - New rows hold a full https:// URL  -> returned unchanged
     *   - Old rows hold just a filename       -> prefixed with uploads/
     *   - Empty                               -> uploads/placeholder.png fallback
     *
     * Safe to call from every page — it is a pure string helper.
     */
    function img_url(?string $image): string
    {
        $image = trim((string) $image);
        if ($image === '') {
            // 1x1 transparent PNG — avoids a broken-image icon when a row has no image
            return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
        }
        if (preg_match('#^https?://#i', $image)) {
            return $image;
        }
        return 'uploads/' . ltrim($image, '/');
    }
}
