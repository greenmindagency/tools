<?php
require_once __DIR__ . '/session.php';
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$month = (int)($input['month'] ?? date('n'));
$year = (int)($input['year'] ?? date('Y'));
$countries = $input['countries'] ?? [];
$apiKey = 'AIzaSyD4GbyZjZjMAvqLJKFruC1_iX07n8u18x0';
$monthName = date('F', mktime(0,0,0,$month,1,$year));
$out = [];
foreach ($countries as $country) {
    $prompt = "List all public holidays, religious events and seasonal occasions such as Back to School and Black Friday in $country during $monthName $year. Provide dates in YYYY-MM-DD format and return as JSON array with fields date and name.";
    $payload = json_encode(['contents' => [[ 'parts' => [['text' => $prompt]] ]]]);
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
    if ($response !== false) {
        $json = json_decode($response, true);
        $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $text = preg_replace('/^```\\w*\\n?|```$/m', '', $text);
        $arr = json_decode(trim($text), true);
        if (is_array($arr)) {
            foreach ($arr as $item) {
                if (isset($item['date'], $item['name'])) {
                    $out[] = ['date'=>$item['date'], 'name'=>preg_replace('/[\x{1F300}-\x{1F6FF}\x{1F900}-\x{1F9FF}\x{2600}-\x{27BF}]/u','',$item['name'])];
                }
            }
        }
    }
}
echo json_encode($out);
?>
