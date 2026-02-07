<?php
// FILE: index.php (Auto-Size Version)
// ==========================================

set_time_limit(0);
error_reporting(0);

// 1. INPUTS
$fileId = isset($_GET['id']) ? $_GET['id'] : '';
if (empty($fileId)) { die("Error: No File ID provided."); }

$isStreaming = isset($_GET['stream']);
$passedTitle = isset($_GET['title']) ? $_GET['title'] : ''; // We use this if provided

$googleUrl = "https://docs.google.com/uc?export=download&id=" . $fileId;

// ==================================================================
// PART A: THE "INFO PAGE" (Fetch Size Automatically)
// ==================================================================
if (!$isStreaming) {
    
    $fileName = $passedTitle ? $passedTitle : "Unknown Movie";
    $fileSize = "Unknown Size"; // Default

    // 1. Fetch the Google Page to find the size
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $googleUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // Pretend to be a browser so Google shows us the warning text
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36');
    $html = curl_exec($ch);
    curl_close($ch);

    // 2. THE SIZE HUNTER
    // Google's warning usually says: "FILENAME (2.5G) is too large..."
    // We look for that pattern: " (" followed by numbers/dots, followed by G or M, followed by ")"
    
    if (preg_match('/\((\d+(\.\d+)?\s*[GM]B?)\)/i', $html, $matches)) {
        // Found it! (e.g., "2.5G" or "500M")
        $fileSize = $matches[1];
        
        // Add "B" if it's just "G" or "M" to make it look nicer (2.5GB)
        if (strpos($fileSize, 'B') === false) {
            $fileSize .= "B"; 
        }
    }

    // 3. RENDER THE CARD
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Download <?php echo htmlspecialchars($fileName); ?></title>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0f0f0f; color: #fff; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .card { background: #1a1a1a; padding: 40px; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.7); text-align: center; max-width: 420px; width: 90%; border: 1px solid #333; }
            h2 { color: #00d4ff; margin-top: 0; font-size: 24px; letter-spacing: 1px; margin-bottom: 20px; }
            .file-icon { font-size: 60px; margin-bottom: 10px; display: block; }
            .file-info { background: #252525; padding: 20px; border-radius: 12px; margin: 25px 0; text-align: left; border: 1px solid #333; }
            .label { font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 1.5px; display: block; margin-bottom: 5px; }
            .value { font-size: 16px; color: #fff; font-weight: 600; display: block; margin-bottom: 15px; word-break: break-word; }
            .value:last-child { margin-bottom: 0; }
            .btn { display: block; background: linear-gradient(90deg, #00d4ff, #005bea); color: #fff; padding: 18px; text-decoration: none; border-radius: 50px; font-weight: bold; font-size: 18px; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 5px 15px rgba(0, 212, 255, 0.3); }
            .btn:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0, 212, 255, 0.5); }
        </style>
    </head>
    <body>
        <div class="card">
            <span class="file-icon">🎬</span>
            <h2>Ready to Download</h2>
            
            <div class="file-info">
                <span class="label">Movie Title</span>
                <span class="value"><?php echo htmlspecialchars($fileName); ?></span>
                
                <span class="label">File Size</span>
                <span class="value"><?php echo htmlspecialchars($fileSize); ?></span>
            </div>
            
            <a href="?id=<?php echo $fileId; ?>&stream=true&title=<?php echo urlencode($fileName); ?>" class="btn">Download Now ⬇</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ==================================================================
// PART B: THE STREAM (Standard Proxy)
// ==================================================================

$cookieFile = tempnam(sys_get_temp_dir(), 'gdrive_cookie_');
$passedTitle = isset($_GET['title']) ? $_GET['title'] : 'movie'; // Recapture title for filename

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
    // If no warning form, maybe it's a direct link. 
    // We can try to use the last effective URL or just stream the original if it was direct.
}

if ($finalUrl) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    // CLEAN THE FILENAME: Remove special chars to avoid errors
    $safeFileName = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $passedTitle);
    header('Content-Disposition: attachment; filename="' . $safeFileName . '.mp4"'); 
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
