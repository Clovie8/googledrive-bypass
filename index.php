<?php
// FILE: get_size.php
// Usage: https://your-site.com/get_size.php?id=YOUR_FILE_ID

// 1. Get the File ID
$fileId = isset($_GET['id']) ? $_GET['id'] : '';
if (empty($fileId)) {
    die("Error: No ID provided.");
}

$googleUrl = "https://docs.google.com/uc?export=download&id=" . $fileId;

// 2. Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $googleUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, true); // We need headers
// Important: Pretend to be a real browser so Google sends the warning page text
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36');

$response = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $header_size);
$body = substr($response, $header_size);
curl_close($ch);

// 3. METHOD A: Check HTTP Headers (For small files)
// Sometimes Google sends the size directly in "Content-Length"
if (preg_match('/Content-Length: (\d+)/i', $headers, $matches)) {
    $bytes = $matches[1];
    echo formatSize($bytes);
    exit;
}

// 4. METHOD B: Scan the "Virus Warning" Text (For large files)
// Google puts the size in text like: "MovieName.mp4 (2.5G) is too large..."
// We look for any pattern like (1.2G) or (500M) inside parentheses.
if (preg_match('/\(([\d\.]+\s*[GM]B?)\)/i', $body, $matches)) {
    echo $matches[1]; // Outputs: 2.5G
    exit;
}

// 5. If we can't find it
echo "Size Unknown";

// --- Helper Function to make bytes readable ---
function formatSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return $bytes . ' byte';
    } else {
        return '0 bytes';
    }
}
?>
