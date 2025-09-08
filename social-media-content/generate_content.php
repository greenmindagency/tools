<?php
require_once __DIR__ . '/session.php';
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$source = $input['source'] ?? '';
$title = $input['title'] ?? '';
$custom = trim($input['prompt'] ?? '');
$existing = trim($input['content'] ?? '');
$apiKey = 'AIzaSyD4GbyZjZjMAvqLJKFruC1_iX07n8u18x0';
$base = "You are an expert social media copywriter. The content is for social media platforms and should include relevant hashtags. Using the following source material:\n" .
        $source . "\nTitle: " . $title . "\n";
if ($custom !== '') {
    if ($existing !== '') {
        $prompt = $base . "Here is the current draft:\n" . $existing . "\n" . $custom;
    } else {
        $prompt = $base . $custom;
    }
} else {
    $prompt = $base . "Write a compelling social media post for the above title. Include 3-5 relevant hashtags.";
}
$prompt .= "\nReturn the post text only.";
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
$content = '';
if ($response !== false) {
    $json = json_decode($response, true);
    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $content = preg_replace('/^```\\w*\\n?|```$/m', '', $text);
}
echo json_encode(['content' => trim($content)]);
