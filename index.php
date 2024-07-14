<?php
ini_set('memory_limit', '1024M');
include "dom.php";
require_once('vendor/autoload.php');

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Firefox\FirefoxOptions; 
use Dompdf\Dompdf;
use Dompdf\Options;

$base_url = "https://zbato.org";

$imageSavePath = 'images';
if (!file_exists($imageSavePath)) {
    mkdir($imageSavePath, 0777, true);
}


function getRequestWithHeaders($url) {
    $headers = array(
        'Referer: wto.to',
        'Sec-Ch-Ua: "Chromium";v="118", "Google Chrome";v="118", "Not=A?Brand";v="99"',
        'Sec-Ch-Ua-Mobile: ?0',
        'Sec-Ch-Ua-Platform: "Windows"',
        'Sec-Fetch-Dest: empty',
        'Sec-Fetch-Mode: cors',
        'Sec-Fetch-Site: same-origin',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36'
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; rv:8.0) Gecko/20100101 Firefox/8.0");
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_ENCODING, "");
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    $html = curl_exec($ch);
    curl_close($ch);
	return $html;
    
}

function getDetail($url,$base_url){
    $host = 'http://localhost:4444/wd/hub';
    $capabilities = DesiredCapabilities::firefox();
    $capabilities->setCapability('acceptSslCerts', false);

    $driver = RemoteWebDriver::create($host, $capabilities);
    $driver->get($url);
    $driver->wait(10)->until(
        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::tagName('body'))
    );
    $pageSource = $driver->getPageSource();
    $dom = new simple_html_dom();
    $dom = $dom->load($pageSource);
    
    foreach($dom->find(".main a") as $item){
        $url =  $base_url.$item->href;
        getMenhwa($url);
    }
    $driver->quit();
}

function convertImageToBase64($imagePath) {
    $type = pathinfo($imagePath, PATHINFO_EXTENSION);
    $data = file_get_contents($imagePath);
    return 'data:image/' . $type . ';base64,' . base64_encode($data);
}

function getMenhwa($url){
    $host = 'http://localhost:4444/wd/hub';
    $capabilities = DesiredCapabilities::firefox();
    $capabilities->setCapability('acceptSslCerts', false);

    $driver = RemoteWebDriver::create($host, $capabilities);
    $driver->get($url);
    $driver->wait(10)->until(
        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::tagName('body'))
    );
    $pageSource = $driver->getPageSource();
    $dom = new simple_html_dom();
    $dom = $dom->load($pageSource);
    $imageData = "";
    $i = 0;
    $imageSavePath = 'images';
    $htmlContent = "<html><head><title>Images</title><style>img { display: block; }</style></head><body>";
    foreach ($dom->find(".item img") as $img) {
        $pngImagePath = downloadAndConvertWebpToPng($img->src, $imageSavePath);
        $base64Image = convertImageToBase64($pngImagePath);
        $htmlContent .= '<img width="auto" src="' . $base64Image . '" alt="' . $img->alt . '">';
    }
    $htmlContent .= "</body></html>";
    $title = $driver->getTitle();
    $pecah = explode("-", $title);
    $title = $pecah[1];
    $title = "KULI-TERBAIK ".$title;
    $driver->quit();

    $filePath = 'html/' . $title . ".html";
    if (!file_exists('html')) {
        mkdir('html', 0777, true);
    }
    file_put_contents($filePath, $htmlContent);
}

getDetail("https://zbato.org/series/112568/kuli-terbaik-the-world-s-best-engineer",$base_url);


function downloadAndConvertWebpToPng($webpUrl, $savePath) {
    // Gunakan cURL untuk mengunduh gambar WebP
    $ch = curl_init($webpUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $webpContent = curl_exec($ch);
    curl_close($ch);

    if ($webpContent === false) {
        throw new Exception('Failed to download image: ' . $webpUrl);
    }

    // Simpan konten WebP ke file sementara
    $webpTempPath = tempnam(sys_get_temp_dir(), 'webp');
    file_put_contents($webpTempPath, $webpContent);

    // Konversi gambar WebP menjadi PNG dan simpan ke path yang diberikan
    $webpImage = imagecreatefromwebp($webpTempPath);
    if ($webpImage === false) {
        throw new Exception('Failed to create image from WebP: ' . $webpTempPath);
    }
    $pngPath = $savePath . '/' . basename($webpUrl, '.webp') . '.png';
    imagepng($webpImage, $pngPath);
    imagedestroy($webpImage);
    unlink($webpTempPath);

    return $pngPath;
}