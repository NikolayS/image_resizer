<?php
// 
// IMAGE RESIZER
//

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

set_error_handler(function ($severity, $message, $filepath, $line) {
    throw new Exception($message . " in $filepath, line $line");
}, E_ALL & ~E_STRICT & ~E_NOTICE);

$SUPPORTED_TYPES = array(
    'image/png',
    'image/jpg',
    'image/jpeg',
    'image/gif',
);

try {
    $src = @$_GET['src'];
    if (!$src) {
        throw new Exception("Required parameter is not set: 'src'.");
    }

    $srcParsed = parse_url($src);
    if (@$srcParsed['host'] && !$ALLOW_ABSOLUTE_URLS) {
        throw new Exception("Absolute URLs are not allowed.");
    }
    if (!@$srcParsed['host']) {
        $src =  $HOST_FOR_URIS . $src;
    }

    if (!($data = file_get_contents($src))) {
        header("HTTP/1.0 404 Not Found");
        exit;
    }
    $data = file_get_contents($src);
    $headers = parseHeaders($http_response_header);
    $contentType = strtolower(@$headers['content-type']);

    if (!$contentType || !in_array($contentType, $SUPPORTED_TYPES)) {
        throw new Exception("Either the file is not an image or its type is not supported.");
    }

    $resImg = null;

    $img = imagecreatefromstring($data);
    $w = imagesx($img);
    $h = imagesy($img);

    $resize = null;
    if (isset($_GET['w']) && (intval($_GET['w']) > 0)) { // resize, target: WIDTH
        $resW = intval($_GET['w']);
        $resH = round($resW * $h / $w);
        $resize = true;
    }
    if (isset($_GET['h']) && (intval($_GET['h']) > 0) && (!isset($resH) || (intval($_GET['h']) < $resH))) { // resize, target: HEIGHT.
        $resH = intval($_GET['h']);
        $resW = round($resH * $w / $h);
        $resize = true;
    }
    $IS_ANIMATED = 0;
    if ($resize) {
        if ($contentType == 'image/gif' && $RESIZE_ANIMATED_GIF && isAnimatedGif($data)) {
            $IS_ANIMATED = 1;
            $TMPFILE = '/var/tmp/' . substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 16);
            file_put_contents($TMPFILE, $data);
            resizeAnimatedGif($TMPFILE, $resW, $resH);
        } else {
            $resImg = imagecreatetruecolor($resW, $resH);
            if (in_array($contentType, array('image/png', 'image/gif'))) { // tricks to preserve transparency of GIF/PNG
                imagealphablending($resImg, false);
                imagesavealpha($resImg, true);
                $transparent = imagecolorallocatealpha($resImg, 255, 255, 255, 1);
                imagefilledrectangle($resImg, 0, 0, $resW, $resH, $transparent);
            }
            imagecopyresampled($resImg, $img, 0, 0, 0, 0, $resW, $resH, $w, $h);
        }
    } else {
        $resImg = $img;
    }

    header("X-Content-Length-Original: " . strlen($data));
    if ($resImg || $TMPFILE) {
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
                $ftmp = fopen($TMPFILE, 'rb');
                fpassthru($ftmp);
                fclose($ftmp);
                deleteFile($TMPFILE);
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
    header("Bad request", true, 400);
    header("X-IMAGE-RESIZER-ERROR: " . str_replace(array("\n", "\r"), array(" ", " "), $e->getMessage()));
    if ($DEBUG) echo $e->getMessage();
    exit;
}

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

        putenv("MAGICK_THREAD_LIMIT=1");
        exec(escapeshellcmd($_image_magick) . ' ' . $f . ' -coalesce -strip -resize ' . $dim . ' ' . $f);
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
