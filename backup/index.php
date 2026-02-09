<?php
// download.php
// A lightweight script to find the "hidden" download link and redirect the user.
// This runs in 1-2 seconds, so it works on ALL shared hosting.

if (isset($_GET['id'])) {
    $fileId = $_GET['id'];
    
    // 1. Get the final storage URL (googleusercontent.com)
    $finalLink = getFinalLink($fileId);
    
    // 2. Redirect the user to that URL
    // This URL usually bypasses the Mobile App because it's not "drive.google.com"
    header("Location: " . $finalLink);
    exit;
} else {
    header("Location: https://theonemovies.com");
    exit;
}

function getFinalLink($id) {
    $url = "https://drive.google.com/uc?export=download&id=" . $id;

    // We need a temp file for cookies
    $cookieFile = tempnam(sys_get_temp_dir(), 'gdrive_cookie');

    // --- STEP 1: Hit the Main Link ---
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true); // We need headers
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Do NOT follow redirects yet
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    curl_close($ch);

    // --- STEP 2: Check for Virus Warning ---
    if (preg_match('/confirm=([0-9A-Za-z_]+)/', $body, $matches)) {
        $token = $matches[1];
        // We found a warning! We need to confirm it.
        $url = "https://drive.google.com/uc?export=download&id=" . $id . "&confirm=" . $token;
        
        // Prepare to hit the confirmation link
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Do NOT follow yet, we want the Location header
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        
        $response = curl_exec($ch);
        $headers = $response; // In this case, we only care about headers
        curl_close($ch);
    }

    // --- STEP 3: Extract the Final "Location" ---
    // Google returns a "302 Redirect" to the storage server (googleusercontent.com)
    // We want to grab that URL and give it to the user.
    if (preg_match('/Location: (.*)/i', $headers, $matches)) {
        $finalLocation = trim($matches[1]);
        
        // Clean up
        if (file_exists($cookieFile)) unlink($cookieFile);
        
        return $finalLocation;
    }

    // Fallback: If we couldn't find a redirect, just send them to the basic link
    if (file_exists($cookieFile)) unlink($cookieFile);
    return "https://drive.google.com/uc?export=download&id=" . $id;
}
?>
