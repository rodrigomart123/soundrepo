<?php
require 'db.php';
require 'extract_cover.php';

$mensagem = "";
$erro = "";

// ============================================
// HANDLE SINGLE TRACK UPLOAD
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mode']) && $_POST['mode'] === 'single') {
    $artista = trim($_POST['artista'] ?? '');
    $album   = trim($_POST['album'] ?? '');
    $musica  = trim($_POST['musica'] ?? '');
    $genero  = trim($_POST['genero'] ?? '');
    $caminho = trim($_POST['caminhoManual'] ?? '');

    $destino = '';

    // Priority 1: File upload
    if (isset($_FILES['ficheiroAudio']) && $_FILES['ficheiroAudio']['error'] == 0) {
        $nomeArquivo = $_FILES['ficheiroAudio']['name'];
        $tempPath    = $_FILES['ficheiroAudio']['tmp_name'];
        $nomeArquivo = preg_replace('/[^a-zA-Z0-9._\-\s]/', '', $nomeArquivo);
        $destino = 'musicas/' . basename($nomeArquivo);

        if (!move_uploaded_file($tempPath, $destino)) {
            $erro = "Falha ao mover o ficheiro. Verifica as permissões da pasta 'musicas'.";
            $destino = '';
        }
    }
    // Priority 2: Manual path
    elseif (!empty($caminho)) {
        $caminho = str_replace('\\', '/', $caminho);

        if (file_exists($caminho)) {
            $nomeArquivo = basename($caminho);
            $nomeArquivo = preg_replace('/[^a-zA-Z0-9._\-\s]/', '', $nomeArquivo);
            $destino = 'musicas/' . $nomeArquivo;
            if (!copy($caminho, $destino)) {
                $erro = "Falha ao copiar o ficheiro. Verifica o caminho.";
                $destino = '';
            }
        } else {
            $erro = "Ficheiro não encontrado: " . htmlspecialchars($caminho);
        }
    } else {
        $erro = "Seleciona ou indica um ficheiro de áudio.";
    }

    if ($destino && !$erro) {
        try {
            // 1. Artist
            $stmt = $pdo->prepare("SELECT ArtistId FROM Artists WHERE Name = ?");
            $stmt->execute([$artista]);
            $artistaExistente = $stmt->fetch();
            if ($artistaExistente) {
                $artistId = $artistaExistente['ArtistId'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO Artists (Name) VALUES (?)");
                $stmt->execute([$artista]);
                $artistId = $pdo->lastInsertId();
            }

            // 2. Album
            $stmt = $pdo->prepare("SELECT AlbumId FROM Albums WHERE Title = ? AND ArtistId = ?");
            $stmt->execute([$album, $artistId]);
            $albumExistente = $stmt->fetch();
            if ($albumExistente) {
                $albumId = $albumExistente['AlbumId'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO Albums (Title, ArtistId) VALUES (?, ?)");
                $stmt->execute([$album, $artistId]);
                $albumId = $pdo->lastInsertId();
            }

            // 3. Music
            $stmt = $pdo->prepare("INSERT INTO Musics (Title, FilePath, AlbumId, Genre) VALUES (?, ?, ?, ?)");
            $stmt->execute([$musica, $destino, $albumId, $genero]);

            // 4. Cover: uploaded image file takes priority
            if (isset($_FILES['coverFile']) && $_FILES['coverFile']['error'] == 0) {
                if (!is_dir('covers')) mkdir('covers', 0755, true);
                $coverExt = strtolower(pathinfo($_FILES['coverFile']['name'], PATHINFO_EXTENSION));
                if (in_array($coverExt, ['jpg','jpeg','png','webp','gif'])) {
                    $coverFile = 'covers/album_' . $albumId . '_' . time() . '.' . $coverExt;
                    if (move_uploaded_file($_FILES['coverFile']['tmp_name'], $coverFile)) {
                        try {
                            $stmt = $pdo->prepare("UPDATE Albums SET CoverPath = ? WHERE AlbumId = ?");
                            $stmt->execute([$coverFile, $albumId]);
                        } catch(Exception $ex) {}
                    }
                }
            }
            // 5. Cover: iTunes URL fallback
            elseif (!empty($_POST['coverUrl'])) {
                $coverUrlInput = filter_var($_POST['coverUrl'], FILTER_VALIDATE_URL);
                if ($coverUrlInput) {
                    if (!is_dir('covers')) mkdir('covers', 0755, true);
                    $coverData = @file_get_contents($coverUrlInput);
                    if ($coverData !== false) {
                        $coverFile = 'covers/album_' . $albumId . '_' . time() . '.jpg';
                        if (file_put_contents($coverFile, $coverData)) {
                            try {
                                $stmt = $pdo->prepare("UPDATE Albums SET CoverPath = ? WHERE AlbumId = ?");
                                $stmt->execute([$coverFile, $albumId]);
                            } catch(Exception $ex) {}
                        }
                    }
                }
            }

            $mensagem = "Música adicionada com sucesso!";
        } catch (Exception $e) {
            $erro = "Erro na Base de Dados: " . $e->getMessage();
        }
    }
}

// ============================================
// HANDLE BULK IMPORT (INTELLIGENT PARSING)
// Supports: webkitdirectory file upload OR manual path
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mode']) && $_POST['mode'] === 'bulk') {
    $detectMeta = isset($_POST['detectMeta']) && $_POST['detectMeta'] === '1';
    $extensions = ['mp3','wav','ogg','flac','m4a','aac','wma'];

    // Determine source: file upload (webkitdirectory) or manual path
    $useUpload = isset($_FILES['folderUpload']) && !empty($_FILES['folderUpload']['name'][0]);
    $pasta = trim($_POST['pastaPath'] ?? '');

    if (!$useUpload && (empty($pasta) || !is_dir($pasta))) {
        if ($useUpload === false && !empty($pasta)) {
            $erro = "Pasta não encontrada: " . htmlspecialchars($pasta);
        } else if (!$useUpload) {
            $erro = "Seleciona uma pasta com ficheiros de áudio.";
        }
    }

    if (!$erro) {
        // Collect files to import
        $filesToImport = []; // Each: ['name' => filename, 'source' => path_or_tmp, 'relativePath' => '...']
        $folderName = '';

        if ($useUpload) {
            // Files from webkitdirectory input
            $relativePaths = json_decode($_POST['relativePaths'] ?? '[]', true) ?: [];
            $totalFiles = count($_FILES['folderUpload']['name']);

            for ($i = 0; $i < $totalFiles; $i++) {
                if ($_FILES['folderUpload']['error'][$i] !== 0) continue;
                $name = $_FILES['folderUpload']['name'][$i];
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, $extensions)) continue;

                $relPath = $relativePaths[$i] ?? $name;
                $filesToImport[] = [
                    'name' => $name,
                    'source' => $_FILES['folderUpload']['tmp_name'][$i],
                    'relativePath' => $relPath,
                    'isUpload' => true
                ];

                // Extract folder name from first file's relative path
                if (!$folderName && $relPath) {
                    $parts = explode('/', str_replace('\\', '/', $relPath));
                    if (count($parts) > 1) {
                        $folderName = $parts[0];
                    }
                }
            }
        } else {
            // Files from manual path (scandir)
            foreach (scandir($pasta) as $f) {
                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                if (!in_array($ext, $extensions)) continue;
                $filesToImport[] = [
                    'name' => $f,
                    'source' => rtrim($pasta, '/\\') . '/' . $f,
                    'relativePath' => $f,
                    'isUpload' => false
                ];
            }
            $pastaClean = rtrim(str_replace('\\', '/', $pasta), '/');
            $folderName = basename($pastaClean);
        }

        if (empty($filesToImport)) {
            $erro = "Nenhum ficheiro de áudio encontrado na pasta selecionada.";
        } else {
            // Limit to prevent timeout (max 100 files per batch)
            if (count($filesToImport) > 100) {
                $erro = "Demasiados ficheiros (" . count($filesToImport) . "). Máximo: 100 ficheiros por importação.";
            } else {
                $imported = 0;
                $failed = 0;

            // Cache for artist/album IDs to avoid repeated queries
            $artistCache = [];
            $albumCache = [];

            // Helper: get or create artist
            $getArtistId = function($name) use ($pdo, &$artistCache) {
                $key = strtolower($name);
                if (isset($artistCache[$key])) return $artistCache[$key];
                $stmt = $pdo->prepare("SELECT ArtistId FROM Artists WHERE Name = ?");
                $stmt->execute([$name]);
                $row = $stmt->fetch();
                if ($row) {
                    $artistCache[$key] = $row['ArtistId'];
                    return $row['ArtistId'];
                }
                $stmt = $pdo->prepare("INSERT INTO Artists (Name) VALUES (?)");
                $stmt->execute([$name]);
                $id = $pdo->lastInsertId();
                $artistCache[$key] = $id;
                return $id;
            };

            // Helper: get or create album
            $getAlbumId = function($title, $artistId) use ($pdo, &$albumCache) {
                $key = strtolower($title) . '_' . $artistId;
                if (isset($albumCache[$key])) return $albumCache[$key];
                $stmt = $pdo->prepare("SELECT AlbumId FROM Albums WHERE Title = ? AND ArtistId = ?");
                $stmt->execute([$title, $artistId]);
                $row = $stmt->fetch();
                if ($row) {
                    $albumCache[$key] = $row['AlbumId'];
                    return $row['AlbumId'];
                }
                $stmt = $pdo->prepare("INSERT INTO Albums (Title, ArtistId) VALUES (?, ?)");
                $stmt->execute([$title, $artistId]);
                $id = $pdo->lastInsertId();
                $albumCache[$key] = $id;
                return $id;
            };

            if (!is_dir('musicas')) mkdir('musicas', 0755, true);

            // Process files with progress feedback
            $processedCount = 0;
            $maxFiles = min(count($filesToImport), 100); // Hard limit to 100

            foreach ($filesToImport as $fileInfo) {
                if ($processedCount >= $maxFiles) break; // Stop at limit
                
                $f = $fileInfo['name'];
                $sanitized = preg_replace('/[^a-zA-Z0-9._\-\s]/', '', $f);
                $destino = 'musicas/' . $sanitized;

                // Copy or move file
                $ok = $fileInfo['isUpload']
                    ? move_uploaded_file($fileInfo['source'], $destino)
                    : copy($fileInfo['source'], $destino);

                if ($ok) {
                    $baseName = pathinfo($f, PATHINFO_FILENAME);
                    $trackTitle = $baseName;
                    $trackArtist = 'Desconhecido';
                    $trackAlbum = 'Desconhecido';

                    // Remove track numbers FIRST (e.g., "01 - C418 - Key" -> "C418 - Key")
                    $cleaned = preg_replace('/^\d{1,3}[\s.\-]+/', '', $baseName);
                    
                    // Clean common tags: (Official Video), [Lyrics], etc.
                    $cleaned = preg_replace('/\s*[\(\[][^\)\]]*[\)\]]\s*/', ' ', $cleaned);
                    $cleaned = trim(preg_replace('/\s+/', ' ', $cleaned));

                    // ALWAYS try to parse Artist and Title using multiple possible separators
                    // Supports: "Artist - Title", "Artist-Title", "Artist – Title", "Artist — Title"
                    $parts = preg_split('/\s*[\-\–\—]\s*/', $cleaned, 2);
                    
                    if (count($parts) >= 2) {
                        $trackArtist = trim($parts[0]);
                        $trackTitle = trim($parts[1]);
                    } else {
                        $trackTitle = $cleaned;
                    }

                    if ($detectMeta) {
                        // Detect album from folder name (webkitdirectory or parent folder)
                        if ($folderName && !preg_match('/^(music|musica|songs|downloads|desktop)$/i', $folderName)) {
                            $trackAlbum = $folderName;
                        }
                    }

                    // Fallbacks
                    if (empty($trackTitle)) $trackTitle = $baseName;
                    if (empty($trackArtist)) $trackArtist = 'Desconhecido';
                    if (empty($trackAlbum)) $trackAlbum = 'Desconhecido';

                    try {
                        $artistId = $getArtistId($trackArtist);
                        $albumId = $getAlbumId($trackAlbum, $artistId);
                        $stmt = $pdo->prepare("INSERT INTO Musics (Title, FilePath, AlbumId) VALUES (?, ?, ?)");
                        $stmt->execute([$trackTitle, $destino, $albumId]);
                        
                        // Try to extract embedded cover art
                        $coverPath = extractEmbeddedCover($destino, $albumId);
                        if ($coverPath) {
                            // Update album with extracted cover
                            $stmt = $pdo->prepare("UPDATE Albums SET CoverPath = ? WHERE AlbumId = ? AND (CoverPath IS NULL OR CoverPath = '')");
                            $stmt->execute([$coverPath, $albumId]);
                        }
                        
                        $imported++;
                        $processedCount++;
                    } catch (Exception $e) {
                        $failed++;
                        $processedCount++;
                    }
                } else {
                    $failed++;
                    $processedCount++;
                }
            }

                $mensagem = "$imported música(s) importada(s) com sucesso!";
                if ($failed > 0) $mensagem .= " ($failed falharam)";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Adicionar - SoundRepo</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="css/style.css">
</head>
<body style="grid-template-columns: var(--sidebar-width) 1fr; grid-template-areas: 'sidebar main' 'sidebar main';">

    <!-- SIDEBAR -->
    <nav class="sidebar">
        <div class="sidebar-top">
            <div class="nav-section">
                <a href="index.php" class="nav-item">
                    <i class="ph ph-house"></i><span>In&iacute;cio</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="ph ph-magnifying-glass"></i><span>Pesquisar</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="ph ph-queue"></i><span>Fila</span>
                </a>
            </div>
            <div class="nav-divider"></div>
            <div class="nav-label">Biblioteca</div>
            <div class="nav-section">
                <a href="adicionar.php" class="nav-item active">
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

    <!-- MAIN: ADD PAGE -->
    <main class="main-view add-page fade-in" id="mainContent">

        <!-- Tab Header -->
        <div class="add-tabs">
            <button class="add-tab active" data-tab="single" onclick="switchTab('single')">
                <i class="ph ph-music-note-simple"></i> Adicionar M&uacute;sica
            </button>
            <button class="add-tab" data-tab="bulk" onclick="switchTab('bulk')">
                <i class="ph ph-folder-open"></i> Importar Pasta
            </button>
            <a href="index.php" class="add-tab-back">
                <i class="ph ph-arrow-left"></i> Biblioteca
            </a>
        </div>

        <?php if($mensagem): ?>
            <div class="add-msg msg-success"><i class="ph ph-check-circle"></i> <?= $mensagem ?></div>
        <?php endif; ?>
        <?php if($erro): ?>
            <div class="add-msg msg-error"><i class="ph ph-warning-circle"></i> <?= $erro ?></div>
        <?php endif; ?>

        <!-- ==================== -->
        <!-- TAB: SINGLE TRACK    -->
        <!-- ==================== -->
        <div class="add-panel" id="tabSingle">
            <form method="POST" action="adicionar.php" enctype="multipart/form-data">
                <input type="hidden" name="mode" value="single">

                <div class="add-layout">
                    <!-- LEFT: Cover area -->
                    <div class="add-cover-area">
                        <div class="cover-box" id="coverBox">
                            <img id="coverPreview" src="" alt="Capa">
                            <div class="cover-placeholder" id="coverPlaceholder">
                                <i class="ph ph-vinyl-record"></i>
                                <span>Capa do &Aacute;lbum</span>
                            </div>
                        </div>
                        <input type="hidden" name="coverUrl" id="coverUrl" value="">
                        <span class="cover-status" id="coverStatus"></span>

                        <label class="cover-upload-btn">
                            <i class="ph ph-image"></i> Importar Capa
                            <input type="file" name="coverFile" id="coverFileInput" accept="image/*" style="display:none;">
                        </label>
                        <button type="button" class="cover-upload-btn btn-reset-manual" id="btnResetManual" style="display:none;">
                            <i class="ph ph-eraser"></i> Limpar / Manual
                        </button>
                    </div>

                    <!-- RIGHT: Form fields -->
                    <div class="add-fields">
                        <!-- Source selector -->
                        <div class="source-toggle">
                            <button type="button" class="source-btn active" data-source="upload" onclick="switchSource('upload')">
                                <i class="ph ph-upload-simple"></i> Upload Ficheiro
                            </button>
                            <button type="button" class="source-btn" data-source="path" onclick="switchSource('path')">
                                <i class="ph ph-path"></i> Caminho Manual
                            </button>
                        </div>

                        <div class="source-panel" id="srcUpload">
                            <div class="input-group">
                                <label>Ficheiro de &Aacute;udio</label>
                                <input type="file" name="ficheiroAudio" id="ficheiroAudio" accept=".mp3,.wav,.ogg,.flac,.m4a,.aac">
                            </div>
                        </div>

                        <div class="source-panel" id="srcPath" style="display:none;">
                            <div class="input-group">
                                <label>Caminho do Ficheiro</label>
                                <input type="text" name="caminhoManual" id="caminhoManual" placeholder="C:\Musica\artista - titulo.mp3">
                                <span class="input-hint"><i class="ph ph-info"></i> Caminho absoluto para o ficheiro no teu computador</span>
                            </div>
                        </div>

                        <div class="input-group">
                            <label>T&iacute;tulo da M&uacute;sica</label>
                            <input type="text" name="musica" id="inputMusica" required placeholder="Ex: Blinding Lights">
                        </div>

                        <div class="input-row">
                            <div class="input-group">
                                <label>Artista</label>
                                <input type="text" name="artista" id="inputArtista" required placeholder="Ex: The Weeknd">
                            </div>
                            <div class="input-group">
                                <label>&Aacute;lbum</label>
                                <input type="text" name="album" id="inputAlbum" required placeholder="Ex: After Hours">
                            </div>
                        </div>

                        <div class="input-group">
                            <label>G&eacute;nero</label>
                            <input type="text" name="genero" id="inputGenero" placeholder="Ex: Pop, Rock, Hip-Hop...">
                        </div>

                        <!-- iTunes Suggestions -->
                        <div id="itunesSuggestions" class="itunes-suggestions" style="display:none;"></div>

                        <button type="submit" class="btn-primary btn-add-submit">
                            <i class="ph ph-plus-circle"></i> Gravar na Biblioteca
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- ==================== -->
        <!-- TAB: BULK IMPORT     -->
        <!-- ==================== -->
        <div class="add-panel" id="tabBulk" style="display:none;">
            <form method="POST" action="adicionar.php" enctype="multipart/form-data">
                <input type="hidden" name="mode" value="bulk">

                <div class="bulk-layout">
                    <div class="bulk-info-card">
                        <div class="bulk-icon">
                            <i class="ph ph-folder-notch-open"></i>
                        </div>
                        <h3>Importar Pasta</h3>
                        <p>Importa todos os ficheiros de &aacute;udio de uma pasta local.<br>Formatos: MP3, WAV, OGG, FLAC, M4A, AAC</p>
                    </div>

                    <div class="bulk-form">
                        <div class="input-group">
                            <label>Selecionar Pasta</label>
                            <input type="file" name="folderUpload[]" id="folderInput" webkitdirectory directory multiple>
                            <span class="input-hint"><i class="ph ph-info"></i> Clica para selecionar uma pasta com ficheiros de &aacute;udio</span>
                        </div>
                        <input type="hidden" name="relativePaths" id="relativePaths" value="[]">

                        <div class="bulk-folder-info" id="bulkFolderInfo" style="display:none;">
                            <i class="ph ph-folder-open"></i>
                            <span id="bulkFolderName"></span>
                            <span class="bulk-file-count" id="bulkFileCount"></span>
                        </div>

                        <!-- Fallback: Manual Path -->
                        <div class="bulk-path-toggle">
                            <button type="button" class="btn-bulk-toggle" id="btnShowManualPath">
                                <i class="ph ph-keyboard"></i> Ou introduzir caminho manualmente
                            </button>
                        </div>
                        <div class="input-group" id="manualPathGroup" style="display:none;">
                            <label>Caminho da Pasta</label>
                            <input type="text" name="pastaPath" placeholder="C:\Users\Tu\Music\Album">
                            <span class="input-hint"><i class="ph ph-info"></i> Caminho completo para a pasta com os ficheiros</span>
                        </div>

                        <div class="bulk-checkbox">
                            <label class="checkbox-label">
                                <input type="checkbox" name="detectMeta" value="1" checked>
                                <span class="checkbox-custom"></span>
                                <span>Tentar detetar metadados pelo nome</span>
                            </label>
                        </div>

                        <div class="bulk-note">
                            <i class="ph ph-lightbulb"></i>
                            <span>Com a dete&ccedil;&atilde;o ativa, ficheiros no formato <strong>"Artista - T&iacute;tulo.mp3"</strong> s&atilde;o analisados automaticamente. O nome da pasta &eacute; usado como &aacute;lbum. Sem dete&ccedil;&atilde;o, tudo fica como "Desconhecido".</span>
                        </div>

                        <button type="submit" class="btn-primary btn-add-submit">
                            <i class="ph ph-folder-plus"></i> Importar Todas as M&uacute;sicas
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- ==================== -->
        <!-- JAVASCRIPT           -->
        <!-- ==================== -->
        <script>
        (function() {
            // === TAB SWITCHING ===
            window.switchTab = function(tab) {
                document.querySelectorAll('.add-tab').forEach(function(t) { t.classList.remove('active'); });
                document.querySelector('.add-tab[data-tab="' + tab + '"]').classList.add('active');
                document.getElementById('tabSingle').style.display = (tab === 'single') ? '' : 'none';
                document.getElementById('tabBulk').style.display = (tab === 'bulk') ? '' : 'none';
            };

            // === SOURCE TOGGLE (Upload / Manual Path) ===
            window.switchSource = function(src) {
                document.querySelectorAll('.source-btn').forEach(function(b) { b.classList.remove('active'); });
                document.querySelector('.source-btn[data-source="' + src + '"]').classList.add('active');
                document.getElementById('srcUpload').style.display = (src === 'upload') ? '' : 'none';
                document.getElementById('srcPath').style.display = (src === 'path') ? '' : 'none';
            };

            // === ELEMENTS ===
            var fileInput = document.getElementById('ficheiroAudio');
            var pathInput = document.getElementById('caminhoManual');
            var titleInput = document.getElementById('inputMusica');
            var artistInput = document.getElementById('inputArtista');
            var albumInput = document.getElementById('inputAlbum');
            var genreInput = document.getElementById('inputGenero');
            var coverUrl = document.getElementById('coverUrl');
            var coverPreview = document.getElementById('coverPreview');
            var coverPlaceholder = document.getElementById('coverPlaceholder');
            var coverStatus = document.getElementById('coverStatus');
            var coverFileInput = document.getElementById('coverFileInput');
            var btnResetManual = document.getElementById('btnResetManual');
            var searchTimer = null;
            var manualMode = false; // When true, iTunes auto-search is suppressed

            if (!titleInput) return;

            // === MANUAL COVER UPLOAD (preview) ===
            if (coverFileInput) {
                coverFileInput.addEventListener('change', function() {
                    var file = coverFileInput.files[0];
                    if (!file) return;
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        coverPreview.src = e.target.result;
                        coverPreview.style.display = 'block';
                        coverPlaceholder.style.display = 'none';
                        if (coverUrl) coverUrl.value = '';
                        if (coverStatus) { coverStatus.textContent = 'Capa importada do computador'; coverStatus.className = 'cover-status found'; }
                        showResetBtn();
                    };
                    reader.readAsDataURL(file);
                });
            }

            // === RESET / MANUAL BUTTON ===
            function showResetBtn() {
                if (btnResetManual) btnResetManual.style.display = '';
            }
            function hideResetBtn() {
                if (btnResetManual) btnResetManual.style.display = 'none';
            }

            if (btnResetManual) {
                btnResetManual.addEventListener('click', function() {
                    // Enter manual mode: suppress auto-search
                    manualMode = true;
                    clearTimeout(searchTimer);

                    // Clear cover
                    if (coverUrl) coverUrl.value = '';
                    if (coverPreview) { coverPreview.src = ''; coverPreview.style.display = 'none'; }
                    if (coverPlaceholder) coverPlaceholder.style.display = '';
                    if (coverStatus) { coverStatus.textContent = 'Modo manual ativo'; coverStatus.className = 'cover-status'; }
                    if (coverFileInput) coverFileInput.value = '';

                    // Clear text inputs
                    titleInput.value = '';
                    artistInput.value = '';
                    albumInput.value = '';
                    if (genreInput) genreInput.value = '';

                    // Clear suggestions
                    clearSuggestions();
                    hideResetBtn();
                });
            }

            // === PARSE FILENAME ===
            function parseFilename(filename) {
                // Re-enable auto-search on new file
                manualMode = false;
                var name = filename.replace(/\.[^.]+$/, '');
                name = name.replace(/\s*[\(\[][^\)\]]*[\)\]]\s*/g, '').replace(/\s+[A-Za-z0-9_-]{11}$/, '').trim();
                var parts = name.split(' - ');
                if (parts.length >= 2) {
                    if (!artistInput.value) artistInput.value = parts[0].trim();
                    if (!titleInput.value) titleInput.value = parts.slice(1).join(' - ').trim();
                } else {
                    if (!titleInput.value) titleInput.value = name;
                }
                debouncedSearch();
            }

            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    var file = fileInput.files[0];
                    if (file) parseFilename(file.name);
                });
            }

            if (pathInput) {
                pathInput.addEventListener('change', function() {
                    var val = pathInput.value.trim();
                    if (val) {
                        var parts = val.replace(/\\/g, '/').split('/');
                        var filename = parts[parts.length - 1];
                        if (filename) parseFilename(filename);
                    }
                });
            }

            // === DEBOUNCED ITUNES SEARCH ===
            titleInput.addEventListener('input', function() {
                if (!manualMode) debouncedSearch();
            });
            artistInput.addEventListener('input', function() {
                if (!manualMode) debouncedSearch();
            });

            function debouncedSearch() {
                if (manualMode) return;
                clearTimeout(searchTimer);
                searchTimer = setTimeout(searchiTunes, 700);
            }

            function searchiTunes() {
                if (manualMode) return;
                var artist = artistInput.value.trim();
                var title = titleInput.value.trim();
                if (!artist && !title) return;
                var term = (artist && title) ? artist + ' ' + title : (artist || title);
                if (coverStatus) { coverStatus.textContent = 'A procurar no iTunes...'; coverStatus.className = 'cover-status searching'; }

                fetch('https://itunes.apple.com/search?term=' + encodeURIComponent(term) + '&media=music&limit=5')
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (manualMode) return; // Abort if user switched to manual during fetch
                        if (data.results && data.results.length > 0) {
                            showSuggestions(data.results);
                            applySuggestion(data.results[0]);
                        } else {
                            if (coverStatus && !(coverFileInput && coverFileInput.files && coverFileInput.files.length > 0)) {
                                coverStatus.textContent = 'Sem resultados no iTunes.';
                                coverStatus.className = 'cover-status';
                            }
                            clearSuggestions();
                        }
                    })
                    .catch(function() {
                        if (coverStatus) { coverStatus.textContent = 'Erro na pesquisa.'; coverStatus.className = 'cover-status error'; }
                        clearSuggestions();
                    });
            }

            function applySuggestion(r) {
                if (manualMode) return;
                // Only fill album if empty
                if (!albumInput.value && r.collectionName) albumInput.value = r.collectionName;
                // Map genre from iTunes
                if (genreInput && !genreInput.value && r.primaryGenreName) genreInput.value = r.primaryGenreName;
                // Don't overwrite cover if user uploaded a file
                if (coverFileInput && coverFileInput.files && coverFileInput.files.length > 0) return;
                if (r.artworkUrl100) {
                    var url = r.artworkUrl100.replace('100x100', '600x600');
                    if (coverUrl) coverUrl.value = url;
                    if (coverPreview) { coverPreview.src = url; coverPreview.style.display = 'block'; }
                    if (coverPlaceholder) coverPlaceholder.style.display = 'none';
                    if (coverStatus) { coverStatus.textContent = 'Capa encontrada!'; coverStatus.className = 'cover-status found'; }
                    showResetBtn();
                }
            }

            function showSuggestions(results) {
                var box = document.getElementById('itunesSuggestions');
                if (!box) return;
                box.style.display = '';
                box.innerHTML = '<div class="suggestions-label">Sugest\u00f5es do iTunes</div>';

                results.forEach(function(r) {
                    var item = document.createElement('div');
                    item.className = 'suggestion-item';
                    var art = r.artworkUrl100 ? r.artworkUrl100.replace('100x100', '200x200') : '';
                    item.innerHTML =
                        (art ? '<img src="' + art + '" class="suggestion-art">' : '') +
                        '<div class="suggestion-info">' +
                            '<div class="suggestion-title">' + escH(r.trackName || '') + '</div>' +
                            '<div class="suggestion-detail">' + escH(r.artistName || '') + ' \u2014 ' + escH(r.collectionName || '') + '</div>' +
                        '</div>';

                    item.addEventListener('click', function() {
                        manualMode = false; // Selecting a suggestion re-enables iTunes
                        titleInput.value = r.trackName || titleInput.value;
                        artistInput.value = r.artistName || artistInput.value;
                        albumInput.value = r.collectionName || albumInput.value;
                        if (genreInput && r.primaryGenreName) genreInput.value = r.primaryGenreName;
                        applySuggestion(r);
                        box.querySelectorAll('.suggestion-item').forEach(function(s) { s.classList.remove('selected'); });
                        item.classList.add('selected');
                        showResetBtn();
                    });
                    box.appendChild(item);
                });
            }

            function clearSuggestions() {
                var box = document.getElementById('itunesSuggestions');
                if (box) { box.innerHTML = ''; box.style.display = 'none'; }
            }

            function escH(str) {
                var d = document.createElement('div');
                d.textContent = str;
                return d.innerHTML;
            }

            // === FOLDER INPUT (webkitdirectory) ===
            var folderInput = document.getElementById('folderInput');
            var relativePathsInput = document.getElementById('relativePaths');
            var bulkFolderInfo = document.getElementById('bulkFolderInfo');
            var bulkFolderName = document.getElementById('bulkFolderName');
            var bulkFileCount = document.getElementById('bulkFileCount');
            var btnShowManualPath = document.getElementById('btnShowManualPath');
            var manualPathGroup = document.getElementById('manualPathGroup');

            if (folderInput) {
                folderInput.addEventListener('change', function() {
                    var files = folderInput.files;
                    if (!files || files.length === 0) {
                        if (bulkFolderInfo) bulkFolderInfo.style.display = 'none';
                        if (relativePathsInput) relativePathsInput.value = '[]';
                        return;
                    }

                    var audioExts = ['mp3','wav','ogg','flac','m4a','aac','wma'];
                    var paths = [];
                    var audioCount = 0;
                    var folderDetected = '';

                    for (var i = 0; i < files.length; i++) {
                        var relPath = files[i].webkitRelativePath || files[i].name;
                        paths.push(relPath);

                        var ext = files[i].name.split('.').pop().toLowerCase();
                        if (audioExts.indexOf(ext) !== -1) audioCount++;

                        if (!folderDetected && relPath) {
                            var parts = relPath.replace(/\\/g, '/').split('/');
                            if (parts.length > 1) folderDetected = parts[0];
                        }
                    }

                    if (relativePathsInput) relativePathsInput.value = JSON.stringify(paths);

                    // Check if no audio files found
                    if (audioCount === 0) {
                        alert('A pasta selecionada não contém ficheiros de áudio.\n\nFormatos suportados: MP3, WAV, OGG, FLAC, M4A, AAC, WMA');
                        folderInput.value = '';
                        if (bulkFolderInfo) bulkFolderInfo.style.display = 'none';
                        if (relativePathsInput) relativePathsInput.value = '[]';
                        return;
                    }

                    // Warn if too many files
                    if (audioCount > 100) {
                        if (!confirm('Foram detetados ' + audioCount + ' ficheiros de áudio.\n\nPor questões de performance, o limite é 100 ficheiros por importação.\n\nQueres continuar? (Apenas os primeiros 100 serão importados)')) {
                            folderInput.value = '';
                            if (bulkFolderInfo) bulkFolderInfo.style.display = 'none';
                            if (relativePathsInput) relativePathsInput.value = '[]';
                            return;
                        }
                    }

                    // Show folder info
                    if (bulkFolderInfo) {
                        bulkFolderInfo.style.display = '';
                        if (bulkFolderName) bulkFolderName.textContent = folderDetected || 'Pasta selecionada';
                        if (bulkFileCount) {
                            var displayCount = audioCount > 100 ? '100 (limite)' : audioCount;
                            bulkFileCount.textContent = displayCount + ' ficheiro(s) de áudio de ' + files.length + ' total';
                        }
                    }
                });
            }

            // Manual path toggle
            if (btnShowManualPath) {
                btnShowManualPath.addEventListener('click', function() {
                    if (manualPathGroup) {
                        var isHidden = manualPathGroup.style.display === 'none';
                        manualPathGroup.style.display = isHidden ? '' : 'none';
                        btnShowManualPath.querySelector('i').className = isHidden ? 'ph ph-eye-slash' : 'ph ph-keyboard';
                    }
                });
            }
        })();
        </script>
    </main>

</body>
</html>
