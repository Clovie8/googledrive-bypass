<?php
// FILE: index.php (Version 3.0 - The HTML Hunter)

$fileId = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($fileId)) {
    die("Error: No File ID provided.");
}

// 1. Setup the initial URL
$googleUrl = "https://drive.google.com/uc?export=download&id=" . $fileId;

// 2. Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $googleUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, true); 
// Act like a real Chrome browser
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

// Get the response (Headers + Body)
$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    die("cURL Error: " . $error);
}

// 3. STRATEGY A: Look for the Cookie (The old way)
if (preg_match('/download_warning[^=]*=([^;]*)/', $response, $matches)) {
    $confirmCode = $matches[1];
    $directUrl = "https://drive.google.com/uc?export=download&id=$fileId&confirm=$confirmCode";
    header("Location: $directUrl");
    exit;
}

// 4. STRATEGY B: Look inside the HTML Button (The new way)
// Google often puts the code inside a link like: href="/uc?export=download&confirm=XXXX&id=..."
if (preg_match('/confirm=([a-zA-Z0-9_-]+)/', $response, $matches)) {
    $confirmCode = $matches[1];
    $directUrl = "https://drive.google.com/uc?export=download&id=$fileId&confirm=$confirmCode";
    header("Location: $directUrl");
    exit;
}

// 5. STRATEGY C: Look for the specific "uuid" confirm pattern
if (preg_match('/&confirm=([a-zA-Z0-9_-]+)/', $response, $matches)) {
    $confirmCode = $matches[1];
    $directUrl = "https://drive.google.com/uc?export=download&id=$fileId&confirm=$confirmCode";
    header("Location: $directUrl");
    exit;
}

// 6. FAILURE REPORTING
// If we reach here, Google has completely blocked the script.
echo "<h2>Failed to Bypass</h2>";
echo "<p>We could not find the confirmation code. Google might be blocking this server's IP.</p>";
echo "<hr>";
echo "<h3>Debug Output (What Google sent back):</h3>";
echo "<textarea style='width:100%; height:400px;'>" . htmlspecialchars($response) . "</textarea>";
?>
