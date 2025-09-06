<?php
require_once __DIR__ . '/session.php';
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$source = $input['source'] ?? '';
$count = max(0, (int)($input['count'] ?? 0));
$month = $input['month'] ?? '';
$year = $input['year'] ?? '';
$countries = $input['countries'] ?? [];
$countryList = is_array($countries) ? implode(', ', $countries) : '';
$apiKey = 'AIzaSyD4GbyZjZjMAvqLJKFruC1_iX07n8u18x0';
$prompt = "You are an expert social media planner. Using the following source material:\n".
          $source.
          "\nGenerate $count engaging social media post titles for $month $year".
          ($countryList ? " targeting the following countries: $countryList" : "") .
          ". Return the titles as a JSON array.";
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
$titles = [];
if ($response !== false) {
    $json = json_decode($response, true);
    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $text = preg_replace('/^```\\w*\\n?|```$/m', '', $text);
    $arr = json_decode(trim($text), true);
    if (is_array($arr)) {
        foreach ($arr as $item) {
            if (is_array($item)) {
                if (isset($item['title'])) {
                    $titles[] = trim((string)$item['title']);
                } else {
                    $val = $item[0] ?? implode(' ', $item);
                    $titles[] = trim((string)$val);
                }
            } else {
                $titles[] = trim((string)$item);
            }
        }
    } else {
        $titles = array_filter(array_map('trim', explode("\n", $text)));
    }
}
echo json_encode(['titles' => $titles]);
