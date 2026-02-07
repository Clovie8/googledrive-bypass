<?php
// FILE: index.php
// Usage: https://your-render-app.onrender.com/?id=YOUR_FILE_ID

// 1. Get the File ID from the URL
$fileId = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($fileId)) {
    die("Error: No File ID provided. Usage: /?id=YOUR_GOOGLE_ID");
}

// 2. The Base Google Drive URL
$googleUrl = "https://drive.google.com/uc?export=download&id=" . $fileId;

// 3. Setup cURL to talk to Google
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $googleUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, true); // We need headers to find the cookies

// 4. Get the response from Google
$response = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $header_size);
$body = substr($response, $header_size);
curl_close($ch);

// 5. Look for the "Virus Warning" Cookie or Token
// Google usually sets a cookie like 'download_warning_123=TOKEN'
if (preg_match('/download_warning_[a-zA-Z0-9_]+=([^;]*)/', $headers, $matches)) {
    
    // We found the token!
    $confirmCode = $matches[1];

    // 6. Build the Bypass Link
    $directUrl = "https://drive.google.com/uc?export=download&id=$fileId&confirm=$confirmCode";
    
    // 7. Redirect the user to the download
    header("Location: $directUrl");
    exit;
} 

// 8. If no warning was found (Small file), just redirect to the normal link
header("Location: $googleUrl");
exit;
?>
