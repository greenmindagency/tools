<?php
// === CONFIGURATION ===
$githubUser = 'greenmindagency';
$githubRepo = 'wordprseo';
$branch     = 'main'; // or 'master'
$zipUrl     = "https://github.com/$githubUser/$githubRepo/archive/refs/heads/$branch.zip";

// === Where to extract ===
$themeDir = __DIR__; // Current theme folder

// === TEMP ZIP File Path ===
$tempZip = __DIR__ . '/update.zip';

// === Download the ZIP ===
echo "Downloading update...<br>";
$zipData = file_get_contents($zipUrl);
if (!$zipData) {
    die("Failed to download ZIP.");
}
file_put_contents($tempZip, $zipData);

// === Extract ZIP ===
echo "Extracting...<br>";
$zip = new ZipArchive;
if ($zip->open($tempZip) === TRUE) {
    $extractPath = __DIR__ . '/temp-update';
    $zip->extractTo($extractPath);
    $zip->close();
} else {
    die("Failed to unzip.");
}

// === Copy Files Over ===
echo "Updating files...<br>";
$sourceDir = "$extractPath/$githubRepo-$branch";
recurseCopy($sourceDir, $themeDir);

// === Clean up ===
unlink($tempZip);
deleteFolder($extractPath);

echo "✅ Update complete.";

// === Helpers ===
function recurseCopy($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir($src . '/' . $file)) {
                recurseCopy($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

function deleteFolder($folder) {
    if (!file_exists($folder)) return;
    foreach (scandir($folder) as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = $folder . DIRECTORY_SEPARATOR . $item;
        is_dir($path) ? deleteFolder($path) : unlink($path);
    }
    rmdir($folder);
}
?>
