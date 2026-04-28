<?php
require 'db.php';
header('Content-Type: application/json; charset=utf-8');

// ============================================
// API — DELETE TRACK
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'ID inválido']);
        exit;
    }

    try {
        // 1. Get music + album info
        $stmt = $pdo->prepare(
            "SELECT m.MusicId, m.FilePath, m.AlbumId, a.CoverPath, a.ArtistId
             FROM Musics m
             JOIN Albums a ON m.AlbumId = a.AlbumId
             WHERE m.MusicId = ?"
        );
        $stmt->execute([$id]);
        $music = $stmt->fetch();

        if (!$music) {
            echo json_encode(['ok' => false, 'error' => 'Música não encontrada']);
            exit;
        }

        $albumId  = $music['AlbumId'];
        $artistId = $music['ArtistId'];

        // 2. Delete the music record
        $stmt = $pdo->prepare("DELETE FROM Musics WHERE MusicId = ?");
        $stmt->execute([$id]);

        // 3. Delete physical audio file
        if (!empty($music['FilePath']) && file_exists($music['FilePath'])) {
            @unlink($music['FilePath']);
        }

        // 4. Check if album is now empty
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM Musics WHERE AlbumId = ?");
        $stmt->execute([$albumId]);
        $albumCount = (int)$stmt->fetchColumn();

        if ($albumCount === 0) {
            // Delete album cover file
            if (!empty($music['CoverPath']) && file_exists($music['CoverPath'])) {
                @unlink($music['CoverPath']);
            }
            // Delete album record
            $stmt = $pdo->prepare("DELETE FROM Albums WHERE AlbumId = ?");
            $stmt->execute([$albumId]);

            // 5. Check if artist has no more albums
            $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM Albums WHERE ArtistId = ?");
            $stmt->execute([$artistId]);
            $artistCount = (int)$stmt->fetchColumn();

            if ($artistCount === 0) {
                $stmt = $pdo->prepare("DELETE FROM Artists WHERE ArtistId = ?");
                $stmt->execute([$artistId]);
            }
        }

        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Ação inválida']);
