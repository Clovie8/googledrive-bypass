<?php
// FILE: index.php (Version 4.0 - The Form Solver)

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

// 2. THE NEW LOGIC: Look for the hidden "Form" inputs
// We are looking for: name="confirm" value="XXXX" and name="uuid" value="XXXX"

$confirmCode = "";
$uuid = "";

// Find the 'confirm' value (In your screenshot, it was "t")
if (preg_match('/name="confirm" value="([^"]+)"/', $response, $matches)) {
    $confirmCode = $matches[1];
}

// Find the 'uuid' value (The long random code)
if (preg_match('/name="uuid" value="([^"]+)"/', $response, $matches)) {
    $uuid = $matches[1];
}

// 3. Construct the Final Download Link
if ($confirmCode && $uuid) {
    // Google moves the download to a different server (drive.usercontent.google.com)
    // We scrape the 'action' URL from the form to be sure we have the right server
    if (preg_match('/action="([^"]+)"/', $response, $actionMatches)) {
        $downloadBaseUrl = $actionMatches[1];
        
        // Build the final "Magic Link"
        // It should look like: https://drive.usercontent.../download?id=ID&confirm=t&uuid=UUID
        $finalUrl = $downloadBaseUrl . "&id=" . $fileId . "&confirm=" . $confirmCode . "&uuid=" . $uuid;
        
        // Redirect the user
        header("Location: $finalUrl");
        exit;
    }
}

// 4. Fallback (If the regex failed)
// Use the Debug Output again if this doesn't work
echo "<h2>Failed to Extract Form Data</h2>";
echo "<p>We found the page, but couldn't find the 'uuid' or 'confirm' code.</p>";
echo "<hr><textarea style='width:100%; height:400px;'>" . htmlspecialchars($response) . "</textarea>";
?>
