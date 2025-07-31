<?php
$output = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["keywords"])) {
    $keywords = trim($_POST["keywords"]);

    // Prepare command
    $descriptorspec = [
        0 => ["pipe", "r"],  // stdin
        1 => ["pipe", "w"],  // stdout
        2 => ["pipe", "w"]   // stderr
    ];

    $process = proc_open("python3 run_cluster.py", $descriptorspec, $pipes);

    if (is_resource($process)) {
        fwrite($pipes[0], $keywords);  // send to stdin
        fclose($pipes[0]);

        $raw = stream_get_contents($pipes[1]);  // get stdout
        fclose($pipes[1]);

        $error = stream_get_contents($pipes[2]);  // get stderr
        fclose($pipes[2]);

        proc_close($process);

        if ($error) {
            $output = "❌ Python Error:\n" . $error;
        } else {
            $clusters = json_decode($raw, true);
            if (is_array($clusters)) {
                $output = "";
                foreach ($clusters as $i => $cluster) {
                    $output .= "Cluster " . ($i+1) . ":\n";
                    foreach ($cluster as $kw) {
                        $output .= "  - " . $kw . "\n";
                    }
                    $output .= "\n";
                }
            } else {
                $output = $raw;
            }
        }
    } else {
        $output = "❌ Could not run Python script.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Keyword Clustering</title>
    <style>
        body { font-family: Arial; padding: 30px; max-width: 800px; margin: auto; }
        textarea { width: 100%; height: 180px; padding: 10px; font-family: monospace; }
        button { padding: 10px 20px; font-size: 16px; }
        pre { background: #f0f0f0; padding: 20px; white-space: pre-wrap; }
    </style>
</head>
<body>
    <h2>🔍 Keyword Clustering Tool</h2>
    <form method="post">
        <label>Enter your keywords (one per line):</label><br><br>
        <textarea name="keywords" required><?php echo isset($_POST["keywords"]) ? htmlspecialchars($_POST["keywords"]) : ""; ?></textarea><br><br>
        <button type="submit">Cluster Keywords</button>
    </form>

    <?php if ($output): ?>
        <h3>✅ Clustering Result:</h3>
        <pre><?php echo htmlspecialchars($output); ?></pre>
    <?php endif; ?>
</body>
</html>
