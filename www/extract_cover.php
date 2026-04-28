<?php
/**
 * Extract embedded cover art from audio files (MP3, FLAC, M4A, etc.)
 * Returns the cover image path or false if not found
 */
function extractEmbeddedCover($audioFilePath, $albumId) {
    if (!file_exists($audioFilePath)) return false;
    
    $ext = strtolower(pathinfo($audioFilePath, PATHINFO_EXTENSION));
    $coverPath = false;
    
    // MP3 files (ID3v2 tags)
    if ($ext === 'mp3') {
        $coverPath = extractMP3Cover($audioFilePath, $albumId);
    }
    // FLAC files
    elseif ($ext === 'flac') {
        $coverPath = extractFLACCover($audioFilePath, $albumId);
    }
    // M4A/AAC files
    elseif (in_array($ext, ['m4a', 'aac', 'mp4'])) {
        $coverPath = extractM4ACover($audioFilePath, $albumId);
    }
    
    return $coverPath;
}

/**
 * Extract cover from MP3 (ID3v2)
 */
function extractMP3Cover($filePath, $albumId) {
    $fp = fopen($filePath, 'rb');
    if (!$fp) return false;
    
    // Read ID3v2 header
    $header = fread($fp, 10);
    if (substr($header, 0, 3) !== 'ID3') {
        fclose($fp);
        return false;
    }
    
    // Parse tag size (synchsafe integer)
    $size = unpack('C4', substr($header, 6, 4));
    $tagSize = ($size[1] << 21) | ($size[2] << 14) | ($size[3] << 7) | $size[4];
    
    // Read tag data
    $tagData = fread($fp, $tagSize);
    fclose($fp);
    
    // Look for APIC frame (Attached Picture)
    $offset = 0;
    while ($offset < strlen($tagData) - 10) {
        $frameId = substr($tagData, $offset, 4);
        
        if ($frameId === 'APIC') {
            // Parse frame size
            $frameSizeData = unpack('C4', substr($tagData, $offset + 4, 4));
            $frameSize = ($frameSizeData[1] << 24) | ($frameSizeData[2] << 16) | ($frameSizeData[3] << 8) | $frameSizeData[4];
            
            // Skip frame header (10 bytes)
            $frameData = substr($tagData, $offset + 10, $frameSize);
            
            // Parse APIC frame: encoding(1) + mime(null-terminated) + picture_type(1) + description(null-terminated) + image_data
            $encoding = ord($frameData[0]);
            $pos = 1;
            
            // Find mime type (null-terminated)
            $mimeEnd = strpos($frameData, "\x00", $pos);
            if ($mimeEnd === false) break;
            $mime = substr($frameData, $pos, $mimeEnd - $pos);
            $pos = $mimeEnd + 1;
            
            // Skip picture type
            $pos++;
            
            // Skip description (null-terminated)
            $descEnd = strpos($frameData, "\x00", $pos);
            if ($descEnd === false) break;
            $pos = $descEnd + 1;
            
            // Extract image data
            $imageData = substr($frameData, $pos);
            
            // Determine extension from mime
            $imgExt = 'jpg';
            if (strpos($mime, 'png') !== false) $imgExt = 'png';
            
            // Save cover
            if (!is_dir('covers')) mkdir('covers', 0755, true);
            $coverFile = 'covers/album_' . $albumId . '_' . time() . '.' . $imgExt;
            if (file_put_contents($coverFile, $imageData)) {
                return $coverFile;
            }
            break;
        }
        
        // Move to next frame
        $frameSizeData = unpack('C4', substr($tagData, $offset + 4, 4));
        $frameSize = ($frameSizeData[1] << 24) | ($frameSizeData[2] << 16) | ($frameSizeData[3] << 8) | $frameSizeData[4];
        $offset += 10 + $frameSize;
    }
    
    return false;
}

/**
 * Extract cover from FLAC (METADATA_BLOCK_PICTURE)
 */
function extractFLACCover($filePath, $albumId) {
    $fp = fopen($filePath, 'rb');
    if (!$fp) return false;
    
    // Check FLAC signature
    $signature = fread($fp, 4);
    if ($signature !== 'fLaC') {
        fclose($fp);
        return false;
    }
    
    // Read metadata blocks
    while (!feof($fp)) {
        $blockHeader = fread($fp, 4);
        if (strlen($blockHeader) < 4) break;
        
        $headerData = unpack('C4', $blockHeader);
        $isLast = ($headerData[1] & 0x80) !== 0;
        $blockType = $headerData[1] & 0x7F;
        $blockSize = ($headerData[2] << 16) | ($headerData[3] << 8) | $headerData[4];
        
        // Block type 6 = PICTURE
        if ($blockType === 6) {
            $pictureData = fread($fp, $blockSize);
            
            // Parse picture block
            $pos = 0;
            
            // Picture type (4 bytes) - skip
            $pos += 4;
            
            // MIME type length (4 bytes)
            $mimeLenData = unpack('N', substr($pictureData, $pos, 4));
            $mimeLen = $mimeLenData[1];
            $pos += 4;
            
            // MIME type
            $mime = substr($pictureData, $pos, $mimeLen);
            $pos += $mimeLen;
            
            // Description length (4 bytes)
            $descLenData = unpack('N', substr($pictureData, $pos, 4));
            $descLen = $descLenData[1];
            $pos += 4;
            
            // Skip description
            $pos += $descLen;
            
            // Skip width, height, depth, colors (16 bytes)
            $pos += 16;
            
            // Image data length (4 bytes)
            $imgLenData = unpack('N', substr($pictureData, $pos, 4));
            $imgLen = $imgLenData[1];
            $pos += 4;
            
            // Extract image data
            $imageData = substr($pictureData, $pos, $imgLen);
            
            // Determine extension
            $imgExt = 'jpg';
            if (strpos($mime, 'png') !== false) $imgExt = 'png';
            
            // Save cover
            if (!is_dir('covers')) mkdir('covers', 0755, true);
            $coverFile = 'covers/album_' . $albumId . '_' . time() . '.' . $imgExt;
            if (file_put_contents($coverFile, $imageData)) {
                fclose($fp);
                return $coverFile;
            }
            break;
        }
        
        // Skip this block
        fseek($fp, $blockSize, SEEK_CUR);
        
        if ($isLast) break;
    }
    
    fclose($fp);
    return false;
}

/**
 * Extract cover from M4A/AAC (MP4 container)
 */
function extractM4ACover($filePath, $albumId) {
    // M4A parsing is complex, skip for now
    // Would require parsing MP4 atom structure
    return false;
}
