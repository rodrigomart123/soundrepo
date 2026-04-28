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

$sql = "SELECT m.MusicId, m.Title, m.FilePath, m.Duration, m.Genre, a.Title as AlbumName, a.CoverPath, art.Name as ArtistName
        FROM Musics m
        JOIN Albums a ON m.AlbumId = a.AlbumId
        JOIN Artists art ON a.ArtistId = art.ArtistId
        ORDER BY m.MusicId DESC";
try {
    $stmt = $pdo->query($sql);
    $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasMusics = count($tracks) > 0;
} catch(Exception $e) {
    $tracks = [];
    $hasMusics = false;
}
?>
<main class="main-view fade-in" id="mainContent">
    <div class="view-header">
        <h1 class="view-title"><?= $viewTitle ?></h1>
        <input type="text" id="searchPersistent" class="library-search" placeholder="Filtrar nesta lista..." autocomplete="off">
    </div>

    <!-- Track List -->
    <div class="track-list-container">
        <?php if($hasMusics): ?>
        <table>
            <thead>
                <tr>
                    <th style="width:50px; text-align:center;">#</th>
                    <th>Título</th>
                    <th>Artista</th>
                    <th>Álbum</th>
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
            <i class="ph ph-music-notes"></i>
            <h2>A tua biblioteca está vazia</h2>
            <p>Adiciona músicas para começares a ouvir.</p>
            <button class="btn-primary" onclick="SPA.navigate('adicionar.php')" style="margin-top:16px;">
                Adicionar Música
            </button>
        </div>
        <?php endif; ?>
    </div>

    <script>
        initDashboardEvents();
    </script>
</main>

