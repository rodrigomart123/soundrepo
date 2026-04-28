<?php
if(file_exists('db.php')) {
    require 'db.php';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>SoundRepo</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <!-- SIDEBAR -->
    <nav class="sidebar">
        <div class="sidebar-top">
            <div class="nav-section">
                <a href="index.php" class="nav-item active">
                    <i class="ph ph-house"></i><span>In&iacute;cio</span>
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
                    <i class="ph ph-plus-circle"></i><span>Adicionar M&uacute;sica</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="ph ph-music-notes"></i><span>Todas as M&uacute;sicas</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="ph ph-microphone-stage"></i><span>Artistas</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="ph ph-vinyl-record"></i><span>&Aacute;lbuns</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- MAIN VIEW -->
    <main class="main-view" id="mainContent">
        
        <!-- Column Browser -->
        <div class="column-browser">
            <div class="col-filter">
                <div class="col-header">G&eacute;nero</div>
                <div class="col-content">
                    <div class="filter-row selected" data-filter="genre" data-value="">Todos</div>
                    <?php
                    if(isset($pdo)) {
                        try {
                            $stmt = $pdo->query("SELECT DISTINCT Genre FROM Musics WHERE Genre IS NOT NULL AND Genre != '' ORDER BY Genre");
                            while($g = $stmt->fetch()) {
                                $genre = htmlspecialchars($g['Genre'], ENT_QUOTES);
                                echo "<div class='filter-row' data-filter='genre' data-value='$genre'>$genre</div>";
                            }
                        } catch(Exception $e) {
                            echo "<div class='filter-row'>Pop</div><div class='filter-row'>Rock</div>";
                        }
                    }
                    ?>
                </div>
            </div>
            <div class="col-filter">
                <div class="col-header">Artista</div>
                <div class="col-content">
                    <div class="filter-row selected" data-filter="artist" data-value="">Todos</div>
                    <?php
                    if(isset($pdo)) {
                        try {
                            $stmt = $pdo->query("SELECT DISTINCT Name FROM Artists ORDER BY Name");
                            while($art = $stmt->fetch()) {
                                $name = htmlspecialchars($art['Name'], ENT_QUOTES);
                                echo "<div class='filter-row' data-filter='artist' data-value='$name'>$name</div>";
                            }
                        } catch(Exception $e) {}
                    }
                    ?>
                </div>
            </div>
            <div class="col-filter">
                <div class="col-header">&Aacute;lbum</div>
                <div class="col-content">
                    <div class="filter-row selected" data-filter="album" data-value="">Todos</div>
                    <?php
                    if(isset($pdo)) {
                        try {
                            $stmt = $pdo->query("SELECT DISTINCT Title FROM Albums ORDER BY Title");
                            while($alb = $stmt->fetch()) {
                                $title = htmlspecialchars($alb['Title'], ENT_QUOTES);
                                echo "<div class='filter-row' data-filter='album' data-value='$title'>$title</div>";
                            }
                        } catch(Exception $e) {}
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Persistent Search Bar -->
        <div class="search-bar-persistent" id="searchBarPersistent">
            <i class="ph ph-magnifying-glass search-persistent-icon"></i>
            <input type="text" id="searchPersistent" class="search-persistent-input" placeholder="Filtrar músicas...">
            <button type="button" id="searchClearBtn" class="search-clear-btn" style="display:none;" title="Limpar filtro">
                <i class="ph ph-x"></i>
            </button>
        </div>

        <!-- Track List -->
        <div class="track-list-container">
            <?php
            $hasMusics = false;
            if(isset($pdo)) {
                // ATENÇÃO: Adicionei 'a.CoverPath' na query SQL abaixo
                $sql = "SELECT m.MusicId, m.Title, m.FilePath, m.Genre, a.Title as AlbumName, a.CoverPath, art.Name as ArtistName
                        FROM Musics m
                        JOIN Albums a ON m.AlbumId = a.AlbumId
                        JOIN Artists art ON a.ArtistId = art.ArtistId
                        ORDER BY m.MusicId DESC";
                try {
                    $stmt = $pdo->query($sql);
                    $tracks = $stmt->fetchAll();
                    $hasMusics = count($tracks) > 0;
                } catch(Exception $e) {
                    $tracks = [];
                }
            } else {
                $tracks = [];
            }

            if($hasMusics):
            ?>
            <table>
                <thead>
                    <tr>
                        <th style="width:50px; text-align:center;">#</th>
                        <th>T&iacute;tulo</th>
                        <th>Artista</th>
                        <th>&Aacute;lbum</th>
                        <th style="width:60px; text-align:right;"><i class="ph ph-clock"></i></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $counter = 1;
                    foreach($tracks as $row) {
                        $caminho = str_replace('\\', '/', $row['FilePath']);
                        $caminho = str_replace('www/', '', $caminho);
                        $titulo = htmlspecialchars($row['Title'], ENT_QUOTES);
                        $artista = htmlspecialchars($row['ArtistName'], ENT_QUOTES);
                        $album = htmlspecialchars($row['AlbumName'], ENT_QUOTES);
                        
                        // Processar capa
                        $cover = !empty($row['CoverPath']) ? str_replace('\\', '/', $row['CoverPath']) : '';
                        $genero = htmlspecialchars($row['Genre'] ?? '', ENT_QUOTES);
                        
                        $id = $row['MusicId'];

                        echo "<tr class='track-row' 
                                  data-id='$id' 
                                  data-src='$caminho' 
                                  data-title='$titulo' 
                                  data-artist='$artista' 
                                  data-album='$album'
                                  data-cover='$cover'
                                  data-genre='$genero'>";
                        echo "  <td style='text-align:center; position:relative;'>";
                        echo "    <span class='track-num'>$counter</span>";
                        echo "    <i class='ph-fill ph-play play-icon'></i>";
                        echo "  </td>";
                        echo "  <td><span class='track-title'>$titulo</span></td>";
                        echo "  <td>$artista</td>";
                        echo "  <td>$album</td>";
                        echo "  <td style='text-align:right; font-variant-numeric:tabular-nums;'>--:--</td>";
                        echo "</tr>";
                        $counter++;
                    }
                    ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="ph ph-music-notes-simple"></i>
                <p>A tua biblioteca est&aacute; vazia</p>
                <a href="adicionar.php">Adicionar a primeira m&uacute;sica</a>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- RIGHT PANEL -->
    <aside class="right-panel" id="rightPanel">
        <div class="right-panel-header">
            <span>A Tocar</span>
        </div>
        
        <div class="album-art-large" id="npArt">
            <i class="ph ph-music-notes-simple art-icon"></i>
            <span class="art-initial" id="npArtInitial" style="display:none;"></span>
        </div>

        <div class="np-info">
            <div class="np-title" id="npTitle">SoundRepo</div>
            <div class="np-artist" id="npArtist">Seleciona uma m&uacute;sica</div>
            <div class="np-album" id="npAlbum"></div>
        </div>
    </aside>

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
                <button class="btn-control" id="btnShuffle" onclick="toggleShuffle()" title="Aleat&oacute;rio">
                    <i class="ph ph-shuffle"></i>
                </button>
                <button class="btn-control" id="btnPrev" onclick="prevTrack()" title="Anterior">
                    <i class="ph-fill ph-skip-back"></i>
                </button>
                <button class="btn-main" id="btnPlay" onclick="togglePlay()" title="Play / Pause">
                    <i class="ph-fill ph-play" id="playIcon"></i>
                </button>
                <button class="btn-control" id="btnNext" onclick="nextTrack()" title="Pr&oacute;xima">
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
        <div class="search-bar-container">
            <i class="ph ph-magnifying-glass search-bar-icon"></i>
            <input type="text" id="searchInput" class="search-bar-input" placeholder="Pesquisar m&uacute;sicas, artistas, &aacute;lbuns..." autocomplete="off">
            <button class="search-bar-close" id="searchClose"><i class="ph ph-x"></i></button>
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
                <i class="ph ph-queue"></i> Adicionar à Fila
            </button>
            <button class="bulk-action-btn bulk-action-danger" id="bulkDelete">
                <i class="ph ph-trash"></i> Apagar
            </button>
            <button class="bulk-action-btn" id="bulkCancel">
                <i class="ph ph-x"></i> Cancelar
            </button>
        </div>
    </div>

    <script>
    // ========================
    // SOUNDREPO PLAYER ENGINE
    // ========================
    const audio = document.getElementById('audioPlayer');
    const seekSlider = document.getElementById('seekSlider');
    const volSlider = document.getElementById('volumeSlider');
    const playIcon = document.getElementById('playIcon');
    const npArt = document.getElementById('npArt');
    const npArtInitial = document.getElementById('npArtInitial');
    const footerArt = document.getElementById('footerArt');

    let isDragging = false;
    let shuffleOn = false;
    let repeatMode = 0; // 0=off, 1=all, 2=one
    let currentTrackEl = null;
    let playingTrackId = null;
    let selectedTracks = new Set(); // Track selection
    let lastSelectedIndex = -1; // For shift-click selection

    // === TRACK LIST ===
    function getAllTracks() {
        return Array.from(document.querySelectorAll('.track-row'));
    }

    // === PLAY TRACK ===
    function playTrack(el) {
        const src = el.dataset.src;
        const title = el.dataset.title;
        const artist = el.dataset.artist;
        const album = el.dataset.album;
        const cover = el.dataset.cover;

        // Check if browser supports the audio format
        const ext = src.split('.').pop().toLowerCase();
        const canPlay = audio.canPlayType('audio/' + ext);
        
        if (!canPlay && ext === 'flac') {
            // Try alternative MIME types for FLAC
            const canPlayFlac = audio.canPlayType('audio/flac') || audio.canPlayType('audio/x-flac');
            if (!canPlayFlac) {
                showToast('Formato FLAC não suportado neste navegador. Tenta converter para MP3.');
                return;
            }
        }

        audio.src = src;
        audio.load(); // Force reload
        audio.play().catch((err) => {
            console.error('Playback error:', err);
            showToast('Erro ao reproduzir: ' + title);
        });
        currentTrackEl = el;
        playingTrackId = el.dataset.id;

        // Update Text UI
        document.getElementById('npTitle').textContent = title;
        document.getElementById('npArtist').textContent = artist;
        document.getElementById('npAlbum').textContent = album;
        document.getElementById('footerTitle').textContent = title;
        document.getElementById('footerArtist').textContent = artist;

        // === LOGICA DA CAPA (ATUALIZADA) ===
        if (cover && cover !== '') {
            // Se tiver capa na base de dados
            npArt.style.backgroundImage = `url('${cover}')`;
            npArt.style.backgroundSize = 'cover';
            npArt.style.backgroundPosition = 'center';
            npArt.style.border = 'none';
            npArt.querySelector('.art-icon').style.display = 'none';
            npArtInitial.style.display = 'none';
            
            // Footer Art
            footerArt.style.backgroundImage = `url('${cover}')`;
            footerArt.style.backgroundSize = 'cover';
            footerArt.style.backgroundPosition = 'center';
            footerArt.innerHTML = '';
            footerArt.classList.add('has-track');
        } else {
            // Se NÃO tiver capa (Fallback para cor + inicial)
            npArt.style.backgroundImage = 'none';
            const initial = artist.charAt(0).toUpperCase();
            npArtInitial.textContent = initial;
            npArtInitial.style.display = '';
            npArt.querySelector('.art-icon').style.display = 'none';
            npArt.style.background = getArtColor(artist);
            
            // Footer Art
            footerArt.style.backgroundImage = 'none';
            footerArt.innerHTML = '<span>' + initial + '</span>';
            footerArt.style.background = getArtColor(artist); // Opcional: meter cor também no footer
            footerArt.classList.add('has-track');
        }

        // Highlight row
        getAllTracks().forEach(r => r.classList.remove('playing-row'));
        el.classList.add('playing-row');

        updatePlayState(true);
        savePlayerState();
    }

    function togglePlay() {
        if (!audio.src) return;
        if (audio.paused) {
            audio.play().catch(() => {});
            updatePlayState(true);
        } else {
            audio.pause();
            updatePlayState(false);
        }
    }

    function updatePlayState(isPlaying) {
        playIcon.className = isPlaying ? 'ph-fill ph-pause' : 'ph-fill ph-play';
        if (isPlaying) {
            npArt.classList.add('pulse-animation');
        } else {
            npArt.classList.remove('pulse-animation');
        }
    }

    // === NEXT / PREV ===
    function nextTrack() {
        const tracks = getAllTracks();
        if (!tracks.length) return;
        
        if (shuffleOn) {
            const idx = Math.floor(Math.random() * tracks.length);
            playTrack(tracks[idx]);
            return;
        }

        let idx = tracks.indexOf(currentTrackEl);
        idx = (idx + 1) % tracks.length;
        playTrack(tracks[idx]);
    }

    function prevTrack() {
        const tracks = getAllTracks();
        if (!tracks.length) return;

        if (audio.currentTime > 3) {
            audio.currentTime = 0;
            return;
        }

        if (shuffleOn) {
            const idx = Math.floor(Math.random() * tracks.length);
            playTrack(tracks[idx]);
            return;
        }

        let idx = tracks.indexOf(currentTrackEl);
        idx = (idx - 1 + tracks.length) % tracks.length;
        playTrack(tracks[idx]);
    }

    // === SHUFFLE / REPEAT ===
    function toggleShuffle() {
        shuffleOn = !shuffleOn;
        document.getElementById('btnShuffle').classList.toggle('active', shuffleOn);
    }

    function toggleRepeat() {
        repeatMode = (repeatMode + 1) % 3;
        const btn = document.getElementById('btnRepeat');
        const icon = btn.querySelector('i');
        btn.classList.toggle('active', repeatMode > 0);
        icon.className = repeatMode === 2 ? 'ph ph-repeat-once' : 'ph ph-repeat';
    }

    // === TRACK END (WITH QUEUE SUPPORT) ===
    audio.addEventListener('ended', () => {
        if (repeatMode === 2) {
            audio.currentTime = 0;
            audio.play().catch(() => {});
        } else if (playQueue.length > 0) {
            // Play next from queue
            const next = playQueue.shift();
            renderQueueBadge();
            // If queue view is open, refresh it
            if (queueViewActive) showQueueView();
            const row = document.querySelector('.track-row[data-id="' + next.id + '"]');
            if (row) {
                playTrack(row);
            } else {
                // Track not in current DOM, play directly
                audio.src = next.src;
                audio.play().catch(() => {});
                document.getElementById('npTitle').textContent = next.title;
                document.getElementById('npArtist').textContent = next.artist;
                document.getElementById('npAlbum').textContent = next.album;
                document.getElementById('footerTitle').textContent = next.title;
                document.getElementById('footerArtist').textContent = next.artist;
                updatePlayState(true);
            }
        } else if (repeatMode === 1 || shuffleOn) {
            nextTrack();
        } else {
            const tracks = getAllTracks();
            const idx = tracks.indexOf(currentTrackEl);
            if (idx < tracks.length - 1) {
                nextTrack();
            } else {
                updatePlayState(false);
            }
        }
    });

    // === SLIDER FILL (COLOR TRACKER) ===
    function updateSliderFill(slider) {
        const min = parseFloat(slider.min) || 0;
        const max = parseFloat(slider.max) || 100;
        const val = parseFloat(slider.value);
        const pct = ((val - min) / (max - min)) * 100;
        // Pinta rosa à esquerda e cinza à direita
        slider.style.background = 'linear-gradient(to right, var(--accent-bright) ' + pct + '%, #2a2a2a ' + pct + '%)';
    }

    // === SEEK ===
    audio.addEventListener('timeupdate', () => {
        if (!isDragging && audio.duration) {
            const pct = (audio.currentTime / audio.duration) * 100;
            seekSlider.value = pct;
            updateSliderFill(seekSlider);
            document.getElementById('currentTime').textContent = formatTime(audio.currentTime);
            document.getElementById('totalTime').textContent = formatTime(audio.duration);
        }
    });

    audio.addEventListener('loadedmetadata', () => {
        document.getElementById('totalTime').textContent = formatTime(audio.duration);
    });

    seekSlider.addEventListener('mousedown', () => { isDragging = true; });
    seekSlider.addEventListener('touchstart', () => { isDragging = true; });

    seekSlider.addEventListener('input', () => {
        isDragging = true;
        updateSliderFill(seekSlider);
        if (audio.duration) {
            const t = (seekSlider.value / 100) * audio.duration;
            document.getElementById('currentTime').textContent = formatTime(t);
        }
    });

    seekSlider.addEventListener('change', () => {
        if (audio.duration) {
            audio.currentTime = (seekSlider.value / 100) * audio.duration;
        }
        isDragging = false;
    });

    // === VOLUME ===
    volSlider.addEventListener('input', () => {
        audio.volume = volSlider.value;
        updateSliderFill(volSlider);
        updateVolIcon();
    });

    function toggleMute() {
        if (audio.volume > 0) {
            audio.dataset.prevVol = audio.volume;
            audio.volume = 0;
            volSlider.value = 0;
        } else {
            audio.volume = parseFloat(audio.dataset.prevVol) || 1;
            volSlider.value = audio.volume;
        }
        updateSliderFill(volSlider);
        updateVolIcon();
    }

    function updateVolIcon() {
        const icon = document.getElementById('volIcon');
        if (audio.volume === 0) icon.className = 'ph ph-speaker-slash';
        else if (audio.volume < 0.4) icon.className = 'ph ph-speaker-low';
        else icon.className = 'ph ph-speaker-high';
    }

    // === HELPERS ===
    function formatTime(s) {
        if (!s || isNaN(s)) return '0:00';
        const m = Math.floor(s / 60);
        const sec = Math.floor(s % 60);
        return m + ':' + (sec < 10 ? '0' + sec : sec);
    }

    function getArtColor(str) {
        const colors = [
            'linear-gradient(135deg, #2d1f3d 0%, #1a1025 100%)',
            'linear-gradient(135deg, #1e2a4a 0%, #0f1525 100%)',
            'linear-gradient(135deg, #1a3a2a 0%, #0f2018 100%)',
            'linear-gradient(135deg, #3d1f2d 0%, #251018 100%)',
            'linear-gradient(135deg, #2d2a1f 0%, #1a1810 100%)',
            'linear-gradient(135deg, #1f2d3d 0%, #101a25 100%)',
            'linear-gradient(135deg, #3d1f3d 0%, #251025 100%)',
        ];
        let hash = 0;
        for (let i = 0; i < str.length; i++) hash = str.charCodeAt(i) + ((hash << 5) - hash);
        return colors[Math.abs(hash) % colors.length];
    }

        // === LOAD TRACK DURATIONS ===
    function loadTrackDurations() {
        document.querySelectorAll('.track-row').forEach(row => {
            const src = row.dataset.src;
            if (!src) return;
            const durationCell = row.querySelector('td:last-child');
            if (!durationCell || (durationCell.textContent.trim() !== '--:--' && durationCell.textContent.trim() !== '0:00')) return;
            
            const tempAudio = new Audio();
            tempAudio.preload = 'metadata';
            tempAudio.addEventListener('loadedmetadata', () => {
                if (tempAudio.duration && !isNaN(tempAudio.duration) && isFinite(tempAudio.duration)) {
                    durationCell.textContent = formatTime(tempAudio.duration);
                }
                tempAudio.src = ''; // release resource
            });
            tempAudio.addEventListener('error', () => {
                // keep --:-- if file can't be loaded
            });
            tempAudio.src = src;
        });
    }

    // === KEYBOARD SHORTCUTS ===
    document.addEventListener('keydown', (e) => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        switch(e.code) {
            case 'Space':
                e.preventDefault();
                togglePlay();
                break;
            case 'ArrowRight':
                if (e.ctrlKey) nextTrack();
                else if (audio.duration) audio.currentTime = Math.min(audio.duration, audio.currentTime + 5);
                break;
            case 'ArrowLeft':
                if (e.ctrlKey) prevTrack();
                else audio.currentTime = Math.max(0, audio.currentTime - 5);
                break;
            case 'ArrowUp':
                e.preventDefault();
                audio.volume = Math.min(1, audio.volume + 0.05);
                volSlider.value = audio.volume;
                updateSliderFill(volSlider);
                updateVolIcon();
                break;
            case 'ArrowDown':
                e.preventDefault();
                audio.volume = Math.max(0, audio.volume - 0.05);
                volSlider.value = audio.volume;
                updateSliderFill(volSlider);
                updateVolIcon();
                break;
            case 'KeyM':
                toggleMute();
                break;
        }
    });

    // Init sliders
    updateSliderFill(seekSlider);
    updateSliderFill(volSlider);

    // ========================
    // SPA ROUTER
    // ========================
    const SPA = {
        mainEl: document.getElementById('mainContent'),

        init() {
            // Intercept all internal link clicks
            document.addEventListener('click', (e) => {
                const link = e.target.closest('a[href]');
                if (!link) return;
                const href = link.getAttribute('href');
                if (!href || href === '#' || href.startsWith('http') || href.startsWith('javascript:') || href.startsWith('mailto:')) return;
                e.preventDefault();
                this.navigate(href);
            });

            // Intercept form submissions inside main content
            document.addEventListener('submit', (e) => {
                const form = e.target;
                if (!form || !this.mainEl.contains(form)) return;
                e.preventDefault();
                this.submitForm(form);
            });

            // Browser back/forward
            window.addEventListener('popstate', (e) => {
                const url = (e.state && e.state.url) ? e.state.url : 'index.php';
                this.loadPage(url, false);
            });

            // Save initial state
            history.replaceState({ url: 'index.php' }, '', 'index.php');
        },

        async navigate(url) {
            await this.loadPage(url, true);
        },

        async loadPage(url, pushState) {
            try {
                const resp = await fetch(url);
                if (!resp.ok) throw new Error(resp.status);
                const html = await resp.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newMain = doc.querySelector('main');
                if (!newMain) throw new Error('No <main> in response');

                this.mainEl.innerHTML = newMain.innerHTML;
                this.mainEl.className = newMain.className;
                this.mainEl.id = 'mainContent';

                this.runScripts(this.mainEl);
                this.updateLayout(url);
                this.updateNav(url);
                this.restorePlayingState();
                loadTrackDurations();
                if (!url.includes('adicionar')) {
                    initDashboardEvents();
                }
                this.mainEl.scrollTop = 0;

                if (pushState) {
                    history.pushState({ url: url }, '', url);
                }
            } catch (err) {
                console.error('SPA:', err);
                window.location.href = url;
            }
        },

        async submitForm(form) {
            const url = form.getAttribute('action') || window.location.pathname.split('/').pop();
            const formData = new FormData(form);
            try {
                const resp = await fetch(url, { method: 'POST', body: formData });
                if (!resp.ok) throw new Error(resp.status);
                const html = await resp.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newMain = doc.querySelector('main');
                if (newMain) {
                    this.mainEl.innerHTML = newMain.innerHTML;
                    this.mainEl.className = newMain.className;
                    this.mainEl.id = 'mainContent';
                    this.runScripts(this.mainEl);
                    loadTrackDurations();
                }
            } catch (err) {
                console.error('SPA form:', err);
            }
        },

        runScripts(container) {
            container.querySelectorAll('script').forEach(old => {
                const s = document.createElement('script');
                if (old.src) {
                    s.src = old.src;
                } else {
                    s.textContent = old.textContent;
                }
                old.parentNode.replaceChild(s, old);
            });
        },

        updateLayout(url) {
            const isFullWidth = url.includes('adicionar');
            document.body.classList.toggle('hide-right-panel', isFullWidth);
        },

        updateNav(url) {
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelectorAll('.nav-item').forEach(item => {
                const href = item.getAttribute('href');
                if (!href || href === '#') return;
                if (url.includes(href)) {
                    item.classList.add('active');
                }
            });
            if (!document.querySelector('.nav-item.active')) {
                const home = document.querySelector('.nav-item[href="index.php"]');
                if (home) home.classList.add('active');
            }
        },

        restorePlayingState() {
            if (!playingTrackId) return;
            document.querySelectorAll('.track-row').forEach(r => r.classList.remove('playing-row'));
            const row = document.querySelector('.track-row[data-id="' + playingTrackId + '"]');
            if (row) {
                row.classList.add('playing-row');
                currentTrackEl = row;
            }
        }
    };

    SPA.init();
    loadTrackDurations();

    // ========================
    // SEARCH FEATURE (Persistent)
    // ========================
    const searchOverlay = document.getElementById('searchOverlay');
    const searchInput = document.getElementById('searchInput');
    const searchClose = document.getElementById('searchClose');
    const navSearch = document.getElementById('navSearch');
    let currentSearchQuery = '';

    function openSearch() {
        searchOverlay.style.display = '';
        searchInput.value = currentSearchQuery;
        searchInput.focus();
    }

    function closeSearch() {
        searchOverlay.style.display = 'none';
        currentSearchQuery = searchInput.value.trim().toLowerCase();
        const sp = document.getElementById('searchPersistent');
        if (sp) sp.value = currentSearchQuery;
        updateSearchClearBtn();
        applyAllFilters();
    }

    function clearSearch() {
        currentSearchQuery = '';
        if (searchInput) searchInput.value = '';
        const sp = document.getElementById('searchPersistent');
        if (sp) sp.value = '';
        updateSearchClearBtn();
        applyAllFilters();
    }

    function updateSearchClearBtn() {
        const btn = document.getElementById('searchClearBtn');
        if (btn) btn.style.display = currentSearchQuery ? '' : 'none';
    }

    if (navSearch) {
        navSearch.addEventListener('click', (e) => {
            e.preventDefault();
            openSearch();
        });
    }
    if (searchClose) {
        searchClose.addEventListener('click', closeSearch);
    }
    if (searchOverlay) {
        searchOverlay.addEventListener('click', (e) => {
            if (e.target === searchOverlay) closeSearch();
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            currentSearchQuery = searchInput.value.trim().toLowerCase();
            const sp = document.getElementById('searchPersistent');
            if (sp) sp.value = searchInput.value;
            updateSearchClearBtn();
            applyAllFilters();
        });
    }

    // Event delegation for elements inside <main> (SPA-safe: survives content replacement)
    SPA.mainEl.addEventListener('input', (e) => {
        if (e.target.id === 'searchPersistent') {
            currentSearchQuery = e.target.value.trim().toLowerCase();
            if (searchInput) searchInput.value = e.target.value;
            updateSearchClearBtn();
            applyAllFilters();
        }
    });

    SPA.mainEl.addEventListener('click', (e) => {
        if (e.target.closest('#searchClearBtn')) {
            clearSearch();
            return;
        }
        const filterRow = e.target.closest('.filter-row[data-filter]');
        if (filterRow) {
            handleFilterClick(filterRow);
        }
    });

    // ESC to close search overlay
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && searchOverlay && searchOverlay.style.display !== 'none') {
            closeSearch();
        }
        // Ctrl+F opens search overlay
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
                openSearch();
            }
        }
    });

    // ========================
    // COLUMN BROWSER FILTERS
    // ========================
    const activeFilters = { genre: '', artist: '', album: '' };

    function handleFilterClick(row) {
        const filterType = row.dataset.filter;
        const filterValue = row.dataset.value || '';

        const colContent = row.closest('.col-content');
        if (colContent) {
            colContent.querySelectorAll('.filter-row').forEach(r => r.classList.remove('selected'));
            row.classList.add('selected');
        }

        activeFilters[filterType] = filterValue;
        updateFilterColumns();
        applyAllFilters();
    }

    function updateFilterColumns() {
        // When genre or artist is selected, filter the visible options in downstream columns
        const rows = document.querySelectorAll('.track-row');
        const visibleArtists = new Set();
        const visibleAlbums = new Set();

        rows.forEach(r => {
            const genre = r.dataset.genre || '';
            const artist = r.dataset.artist || '';
            const album = r.dataset.album || '';

            // Check genre filter
            if (activeFilters.genre && genre !== activeFilters.genre) return;
            visibleArtists.add(artist);

            // Check artist filter
            if (activeFilters.artist && artist !== activeFilters.artist) return;
            visibleAlbums.add(album);
        });

        // Update artist column visibility
        document.querySelectorAll('.filter-row[data-filter="artist"]').forEach(fr => {
            const val = fr.dataset.value;
            if (!val) { fr.style.display = ''; return; } // "Todos" always visible
            fr.style.display = visibleArtists.has(val) ? '' : 'none';
        });

        // Update album column visibility
        document.querySelectorAll('.filter-row[data-filter="album"]').forEach(fr => {
            const val = fr.dataset.value;
            if (!val) { fr.style.display = ''; return; }
            fr.style.display = visibleAlbums.has(val) ? '' : 'none';
        });
    }

    // ========================
    // UNIFIED FILTER FUNCTION
    // ========================
    function applyAllFilters() {
        const rows = document.querySelectorAll('.track-row');
        rows.forEach(row => {
            let show = true;

            // Column browser filters
            if (activeFilters.genre) {
                if ((row.dataset.genre || '') !== activeFilters.genre) show = false;
            }
            if (activeFilters.artist) {
                if ((row.dataset.artist || '') !== activeFilters.artist) show = false;
            }
            if (activeFilters.album) {
                if ((row.dataset.album || '') !== activeFilters.album) show = false;
            }

            // Search filter
            if (show && currentSearchQuery) {
                const title = (row.dataset.title || '').toLowerCase();
                const artist = (row.dataset.artist || '').toLowerCase();
                const album = (row.dataset.album || '').toLowerCase();
                if (!title.includes(currentSearchQuery) && !artist.includes(currentSearchQuery) && !album.includes(currentSearchQuery)) {
                    show = false;
                }
            }

            row.style.display = show ? '' : 'none';
        });
    }

    // ========================
    // INIT DASHBOARD (SPA-safe re-init)
    // ========================
    function initDashboardEvents() {
        activeFilters.genre = '';
        activeFilters.artist = '';
        activeFilters.album = '';
        const sp = document.getElementById('searchPersistent');
        if (sp) sp.value = currentSearchQuery;
        updateSearchClearBtn();
        if (currentSearchQuery) applyAllFilters();
    }

    // ========================
    // MULTI-SELECT FEATURE (Visual Selection)
    // ========================
    const bulkActionsBar = document.getElementById('bulkActionsBar');
    const bulkSelectedCount = document.getElementById('bulkSelectedCount');
    const bulkAddQueueBtn = document.getElementById('bulkAddQueue');
    const bulkDeleteBtn = document.getElementById('bulkDelete');
    const bulkCancelBtn = document.getElementById('bulkCancel');

    // Handle track row clicks with Shift/Ctrl
    document.addEventListener('click', (e) => {
        const row = e.target.closest('.track-row');
        if (!row) return;
        
        // Ignore if clicking on play icon
        if (e.target.closest('.play-icon')) {
            playTrack(row);
            return;
        }
        
        const trackId = row.dataset.id;
        const allRows = getAllTracks();
        const currentIndex = allRows.indexOf(row);
        
        if (e.shiftKey && lastSelectedIndex !== -1) {
            // Shift-click: select range
            e.preventDefault();
            const start = Math.min(lastSelectedIndex, currentIndex);
            const end = Math.max(lastSelectedIndex, currentIndex);
            
            for (let i = start; i <= end; i++) {
                const r = allRows[i];
                const id = r.dataset.id;
                selectedTracks.add(id);
                r.classList.add('selected');
            }
        } else if (e.ctrlKey || e.metaKey) {
            // Ctrl-click: toggle individual
            e.preventDefault();
            if (selectedTracks.has(trackId)) {
                selectedTracks.delete(trackId);
                row.classList.remove('selected');
            } else {
                selectedTracks.add(trackId);
                row.classList.add('selected');
            }
        } else {
            // Normal click: play track (double-click) or select single
            if (e.detail === 2) {
                // Double-click: play
                playTrack(row);
                return;
            }
            // Single click: clear selection and select this one
            clearSelection();
            selectedTracks.add(trackId);
            row.classList.add('selected');
        }
        
        lastSelectedIndex = currentIndex;
        updateBulkActions();
    });

    function updateBulkActions() {
        const count = selectedTracks.size;
        
        if (count > 0) {
            bulkActionsBar.style.display = 'flex';
            bulkSelectedCount.textContent = count;
        } else {
            bulkActionsBar.style.display = 'none';
        }
    }

    function clearSelection() {
        selectedTracks.clear();
        getAllTracks().forEach(row => {
            row.classList.remove('selected');
        });
        updateBulkActions();
    }

    // Bulk actions
    if (bulkAddQueueBtn) {
        bulkAddQueueBtn.addEventListener('click', () => {
            const allRows = getAllTracks();
            let added = 0;
            
            selectedTracks.forEach(id => {
                const row = allRows.find(r => r.dataset.id === id);
                if (row) {
                    addToQueue(row);
                    added++;
                }
            });
            
            showToast(`${added} música(s) adicionada(s) à fila`);
            clearSelection();
        });
    }

    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', () => {
            const count = selectedTracks.size;
            
            showConfirm(
                'Apagar Músicas',
                `Tens a certeza que queres apagar ${count} música(s)? Esta ação é irreversível.`,
                () => {
                    const ids = Array.from(selectedTracks);
                    let deleted = 0;
                    let failed = 0;
                    
                    // Delete sequentially
                    const deleteNext = (index) => {
                        if (index >= ids.length) {
                            showToast(`${deleted} música(s) apagada(s)` + (failed > 0 ? ` (${failed} falharam)` : ''));
                            clearSelection();
                            return;
                        }
                        
                        const id = ids[index];
                        
                        fetch('api.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=delete&id=' + encodeURIComponent(id)
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.ok) {
                                const row = getAllTracks().find(r => r.dataset.id === id);
                                if (row) {
                                    // If playing this track, stop
                                    if (playingTrackId == id) {
                                        audio.pause();
                                        audio.src = '';
                                        playingTrackId = null;
                                        currentTrackEl = null;
                                        resetPlayerUI();
                                    }
                                    row.remove();
                                }
                                deleted++;
                            } else {
                                failed++;
                            }
                            deleteNext(index + 1);
                        })
                        .catch(() => {
                            failed++;
                            deleteNext(index + 1);
                        });
                    };
                    
                    deleteNext(0);
                }
            );
        });
    }

    if (bulkCancelBtn) {
        bulkCancelBtn.addEventListener('click', clearSelection);
    }

    function resetPlayerUI() {
        document.getElementById('npTitle').textContent = 'SoundRepo';
        document.getElementById('npArtist').textContent = 'Seleciona uma música';
        document.getElementById('npAlbum').textContent = '';
        document.getElementById('footerTitle').textContent = 'SoundRepo';
        document.getElementById('footerArtist').textContent = 'Pronto';
        
        npArt.style.backgroundImage = 'none';
        npArt.style.background = '';
        npArt.querySelector('.art-icon').style.display = '';
        npArtInitial.style.display = 'none';
        
        footerArt.style.backgroundImage = 'none';
        footerArt.style.background = '';
        footerArt.innerHTML = '<i class="ph ph-music-note"></i>';
        footerArt.classList.remove('has-track');
        
        updatePlayState(false);
        localStorage.removeItem('sr_state');
    }

    // ========================
    // QUEUE FEATURE
    // ========================
    let playQueue = [];
    let queueViewActive = false;
    let savedTrackListHTML = '';
    const navQueue = document.getElementById('navQueue');
    const contextMenu = document.getElementById('contextMenu');
    const ctxAddQueue = document.getElementById('ctxAddQueue');
    const ctxDelete = document.getElementById('ctxDelete');
    let ctxTargetRow = null;

    function renderQueueBadge() {
        if (!navQueue) return;
        let badge = navQueue.querySelector('.queue-badge');
        if (playQueue.length > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'queue-badge';
                navQueue.appendChild(badge);
            }
            badge.textContent = playQueue.length;
        } else {
            if (badge) badge.remove();
        }
    }

    // Context menu on right-click
    document.addEventListener('contextmenu', (e) => {
        const row = e.target.closest('.track-row');
        if (!row) return;
        e.preventDefault();
        ctxTargetRow = row;
        contextMenu.style.display = 'block';
        contextMenu.style.left = e.pageX + 'px';
        contextMenu.style.top = e.pageY + 'px';

        // Keep within viewport
        const rect = contextMenu.getBoundingClientRect();
        if (rect.right > window.innerWidth) {
            contextMenu.style.left = (e.pageX - rect.width) + 'px';
        }
        if (rect.bottom > window.innerHeight) {
            contextMenu.style.top = (e.pageY - rect.height) + 'px';
        }
    });

    document.addEventListener('click', () => {
        contextMenu.style.display = 'none';
    });

    ctxAddQueue.addEventListener('click', () => {
        if (!ctxTargetRow) return;
        addToQueue(ctxTargetRow);
    });

    // Delete track via context menu
    if (ctxDelete) {
        ctxDelete.addEventListener('click', () => {
            if (!ctxTargetRow) return;
            const id = ctxTargetRow.dataset.id;
            const title = ctxTargetRow.dataset.title;
            
            showConfirm(
                'Apagar Música',
                'Tens a certeza que queres apagar "' + title + '"? Esta ação é irreversível.',
                () => {
                    // User confirmed
                    fetch('api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=delete&id=' + encodeURIComponent(id)
                    })
                    .then(r => {
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.json();
                    })
                    .then(data => {
                        if (data.ok) {
                            // If playing this track, stop playback and reset UI
                            if (playingTrackId == id) {
                                audio.pause();
                                audio.src = '';
                                playingTrackId = null;
                                currentTrackEl = null;
                                
                                // Reset Now Playing panel
                                document.getElementById('npTitle').textContent = 'SoundRepo';
                                document.getElementById('npArtist').textContent = 'Seleciona uma música';
                                document.getElementById('npAlbum').textContent = '';
                                
                                // Reset footer
                                document.getElementById('footerTitle').textContent = 'SoundRepo';
                                document.getElementById('footerArtist').textContent = 'Pronto';
                                
                                // Reset album art
                                npArt.style.backgroundImage = 'none';
                                npArt.style.background = '';
                                npArt.querySelector('.art-icon').style.display = '';
                                npArtInitial.style.display = 'none';
                                
                                footerArt.style.backgroundImage = 'none';
                                footerArt.style.background = '';
                                footerArt.innerHTML = '<i class="ph ph-music-note"></i>';
                                footerArt.classList.remove('has-track');
                                
                                // Reset play button
                                updatePlayState(false);
                                
                                // Clear localStorage
                                localStorage.removeItem('sr_state');
                            }
                            
                            // Remove row from list
                            ctxTargetRow.remove();
                            showToast('Música apagada: ' + title);
                        } else {
                            console.error('Delete failed:', data.error);
                            showToast('Erro ao apagar: ' + (data.error || 'Erro desconhecido'));
                        }
                    })
                    .catch(err => {
                        console.error('Delete error:', err);
                        showToast('Erro de rede ao apagar');
                    });
                }
            );
        });
    }

    function addToQueue(row) {
        const item = {
            id: row.dataset.id,
            src: row.dataset.src,
            title: row.dataset.title,
            artist: row.dataset.artist,
            album: row.dataset.album,
            cover: row.dataset.cover || ''
        };
        playQueue.push(item);
        renderQueueBadge();
        showToast('Adicionado à fila: ' + item.title);
        savePlayerState();
        if (queueViewActive) showQueueView();
    }

    function showToast(msg) {
        let toast = document.getElementById('srToast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'srToast';
            toast.className = 'sr-toast';
            document.body.appendChild(toast);
        }
        toast.textContent = msg;
        toast.classList.add('show');
        clearTimeout(toast._timer);
        toast._timer = setTimeout(() => toast.classList.remove('show'), 2500);
    }

    // Queue view toggle
    if (navQueue) {
        navQueue.addEventListener('click', (e) => {
            e.preventDefault();
            if (queueViewActive) {
                hideQueueView();
            } else {
                showQueueView();
            }
        });
    }

    function showQueueView() {
        queueViewActive = true;
        const container = document.querySelector('.track-list-container');
        if (!container) return;

        // Save original HTML only on first open
        if (!savedTrackListHTML) {
            savedTrackListHTML = container.innerHTML;
        }

        // Highlight nav
        if (navQueue) navQueue.classList.add('active');
        const homeNav = document.querySelector('.nav-item[href="index.php"]');
        if (homeNav) homeNav.classList.remove('active');

        if (playQueue.length === 0) {
            container.innerHTML = '<div class="empty-state"><i class="ph ph-queue"></i><p>A fila está vazia</p><span style="color:var(--text-tertiary);font-size:12px;">Clica com o botão direito numa música para a adicionar</span></div>';
            return;
        }

        let html = '<table><thead><tr>';
        html += '<th style="width:50px;text-align:center;">#</th>';
        html += '<th>Título</th><th>Artista</th><th>Álbum</th>';
        html += '<th style="width:100px;text-align:right;">Ações</th>';
        html += '</tr></thead><tbody>';

        playQueue.forEach((item, i) => {
            html += '<tr class="track-row queue-row" data-id="' + item.id + '" data-src="' + item.src + '" data-title="' + escapeAttr(item.title) + '" data-artist="' + escapeAttr(item.artist) + '" data-album="' + escapeAttr(item.album) + '" data-cover="' + escapeAttr(item.cover) + '" onclick="playTrack(this)">';
            html += '<td style="text-align:center;"><span class="track-num">' + (i + 1) + '</span><i class="ph-fill ph-play play-icon"></i></td>';
            html += '<td><span class="track-title">' + escapeHtml(item.title) + '</span></td>';
            html += '<td>' + escapeHtml(item.artist) + '</td>';
            html += '<td>' + escapeHtml(item.album) + '</td>';
            html += '<td style="text-align:right;" class="queue-actions">';
            html += '<button class="btn-queue-move' + (i === 0 ? ' disabled' : '') + '" onclick="event.stopPropagation();moveQueue(' + i + ',\'up\')" title="Subir"><i class="ph ph-caret-up"></i></button>';
            html += '<button class="btn-queue-move' + (i === playQueue.length - 1 ? ' disabled' : '') + '" onclick="event.stopPropagation();moveQueue(' + i + ',\'down\')" title="Descer"><i class="ph ph-caret-down"></i></button>';
            html += '<button class="btn-queue-remove" onclick="event.stopPropagation();removeFromQueue(' + i + ')"><i class="ph ph-x"></i></button>';
            html += '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';

        // Header
        container.innerHTML = '<div class="queue-header"><span class="queue-header-title"><i class="ph ph-queue"></i> Fila de reprodução (' + playQueue.length + ')</span><button class="btn-secondary btn-queue-clear" onclick="clearQueue()"><i class="ph ph-trash"></i> Limpar Fila</button></div>' + html;
    }

    function hideQueueView() {
        queueViewActive = false;
        const container = document.querySelector('.track-list-container');
        if (!container) return;
        if (savedTrackListHTML) {
            container.innerHTML = savedTrackListHTML;
            savedTrackListHTML = '';
        }
        if (navQueue) navQueue.classList.remove('active');
        const homeNav = document.querySelector('.nav-item[href="index.php"]');
        if (homeNav) homeNav.classList.add('active');
        // Restore playing state
        SPA.restorePlayingState();
        loadTrackDurations();
    }

    window.removeFromQueue = function(idx) {
        playQueue.splice(idx, 1);
        renderQueueBadge();
        savePlayerState();
        if (queueViewActive) showQueueView();
    };

    window.clearQueue = function() {
        playQueue = [];
        renderQueueBadge();
        savePlayerState();
        if (queueViewActive) showQueueView();
    };

    window.moveQueue = function(idx, dir) {
        if (dir === 'up' && idx > 0) {
            [playQueue[idx], playQueue[idx - 1]] = [playQueue[idx - 1], playQueue[idx]];
        } else if (dir === 'down' && idx < playQueue.length - 1) {
            [playQueue[idx], playQueue[idx + 1]] = [playQueue[idx + 1], playQueue[idx]];
        } else {
            return;
        }
        savePlayerState();
        if (queueViewActive) showQueueView();
    };

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function escapeAttr(str) {
        return (str || '').replace(/&/g, '&amp;').replace(/'/g, '&#39;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // ========================
    // LOCALSTORAGE PERSISTENCE
    // ========================
    function savePlayerState() {
        try {
            localStorage.setItem('sr_state', JSON.stringify({
                trackId: playingTrackId,
                currentTime: audio.currentTime || 0,
                src: audio.src || '',
                title: document.getElementById('npTitle').textContent,
                artist: document.getElementById('npArtist').textContent,
                album: document.getElementById('npAlbum').textContent,
                cover: currentTrackEl ? (currentTrackEl.dataset.cover || '') : '',
                queue: playQueue
            }));
        } catch(e) {}
    }

    function restorePlayerState() {
        try {
            const raw = localStorage.getItem('sr_state');
            if (!raw) return;
            const state = JSON.parse(raw);
            if (!state.trackId || !state.src) return;

            playingTrackId = state.trackId;

            // Restore queue
            if (state.queue && Array.isArray(state.queue)) {
                playQueue = state.queue;
                renderQueueBadge();
            }

            // Update UI
            document.getElementById('npTitle').textContent = state.title || 'SoundRepo';
            document.getElementById('npArtist').textContent = state.artist || '';
            document.getElementById('npAlbum').textContent = state.album || '';
            document.getElementById('footerTitle').textContent = state.title || '';
            document.getElementById('footerArtist').textContent = state.artist || '';

            // Cover
            if (state.cover) {
                npArt.style.backgroundImage = "url('" + state.cover + "')";
                npArt.style.backgroundSize = 'cover';
                npArt.style.backgroundPosition = 'center';
                npArt.style.border = 'none';
                npArt.querySelector('.art-icon').style.display = 'none';
                npArtInitial.style.display = 'none';
                footerArt.style.backgroundImage = "url('" + state.cover + "')";
                footerArt.style.backgroundSize = 'cover';
                footerArt.style.backgroundPosition = 'center';
                footerArt.innerHTML = '';
                footerArt.classList.add('has-track');
            } else if (state.artist) {
                const initial = state.artist.charAt(0).toUpperCase();
                npArtInitial.textContent = initial;
                npArtInitial.style.display = '';
                npArt.querySelector('.art-icon').style.display = 'none';
                npArt.style.background = getArtColor(state.artist);
                footerArt.innerHTML = '<span>' + initial + '</span>';
                footerArt.style.background = getArtColor(state.artist);
                footerArt.classList.add('has-track');
            }

            // Load audio (paused)
            audio.src = state.src;
            audio.addEventListener('loadedmetadata', function onMeta() {
                if (state.currentTime > 0 && state.currentTime < audio.duration) {
                    audio.currentTime = state.currentTime;
                }
                const pct = audio.duration ? (audio.currentTime / audio.duration) * 100 : 0;
                seekSlider.value = pct;
                updateSliderFill(seekSlider);
                document.getElementById('currentTime').textContent = formatTime(audio.currentTime);
                document.getElementById('totalTime').textContent = formatTime(audio.duration);
                audio.removeEventListener('loadedmetadata', onMeta);
            });

            updatePlayState(false);
            SPA.restorePlayingState();
        } catch(e) {
            console.error('Restore state:', e);
        }
    }

    // Save state periodically + on key events
    let _lastSave = 0;
    audio.addEventListener('timeupdate', () => {
        const now = Date.now();
        if (now - _lastSave > 5000) {
            _lastSave = now;
            savePlayerState();
        }
    });
    audio.addEventListener('pause', savePlayerState);
    audio.addEventListener('play', savePlayerState);

    // ========================
    // CUSTOM CONFIRM DIALOG
    // ========================
    function showConfirm(title, message, onConfirm) {
        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'confirm-modal-overlay';
        
        // Create modal
        const modal = document.createElement('div');
        modal.className = 'confirm-modal';
        
        modal.innerHTML = `
            <div class="confirm-modal-header">
                <i class="ph-fill ph-warning-circle confirm-modal-icon"></i>
                <div class="confirm-modal-title">${escapeHtml(title)}</div>
            </div>
            <div class="confirm-modal-body">${escapeHtml(message)}</div>
            <div class="confirm-modal-actions">
                <button class="confirm-btn confirm-btn-cancel">Cancelar</button>
                <button class="confirm-btn confirm-btn-confirm">Apagar</button>
            </div>
        `;
        
        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        
        const btnCancel = modal.querySelector('.confirm-btn-cancel');
        const btnConfirm = modal.querySelector('.confirm-btn-confirm');
        
        function close() {
            overlay.remove();
        }
        
        btnCancel.addEventListener('click', close);
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) close();
        });
        
        btnConfirm.addEventListener('click', () => {
            close();
            if (onConfirm) onConfirm();
        });
        
        // Focus confirm button
        setTimeout(() => btnConfirm.focus(), 100);
    }

    // Restore on startup
    restorePlayerState();
    initDashboardEvents();
    </script>
</body>
</html>