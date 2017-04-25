<?php
// 
// IMAGE RESIZER
//

$start = microtime();

if (file_exists("config.local.php")) {
    require_once("config.local.php");
} else {
    trigger_error("Config is missing", E_USER_ERROR); 
}

if (!extension_loaded('gd') && !extension_loaded('gd2')) {
    trigger_error("GD is not loaded", E_USER_WARNING);
    exit;
}
if ($RESIZE_ANIMATED_GIF && !($_image_magick = exec("which convert"))) {
    trigger_error("ImageMagic is not loaded, and RESIZE_ANIMATED_GIF is set to TRUE", E_USER_WARNING);
    exit;
}

if ($DEBUG) {
    set_error_handler(function ($severity, $message, $filepath, $line) {
        global $tmpFile;
        if (isset($tmpFile)) {
            deleteFile($tmpFile);
        }
        throw new Exception($message . " in $filepath, line $line");
    }, E_ALL);
} else {
    set_error_handler(function ($severity, $message, $filepath, $line) {
        global $tmpFile;
        if (isset($tmpFile)) {
            deleteFile($tmpFile);
        }
        throw new Exception($message . " in $filepath, line $line");
    }, E_ALL & ~E_STRICT & ~E_NOTICE & ~E_WARNING);
}

$SUPPORTED_TYPES = array(
    'image/png',
    'image/jpg',
    'image/jpeg',
    'image/gif',
);

logTime("Start parse params at line " . __LINE__ );
try {
    $src = isset($_GET['src']) ? $_GET['src'] : null;
    if (!$src) {
        throw new Exception("Required parameter is not set: 'src'.");
    }

    $srcParsed = parse_url($src);
    if (array_key_exists('host', $srcParsed) && $srcParsed['host'] && !$ALLOW_ABSOLUTE_URLS) {
        throw new Exception("Absolute URLs are not allowed.");
    }
    if (!array_key_exists('host', $srcParsed) || !$srcParsed['host']) {
        $src =  $HOST_FOR_URIS . $src;
    }
    logTime("Start file_get_contents at line " . __LINE__ );
    $data = file_get_contents($src); // !! if the file doesn't exist, this will cause Exception in DEBUG mode, instead of 404
    if ($data === FALSE) {
        header("HTTP/1.0 404 Not Found");
        header("X-IMAGE-RESIZER-ERROR: Cannot read this file: $src");
        exit;
    }
    $headers = parseHeaders($http_response_header);
    $contentType = strtolower(@$headers['content-type']);

    if (!$contentType || !in_array($contentType, $SUPPORTED_TYPES)) {
        throw new Exception("Either the file is not an image or its type is not supported.");
    }

    $resImg = null;

    logTime("Start imagecreatefromstring at line " . __LINE__ );
    $img = imagecreatefromstring($data);
    $w = imagesx($img);
    $h = imagesy($img);

    $resize = null;
    if (isset($_GET['w']) && (intval($_GET['w']) > 0) && intval($_GET['w']) != $w) { // resize, target: WIDTH
        $resW = intval($_GET['w']);
        $resH = round($resW * $h / $w);
        $resize = true;
    }

    if (isset($_GET['h']) && (intval($_GET['h']) > 0) 
                          && (!isset($resH) || (intval($_GET['h']) < $resH))
                          && intval($_GET['h']) != $h) { // resize, target: HEIGHT.
        $resH = intval($_GET['h']);
        $resW = round($resH * $w / $h);
        $resize = true;
    }
    $IS_ANIMATED = 0;
    if ($resize) {
        if ($contentType == 'image/gif' && $RESIZE_ANIMATED_GIF && isAnimatedGif($data)) {
            $IS_ANIMATED = 1;
            $tmpFile = $TMP_DIR . '/' . substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 16);
            logTime("Start file_get_contents at line " . __LINE__ );
            file_put_contents($tmpFile, $data);
            logTime("Start resizeAnimatedGif at line " . __LINE__ );
            resizeAnimatedGif($tmpFile, $resW, $resH);
        } else {
            logTime("Start imagecreatetruecolor at line " . __LINE__ );
            $resImg = imagecreatetruecolor($resW, $resH);
            if (in_array($contentType, array('image/png', 'image/gif'))) { // tricks to preserve transparency of GIF/PNG
                logTime("Start imagealphablending at line " . __LINE__ );
                imagealphablending($resImg, false);
                logTime("Start imagesavealpha at line " . __LINE__ );
                imagesavealpha($resImg, true);
                logTime("Start imagecolorallocatealpha at line " . __LINE__ );
                $transparent = imagecolorallocatealpha($resImg, 255, 255, 255, 1);
                logTime("Start imagefilledrectangle at line " . __LINE__ );
                imagefilledrectangle($resImg, 0, 0, $resW, $resH, $transparent);
            }
            logTime("Start imagecopyresampled at line " . __LINE__ );
            imagecopyresampled($resImg, $img, 0, 0, 0, 0, $resW, $resH, $w, $h);
        }
    } else {
        $resImg = $img;
    }

    logTime("Start prepare output at line " . __LINE__ );
    header("X-Content-Length-Original: " . strlen($data));
    header("Access-Control-Allow-Origin: *");
    if ($resImg || $tmpFile) {
        switch ($contentType) {
        case 'image/jpeg':
            ob_start();
            header("Content-Type: image/jpeg");
            imagejpeg($resImg, NULL, 80);
            $outImg = ob_get_clean();
            break;
        case 'image/png':
            ob_start();
            header("Content-Type: image/png");
            imagepng($resImg, NULL, 9);
            $outImg = ob_get_clean();
            break;
        case 'image/gif':
            ob_start();
            header("Content-Type: image/gif");
            if ($IS_ANIMATED) {
                $ftmp = fopen($tmpFile, 'rb');
                fpassthru($ftmp);
                fclose($ftmp);
                deleteFile($tmpFile);
            } else {
                imagegif($resImg, NULL);
            }
            $outImg = ob_get_clean();
            break;
        default:
            $err = "Output for Content-Type='{$headers['content-type']}' is not yet implemented";
            header("X-IMAGE-RESIZER-ERROR: $err");
            header($err, true, 501);
            unset($err);
            exit;
        }
        header("Content-Lenght: " . strlen($outImg));
        echo $outImg;
    }
} catch (Exception $e) {
    if (isset($tmpFile)) {
        deleteFile($tmpFile);
    }
    header("Bad request", true, 400);
    header("X-IMAGE-RESIZER-ERROR: " . str_replace(array("\n", "\r"), array(" ", " "), $e->getMessage()));
    if ($DEBUG) echo $e->getMessage();
    exit;
}
logTime("Stop at line " . __LINE__ );

function parseHeaders($headers, $lowerNames = true)
{
    $res = array();
    foreach ($headers as $h) {
        if (strpos($h, ": ") > 0) {
            preg_match("/^(.*)\: (.*)$/", $h, $matches);
            if ($lowerNames) {
                $matches[1] = strtolower($matches[1]);
            } 
            $res[$matches[1]] = $matches[2];
            unset($matches);
        }
    }
    return $res;
}


/**
* Using ImageMagick package, resize animated GIF file.
*
* @param string $f filename of original image
* @param integer $width
* @param integer $height
* @return string resized file name
*/
function resizeAnimatedGif($f, $width, $height, $master = NULL)
{
    $_image_magick = exec("which convert");
    if (!empty($_image_magick)) {
        if (empty($width) AND empty($height)) {
            throw new CException('image invalid dimensions');
        }

        $dim = $width.'x'.$height;

        global $TMP_DIR_IMAGEMAGICK;
        putenv("MAGICK_THREAD_LIMIT=1");
        if (!is_null($TMP_DIR_IMAGEMAGICK)) {
            putenv("MAGICK_TEMPORARY_PATH=$TMP_DIR_IMAGEMAGICK");
            putenv("MAGICK_TMPDIR=$TMP_DIR_IMAGEMAGICK");
        }
        $prefix = "";
        global $CONVERT_TIMEOUT;
        if (!is_null($CONVERT_TIMEOUT)) {
            $prefix = "timeout $CONVERT_TIMEOUT ";
        }
        logTime("Start ImageMagick call at line " . __LINE__ );
        exec($prefix . escapeshellcmd($_image_magick) . ' ' . $f . ' -coalesce -strip -resize ' . $dim . ' ' . $f, $output, $status);
        logTime("Done ImageMagick call at line " . __LINE__ );
        if ($status > 0) { // assume that timeout occured; exit right now
            global $tmpFile;
            if (isset($tmpFile)) {
                deleteFile($tmpFile);
            }
            header("Service Unavailable", true, 503);
            header("X-IMAGE-RESIZER-ERROR: " . "Image convertion timeout reached (CONVERT_TIMEOUT: $CONVERT_TIMEOUT)");
            exit;    
        }
        
        return $f;
    }
}

/**
* Checking if given gif file (given as binary data) is an animated gif
*
* @return boolean
*/
function isAnimatedGif($buf)
{
    if (strpos($buf, "\x21\xFF\x0B\x4E\x45\x54\x53\x43\x41\x50\x45\x32\x2E\x30" ) !== FALSE) {
        return TRUE;
    }
    return FALSE;
}

function deleteFile($filename)
{
    if (file_exists($filename)) {
        unlink($filename);
    }
}

function logTime($message) {
    global $DEBUG;
    global $LOG_DIR;
    global $start;
    if ($DEBUG && isset($LOG_DIR)) {
        $msg = $message . ". Duration from start is " . (microtime() - $start) . ' msec.';
        if (is_writable($LOG_DIR)) {
            file_put_contents("$LOG_DIR/image_resizer_time.log", date("c") . " [" . posix_getpid() . "] $msg \n", FILE_APPEND);
        }
    }
}
