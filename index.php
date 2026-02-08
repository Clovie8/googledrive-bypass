<?php
// FILE: get_size.php
// Scans Google Drive Virus Warning page for file size.

// 1. Get the File ID
$fileId = isset($_GET['id']) ? $_GET['id'] : '';
if (empty($fileId)) { die("Error: No ID."); }

$googleUrl = "https://docs.google.com/uc?export=download&id=" . $fileId;

// 2. Setup CURL (Standard Browser Mode)
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $googleUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36');

// Get the HTML content
$html = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if ($error) { die("Connection Error: $error"); }

// 3. THE FIX: Only look for the text pattern in the HTML Body
// We look for: (NUMBER + G or M) 
// Example: matches "(2.5G)" or "(500M)"
$foundSize = "Unknown";

if (preg_match('/\(([\d\.]+\s*[GM]B?)\)/', $html, $matches)) {
    $foundSize = $matches[1];
} 
// Backup check: Sometimes it looks like "2.5 GB" without parenthesis
elseif (preg_match('/([\d\.]+\s*[GM]B)/', $html, $matches)) {
    $foundSize = $matches[1];
}

// 4. Output ONLY the size (or debug info if it fails)
if ($foundSize != "Unknown") {
    // SUCCESS
    echo $foundSize; 
} else {
    // DEBUG: If it fails, tell us what the page title is so we know why.
    if (preg_match('/<title>(.*?)<\/title>/', $html, $titles)) {
        echo "Failed. Page Title: " . $titles[1];
    } else {
        echo "Failed. Could not read page.";
    }
}
?>
