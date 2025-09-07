<?php
require_once __DIR__ . '/session.php';
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$month = (int)($input['month'] ?? date('n'));
$year = (int)($input['year'] ?? date('Y'));
$countries = $input['countries'] ?? [];
$apiKey = 'AIzaSyD4GbyZjZjMAvqLJKFruC1_iX07n8u18x0';
$monthName = date('F', mktime(0,0,0,$month,1,$year));
$eventMap = [];
foreach ($countries as $country) {
    // Retry a few times if Gemini returns dates from the wrong year
    $attempts = 0;
    $valid = [];
    while ($attempts < 3 && !$valid) {
        $suffix = $attempts ? " Only include events that occur in $year." : '';
        $prompt = "List all public holidays, religious events and seasonal occasions such as Back to School and Black Friday in $country during $monthName $year. Provide dates in YYYY-MM-DD format and return as JSON array with fields date and name.$suffix";
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
                    if (!isset($item['date'], $item['name'])) continue;
                    $dt = $item['date'];
                    if (substr($dt,0,4) != $year) { $valid = []; break; }
                    $name = preg_replace('/[\x{1F300}-\x{1F6FF}\x{1F900}-\x{1F9FF}\x{2600}-\x{27BF}]/u','',$item['name']);
                    $valid[] = ['date'=>$dt,'name'=>$name];
                }
            }
        }
        $attempts++;
    }
    foreach ($valid as $item) {
        $key = $item['date'].'|'.$item['name'];
        if (!isset($eventMap[$key])) {
            $eventMap[$key] = ['date'=>$item['date'],'name'=>$item['name'],'countries'=>[]];
        }
        $eventMap[$key]['countries'][] = $country;
    }
}

$all = [];
$perCountry = array_fill_keys($countries, []);
$total = count($countries);
foreach ($eventMap as $ev) {
    if (count($ev['countries']) === $total) {
        $all[] = ['date'=>$ev['date'],'name'=>$ev['name']];
    } else {
        foreach ($ev['countries'] as $c) {
            $perCountry[$c][] = ['date'=>$ev['date'],'name'=>$ev['name']];
        }
    }
}
usort($all, fn($a,$b)=>strcmp($a['date'],$b['date']));
foreach ($perCountry as &$list) {
    usort($list, fn($a,$b)=>strcmp($a['date'],$b['date']));
}
echo json_encode(['all'=>$all,'countries'=>$perCountry]);
?>
