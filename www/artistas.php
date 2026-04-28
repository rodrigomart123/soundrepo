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

    <!-- PLAYER BAR -->
    <footer class="player-bar">
        <!-- Left: Track Info -->
        <div class="player-track-info">
            <div class="player-mini-art" id="footerArt">
                <i class="ph ph-music-note"></i>
            </div>
            <div class="player-track-text">
                <div class="player-track-title" id="footerTitle">SoundRepo</div>
                <div class="player-track-artist" id="footerArtist">Pronto</div>
            </div>
        </div>

        <!-- Center: Controls -->
        <div class="controls-center">
            <div class="buttons-row">
                <button class="btn-control" id="btnShuffle" onclick="toggleShuffle()" title="Aleatório">
                    <i class="ph ph-shuffle"></i>
                </button>
                <button class="btn-control" id="btnPrev" onclick="prevTrack()" title="Anterior">
                    <i class="ph-fill ph-skip-back"></i>
                </button>
                <button class="btn-main" id="btnPlay" onclick="togglePlay()" title="Play / Pause">
                    <i class="ph-fill ph-play" id="playIcon"></i>
                </button>
                <button class="btn-control" id="btnNext" onclick="nextTrack()" title="Próxima">
                    <i class="ph-fill ph-skip-forward"></i>
                </button>
                <button class="btn-control" id="btnRepeat" onclick="toggleRepeat()" title="Repetir">
                    <i class="ph ph-repeat"></i>
                </button>
            </div>
            <div class="progress-row">
                <span class="time" id="currentTime">0:00</span>
                <input type="range" id="seekSlider" min="0" max="100" value="0" step="0.1">
                <span class="time" id="totalTime">0:00</span>
            </div>
        </div>

        <!-- Right: Volume -->
        <div class="volume-controls">
            <i class="ph ph-speaker-high" id="volIcon" onclick="toggleMute()"></i>
            <input type="range" id="volumeSlider" min="0" max="1" step="0.01" value="1">
        </div>
    </footer>

    <audio id="audioPlayer" preload="metadata"></audio>

    <!-- SEARCH OVERLAY -->
    <div class="search-overlay" id="searchOverlay" style="display:none;">
        <div class="search-container">
            <div class="search-bar-container">
                <i class="ph ph-magnifying-glass search-bar-icon"></i>
                <input type="text" id="searchInput" class="search-bar-input" placeholder="Pesquisar músicas, artistas, álbuns..." autocomplete="off">
                <button class="search-bar-close" id="searchClose"><i class="ph ph-x"></i></button>
            </div>
            
            <div class="search-results" id="searchResults">
                <!-- Results will be injected here -->
            </div>
        </div>
    </div>

    <!-- CONTEXT MENU -->
    <div class="context-menu" id="contextMenu" style="display:none;">
        <div class="context-menu-item" id="ctxAddQueue"><i class="ph ph-queue"></i> Adicionar à Fila</div>
        <div class="context-menu-divider"></div>
        <div class="context-menu-item ctx-danger" id="ctxDelete"><i class="ph ph-trash"></i> Apagar Música</div>
    </div>

    <!-- BULK ACTIONS BAR -->
    <div class="bulk-actions-bar" id="bulkActionsBar" style="display:none;">
        <div class="bulk-actions-info">
            <span id="bulkSelectedCount">0</span> música(s) selecionada(s)
        </div>
        <div class="bulk-actions-buttons">
            <button class="bulk-action-btn" id="bulkAddQueue">
                <i class="ph ph-queue"></i> <span>Adicionar à Fila</span>
            </button>
            <button class="bulk-action-btn bulk-action-danger" id="bulkDelete">
                <i class="ph ph-trash"></i> <span>Apagar</span>
            </button>
            <button class="bulk-action-btn" id="bulkCancel">
                <i class="ph ph-x"></i> <span>Cancelar</span>
            </button>
        </div>
    </div>

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
