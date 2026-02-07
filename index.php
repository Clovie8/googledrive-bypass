<?php
// FILE: index.php (With Info Page)
// ==========================================

// 1. SETTINGS
set_time_limit(0);
error_reporting(0);

// 2. CHECK INPUT
$fileId = isset($_GET['id']) ? $_GET['id'] : '';
if (empty($fileId)) { die("Error: No File ID provided."); }

// 3. DECISION: DO WE SHOW INFO OR START DOWNLOAD?
// If the URL has "&stream=true", we download. Otherwise, we show info.
$isStreaming = isset($_GET['stream']);

// Google URL
$googleUrl = "https://docs.google.com/uc?export=download&id=" . $fileId;

// ==================================================================
// PART A: THE "INFO PAGE" (Shows Name & Size)
// ==================================================================
if (!$isStreaming) {
    
    // We fetch the Google Page just to read the text
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $googleUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36');
    $html = curl_exec($ch);
    curl_close($ch);

    // Default values if we can't find them
    $fileName = "Unknown Movie.mp4";
    $fileSize = "Unknown Size";

    // SCRAPE THE INFO (Using DOMDocument)
    $dom = new DOMDocument();
    @$dom->loadHTML($html);

    // 1. Try to find the "Virus Warning" text which contains the name and size
    // Example text: "AVATAR.mp4 (2.5G) is too large..."
    $xpath = new DOMXPath($dom);
    $warningNodes = $xpath->query('//*[contains(@class, "uc-warning-subcaption")]');
    
    if ($warningNodes->length > 0) {
        $text = $warningNodes->item(0)->nodeValue;
        
        // Extract Name (Everything before the first parenthesis)
        if (preg_match('/^(.*?) \((.*?)\)/', $text, $matches)) {
            $fileName = trim($matches[1]);
            $fileSize = trim($matches[2]);
        }
    } else {
        // Fallback: Check for title tag or download button
        $nodes = $xpath->query('//span[contains(@class, "uc-name-size")]');
        if ($nodes->length > 0) {
             $fileName = $nodes->item(0)->nodeValue;
        }
    }

    // RENDER THE HTML PAGE
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Download <?php echo htmlspecialchars($fileName); ?></title>
        <style>
            body { font-family: 'Arial', sans-serif; background: #121212; color: #fff; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .card { background: #1e1e1e; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); text-align: center; max-width: 400px; width: 100%; border: 1px solid #333; }
            h2 { color: #00d4ff; margin-bottom: 10px; font-size: 20px; }
            p { color: #bbb; margin-bottom: 25px; }
            .file-info { background: #2a2a2a; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: left; }
            .label { font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 1px; }
            .value { font-size: 16px; color: #fff; font-weight: bold; margin-bottom: 10px; display: block; }
            .btn { display: inline-block; background: #00d4ff; color: #000; padding: 15px 30px; text-decoration: none; border-radius: 50px; font-weight: bold; font-size: 16px; transition: 0.3s; width: 80%; }
            .btn:hover { background: #00b8db; transform: scale(1.05); }
        </style>
    </head>
    <body>
        <div class="card">
            <h2>Ready to Download</h2>
            <div class="file-info">
                <span class="label">File Name</span>
                <span class="value"><?php echo htmlspecialchars($fileName); ?></span>
                
                <span class="label">File Size</span>
                <span class="value"><?php echo htmlspecialchars($fileSize); ?></span>
            </div>
            
            <a href="?id=<?php echo $fileId; ?>&stream=true" class="btn">Download Now ⬇</a>
        </div>
    </body>
    </html>
    <?php
    exit; // Stop here! Don't download yet.
}


// ==================================================================
// PART B: THE "STREAMING" LOGIC (Your existing code)
// ==================================================================
// This only runs if "&stream=true" is in the URL

$cookieFile = tempnam(sys_get_temp_dir(), 'gdrive_cookie_');

// 1. GET THE FORM & COOKIES
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $googleUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36');

$html = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);

// 2. PARSE FORM
$dom = new DOMDocument();
@$dom->loadHTML($html);
$downloadForm = $dom->getElementById('download-form');
$finalUrl = '';

if ($downloadForm) {
    $action = $downloadForm->getAttribute('action');
    $inputs = $downloadForm->getElementsByTagName('input');
    $params = [];
    foreach ($inputs as $input) {
        $params[$input->getAttribute('name')] = $input->getAttribute('value');
    }
    $finalUrl = $action . '?' . http_build_query($params);
} else {
    $finalUrl = $info['url']; // Direct download fallback
}

// 3. STREAM TO USER
if ($finalUrl) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    // NOTE: We try to use the name from the ID, but for now we use a generic name or passed name
    // If you want the real name in the download, you'd need to scrape it again here or pass it in URL
    header('Content-Disposition: attachment; filename="movie.mp4"'); 
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');

    $outputParams = fopen('php://output', 'w');
    $ch = curl_init($finalUrl);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use ($outputParams) {
        return fwrite($outputParams, $data);
    });
    curl_exec($ch);
    curl_close($ch);
    fclose($outputParams);
    @unlink($cookieFile);
    exit;
}
?>
