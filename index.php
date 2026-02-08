<?php
// FILE: get_size.php
// Accurately gets file size for both Small Files (Direct) and Large Files (Warning)

// 1. INPUT CHECK
$fileId = isset($_GET['id']) ? $_GET['id'] : '';
if (empty($fileId)) { die("Error: No ID."); }

$googleUrl = "https://docs.google.com/uc?export=download&id=" . $fileId;

// 2. FETCH HEADERS AND BODY SEPARATELY
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $googleUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, true); // Important: Get Headers
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36');

$response = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $header_size);
$body = substr($response, $header_size);
curl_close($ch);

$finalSize = "Unknown";

// 3. CHECK CONTENT TYPE
// If Content-Type is "text/html", it means we hit a webpage (Virus Warning).
// If it is anything else (video/mp4, octet-stream), it is a Direct Download.
$isHtml = preg_match('/Content-Type:\s*text\/html/i', $headers);

// --- STRATEGY A: SMALL FILE (Direct Download) ---
if (!$isHtml) {
    if (preg_match('/Content-Length:\s*(\d+)/i', $headers, $matches)) {
        $bytes = (int)$matches[1];
        $finalSize = formatBytes($bytes);
    }
}

// --- STRATEGY B: LARGE FILE (Virus Warning Page) ---
if ($isHtml || $finalSize == "Unknown") {
    // Look for pattern like: (2.5G) or (500M) or (1.72GB)
    // We capture the Number and the Unit (K, M, G, T)
    if (preg_match('/\(([\d\.]+)\s*([KMGT])B?\)/i', $body, $matches)) {
        $number = $matches[1];
        $unit = strtoupper($matches[2]); 
        
        // Google usually says "2.5G", we want "2.5GB"
        $finalSize = $number . $unit . "B"; 
    }
}

// 4. OUTPUT RESULT
echo $finalSize;


// --- HELPER FUNCTION: Convert Bytes to MB/GB ---
function formatBytes($bytes, $precision = 2) { 
    $units = array('B', 'KB', 'MB', 'GB', 'TB'); 
    
    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 
    
    $bytes /= pow(1024, $pow); 
    
    // Returns format like "1.72GB" (No space, per your request)
    return round($bytes, $precision) . $units[$pow]; 
}
?>
