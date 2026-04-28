<?php

function soundrepoHttpGet(string $url, int $timeoutSeconds = 5)
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeoutSeconds,
            'header' => "User-Agent: Mozilla/5.0\r\nAccept-Language: en-US,en;q=0.9\r\n",
        ],
    ]);

    return @file_get_contents($url, false, $context);
}

function soundrepoSlugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
    $value = trim($value, '-');

    return $value !== '' ? $value : 'artist';
}

function soundrepoNormalizeAppleImageUrl(string $url): string
{
    $url = html_entity_decode(str_replace('\/', '/', $url), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    if (preg_match('~\.(jpg|jpeg|png|webp)$~i', $url, $matches)) {
        $ext = strtolower($matches[1]);
        $url = preg_replace('~/\d+x\d+[a-z]{2}(?:-\d+)?\.(jpg|jpeg|png|webp)$~i', '/1200x1200cc.' . $ext, $url);
    }

    return $url;
}

function soundrepoExtractArtistImageUrl(string $html): string
{
    // tenta primeiro o bloco json da página e só depois o og:image
    if (preg_match('/<script[^>]+id="schema:music-group"[^>]*>(.*?)<\/script>/is', $html, $matches)) {
        $schemaJson = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $schema = json_decode($schemaJson, true);

        if (is_array($schema) && !empty($schema['image']) && is_string($schema['image'])) {
            return soundrepoNormalizeAppleImageUrl($schema['image']);
        }
    }

    if (preg_match('/<meta[^>]+property="og:image"[^>]+content="([^"]+)"/i', $html, $matches)) {
        return soundrepoNormalizeAppleImageUrl($matches[1]);
    }

    return '';
}

function soundrepoDownloadArtistImage(string $artistName, int $artistId): string
{
    $searchUrl = 'https://itunes.apple.com/search?term=' . rawurlencode($artistName) . '&entity=musicArtist&limit=1';
    $searchResponse = soundrepoHttpGet($searchUrl, 4);

    if ($searchResponse === false) {
        return '';
    }

    $searchData = json_decode($searchResponse, true);
    if (!is_array($searchData) || empty($searchData['results'][0]['artistLinkUrl'])) {
        return '';
    }

    $artistPageUrl = $searchData['results'][0]['artistLinkUrl'];
    $artistPageHtml = soundrepoHttpGet($artistPageUrl, 5);
    if ($artistPageHtml === false) {
        return '';
    }

    $imageUrl = soundrepoExtractArtistImageUrl($artistPageHtml);
    if ($imageUrl === '') {
        return '';
    }

    $imageData = soundrepoHttpGet($imageUrl, 6);
    if ($imageData === false || strlen($imageData) < 1024) {
        return '';
    }

    $path = parse_url($imageUrl, PHP_URL_PATH) ?: '';
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        $extension = 'jpg';
    }

    $directory = __DIR__ . '/artist_images';
    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }

    if (!is_dir($directory)) {
        return '';
    }

    $relativePath = 'artist_images/artist_' . $artistId . '_' . soundrepoSlugify($artistName) . '.' . $extension;
    $fullPath = __DIR__ . '/' . $relativePath;

    if (@file_put_contents($fullPath, $imageData) === false) {
        return '';
    }

    return $relativePath;
}

function soundrepoEnsureArtistImage(PDO $pdo, int $artistId, string $artistName, ?string $currentImagePath = null, int $lookupChecked = 0): string
{
    if (!empty($currentImagePath) && file_exists(__DIR__ . '/' . $currentImagePath)) {
        return $currentImagePath;
    }

    if ($lookupChecked) {
        return '';
    }

    $imagePath = soundrepoDownloadArtistImage($artistName, $artistId);

    $stmt = $pdo->prepare('UPDATE Artists SET ImagePath = ?, ImageLookupChecked = 1 WHERE ArtistId = ?');
    $stmt->execute([$imagePath !== '' ? $imagePath : null, $artistId]);

    return $imagePath;
}
