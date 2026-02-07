<?php
// FILE: index.php (Version 5.0 - The URL Fix)

$fileId = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($fileId)) {
    die("Error: No File ID provided.");
}

// 1. Initial Request to Google
$googleUrl = "https://drive.google.com/uc?export=download&id=" . $fileId;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $googleUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

$response = curl_exec($ch);
curl_close($ch);

// 2. SCRAPE DATA: Look for the hidden "Form" inputs
$confirmCode = "";
$uuid = "";

// Find the 'confirm' value (usually "t")
if (preg_match('/name="confirm" value="([^"]+)"/', $response, $matches)) {
    $confirmCode = $matches[1];
}

// Find the 'uuid' value
if (preg_match('/name="uuid" value="([^"]+)"/', $response, $matches)) {
    $uuid = $matches[1];
}

// 3. Construct the Final Download Link
if ($confirmCode && $uuid) {
    // Scrape the 'action' URL (e.g., https://drive.usercontent.google.com/download)
    if (preg_match('/action="([^"]+)"/', $response, $actionMatches)) {
        $downloadBaseUrl = $actionMatches[1];
        
        // FIX IS HERE: We correctly determine if we need '?' or '&'
        $separator = (strpos($downloadBaseUrl, '?') !== false) ? '&' : '?';
        
        // Build the correct URL with "export=download" included
        $finalUrl = $downloadBaseUrl . $separator . "id=" . $fileId . "&export=download&confirm=" . $confirmCode . "&uuid=" . $uuid;
        
        // Redirect the user
        header("Location: $finalUrl");
        exit;
    }
}

// 4. Fallback (If scraping failed)
echo "<h2>Failed to Bypass</h2>";
echo "<p>Could not find confirmation tokens.</p>";
echo "<hr><textarea style='width:100%; height:400px;'>" . htmlspecialchars($response) . "</textarea>";
?>
