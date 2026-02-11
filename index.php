<?php
//======================================================================
// GDrive Proxy Downloader - Bypasses Virus Scan & Supports Resume/ETA
//======================================================================

// Max execution time: Unlimited (for large video files)
set_time_limit(0);
// Turn off standard error reporting to prevent corrupting the stream, 
// but ensure fatal errors are logged internally on AWS.
ini_set('display_errors', 0);
ini_set('log_errors', 1);

class GDriveProxy {
    private $fileId;
    private $safeTitle;
    private $cookieFile;
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    public function __construct($fileId, $title) {
        $this->fileId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $fileId);
        
        $title = $title ?: 'Video';
        $this->safeTitle = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $title) . ' - TheOneMovies.com.mp4';
        
        // Setup secure temp file for cookies
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'gdrive_cookie_');
    }

    /**
     * Destructor guarantees the cookie file is deleted when the script finishes or aborts.
     */
    public function __destruct() {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function process() {
        try {
            $finalUrl = $this->getDirectDownloadUrl();
            if (!$finalUrl) {
                throw new Exception("Could not resolve Google Drive download URL.");
            }
            $this->streamToClient($finalUrl);
        } catch (Exception $e) {
            error_log("Download Proxy Error: " . $e->getMessage());
            header("HTTP/1.1 500 Internal Server Error");
            exit;
        }
    }

    /**
     * Handles the initial request and bypasses the virus warning page.
     */
    private function getDirectDownloadUrl() {
        $googleUrl = "https://docs.google.com/uc?export=download&id=" . $this->fileId;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $googleUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_USERAGENT => $this->userAgent
        ]);

        $html = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if (!$html) return false;

        // Parse DOM quietly (suppress HTML5 warnings)
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        libxml_clear_errors();

        $downloadForm = $dom->getElementById('download-form');
        if ($downloadForm) {
            $action = $downloadForm->getAttribute('action');
            $inputs = $downloadForm->getElementsByTagName('input');
            $params = [];
            
            foreach ($inputs as $input) {
                $params[$input->getAttribute('name')] = $input->getAttribute('value');
            }
            return $action . '?' . http_build_query($params);
        }

        return $info['url'];
    }

    /**
     * Streams the file while passing Range and Content-Length headers.
     */
    private function streamToClient($url) {
        if (ob_get_level()) ob_end_clean();

        $ch = curl_init($url);
        
        // Base headers required for downloading
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $this->safeTitle . '"');
        header('Accept-Ranges: bytes'); // Crucial: Tells the browser it can pause/resume
        header('Cache-Control: public, max-age=0');
        header('Pragma: public');

        // 1. Check if the user is resuming a download
        $isRangeRequest = false;
        if (isset($_SERVER['HTTP_RANGE'])) {
            $isRangeRequest = true;
            $range = $_SERVER['HTTP_RANGE'];
            
            // Tell Google Drive to only send the remaining bytes
            curl_setopt($ch, CURLOPT_RANGE, str_replace('bytes=', '', $range));
            http_response_code(206); // 206 Partial Content indicates a resumed download
        } else {
            http_response_code(200); // Standard full download
        }

        curl_setopt_array($ch, [
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_BUFFERSIZE => 1024 * 1024, // 1MB chunk size: optimizes AWS memory usage and throughput
            CURLOPT_RETURNTRANSFER => false, 
        ]);

        // 2. Intercept Google Drive's headers and pass them to the user's browser
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) {
            $len = strlen($header);
            $headerParts = explode(':', $header, 2);
            
            if (count($headerParts) >= 2) {
                $name = strtolower(trim($headerParts[0]));
                $value = trim($headerParts[1]);

                // Content-Length triggers the ETA and file size display in the browser.
                // Content-Range tells the browser exactly what part of the file is arriving.
                if (in_array($name, ['content-length', 'content-range'])) {
                    header("$name: $value");
                }
            }
            return $len;
        });

        // 3. Stream data securely
        $outputParams = fopen('php://output', 'w');
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) use ($outputParams) {
            $writeStatus = fwrite($outputParams, $data);
            
            // If the user cancels the download, immediately abort cURL to save AWS bandwidth
            if ($writeStatus === false) {
                return 0; 
            }
            
            fflush($outputParams); // Push chunk to user immediately
            return strlen($data);
        });

        curl_exec($ch);
        
        if (curl_errno($ch)) {
            error_log("cURL Stream Error: " . curl_error($ch));
        }
        
        curl_close($ch);
        fclose($outputParams);
    }
}

// --- Trigger ---
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $title = isset($_GET['title']) ? $_GET['title'] : '';
    
    $downloader = new GDriveProxy($_GET['id'], $title);
    $downloader->process();
} else {
    header("Location: https://theonemovies.com");
    exit;
}
?>
