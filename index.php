<?php
// FILE: get_size.php
// Version 3.0: Uses "Byte Range" to detect size instantly without downloading.

// 1. INPUT
$fileId = isset($_GET['id']) ? $_GET['id'] : '';
if (empty($fileId)) { die("Error: No ID."); }

$googleUrl = "https://docs.google.com/uc?export=download&id=" . $fileId;

// 2. THE SMART REQUEST
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $googleUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, true); // Get Headers
curl_setopt($ch, CURLOPT_NOBODY, false); // We need body if it's HTML
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36');

// THE TRICK: Ask for only the first byte!
// This stops us from downloading the whole 2.2MB file.
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Range: bytes=0-1']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $header_size);
$body = substr($response, $header_size);
curl_close($ch);

$finalSize = "Unknown";

// 3. CASE A: DIRECT FILE (Status 206 Partial Content)
// Google said: "Here is byte 0-1. The total size is X."
if ($httpCode == 206) {
    if (preg_match('/Content-Range: bytes \d+-\d+\/(\d+)/i', $headers, $matches)) {
        $bytes = $matches[1];
        $finalSize = formatBytes($bytes);
    }
} 

// 4. CASE B: VIRUS WARNING PAGE (Status 200 OK)
// Google ignored our range request and sent the HTML page instead.
elseif ($httpCode == 200) {
    // Scan the text for "(2.5G)" or "(500M)"
    if (preg_match('/\(([\d\.]+)\s*([KMGT])B?\)/i', $body, $matches)) {
        $number = $matches[1];
        $unit = strtoupper($matches[2]); 
        $finalSize = $number . $unit . "B"; 
    }
}

// 5. OUTPUT
echo $finalSize;


// --- HELPER: Format Bytes to Readable Size ---
function formatBytes($bytes, $precision = 2) { 
    $units = array('B', 'KB', 'MB', 'GB', 'TB'); 
    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 
    $bytes /= pow(1024, $pow); 
    return round($bytes, $precision) . $units[$pow]; 
}
?>
