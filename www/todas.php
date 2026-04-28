<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Todas as Músicas - SoundRepo</title>
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
                <a href="todas.php" class="nav-item active">
                    <i class="ph ph-music-notes"></i><span>Todas as Músicas</span>
                </a>
                <a href="artistas.php" class="nav-item">
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

    $queryArtist = $_GET['artist'] ?? '';
    $queryAlbum = $_GET['album'] ?? '';

    $viewTitle = "Todas as Músicas";
    if ($queryAlbum && $queryArtist) {
        $viewTitle = htmlspecialchars($queryAlbum) . " — " . htmlspecialchars($queryArtist);
    } elseif ($queryArtist) {
        $viewTitle = htmlspecialchars($queryArtist);
    } elseif ($queryAlbum) {
        $viewTitle = htmlspecialchars($queryAlbum);
    }

    // Build SQL with filters
    $sql = "SELECT m.MusicId, m.Title, m.FilePath, m.DurationSeconds, m.Genre, a.Title as AlbumName, a.CoverPath, art.Name as ArtistName
            FROM Musics m
            LEFT JOIN Albums a ON m.AlbumId = a.AlbumId
            LEFT JOIN Artists art ON a.ArtistId = art.ArtistId";
    
    $conditions = [];
    $params = [];
    
    if ($queryArtist) {
        $conditions[] = "art.Name = :artist";
        $params[':artist'] = $queryArtist;
    }
    if ($queryAlbum) {
        $conditions[] = "a.Title = :album";
        $params[':album'] = $queryAlbum;
    }
    
    if (count($conditions) > 0) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    // Check if we are viewing a specific artist's discography (no specific album chosen)
    $isArtistView = ($queryArtist && !$queryAlbum);

    if ($isArtistView) {
        // Group by album naturally in SQL
        $sql .= " ORDER BY a.Title ASC, m.Title ASC";
    } else {
        $sql .= " ORDER BY m.MusicId DESC";
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hasMusics = count($tracks) > 0;
        
        // Prepare grouped structure if needed
        $groupedTracks = [];
        if ($isArtistView && $hasMusics) {
            foreach ($tracks as $track) {
                $albumName = !empty($track['AlbumName']) ? $track['AlbumName'] : 'Outros / Singles';
                if (!isset($groupedTracks[$albumName])) {
                    $groupedTracks[$albumName] = [
                        'CoverPath' => $track['CoverPath'],
                        'Tracks' => []
                    ];
                }
                $groupedTracks[$albumName]['Tracks'][] = $track;
            }
        }

    } catch(Exception $e) {
        $tracks = [];
        $hasMusics = false;
        $groupedTracks = [];
    }

    // Helper function to render table of tracks
    function renderTrackTable($trackList) {
        $html = '<table>
                    <thead>
                        <tr>
                            <th style="width:50px; text-align:center;">#</th>
                            <th>Título</th>
                            <th>Artista</th>
                            <th>Álbum</th>
                            <th style="width:60px; text-align:right;"><i class="ph ph-clock"></i></th>
                        </tr>
                    </thead>
                    <tbody>';
        $counter = 1;
        foreach($trackList as $row) {
            $caminho = str_replace('\\', '/', $row['FilePath']);
            $caminho = str_replace('www/', '', $caminho);
            $titulo = htmlspecialchars($row['Title'], ENT_QUOTES);
            $artista = htmlspecialchars($row['ArtistName'] ?? 'Desconhecido', ENT_QUOTES);
            $album = htmlspecialchars($row['AlbumName'] ?? 'Desconhecido', ENT_QUOTES);
            $cover = !empty($row['CoverPath']) ? str_replace('\\', '/', $row['CoverPath']) : '';
            $genero = htmlspecialchars($row['Genre'] ?? '', ENT_QUOTES);
            $id = $row['MusicId'];

            $html .= "<tr class='track-row' 
                        data-id='$id' 
                        data-src='$caminho' 
                        data-title='$titulo' 
                        data-artist='$artista' 
                        data-album='$album'
                        data-cover='$cover'
                        data-genre='$genero'>
                        <td style='text-align:center; position:relative;'>
                            <span class='track-num'>$counter</span>
                            <i class='ph-fill ph-play play-icon'></i>
                        </td>
                        <td><span class='track-title'>$titulo</span></td>
                        <td>$artista</td>
                        <td>$album</td>
                        <td style='text-align:right; font-variant-numeric:tabular-nums;'>--:--</td>
                    </tr>";
            $counter++;
        }
        $html .= '</tbody></table>';
        return $html;
    }
    ?>

    <!-- MAIN VIEW -->
    <main class="main-view fade-in" id="mainContent">
        <div class="view-header">
            <h1 class="view-title"><?= $viewTitle ?></h1>
            <input type="text" id="searchPersistent" class="library-search" placeholder="Filtrar nesta lista..." autocomplete="off">
        </div>

        <!-- Track List -->
        <div class="track-list-container" style="padding: 24px 32px;">
            <?php if($hasMusics): ?>
                <?php if ($isArtistView): ?>
                    <!-- ARTIST TREE VIEW (GROUP BY ALBUM) -->
                    <?php foreach($groupedTracks as $albumName => $albumData): 
                        $coverStyle = !empty($albumData['CoverPath']) ? "background-image: url('".str_replace('\\', '/', htmlspecialchars($albumData['CoverPath']))."');" : "";
                    ?>
                        <div class="album-group">
                            <div class="album-group-header">
                                <div class="album-group-cover" style="<?= $coverStyle ?>">
                                    <?php if(empty($albumData['CoverPath'])): ?>
                                        <i class="ph ph-vinyl-record"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="album-group-info">
                                    <h2><?= htmlspecialchars($albumName) ?></h2>
                                    <p><?= count($albumData['Tracks']) ?> música(s)</p>
                                </div>
                            </div>
                            <?= renderTrackTable($albumData['Tracks']) ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- STANDARD FLAT LIST -->
                    <div style="background: var(--bg-surface); border-radius: var(--radius-lg); overflow: hidden; border: 1px solid var(--border-light);">
                        <?= renderTrackTable($tracks) ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="ph ph-music-notes"></i>
                    <h2>Nenhuma música encontrada</h2>
                    <p>A tua biblioteca está vazia ou não há músicas com esses filtros.</p>
                    <button class="btn-primary" onclick="window.location.href='adicionar.php'" style="margin-top:16px;">
                        Adicionar Música
                    </button>
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
        initDashboardEvents();
        loadTrackDurations();
    </script>

</body>
</html>
