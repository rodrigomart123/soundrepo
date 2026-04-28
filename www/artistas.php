<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Artistas - SoundRepo</title>
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
                <a href="artistas.php" class="nav-item active">
                    <i class="ph ph-microphone-stage"></i><span>Artistas</span>
                </a>
                <a href="albuns.php" class="nav-item">
                    <i class="ph ph-vinyl-record"></i><span>Álbuns</span>
                </a>
            </div>
        </div>
    </nav>

    <?php
    require 'db.php';

    $sql = "SELECT art.ArtistId, art.Name, 
            COUNT(DISTINCT a.AlbumId) as AlbumCount,
            COUNT(m.MusicId) as TrackCount,
            (SELECT CoverPath FROM Albums WHERE ArtistId = art.ArtistId AND CoverPath IS NOT NULL AND CoverPath != '' LIMIT 1) as CoverPath
            FROM Artists art
            LEFT JOIN Albums a ON a.ArtistId = art.ArtistId
            LEFT JOIN Musics m ON m.AlbumId = a.AlbumId
            GROUP BY art.ArtistId
            ORDER BY art.Name ASC";
    
    try {
        $stmt = $pdo->query($sql);
        $artists = $stmt->fetchAll();
    } catch(Exception $e) {
        $artists = [];
    }
    ?>

    <!-- MAIN VIEW -->
    <main class="main-view fade-in" id="mainContent">
        <div class="view-header">
            <h1 class="view-title">Artistas</h1>
            <input type="text" id="artistSearch" class="library-search" placeholder="Procurar artista..." autocomplete="off">
        </div>

        <div class="library-scroll-container">
            <?php if (count($artists) > 0): ?>
            <div class="card-grid" id="artistGrid">
                <?php foreach($artists as $artist): 
                    $coverStyle = !empty($artist['CoverPath']) ? "background-image: url('".htmlspecialchars($artist['CoverPath'])."');" : "";
                    $albumCount = $artist['AlbumCount'];
                    $trackCount = $artist['TrackCount'];
                    $subtitle = "$albumCount álbum(s) • $trackCount música(s)";
                ?>
                <a href="todas.php?artist=<?= urlencode($artist['Name']) ?>" class="library-card artist-card" data-name="<?= htmlspecialchars(strtolower($artist['Name'])) ?>">
                    <div class="library-card-art" style="<?= $coverStyle ?>">
                        <?php if(empty($artist['CoverPath'])): ?>
                            <i class="ph ph-microphone-stage"></i>
                        <?php endif; ?>
                    </div>
                    <div class="library-card-title"><?= htmlspecialchars($artist['Name']) ?></div>
                    <div class="library-card-subtitle"><?= $subtitle ?></div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="ph ph-microphone-stage"></i>
                <h2>Nenhum artista encontrado</h2>
                <p>A tua biblioteca não tem artistas.</p>
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
        document.getElementById('artistSearch')?.addEventListener('input', (e) => {
            const term = e.target.value.trim().toLowerCase();
            document.querySelectorAll('.artist-card').forEach(card => {
                const name = card.dataset.name || '';
                card.style.display = name.includes(term) ? '' : 'none';
            });
        });
    </script>

</body>
</html>
