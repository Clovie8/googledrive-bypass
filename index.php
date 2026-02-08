<?php
// FILE: index.php (Version 7.0 - The Fail-Safe)
// ==============================================

set_time_limit(0);
error_reporting(0);

// 1. INPUTS
$fileId = isset($_GET['id']) ? $_GET['id'] : '';
if (empty($fileId)) { die("Error: No File ID provided."); }

// We use the title provided in the URL (because you know it)
$passedTitle = isset($_GET['title']) ? $_GET['title'] : 'Unknown Movie';
$isStreaming = isset($_GET['stream']);

$googleUrl = "https://docs.google.com/uc?export=download&id=" . $fileId;

// ==================================================================
// PART A: THE "INFO PAGE" (The Menu)
// ==================================================================
if (!$isStreaming) {
    
    $displaySize = ""; // Default to empty (hidden)

    // 1. Ask Google for the page
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $googleUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // Act like a real Chrome browser to see the "Virus Warning"
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36');
    $html = curl_exec($ch);
    curl_close($ch);

    // 2. Try to find the size (The "Bonus" Step)
    // We look for the text class we saw in your screenshot: "uc-warning-subcaption"
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    
    // Look for: <p class="uc-warning-subcaption">... (2.5G) ...</p>
    $nodes = $xpath->query('//*[contains(@class, "uc-warning-subcaption")]');
    
    if ($nodes->length > 0) {
        $text = $nodes->item(0)->nodeValue;
        // Regex to find any number inside brackets like (2.5G) or (500M)
        if (preg_match('/\(([\d\.]+\s*[GM]B?)\)/i', $text, $matches)) {
            $displaySize = $matches[1]; // Found it! (e.g. "2.5G")
        }
    }

    // 3. RENDER THE CARD
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Download <?php echo htmlspecialchars($passedTitle); ?></title>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0f0f0f; color: #fff; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .card { background: #1a1a1a; padding: 40px; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.7); text-align: center; max-width: 420px; width: 90%; border: 1px solid #333; }
            h2 { color: #00d4ff; margin-top: 0; font-size: 24px; letter-spacing: 1px; margin-bottom: 20px; }
            .file-icon { font-size: 60px; margin-bottom: 10px; display: block; }
            .file-info { background: #252525; padding: 20px; border-radius: 12px; margin: 25px 0; text-align: left; border: 1px solid #333; }
            .label { font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 1.5px; display: block; margin-bottom: 5px; }
            .value { font-size: 16px; color: #fff; font-weight: 600; display: block; margin-bottom: 15px; word-break: break-word; }
            .btn { display: block; background: linear-gradient(90deg, #00d4ff, #005bea); color: #fff; padding: 18px; text-decoration: none; border-radius: 50px; font-weight: bold; font-size: 18px; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 5px 15px rgba(0, 212, 255, 0.3); cursor: pointer; border: none; width: 100%; box-sizing: border-box;}
            .btn:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0, 212, 255, 0.5); }
        </style>
    </head>
    <body>
        <div class="card">
            <span class="file-icon">🎬</span>
            <h2>Ready to Download</h2>
            
            <div class="file-info">
                <span class="label">Movie Title</span>
                <span class="value"><?php echo htmlspecialchars($passedTitle); ?></span>
                
                <?php if ($displaySize): ?>
                    <span class="label">File Size</span>
                    <span class="value"><?php echo htmlspecialchars($displaySize); ?></span>
                <?php endif; ?>
            </div>
            
            <a href="?id=<?php echo $fileId; ?>&stream=true&title=<?php echo urlencode($passedTitle); ?>" class="btn">Download Now ⬇</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ==================================================================
// PART B: THE STREAM (This is your working proxy code)
// ==================================================================

$cookieFile = tempnam(sys_get_temp_dir(), 'gdrive_cookie_');

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $googleUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36');
$html = curl_exec($ch);
curl_close($ch);

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
    // Fallback if no form found
}

if ($finalUrl) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    
    // Use the Title from URL to name the file (e.g. "Avatar.mp4")
    $safeName = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $passedTitle);
    header('Content-Disposition: attachment; filename="' . $safeName . '.mp4"'); 
    
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
