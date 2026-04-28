CREATE DATABASE IF NOT EXISTS soundrepo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE soundrepo;

CREATE TABLE IF NOT EXISTS Artists (
    ArtistId INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(255) NOT NULL,
    ImagePath VARCHAR(500),
    ImageLookupChecked TINYINT(1) NOT NULL DEFAULT 0,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (Name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Albums (
    AlbumId INT AUTO_INCREMENT PRIMARY KEY,
    Title VARCHAR(255) NOT NULL,
    ArtistId INT NOT NULL,
    CoverPath VARCHAR(500),
    Year INT,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ArtistId) REFERENCES Artists(ArtistId) ON DELETE CASCADE,
    INDEX idx_title (Title),
    INDEX idx_artist (ArtistId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Musics (
    MusicId INT AUTO_INCREMENT PRIMARY KEY,
    Title VARCHAR(255) NOT NULL,
    AlbumId INT NOT NULL,
    FilePath VARCHAR(500) NOT NULL,
    Duration INT,
    Genre VARCHAR(100),
    TrackNumber INT,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (AlbumId) REFERENCES Albums(AlbumId) ON DELETE CASCADE,
    INDEX idx_title (Title),
    INDEX idx_album (AlbumId),
    INDEX idx_genre (Genre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
