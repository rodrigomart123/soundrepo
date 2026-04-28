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
require 'apple_music.php';

    $sql = "SELECT art.ArtistId, art.Name, art.ImagePath, art.ImageLookupChecked,
            COUNT(DISTINCT a.AlbumId) as AlbumCount,
            COUNT(m.MusicId) as TrackCount
            FROM Artists art
            LEFT JOIN Albums a ON a.ArtistId = art.ArtistId
            LEFT JOIN Musics m ON m.AlbumId = a.AlbumId
            GROUP BY art.ArtistId, art.Name, art.ImagePath, art.ImageLookupChecked
            ORDER BY art.Name ASC";
    
    try {
        $stmt = $pdo->query($sql);
        $artists = $stmt->fetchAll();

        foreach ($artists as &$artist) {
            $artist['ImagePath'] = soundrepoEnsureArtistImage(
                $pdo,
                (int) $artist['ArtistId'],
                $artist['Name'],
                $artist['ImagePath'] ?? null,
                (int) ($artist['ImageLookupChecked'] ?? 0)
            );
        }
        unset($artist);
    } catch(Exception $e) {
        $artists = [];
    }
    ?>

    <main class="main-view fade-in" id="mainContent">
        <div class="view-header">
            <h1 class="view-title">Artistas</h1>
            <input type="text" id="artistSearch" class="library-search" placeholder="Procurar artista..." autocomplete="off">
        </div>

        <div class="library-scroll-container">
            <?php if (count($artists) > 0): ?>
            <div class="card-grid" id="artistGrid">
                <?php foreach($artists as $artist): 
                    $hasArtistImage = !empty($artist['ImagePath']) && file_exists(__DIR__ . '/' . $artist['ImagePath']);
                    $coverStyle = $hasArtistImage ? "background-image: url('".htmlspecialchars($artist['ImagePath'])."');" : "";
                    $artistInitial = function_exists('mb_substr') ? mb_strtoupper(mb_substr(trim($artist['Name']), 0, 1, 'UTF-8'), 'UTF-8') : strtoupper(substr(trim($artist['Name']), 0, 1));
                    $albumCount = $artist['AlbumCount'];
                    $trackCount = $artist['TrackCount'];
                    $subtitle = "$albumCount álbum(s) • $trackCount música(s)";
                ?>
                <a href="todas.php?artist=<?= urlencode($artist['Name']) ?>" class="library-card artist-card" data-name="<?= htmlspecialchars(strtolower($artist['Name'])) ?>">
                    <div class="library-card-art" style="<?= $coverStyle ?>">
                        <?php if(!$hasArtistImage): ?>
                            <?php if($artistInitial !== ''): ?>
                                <span class="artist-card-initial"><?= htmlspecialchars($artistInitial) ?></span>
                            <?php else: ?>
                                <i class="ph ph-user"></i>
                            <?php endif; ?>
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

        <script>
            (function() {
                const searchInput = document.getElementById('artistSearch');
                if (searchInput) {
                    searchInput.addEventListener('input', (e) => {
                        const term = e.target.value.trim().toLowerCase();
                        document.querySelectorAll('.artist-card').forEach(card => {
                            const name = card.dataset.name || '';
                            card.style.display = name.includes(term) ? '' : 'none';
                        });
                    });
                }
            })();
        </script>
    </main>

    <footer class="player-bar">
        <div class="player-track-info">
            <div class="player-mini-art" id="footerArt">
                <i class="ph ph-music-note"></i>
            </div>
            <div class="player-track-text">
                <div class="player-track-title" id="footerTitle">SoundRepo</div>
                <div class="player-track-artist" id="footerArtist">Pronto</div>
            </div>
        </div>

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

        <div class="volume-controls">
            <i class="ph ph-speaker-high" id="volIcon" onclick="toggleMute()"></i>
            <input type="range" id="volumeSlider" min="0" max="1" step="0.01" value="1">
        </div>
    </footer>

    <audio id="audioPlayer" preload="metadata"></audio>

    <div class="search-overlay" id="searchOverlay" style="display:none;">
        <div class="search-container">
            <div class="search-bar-container">
                <i class="ph ph-magnifying-glass search-bar-icon"></i>
                <input type="text" id="searchInput" class="search-bar-input" placeholder="Pesquisar músicas, artistas, álbuns..." autocomplete="off">
                <button class="search-bar-close" id="searchClose"><i class="ph ph-x"></i></button>
            </div>
            
            <div class="search-results" id="searchResults"></div>
        </div>
    </div>

    <div class="context-menu" id="contextMenu" style="display:none;">
        <div class="context-menu-item" id="ctxAddQueue"><i class="ph ph-queue"></i> Adicionar à Fila</div>
        <div class="context-menu-divider"></div>
        <div class="context-menu-item ctx-danger" id="ctxDelete"><i class="ph ph-trash"></i> Apagar Música</div>
    </div>

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
</body>
</html>
