<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Álbuns - SoundRepo</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/search-overlay.css">
</head>
<body>

    <!-- SIDEBAR -->
    <nav class="sidebar">
        <div class="sidebar-top">
            <div class="nav-section">
                <a href="index.php" class="nav-item">
                    <i class="ph ph-house"></i><span>Início</span>
                </a>
                <a href="#" class="nav-item" id="navSearch">
                    <i class="ph ph-magnifying-glass"></i><span>Pesquisar</span>
                </a>
                <a href="#" class="nav-item" id="navQueue">
                    <i class="ph ph-queue"></i><span>Fila</span>
                </a>
            </div>
            <div class="nav-divider"></div>
            <div class="nav-label">Biblioteca</div>
            <div class="nav-section">
                <a href="adicionar.php" class="nav-item">
                    <i class="ph ph-plus-circle"></i><span>Adicionar Música</span>
                </a>
                <a href="todas.php" class="nav-item">
                    <i class="ph ph-music-notes"></i><span>Todas as Músicas</span>
                </a>
                <a href="artistas.php" class="nav-item">
                    <i class="ph ph-microphone-stage"></i><span>Artistas</span>
                </a>
                <a href="albuns.php" class="nav-item active">
                    <i class="ph ph-vinyl-record"></i><span>Álbuns</span>
                </a>
            </div>
        </div>
    </nav>

    <?php
    require 'db.php';

    $sql = "SELECT a.AlbumId, a.Title, a.CoverPath, a.Year, art.Name as ArtistName,
            COUNT(m.MusicId) as TrackCount
            FROM Albums a
            LEFT JOIN Artists art ON a.ArtistId = art.ArtistId
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

    <!-- MAIN VIEW -->
    <main class="main-view fade-in" id="mainContent">
        <div class="view-header">
            <h1 class="view-title">Álbuns</h1>
            <input type="text" id="albumSearch" class="library-search" placeholder="Procurar álbum..." autocomplete="off">
        </div>

        <div class="library-scroll-container">
            <?php if (count($albums) > 0): ?>
            <div class="card-grid" id="albumGrid">
                <?php foreach($albums as $album): 
                    $coverStyle = !empty($album['CoverPath']) ? "background-image: url('".htmlspecialchars($album['CoverPath'])."');" : "";
                    $year = $album['Year'] ? $album['Year'] : 'Desconhecido';
                    $artistName = $album['ArtistName'] ?? 'Desconhecido';
                    $subtitle = $artistName . " • " . $album['TrackCount'] . " música(s)";
                ?>
                <a href="todas.php?album=<?= urlencode($album['Title']) ?>&artist=<?= urlencode($artistName) ?>" class="library-card" data-title="<?= htmlspecialchars(strtolower($album['Title'])) ?>" data-artist="<?= htmlspecialchars(strtolower($artistName)) ?>">
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
    </main>

    <?php include 'player.php'; ?>
    <?php include 'search-overlay.php'; ?>

    <script src="js/spa.js"></script>
    <script src="js/player.js"></script>
    <script src="js/queue.js"></script>
    <script src="js/search.js"></script>
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

</body>
</html>
