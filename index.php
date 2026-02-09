<?php
//======================================================================
// THIS WORK WELL TO REDICT ON ERROR PAGE WITH OUT ASK TO CHOOSE ACCOUNT
//======================================================================


// SETTINGS
// ----------------------
// Max execution time: Unlimited (for large files)
set_time_limit(0); 
// Turn off error reporting to prevent corrupting the video file
error_reporting(0); 

if (isset($_GET['id'])) {

    // 1. Get the title from the URL (or default to 'video' if not provided)
    $title = isset($_GET['title']) ? $_GET['title'] : 'Video - TheOneMovies.com';

    // 2. SANITIZE THE FILENAME
    $safeTitle = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $title);

    
    $fileId = $_GET['id'];
    $googleUrl = "https://docs.google.com/uc?export=download&id=" . $fileId;

    // 1. COOKIE JAR SETUP
    // We need a place to store cookies so Google thinks we are the same person
    $cookieFile = tempnam(sys_get_temp_dir(), 'gdrive_cookie_');

    // 2. FIRST REQUEST: GET THE FORM
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $googleUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    
    $html = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    // 3. PARSE THE HTML TO FIND THE HIDDEN BUTTON
    $dom = new DOMDocument();
    @$dom->loadHTML($html);

    $downloadForm = $dom->getElementById('download-form');
    $finalUrl = '';

    if ($downloadForm) {
        // If we found the form (Virus Warning Page)
        $action = $downloadForm->getAttribute('action');
        $inputs = $downloadForm->getElementsByTagName('input');
        $params = [];
        
        foreach ($inputs as $input) {
            $params[$input->getAttribute('name')] = $input->getAttribute('value');
        }
        
        // Build the secret link with the UUID and Confirm code
        $finalUrl = $action . '?' . http_build_query($params);
        
    } else {
        // If no form found, maybe it's a direct download (small file)
        // We use the last URL we landed on.
        $finalUrl = $info['url'];
    }

    // 4. FINAL STEP: STREAM THE FILE TO THE USER
    // Instead of redirecting, we pull the data and pass it through.
    
    if ($finalUrl) {
        // Clear any previous output (crucial for video files)
        if (ob_get_level()) ob_end_clean();

        // Set Headers so browser knows it's a download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $safeTitle . ' - TheOneMovies.com.mp4"'); // You can change the name here
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        // Open a stream to the User
        $outputParams = fopen('php://output', 'w');

        $ch = curl_init($finalUrl);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile); // USE SAME COOKIES
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        // This function writes data chunk-by-chunk to the user
        // (Saves RAM so your server doesn't crash)
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use ($outputParams) {
            return fwrite($outputParams, $data);
        });

        curl_exec($ch);
        curl_close($ch);
        fclose($outputParams);
        
        // Clean up
        @unlink($cookieFile);
        exit;
    }

} else {
    header("Location: https://theonemovies.com");
    exit;
}
?>
