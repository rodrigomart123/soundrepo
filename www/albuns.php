<?php
require 'db.php';
?>
<main class="main-view fade-in" id="mainContent">
    <div class="view-header">
        <h1 class="view-title">Álbuns</h1>
        <input type="text" id="albumSearch" class="library-search" placeholder="Procurar álbum..." autocomplete="off">
    </div>

    <?php
    $sql = "SELECT a.AlbumId, a.Title, a.CoverPath, a.Year, art.Name as ArtistName,
            COUNT(m.MusicId) as TrackCount
            FROM Albums a
            JOIN Artists art ON a.ArtistId = art.ArtistId
            LEFT JOIN Musics m ON m.AlbumId = a.AlbumId
            GROUP BY a.AlbumId
            ORDER BY a.Title ASC";
    
    try {
        $stmt = $pdo->query($sql);
        $albums = $stmt->fetchAll();
    } catch(Exception $e) {
        $albums = [];
    }
    ?>

    <div class="library-scroll-container">
        <?php if (count($albums) > 0): ?>
        <div class="card-grid" id="albumGrid">
            <?php foreach($albums as $album): 
                $coverStyle = !empty($album['CoverPath']) ? "background-image: url('".htmlspecialchars($album['CoverPath'])."');" : "";
                $year = $album['Year'] ? $album['Year'] : 'Desconhecido';
                $subtitle = $album['ArtistName'] . " • " . $album['TrackCount'] . " música(s)";
            ?>
            <a href="todas.php?album=<?= urlencode($album['Title']) ?>&artist=<?= urlencode($album['ArtistName']) ?>" class="library-card" data-title="<?= htmlspecialchars(strtolower($album['Title'])) ?>" data-artist="<?= htmlspecialchars(strtolower($album['ArtistName'])) ?>">
                <div class="library-card-art" style="<?= $coverStyle ?>">
                    <?php if(empty($album['CoverPath'])): ?>
                        <i class="ph ph-vinyl-record"></i>
                    <?php endif; ?>
                </div>
                <div class="library-card-title"><?= htmlspecialchars($album['Title']) ?></div>
                <div class="library-card-subtitle"><?= $subtitle ?></div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="ph ph-vinyl-record"></i>
            <h2>Nenhum álbum encontrado</h2>
            <p>A tua biblioteca não tem álbuns.</p>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Inline script to handle local filtering
        document.getElementById('albumSearch')?.addEventListener('input', (e) => {
            const term = e.target.value.trim().toLowerCase();
            document.querySelectorAll('.library-card').forEach(card => {
                const title = card.dataset.title || '';
                const artist = card.dataset.artist || '';
                card.style.display = (title.includes(term) || artist.includes(term)) ? '' : 'none';
            });
        });
    </script>
</main>
