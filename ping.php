<?php
// FILE: ping.php
// Purpose: Keep Render awake
http_response_code(200); // Force 200 OK
echo "Pong!";
?>
