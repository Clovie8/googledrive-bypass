<?php
// FILE: index.php (Version 2.0 - Stronger Cookie Finder)

$fileId = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($fileId)) {
    die("Error: No File ID provided.");
}

// 1. Setup the Google URL
$googleUrl = "https://drive.google.com/uc?export=download&id=" . $fileId;

// 2. Initialize cURL with "Browser" settings
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $googleUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, true); // We need headers

// TRICK: Act like a real browser so Google sends the cookies
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

$response = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $header_size);
curl_close($ch);

// 3. The "Smart" Search
// We loop through EVERY header to find the specific "download_warning" cookie
$confirmCode = "";

// Split headers into lines
$headerLines = explode("\r\n", $headers);

foreach ($headerLines as $line) {
    // Look for "Set-Cookie" lines
    if (stripos($line, 'Set-Cookie') !== false) {
        // Look for the magic words "download_warning"
        if (preg_match('/download_warning[^=]*=([^;]*)/', $line, $matches)) {
            $confirmCode = $matches[1];
            break; // Found it! Stop looking.
        }
    }
}

// 4. The Decision
if ($confirmCode) {
    // SUCCESS: We found the code. Add it to the link.
    $directUrl = "https://drive.google.com/uc?export=download&id=$fileId&confirm=$confirmCode";
    header("Location: $directUrl");
    exit;
} else {
    // FAILURE: We didn't find the code.
    // Instead of sending you to the warning page, let's print an error so we know what happened.
    // (You can change this back to a redirect later if you want).
    echo "<h2>Error: Could not bypass Google Virus Warning.</h2>";
    echo "<p>Google might be blocking the server IP or the file is too restricted.</p>";
    echo "<p><b>Debug Info:</b> Check if the file is 'Public' on Google Drive.</p>";
    exit;
}
?>
