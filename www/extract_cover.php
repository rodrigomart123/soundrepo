<?php
function extractEmbeddedCover($audioFilePath, $albumId) {
    if (!file_exists($audioFilePath)) return false;
    
    $ext = strtolower(pathinfo($audioFilePath, PATHINFO_EXTENSION));
    $coverPath = false;
    
    if ($ext === 'mp3') {
        $coverPath = extractMP3Cover($audioFilePath, $albumId);
    }
    elseif ($ext === 'flac') {
        $coverPath = extractFLACCover($audioFilePath, $albumId);
    }
    elseif (in_array($ext, ['m4a', 'aac', 'mp4'])) {
        $coverPath = extractM4ACover($audioFilePath, $albumId);
    }
    
    return $coverPath;
}

function extractAudioMetadata($audioFilePath) {
    if (!file_exists($audioFilePath)) {
        return [];
    }

    $ext = strtolower(pathinfo($audioFilePath, PATHINFO_EXTENSION));

    if ($ext === 'mp3') {
        return extractMP3Metadata($audioFilePath);
    }

    if ($ext === 'flac') {
        return extractFLACMetadata($audioFilePath);
    }

    return [];
}

function normalizeTrackNumberValue($value) {
    if ($value === null || $value === '') return null;

    if (preg_match('/\d+/', (string) $value, $matches)) {
        return (int) $matches[0];
    }

    return null;
}

function decodeMetadataTextValue($rawValue, $encodingByte = 0) {
    if ($rawValue === '') return '';

    $decoded = '';

    if ($encodingByte === 0) {
        if (function_exists('iconv')) {
            $decoded = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $rawValue);
        }
        if ($decoded === false || $decoded === '') {
            $decoded = $rawValue;
        }
    } elseif ($encodingByte === 1) {
        if (substr($rawValue, 0, 2) === "\xFE\xFF") {
            $rawValue = substr($rawValue, 2);
            $decoded = function_exists('iconv') ? @iconv('UTF-16BE', 'UTF-8//IGNORE', $rawValue) : '';
        } elseif (substr($rawValue, 0, 2) === "\xFF\xFE") {
            $rawValue = substr($rawValue, 2);
            $decoded = function_exists('iconv') ? @iconv('UTF-16LE', 'UTF-8//IGNORE', $rawValue) : '';
        } else {
            $decoded = function_exists('iconv') ? @iconv('UTF-16', 'UTF-8//IGNORE', $rawValue) : '';
        }
    } elseif ($encodingByte === 2) {
        $decoded = function_exists('iconv') ? @iconv('UTF-16BE', 'UTF-8//IGNORE', $rawValue) : '';
    } else {
        $decoded = $rawValue;
    }

    if ($decoded === false || $decoded === null) {
        $decoded = $rawValue;
    }

    $decoded = str_replace(["\x00", "\r"], ['', ' '], $decoded);
    $decoded = trim(preg_replace('/\s+/', ' ', $decoded));

    return $decoded;
}

function parseID3FrameSize($sizeBytes, $versionMajor) {
    $bytes = array_values(unpack('C4', $sizeBytes));
    if ($versionMajor >= 4) {
        return ($bytes[0] << 21) | ($bytes[1] << 14) | ($bytes[2] << 7) | $bytes[3];
    }

    return ($bytes[0] << 24) | ($bytes[1] << 16) | ($bytes[2] << 8) | $bytes[3];
}

function extractMP3Metadata($filePath) {
    $fp = fopen($filePath, 'rb');
    if (!$fp) return [];

    $header = fread($fp, 10);
    if (strlen($header) < 10 || substr($header, 0, 3) !== 'ID3') {
        fclose($fp);
        return [];
    }

    $versionMajor = ord($header[3]);
    $size = unpack('C4', substr($header, 6, 4));
    $tagSize = ($size[1] << 21) | ($size[2] << 14) | ($size[3] << 7) | $size[4];
    $tagData = fread($fp, $tagSize);
    fclose($fp);

    $metadata = [];
    $offset = 0;
    $wantedFrames = ['TIT2', 'TPE1', 'TPE2', 'TALB', 'TCON', 'TRCK'];

    // lê os frames de texto mais úteis para preencher o import
    while ($offset + 10 <= strlen($tagData)) {
        $frameId = substr($tagData, $offset, 4);
        if (!preg_match('/^[A-Z0-9]{4}$/', $frameId)) {
            break;
        }

        $frameSize = parseID3FrameSize(substr($tagData, $offset + 4, 4), $versionMajor);
        if ($frameSize <= 0 || $offset + 10 + $frameSize > strlen($tagData)) {
            break;
        }

        if (in_array($frameId, $wantedFrames, true)) {
            $frameData = substr($tagData, $offset + 10, $frameSize);
            $encoding = strlen($frameData) ? ord($frameData[0]) : 0;
            $textValue = decodeMetadataTextValue(substr($frameData, 1), $encoding);

            if ($textValue !== '') {
                if ($frameId === 'TIT2') $metadata['title'] = $textValue;
                if ($frameId === 'TPE1') $metadata['artist'] = $textValue;
                if ($frameId === 'TPE2' && empty($metadata['artist'])) $metadata['artist'] = $textValue;
                if ($frameId === 'TALB') $metadata['album'] = $textValue;
                if ($frameId === 'TCON') $metadata['genre'] = $textValue;
                if ($frameId === 'TRCK') $metadata['trackNumber'] = normalizeTrackNumberValue($textValue);
            }
        }

        $offset += 10 + $frameSize;
    }

    return $metadata;
}

function extractFLACMetadata($filePath) {
    $fp = fopen($filePath, 'rb');
    if (!$fp) return [];

    $signature = fread($fp, 4);
    if ($signature !== 'fLaC') {
        fclose($fp);
        return [];
    }

    $metadata = [];

    while (!feof($fp)) {
        $blockHeader = fread($fp, 4);
        if (strlen($blockHeader) < 4) break;

        $headerData = unpack('C4', $blockHeader);
        $isLast = ($headerData[1] & 0x80) !== 0;
        $blockType = $headerData[1] & 0x7F;
        $blockSize = ($headerData[2] << 16) | ($headerData[3] << 8) | $headerData[4];

        $blockData = fread($fp, $blockSize);

        if ($blockType === 4 && strlen($blockData) >= 8) {
            $offset = 0;
            $vendorLength = unpack('V', substr($blockData, $offset, 4))[1];
            $offset += 4 + $vendorLength;

            if ($offset + 4 <= strlen($blockData)) {
                $commentCount = unpack('V', substr($blockData, $offset, 4))[1];
                $offset += 4;

                for ($i = 0; $i < $commentCount; $i++) {
                    if ($offset + 4 > strlen($blockData)) break;

                    $commentLength = unpack('V', substr($blockData, $offset, 4))[1];
                    $offset += 4;
                    $comment = substr($blockData, $offset, $commentLength);
                    $offset += $commentLength;

                    if (strpos($comment, '=') === false) continue;
                    [$key, $value] = explode('=', $comment, 2);
                    $key = strtoupper(trim($key));
                    $value = trim($value);

                    if ($key === 'TITLE' && empty($metadata['title'])) $metadata['title'] = $value;
                    if (($key === 'ARTIST' || $key === 'ALBUMARTIST') && empty($metadata['artist'])) $metadata['artist'] = $value;
                    if ($key === 'ALBUM' && empty($metadata['album'])) $metadata['album'] = $value;
                    if ($key === 'GENRE' && empty($metadata['genre'])) $metadata['genre'] = $value;
                    if ($key === 'TRACKNUMBER' && empty($metadata['trackNumber'])) $metadata['trackNumber'] = normalizeTrackNumberValue($value);
                }
            }
        }

        if ($isLast) break;
    }

    fclose($fp);

    return $metadata;
}

function extractMP3Cover($filePath, $albumId) {
    $fp = fopen($filePath, 'rb');
    if (!$fp) return false;
    
    $header = fread($fp, 10);
    if (substr($header, 0, 3) !== 'ID3') {
        fclose($fp);
        return false;
    }
    
    $size = unpack('C4', substr($header, 6, 4));
    $tagSize = ($size[1] << 21) | ($size[2] << 14) | ($size[3] << 7) | $size[4];
    
    $tagData = fread($fp, $tagSize);
    fclose($fp);
    
    $offset = 0;
    while ($offset < strlen($tagData) - 10) {
        $frameId = substr($tagData, $offset, 4);
        
        if ($frameId === 'APIC') {
            $frameSizeData = unpack('C4', substr($tagData, $offset + 4, 4));
            $frameSize = ($frameSizeData[1] << 24) | ($frameSizeData[2] << 16) | ($frameSizeData[3] << 8) | $frameSizeData[4];
            $frameData = substr($tagData, $offset + 10, $frameSize);
            $encoding = ord($frameData[0]);
            $pos = 1;
            $mimeEnd = strpos($frameData, "\x00", $pos);
            if ($mimeEnd === false) break;
            $mime = substr($frameData, $pos, $mimeEnd - $pos);
            $pos = $mimeEnd + 1;
            $pos++;
            $descEnd = strpos($frameData, "\x00", $pos);
            if ($descEnd === false) break;
            $pos = $descEnd + 1;
            $imageData = substr($frameData, $pos);
            $imgExt = 'jpg';
            if (strpos($mime, 'png') !== false) $imgExt = 'png';
            if (!is_dir('covers')) mkdir('covers', 0755, true);
            $coverFile = 'covers/album_' . $albumId . '_' . time() . '.' . $imgExt;
            if (file_put_contents($coverFile, $imageData)) {
                return $coverFile;
            }
            break;
        }
        
        $frameSizeData = unpack('C4', substr($tagData, $offset + 4, 4));
        $frameSize = ($frameSizeData[1] << 24) | ($frameSizeData[2] << 16) | ($frameSizeData[3] << 8) | $frameSizeData[4];
        $offset += 10 + $frameSize;
    }
    
    return false;
}

function extractFLACCover($filePath, $albumId) {
    $fp = fopen($filePath, 'rb');
    if (!$fp) return false;
    
    $signature = fread($fp, 4);
    if ($signature !== 'fLaC') {
        fclose($fp);
        return false;
    }
    
    while (!feof($fp)) {
        $blockHeader = fread($fp, 4);
        if (strlen($blockHeader) < 4) break;
        
        $headerData = unpack('C4', $blockHeader);
        $isLast = ($headerData[1] & 0x80) !== 0;
        $blockType = $headerData[1] & 0x7F;
        $blockSize = ($headerData[2] << 16) | ($headerData[3] << 8) | $headerData[4];
        
        if ($blockType === 6) {
            $pictureData = fread($fp, $blockSize);
            $pos = 0;
            $pos += 4;
            $mimeLenData = unpack('N', substr($pictureData, $pos, 4));
            $mimeLen = $mimeLenData[1];
            $pos += 4;
            $mime = substr($pictureData, $pos, $mimeLen);
            $pos += $mimeLen;
            $descLenData = unpack('N', substr($pictureData, $pos, 4));
            $descLen = $descLenData[1];
            $pos += 4;
            $pos += $descLen;
            $pos += 16;
            $imgLenData = unpack('N', substr($pictureData, $pos, 4));
            $imgLen = $imgLenData[1];
            $pos += 4;
            $imageData = substr($pictureData, $pos, $imgLen);
            $imgExt = 'jpg';
            if (strpos($mime, 'png') !== false) $imgExt = 'png';
            if (!is_dir('covers')) mkdir('covers', 0755, true);
            $coverFile = 'covers/album_' . $albumId . '_' . time() . '.' . $imgExt;
            if (file_put_contents($coverFile, $imageData)) {
                fclose($fp);
                return $coverFile;
            }
            break;
        }
        
        fseek($fp, $blockSize, SEEK_CUR);
        
        if ($isLast) break;
    }
    
    fclose($fp);
    return false;
}

function extractM4ACover($filePath, $albumId) {
    return false;
}
