<?php
require 'db.php';
require 'extract_cover.php';
require 'apple_music.php';

$mensagem = "";
$erro = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mode']) && $_POST['mode'] === 'single') {
    $artista = trim($_POST['artista'] ?? '');
    $album   = trim($_POST['album'] ?? '');
    $musica  = trim($_POST['musica'] ?? '');
    $genero  = trim($_POST['genero'] ?? '');
    $caminho = trim($_POST['caminhoManual'] ?? '');

    $destino = '';

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

            soundrepoEnsureArtistImage($pdo, (int) $artistId, $artista);

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

            $stmt = $pdo->prepare("INSERT INTO Musics (Title, FilePath, AlbumId, Genre) VALUES (?, ?, ?, ?)");
            $stmt->execute([$musica, $destino, $albumId, $genero]);

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mode']) && $_POST['mode'] === 'bulk') {
    $detectMeta = isset($_POST['detectMeta']) && $_POST['detectMeta'] === '1';
    $extensions = ['mp3','wav','ogg','flac','m4a','aac','wma'];
    $bulkTitles = $_POST['bulkTitle'] ?? [];
    $bulkArtists = $_POST['bulkArtist'] ?? [];
    $bulkAlbums = $_POST['bulkAlbum'] ?? [];
    $bulkGenres = $_POST['bulkGenre'] ?? [];
    $bulkTrackNumbers = $_POST['bulkTrackNumber'] ?? [];

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
        $filesToImport = [];
        $folderName = '';

        if ($useUpload) {
            $relativePaths = json_decode($_POST['relativePaths'] ?? '[]', true) ?: [];
            $totalFiles = count($_FILES['folderUpload']['name']);
            $bulkMetaIndex = 0;

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
                    'isUpload' => true,
                    'meta' => [
                        'title' => trim($bulkTitles[$bulkMetaIndex] ?? ''),
                        'artist' => trim($bulkArtists[$bulkMetaIndex] ?? ''),
                        'album' => trim($bulkAlbums[$bulkMetaIndex] ?? ''),
                        'genre' => trim($bulkGenres[$bulkMetaIndex] ?? ''),
                        'trackNumber' => trim($bulkTrackNumbers[$bulkMetaIndex] ?? ''),
                    ],
                ];
                $bulkMetaIndex++;

                if (!$folderName && $relPath) {
                    $parts = explode('/', str_replace('\\', '/', $relPath));
                    if (count($parts) > 1) {
                        $folderName = $parts[0];
                    }
                }
            }
        } else {
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
            if (count($filesToImport) > 100) {
                $erro = "Demasiados ficheiros (" . count($filesToImport) . "). Máximo: 100 ficheiros por importação.";
            } else {
                $imported = 0;
                $failed = 0;

            $artistCache = [];
            $albumCache = [];
            $artistImageCache = [];

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

            $ensureArtistImageForImport = function($artistId, $artistName) use ($pdo, &$artistImageCache) {
                $key = strtolower($artistName);
                if (isset($artistImageCache[$key])) return;

                $stmt = $pdo->prepare("SELECT ImagePath, ImageLookupChecked FROM Artists WHERE ArtistId = ?");
                $stmt->execute([$artistId]);
                $artistRow = $stmt->fetch();

                soundrepoEnsureArtistImage(
                    $pdo,
                    (int) $artistId,
                    $artistName,
                    $artistRow['ImagePath'] ?? null,
                    (int) ($artistRow['ImageLookupChecked'] ?? 0)
                );

                $artistImageCache[$key] = true;
            };

            if (!is_dir('musicas')) mkdir('musicas', 0755, true);

            $processedCount = 0;
            $maxFiles = min(count($filesToImport), 100);

            foreach ($filesToImport as $fileInfo) {
                if ($processedCount >= $maxFiles) break;
                
                $f = $fileInfo['name'];
                $sanitized = preg_replace('/[^a-zA-Z0-9._\-\s]/', '', $f);
                $destino = 'musicas/' . $sanitized;

                $ok = $fileInfo['isUpload']
                    ? move_uploaded_file($fileInfo['source'], $destino)
                    : copy($fileInfo['source'], $destino);

                if ($ok) {
                    $baseName = pathinfo($f, PATHINFO_FILENAME);
                    $trackMeta = $fileInfo['meta'] ?? [];
                    $embeddedMeta = $detectMeta ? extractAudioMetadata($destino) : [];
                    $detectedTrackNumber = null;
                    if (preg_match('/^(\d{1,3})[\s.\-]+/', $baseName, $trackNumberMatches)) {
                        $detectedTrackNumber = (int) $trackNumberMatches[1];
                    }

                    $trackTitle = trim($trackMeta['title'] ?? '');
                    $trackArtist = trim($trackMeta['artist'] ?? '');
                    $trackAlbum = trim($trackMeta['album'] ?? '');
                    $trackGenre = trim($trackMeta['genre'] ?? '');
                    $trackNumber = normalizeTrackNumberValue($trackMeta['trackNumber'] ?? null);

                    if ($trackTitle === '' && !empty($embeddedMeta['title'])) $trackTitle = trim($embeddedMeta['title']);
                    if ($trackArtist === '' && !empty($embeddedMeta['artist'])) $trackArtist = trim($embeddedMeta['artist']);
                    if ($trackAlbum === '' && !empty($embeddedMeta['album'])) $trackAlbum = trim($embeddedMeta['album']);
                    if ($trackGenre === '' && !empty($embeddedMeta['genre'])) $trackGenre = trim($embeddedMeta['genre']);
                    if ($trackNumber === null && isset($embeddedMeta['trackNumber'])) $trackNumber = normalizeTrackNumberValue($embeddedMeta['trackNumber']);

                    $cleaned = preg_replace('/^\d{1,3}[\s.\-]+/', '', $baseName);
                    $cleaned = preg_replace('/\s*[\(\[][^\)\]]*[\)\]]\s*/', ' ', $cleaned);
                    $cleaned = trim(preg_replace('/\s+/', ' ', $cleaned));

                    // faz fallback para o nome do ficheiro quando as tags embutidas não chegam
                    $parts = preg_split('/\s*[\-\–\—]\s*/', $cleaned, 2);
                    
                    if ($trackArtist === '' || $trackTitle === '') {
                        if (count($parts) >= 2) {
                            if ($trackArtist === '') $trackArtist = trim($parts[0]);
                            if ($trackTitle === '') $trackTitle = trim($parts[1]);
                        } elseif ($trackTitle === '') {
                            $trackTitle = $cleaned;
                        }
                    }

                    if ($trackAlbum === '' && $detectMeta) {
                        $relativePathClean = str_replace('\\', '/', $fileInfo['relativePath'] ?? '');
                        $pathParts = array_values(array_filter(explode('/', $relativePathClean)));
                        $parentFolder = count($pathParts) > 1 ? $pathParts[count($pathParts) - 2] : $folderName;

                        if ($parentFolder && !preg_match('/^(music|musica|songs|downloads|desktop)$/i', $parentFolder)) {
                            $trackAlbum = $parentFolder;
                        }
                    }

                    if (empty($trackTitle)) $trackTitle = $baseName;
                    if (empty($trackArtist)) $trackArtist = 'Desconhecido';
                    if (empty($trackAlbum)) $trackAlbum = 'Desconhecido';
                    if ($trackNumber === null) $trackNumber = $detectedTrackNumber;

                    try {
                        $artistId = $getArtistId($trackArtist);
                        $ensureArtistImageForImport($artistId, $trackArtist);
                        $albumId = $getAlbumId($trackAlbum, $artistId);
                        $stmt = $pdo->prepare("INSERT INTO Musics (Title, FilePath, AlbumId, Genre, TrackNumber) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$trackTitle, $destino, $albumId, $trackGenre !== '' ? $trackGenre : null, $trackNumber]);
                        
                        $coverPath = extractEmbeddedCover($destino, $albumId);
                        if ($coverPath) {
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
                <a href="todas.php" class="nav-item">
                    <i class="ph ph-music-notes"></i><span>Todas as M&uacute;sicas</span>
                </a>
                <a href="artistas.php" class="nav-item">
                    <i class="ph ph-microphone-stage"></i><span>Artistas</span>
                </a>
                <a href="albuns.php" class="nav-item">
                    <i class="ph ph-vinyl-record"></i><span>&Aacute;lbuns</span>
                </a>
            </div>
        </div>
    </nav>

    <main class="main-view add-page fade-in" id="mainContent">
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

        <div class="add-panel" id="tabSingle">
            <form method="POST" action="adicionar.php" enctype="multipart/form-data">
                <input type="hidden" name="mode" value="single">

                <div class="add-layout">
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

                    <div class="add-fields">
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

                        <div id="itunesSuggestions" class="itunes-suggestions" style="display:none;"></div>

                        <button type="submit" class="btn-primary btn-add-submit">
                            <i class="ph ph-plus-circle"></i> Gravar na Biblioteca
                        </button>
                    </div>
                </div>
            </form>
        </div>

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

                        <div class="bulk-preview-card" id="bulkPreviewCard" style="display:none;">
                            <div class="bulk-apply-card" id="bulkApplyCard" style="display:none;">
                                <h4>Edição rápida em massa</h4>
                                <p>Aplica um valor comum a todas as músicas ou numera as faixas de forma sequencial.</p>
                                <div class="bulk-apply-grid">
                                    <input type="text" id="bulkApplyArtist" placeholder="Artista para todas">
                                    <input type="text" id="bulkApplyAlbum" placeholder="Álbum para todas">
                                    <input type="text" id="bulkApplyGenre" placeholder="Género para todas">
                                    <input type="number" id="bulkApplyTrackStart" min="1" placeholder="Faixa inicial">
                                </div>
                                <div class="bulk-apply-actions">
                                    <button type="button" class="btn-secondary" id="btnApplyBulkFields">
                                        <i class="ph ph-pencil-simple-line"></i> Aplicar campos preenchidos a todas
                                    </button>
                                    <button type="button" class="btn-secondary" id="btnApplyTrackNumbers">
                                        <i class="ph ph-list-numbers"></i> Numerar sequencialmente
                                    </button>
                                </div>
                            </div>

                            <div class="bulk-preview-header">
                                <div>
                                    <h4>Pré-visualização editável</h4>
                                    <p>Confirma ou corrige faixa, título, artista, álbum e género antes de importar.</p>
                                </div>
                            </div>
                            <div class="bulk-preview-list" id="bulkPreviewList"></div>
                        </div>

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
                            <span id="bulkTipText">A carregar dica...</span>
                        </div>

                        <button type="submit" class="btn-primary btn-add-submit">
                            <i class="ph ph-folder-plus"></i> Importar Todas as M&uacute;sicas
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <script>
        (function() {
            var bulkTips = [
                'Dica: se a tua pasta tiver subpastas por álbum, o preview tenta usar automaticamente essa pasta como nome do álbum.',
                'Dica: podes corrigir artista, álbum, género e faixa antes de importar, evitando trabalho depois na biblioteca.',
                'Dica: usa o botão de numeração sequencial quando tens um álbum inteiro e os ficheiros não trazem track numbers corretos.',
                'Dica: o SoundRepo tenta ler tags embutidas de MP3 antes de cair para o nome do ficheiro.',
                'Dica: aplicar artista, álbum ou género a todas é ideal para mixtapes, bandas sonoras ou discos importados de uma vez.'
            ];

            function setRandomBulkTip() {
                var bulkTipText = document.getElementById('bulkTipText');
                if (!bulkTipText) return;
                bulkTipText.textContent = bulkTips[Math.floor(Math.random() * bulkTips.length)];
            }

            window.switchTab = function(tab) {
                document.querySelectorAll('.add-tab').forEach(function(t) { t.classList.remove('active'); });
                document.querySelector('.add-tab[data-tab="' + tab + '"]').classList.add('active');
                document.getElementById('tabSingle').style.display = (tab === 'single') ? '' : 'none';
                document.getElementById('tabBulk').style.display = (tab === 'bulk') ? '' : 'none';
                if (tab === 'bulk') setRandomBulkTip();
            };

            window.switchSource = function(src) {
                document.querySelectorAll('.source-btn').forEach(function(b) { b.classList.remove('active'); });
                document.querySelector('.source-btn[data-source="' + src + '"]').classList.add('active');
                document.getElementById('srcUpload').style.display = (src === 'upload') ? '' : 'none';
                document.getElementById('srcPath').style.display = (src === 'path') ? '' : 'none';
            };

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
            var manualMode = false;

            if (!titleInput) return;

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

            function showResetBtn() {
                if (btnResetManual) btnResetManual.style.display = '';
            }
            function hideResetBtn() {
                if (btnResetManual) btnResetManual.style.display = 'none';
            }

            if (btnResetManual) {
                btnResetManual.addEventListener('click', function() {
                    manualMode = true;
                    clearTimeout(searchTimer);

                    if (coverUrl) coverUrl.value = '';
                    if (coverPreview) { coverPreview.src = ''; coverPreview.style.display = 'none'; }
                    if (coverPlaceholder) coverPlaceholder.style.display = '';
                    if (coverStatus) { coverStatus.textContent = 'Modo manual ativo'; coverStatus.className = 'cover-status'; }
                    if (coverFileInput) coverFileInput.value = '';

                    titleInput.value = '';
                    artistInput.value = '';
                    albumInput.value = '';
                    if (genreInput) genreInput.value = '';

                    clearSuggestions();
                    hideResetBtn();
                });
            }

            function parseFilename(filename) {
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
                        if (manualMode) return;
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
                if (!albumInput.value && r.collectionName) albumInput.value = r.collectionName;
                if (genreInput && !genreInput.value && r.primaryGenreName) genreInput.value = r.primaryGenreName;
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
                        manualMode = false;
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

            function extractLeadingTrackNumber(filename) {
                var baseName = filename.replace(/\.[^.]+$/, '');
                var match = baseName.match(/^(\d{1,3})[\s.\-]+/);
                return match ? String(parseInt(match[1], 10)) : '';
            }

            function parseSyncsafeInt(bytes) {
                return (bytes[0] << 21) | (bytes[1] << 14) | (bytes[2] << 7) | bytes[3];
            }

            function parseUInt32(bytes) {
                return ((bytes[0] << 24) >>> 0) + (bytes[1] << 16) + (bytes[2] << 8) + bytes[3];
            }

            function decodeBrowserMetadataText(bytes, encoding) {
                if (!bytes || !bytes.length) return '';

                try {
                    var decoder;
                    if (encoding === 0) {
                        decoder = new TextDecoder('iso-8859-1');
                    } else if (encoding === 1) {
                        if (bytes.length >= 2 && bytes[0] === 0xFE && bytes[1] === 0xFF) {
                            decoder = new TextDecoder('utf-16be');
                            bytes = bytes.slice(2);
                        } else if (bytes.length >= 2 && bytes[0] === 0xFF && bytes[1] === 0xFE) {
                            decoder = new TextDecoder('utf-16le');
                            bytes = bytes.slice(2);
                        } else {
                            decoder = new TextDecoder('utf-16le');
                        }
                    } else if (encoding === 2) {
                        decoder = new TextDecoder('utf-16be');
                    } else {
                        decoder = new TextDecoder('utf-8');
                    }

                    return decoder.decode(bytes).replace(/\u0000/g, '').replace(/\s+/g, ' ').trim();
                } catch (e) {
                    return '';
                }
            }

            async function parseBrowserMP3Metadata(file) {
                try {
                    var headerBuffer = await file.slice(0, 10).arrayBuffer();
                    var header = new Uint8Array(headerBuffer);
                    if (header.length < 10 || String.fromCharCode(header[0], header[1], header[2]) !== 'ID3') {
                        return {};
                    }

                    var versionMajor = header[3];
                    var tagSize = parseSyncsafeInt([header[6], header[7], header[8], header[9]]);
                    var tagBuffer = await file.slice(10, Math.min(file.size, tagSize + 10)).arrayBuffer();
                    var tagData = new Uint8Array(tagBuffer);
                    var offset = 0;
                    var metadata = {};

                    while (offset + 10 <= tagData.length) {
                        var frameId = String.fromCharCode(tagData[offset], tagData[offset + 1], tagData[offset + 2], tagData[offset + 3]);
                        if (!/^[A-Z0-9]{4}$/.test(frameId)) break;

                        var sizeBytes = [tagData[offset + 4], tagData[offset + 5], tagData[offset + 6], tagData[offset + 7]];
                        var frameSize = versionMajor >= 4 ? parseSyncsafeInt(sizeBytes) : parseUInt32(sizeBytes);
                        if (!frameSize || offset + 10 + frameSize > tagData.length) break;

                        if (['TIT2', 'TPE1', 'TPE2', 'TALB', 'TCON', 'TRCK'].indexOf(frameId) !== -1) {
                            var frameBytes = tagData.slice(offset + 10, offset + 10 + frameSize);
                            var textValue = decodeBrowserMetadataText(frameBytes.slice(1), frameBytes[0] || 0);
                            if (textValue) {
                                if (frameId === 'TIT2') metadata.title = textValue;
                                if (frameId === 'TPE1') metadata.artist = textValue;
                                if (frameId === 'TPE2' && !metadata.artist) metadata.artist = textValue;
                                if (frameId === 'TALB') metadata.album = textValue;
                                if (frameId === 'TCON') metadata.genre = textValue;
                                if (frameId === 'TRCK') {
                                    var numMatch = textValue.match(/\d+/);
                                    if (numMatch) metadata.trackNumber = String(parseInt(numMatch[0], 10));
                                }
                            }
                        }

                        offset += 10 + frameSize;
                    }

                    return metadata;
                } catch (e) {
                    return {};
                }
            }

            async function parseBrowserAudioMetadata(file) {
                var ext = file.name.split('.').pop().toLowerCase();
                if (ext === 'mp3') {
                    return await parseBrowserMP3Metadata(file);
                }
                return {};
            }

            function isGenericFolderName(name) {
                return /^(music|musica|songs|downloads|desktop)$/i.test((name || '').trim());
            }

            async function parseBulkTrackMeta(file, relativePath, detectMetaEnabled) {
                var filename = file.name;
                var baseName = filename.replace(/\.[^.]+$/, '');
                var cleaned = baseName.replace(/^\d{1,3}[\s.\-]+/, '');
                cleaned = cleaned.replace(/\s*[\(\[][^\)\]]*[\)\]]\s*/g, ' ');
                cleaned = cleaned.replace(/\s+/g, ' ').trim();
                var embeddedMeta = detectMetaEnabled ? await parseBrowserAudioMetadata(file) : {};

                var title = embeddedMeta.title || cleaned || baseName;
                var artist = embeddedMeta.artist || 'Desconhecido';
                var album = embeddedMeta.album || 'Desconhecido';
                var genre = embeddedMeta.genre || '';
                var trackNumber = embeddedMeta.trackNumber || extractLeadingTrackNumber(filename);
                var parts = cleaned.split(/\s*[\-–—]\s*/, 2);

                if (!embeddedMeta.artist && parts.length >= 2) {
                    artist = parts[0].trim() || 'Desconhecido';
                    title = parts[1].trim() || title;
                }

                if (detectMetaEnabled) {
                    var relParts = (relativePath || filename).replace(/\\/g, '/').split('/').filter(Boolean);
                    var parentFolder = relParts.length > 1 ? relParts[relParts.length - 2] : '';
                    if (parentFolder && !isGenericFolderName(parentFolder)) {
                        album = parentFolder;
                    }
                }

                return {
                    title: title || baseName,
                    artist: artist || 'Desconhecido',
                    album: album || 'Desconhecido',
                    genre: genre,
                    trackNumber: trackNumber || ''
                };
            }

            var bulkPreviewRenderToken = 0;

            async function renderBulkPreview(files) {
                var bulkPreviewCard = document.getElementById('bulkPreviewCard');
                var bulkApplyCard = document.getElementById('bulkApplyCard');
                var bulkPreviewList = document.getElementById('bulkPreviewList');
                var detectMetaCheckbox = document.querySelector('input[name="detectMeta"]');
                var renderToken = ++bulkPreviewRenderToken;

                if (!bulkPreviewCard || !bulkPreviewList) return;

                bulkPreviewList.innerHTML = '';

                if (!files || files.length === 0) {
                    bulkPreviewCard.style.display = 'none';
                    if (bulkApplyCard) bulkApplyCard.style.display = 'none';
                    return;
                }

                var audioExts = ['mp3','wav','ogg','flac','m4a','aac','wma'];
                var audioFiles = [];
                for (var i = 0; i < files.length; i++) {
                    var ext = files[i].name.split('.').pop().toLowerCase();
                    if (audioExts.indexOf(ext) !== -1) audioFiles.push(files[i]);
                }

                audioFiles = audioFiles.slice(0, 100);

                if (audioFiles.length === 0) {
                    bulkPreviewCard.style.display = 'none';
                    if (bulkApplyCard) bulkApplyCard.style.display = 'none';
                    return;
                }

                var metaRows = await Promise.all(audioFiles.map(async function(file) {
                    var relativePath = file.webkitRelativePath || file.name;
                    var meta = await parseBulkTrackMeta(file, relativePath, detectMetaCheckbox && detectMetaCheckbox.checked);
                    return { file: file, relativePath: relativePath, meta: meta };
                }));

                if (renderToken !== bulkPreviewRenderToken) return;

                metaRows.forEach(function(entry) {
                    var row = document.createElement('div');
                    row.className = 'bulk-preview-row';
                    row.innerHTML =
                        '<div class="bulk-preview-file">' +
                            '<div class="bulk-preview-file-name">' + escH(entry.file.name) + '</div>' +
                            '<div class="bulk-preview-file-path">' + escH(entry.relativePath) + '</div>' +
                        '</div>' +
                        '<div class="bulk-preview-fields">' +
                            '<input type="number" name="bulkTrackNumber[]" value="' + escH(entry.meta.trackNumber) + '" min="1" placeholder="Faixa">' +
                            '<input type="text" name="bulkTitle[]" value="' + escH(entry.meta.title) + '" placeholder="Título">' +
                            '<input type="text" name="bulkArtist[]" value="' + escH(entry.meta.artist) + '" placeholder="Artista">' +
                            '<input type="text" name="bulkAlbum[]" value="' + escH(entry.meta.album) + '" placeholder="Álbum">' +
                            '<input type="text" name="bulkGenre[]" value="' + escH(entry.meta.genre) + '" placeholder="Género">' +
                        '</div>';
                    bulkPreviewList.appendChild(row);
                });

                if (bulkApplyCard) bulkApplyCard.style.display = '';
                bulkPreviewCard.style.display = '';
            }

            function applyBulkField(selector, value) {
                document.querySelectorAll(selector).forEach(function(input) {
                    input.value = value;
                });
            }

            function applySequentialTrackNumbers(startAt) {
                var current = startAt;
                document.querySelectorAll('input[name="bulkTrackNumber[]"]').forEach(function(input) {
                    input.value = current;
                    current++;
                });
            }

            var folderInput = document.getElementById('folderInput');
            var relativePathsInput = document.getElementById('relativePaths');
            var bulkFolderInfo = document.getElementById('bulkFolderInfo');
            var bulkFolderName = document.getElementById('bulkFolderName');
            var bulkFileCount = document.getElementById('bulkFileCount');
            var btnShowManualPath = document.getElementById('btnShowManualPath');
            var manualPathGroup = document.getElementById('manualPathGroup');
            var detectMetaCheckbox = document.querySelector('input[name="detectMeta"]');
            var btnApplyBulkFields = document.getElementById('btnApplyBulkFields');
            var btnApplyTrackNumbers = document.getElementById('btnApplyTrackNumbers');
            var bulkApplyArtist = document.getElementById('bulkApplyArtist');
            var bulkApplyAlbum = document.getElementById('bulkApplyAlbum');
            var bulkApplyGenre = document.getElementById('bulkApplyGenre');
            var bulkApplyTrackStart = document.getElementById('bulkApplyTrackStart');

            if (folderInput) {
                folderInput.addEventListener('change', function() {
                    var files = folderInput.files;
                    if (!files || files.length === 0) {
                        if (bulkFolderInfo) bulkFolderInfo.style.display = 'none';
                        if (relativePathsInput) relativePathsInput.value = '[]';
                        renderBulkPreview([]);
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

                    if (audioCount === 0) {
                        alert('A pasta selecionada não contém ficheiros de áudio.\n\nFormatos suportados: MP3, WAV, OGG, FLAC, M4A, AAC, WMA');
                        folderInput.value = '';
                        if (bulkFolderInfo) bulkFolderInfo.style.display = 'none';
                        if (relativePathsInput) relativePathsInput.value = '[]';
                        renderBulkPreview([]);
                        return;
                    }

                    if (audioCount > 100) {
                        if (!confirm('Foram detetados ' + audioCount + ' ficheiros de áudio.\n\nPor questões de performance, o limite é 100 ficheiros por importação.\n\nQueres continuar? (Apenas os primeiros 100 serão importados)')) {
                            folderInput.value = '';
                            if (bulkFolderInfo) bulkFolderInfo.style.display = 'none';
                            if (relativePathsInput) relativePathsInput.value = '[]';
                            renderBulkPreview([]);
                            return;
                        }
                    }

                    if (bulkFolderInfo) {
                        bulkFolderInfo.style.display = '';
                        if (bulkFolderName) bulkFolderName.textContent = folderDetected || 'Pasta selecionada';
                        if (bulkFileCount) {
                            var displayCount = audioCount > 100 ? '100 (limite)' : audioCount;
                            bulkFileCount.textContent = displayCount + ' ficheiro(s) de áudio de ' + files.length + ' total';
                        }
                    }

                    renderBulkPreview(files);
                });
            }

            if (detectMetaCheckbox) {
                detectMetaCheckbox.addEventListener('change', function() {
                    if (folderInput && folderInput.files && folderInput.files.length > 0) {
                        renderBulkPreview(folderInput.files);
                    }
                });
            }

            if (btnApplyBulkFields) {
                btnApplyBulkFields.addEventListener('click', function() {
                    if (bulkApplyArtist && bulkApplyArtist.value.trim() !== '') {
                        applyBulkField('input[name="bulkArtist[]"]', bulkApplyArtist.value.trim());
                    }
                    if (bulkApplyAlbum && bulkApplyAlbum.value.trim() !== '') {
                        applyBulkField('input[name="bulkAlbum[]"]', bulkApplyAlbum.value.trim());
                    }
                    if (bulkApplyGenre && bulkApplyGenre.value.trim() !== '') {
                        applyBulkField('input[name="bulkGenre[]"]', bulkApplyGenre.value.trim());
                    }
                });
            }

            if (btnApplyTrackNumbers) {
                btnApplyTrackNumbers.addEventListener('click', function() {
                    var startAt = parseInt((bulkApplyTrackStart && bulkApplyTrackStart.value) || '1', 10);
                    if (!startAt || startAt < 1) startAt = 1;
                    applySequentialTrackNumbers(startAt);
                });
            }

            setRandomBulkTip();

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
