<?php
define("DEBUG", 1);

if (!extension_loaded('gd') && !extension_loaded('gd2')) {
    trigger_error("GD is not loaded", E_USER_WARNING);
    return false;
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
    $data = file_get_contents($src);
    $headers = parseHeaders($http_response_header);
    print_r($headers);die;

    if (!isset($headers['content-type']) || !in_array(strtolower($headers['content-type']), $SUPPORTED_TYPES)) {
        throw new Exception("Either the file is not an image or its type is not supported.");
    }

    $img = imagecreatefromstring($data);
    $w = imagesx($img);
    $h = imagesy($img);

    if (isset($_GET['w']) && ($resW = intval($_GET['w'])) && $resW > 0) { // resize, target: WIDTH
        $resH = round($resW * $h / $w);
        $resImg = imagecreatetruecolor($resW, $resH);
        if (in_array(strtolower($headers['content-type']), array('image/png', 'image/gif'))) { // tricks to preserve PNG's transparency
            imagealphablending($resImg, false);
            imagesavealpha($resImg, true);
            $transparent = imagecolorallocatealpha($resImg, 255, 255, 255, 127);
            imagefilledrectangle($resImg, 0, 0, $resW, $resH, $transparent);
        }
        imagecopyresampled($resImg, $img, 0, 0, 0, 0, $resW, $resH, $w, $h);
    } else {
        $resImg = $img;
    }

    if (@$headers['content-length'] > 0 && strpos(@$headers['content-type'], "Image") == 0) {
        switch (strtolower($headers['content-type'])) {
        case 'image/jpeg':
            header("Content-Type: image/jpeg");
            imagejpeg($resImg, NULL, 80);
            break;
        case 'image/png':
            header("Content-Type: image/png");
            imagepng($resImg, NULL, 9);
        case 'image/gif':
            header("Content-Type: image/gif");
            imagegif($resImg, NULL);
        default:
            $err = "Output for Content-Type='{$headers['content-type']}' is not yet implemented";
            header("X-IMAGE-RESIZER-ERROR: $err");
            header($err, true, 501);
            unset($err);
            exit;
        }
    }
} catch (Exception $e) {
    header("Bad request", true, 400);
    header("X-IMAGE-RESIZER-ERROR: " . str_replace(array("\n", "\r"), array(" ", " "), $e->getMessage()));
    if (defined("DEBUG")) echo $e->getMessage();
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
