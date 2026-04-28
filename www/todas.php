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

    $sql = "SELECT m.MusicId, m.Title, m.FilePath, m.DurationSeconds, m.Genre, m.TrackNumber, a.Title as AlbumName, a.CoverPath, art.Name as ArtistName
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
    
    $sql .= " ORDER BY art.Name ASC, a.Title ASC, CASE WHEN m.TrackNumber IS NULL OR m.TrackNumber = 0 THEN 1 ELSE 0 END ASC, m.TrackNumber ASC, m.Title ASC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hasMusics = count($tracks) > 0;
        
        $groupedTracks = [];
        if ($hasMusics) {
            foreach ($tracks as $track) {
                $albumName = !empty($track['AlbumName']) ? $track['AlbumName'] : 'Outros / Singles';
                $artistName = !empty($track['ArtistName']) ? $track['ArtistName'] : 'Artista Desconhecido';
                $groupKey = $artistName . " | " . $albumName;

                if (!isset($groupedTracks[$groupKey])) {
                    $groupedTracks[$groupKey] = [
                        'AlbumName' => $albumName,
                        'ArtistName' => $artistName,
                        'CoverPath' => $track['CoverPath'],
                        'Tracks' => []
                    ];
                }
                $groupedTracks[$groupKey]['Tracks'][] = $track;
            }
        }

    } catch(Exception $e) {
        $tracks = [];
        $hasMusics = false;
        $groupedTracks = [];
    }

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
            $displayNumber = !empty($row['TrackNumber']) ? (int) $row['TrackNumber'] : $counter;

            $html .= "<tr class='track-row' 
                        data-id='$id' 
                        data-src='$caminho' 
                        data-title='$titulo' 
                        data-artist='$artista' 
                        data-album='$album'
                        data-cover='$cover'
                        data-genre='$genero'>
                        <td style='text-align:center; position:relative;'>
                            <span class='track-num'>$displayNumber</span>
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

    <main class="main-view fade-in" id="mainContent">
        <div class="view-header">
            <h1 class="view-title"><?= $viewTitle ?></h1>
            <input type="text" id="searchPersistent" class="library-search" placeholder="Filtrar nesta lista..." autocomplete="off">
        </div>

        <div class="track-list-container" style="padding: 24px 32px;">
            <?php if($hasMusics): ?>
                <?php foreach($groupedTracks as $groupKey => $albumData): 
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
                                <h2><?= htmlspecialchars($albumData['AlbumName']) ?></h2>
                                <p><?= htmlspecialchars($albumData['ArtistName']) ?> • <?= count($albumData['Tracks']) ?> música(s)</p>
                            </div>
                        </div>
                        <?= renderTrackTable($albumData['Tracks']) ?>
                    </div>
                <?php endforeach; ?>
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
