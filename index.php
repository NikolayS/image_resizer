<?php
define("DEBUG", 1);

set_error_handler(function ($severity, $message, $filepath, $line) {
    throw new Exception($message . " in $filepath, line $line");
}, E_ALL & ~E_STRICT & ~E_NOTICE);

try {
    $src = @$_GET['src'];
    if (!$src) {
        throw new Exception("Required parameter not set: src");
    }
    $src = urldecode($src);
    $data = file_get_contents($src);
    $headers = parseHeaders($http_response_header);

    $img = imagecreatefromstring($data);
    $w = imagesx($img);
    $h = imagesy($img);

    if (isset($_GET['w']) && ($resW = intval($_GET['w'])) && $resW > 0) { // resize, target: WIDTH
        $resH = round($resW * $h / $w);
        //die("rd: $resW x $resH, orig: $width x $height");
        $resImg = imagecreatetruecolor($resW, $resH);
        imagecopyresampled($resImg, $img, 0, 0, 0, 0, $resW, $resH, $w, $h);
        //imagecopyresized (
    }
    
    if (@$headers['content-length'] > 0 && strpos(@$headers['content-type'], "Image") == 0) {
        header("Content-type: {$headers['content-type']}");
        //echo $data;
        imagejpeg($resImg, NULL, 80);
    }
} catch (Exception $e) {
    header("Bad request", true, 400);
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
