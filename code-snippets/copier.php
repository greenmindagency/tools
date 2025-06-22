<?php
// Define the WordPress latest zip URL
$wordpress_url = "https://wordpress.org/latest.zip";

// Define the local file name (same directory as the script)
$local_file = __DIR__ . "/latest.zip";

// Initialize cURL session
$ch = curl_init($wordpress_url);

// Open file for writing
$fp = fopen($local_file, "w");

// Set cURL options
curl_setopt($ch, CURLOPT_FILE, $fp);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 300); // Timeout after 5 minutes

// Execute cURL request
curl_exec($ch);

// Check for errors
if (curl_errno($ch)) {
    echo "cURL error: " . curl_error($ch);
} else {
    echo "WordPress latest.zip downloaded successfully!";
}

// Close cURL session and file
curl_close($ch);
fclose($fp);
?>
