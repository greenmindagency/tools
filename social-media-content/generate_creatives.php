<?php
require_once __DIR__ . '/session.php';
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$source = $input['source'] ?? '';
$title = $input['title'] ?? '';
$apiKey = 'AIzaSyD4GbyZjZjMAvqLJKFruC1_iX07n8u18x0';
$prompt = "You are a creative strategist. Using the following source material and post title, suggest three short keywords for searching Pinterest for design inspiration.\nSource:\n$source\nTitle: $title\nReturn only the keywords separated by commas.";
$payload = json_encode([
    'contents' => [[ 'parts' => [['text' => $prompt]] ]]
]);
$ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-goog-api-key: ' . $apiKey,
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
$response = curl_exec($ch);
curl_close($ch);
$keywords = [];
if ($response !== false) {
    $json = json_decode($response, true);
    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $text = preg_replace('/^```\\w*\\n?|```$/m', '', $text);
    $keywords = array_filter(array_map('trim', preg_split('/[,\n]/', $text)));
    $keywords = array_slice($keywords, 0, 3);
}
echo json_encode(['keywords' => $keywords]);
?>
