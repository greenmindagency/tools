<?php
session_start();
require __DIR__ . '/config.php';
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
// ensure table for page contents exists
$pdo->exec("CREATE TABLE IF NOT EXISTS client_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    page VARCHAR(255) NOT NULL,
    content LONGTEXT,
    UNIQUE KEY client_page (client_id, page)
)");

$client_id = (int)($_GET['client_id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$client) {
    header('Location: index.php');
    exit;
}
$stmt = $pdo->prepare('SELECT page, content FROM client_pages WHERE client_id = ?');
$stmt->execute([$client_id]);
$pageData = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $pageData[$row['page']] = json_decode($row['content'], true) ?: [];
}
$stmt = $pdo->prepare('SELECT page, structure FROM client_structures WHERE client_id = ?');
$stmt->execute([$client_id]);
$pageStructures = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $arr = json_decode($row['structure'], true) ?: [];
    $pageStructures[$row['page']] = array_values($arr);
}
$error = '';
$saved = '';
$generated = '';
$openPage = '';
$sitemap = $client['sitemap'] ? json_decode($client['sitemap'], true) : [];

// Placeholder instructions for each supported section.
$sectionInstructions = [
    'accordion' => 'Full-width section with a title, subtitle, and a 3-panel accordion (one open at a time) showing brief text or bullet points per panel.',
'articleimages' => 'Single row of 4–6 equal-width logo/image blocks with consistent sizing and no text.',
'articletitle' => 'Full-width section with a main title and a short subtitle directly beneath it.',
'articlevideo' => 'Full-width embedded video (optional short heading above) in a responsive container.',
'articlevideogallery' => 'Two-column layout displaying 2–4 equal-sized embedded videos side by side.',
'catconnection' => 'Two-column layout with a full-height image and category-loaded title (3–5 words) plus subtitle (4–8 words).',
'postconnection' => 'Two-column layout with a full-height image and selected post title (3–5 words) plus subtitle (4–8 words).',
'tagconnection' => 'Two-column layout with a full-height image and selected tag title (3–5 words) plus subtitle (4–8 words).',
'contacts' => 'Two-column contact block: left shows locations with phone/address icons, right shows a CF7 form, with heading and brief intro.',
'herovideo' => 'Full-width background video with large title, short subtitle, and 1–2 CTAs (use stock loops like “city timelapse” or “abstract bokeh” on YouTube).',
'imagesslider' => 'Single-row carousel showing 4–6 logos/images per view with left/right arrows.',
'imgcarousel' => 'Full-width rotating gallery showing one large image at a time with dot/arrow navigation.',
'pagecontent1' => 'Section with heading and subheading followed by three columns each with a Font Awesome icon, 2–3-word title, and 8–10-word description.',
'pagecontent2' => 'Three equal columns showing a Font Awesome icon, a statistic number, and a 2–3-word label for achievements.',
'pagecontent3' => 'a 2 columns section at the left a 6 words title and 10-15 words subtitle, with a full description content it can accept a pagraph and a bullet points, make it more descriptive.',
'pagecontent4' => 'Team/feature loop in two columns, please make a title (3–5 words) plus subtitle (5–10 words), after that we will have the team loop image in left of each team member, and in right name of the team member, below it is the title, then 1–2 short paragraphs about the team member, at the end of all the team members loop  you can add something like Message From .. or anything else related to the team, it optional to have the end description based on content',
'pagecontent5' => 'Single-column closing block with title, subtitle, and 1–2 call-to-action buttons (form/lead capture).',
'pagecontent6' => 'Single-column map section with a 5–6-word title and a 15–20-word description above the map.',
'pagecontent7' => 'Two-column success section: left has 5–6-word title and 15–20-word copy; right lists three items each with a title and percentage.',
'pagecontent8' => 'Two-column block with full-height image left and right column showing title, subtitle, 3-panel accordion with 20–35-word content, brief summary, and one CTA.',
'pagecontent9' => 'Full-width header (heading + subtitle) above a three-card grid, each with image, 2–4-word title, 10–20-word text, CTA, and hidden expandable content.',
'postsrelatedcat' => 'Three-column grid of posts from a selected category with heading/subtitle and a “View all” button linking to the category.',
'postsrelatedcatslider' => 'Two-column-width layout featuring a single-card carousel of posts from the selected category with heading and subtitle.',
'postsrelatedwithfilter' => 'Full-width layout with heading/subtitle and tag filter buttons (e.g., News, Tips, Case Studies, Events) above the posts grid.',
'slider' => 'Full-width image slider with overlay 4–6-word title, and 6–10-word subtitle, minmum 3 slides and maxmuim 6 slides.',
'tagslist' => 'Two-row, two-column grid with heading/subtitle listing selected tags from admin as service items.',
'testimonial' => 'Full-width testimonials section with heading/subtitle that displays a chosen number of entries from single testimonial pages.',
'verticaltabs' => 'starting with 4–6-word title, and 6–10-word subtitle, this section is a loop of 4 or 5 tabs, 4 to 7 words titles and 5 to 10 subtitles with description for each tab'
];

// Instructions for meta fields.
$metaInstructions = [
    'meta_title' => 'SEO-optimized title related to the page name, maximum 60 characters, dont add the client name in the title, dont cut off the content to follow the characters limit, make the content has a meaning.',
    'meta_description' => 'SEO-optimized description related to the page name, 110-140 characters, dont cut off the content to follow the characters limit, make the content has a meaning.',
    'slug' => 'URL-friendly, lowercase, hyphen-separated slug tied to the page name.'
];

function sectionInstr(array $sections): string {
    global $sectionInstructions;
    $result = [];
    foreach ($sections as $s) {
        $key = strtolower($s);
        if (isset($sectionInstructions[$key])) {
            $result[] = "Section Name: {$s}\n" . $sectionInstructions[$key];
        }
    }
    return implode("\n", $result);
}

function metaInstr(array $fields): string {
    global $metaInstructions;
    $out = [];
    foreach ($fields as $f) {
        if (isset($metaInstructions[$f])) {
            $label = ucwords(str_replace('_', ' ', $f));
            $out[] = "$label: {$metaInstructions[$f]}";
        }
    }
    return implode("\n", $out);
}

function slugify(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

function suggestMedia(string $html): array {
    $apiKey = 'AIzaSyD4GbyZjZjMAvqLJKFruC1_iX07n8u18x0';
    $prompt = "Analyze the following HTML section and suggest relevant media.\n{$html}\nReturn JSON with keys 'icons', 'images', and 'videos'. 'icons' should list three Font Awesome icon class names, 'images' three 2-3 word stock photo keywords, and 'videos' three 2-3 word stock footage keywords. Avoid duplicates and relate suggestions to the content. Return JSON only.";
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
    $out = ['icons' => [], 'images' => [], 'videos' => []];
    if ($response !== false) {
        $json = json_decode($response, true);
        $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $text = preg_replace('/^```\w*\n?|```$/m', '', $text);
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end >= $start) {
            $text = substr($text, $start, $end - $start + 1);
            $res = json_decode($text, true);
            if (is_array($res)) {
                $out['icons'] = $res['icons'] ?? [];
                $out['images'] = $res['images'] ?? [];
                $out['videos'] = $res['videos'] ?? [];
            }
        }
    }
    curl_close($ch);
    return $out;
}

if ($sitemap) {
    $convert = function (&$items) use (&$convert) {
        foreach ($items as &$item) {
            if (($item['type'] ?? '') === 'cat') $item['type'] = 'category';
            if (!empty($item['children'])) $convert($item['children']);
        }
    };
    $convert($sitemap);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = isset($_POST['ajax']);
    if (isset($_POST['generate_page'])) {
        $page = $_POST['page'] ?? '';
        $openPage = $page;
        $sections = $pageStructures[$page] ?? [];
        $sectionInstr = sectionInstr($sections);
        $metaInstr = metaInstr(['meta_title','meta_description','slug']);
        $apiKey = 'AIzaSyD4GbyZjZjMAvqLJKFruC1_iX07n8u18x0';
        $sectionList = implode(', ', $sections);
        $prompt = "Using the following source text:\n{$client['core_text']}\n\nPage name: {$page}\nSections: {$sectionList}\n\nSection instructions:\n{$sectionInstr}\nMeta instructions:\n{$metaInstr}\nGenerate JSON with keys: meta_title, meta_description, slug, and sections (object mapping section name to HTML content using only <h3>, <h4>, and <p> tags). Provide non-empty content for every listed section. If unsure, add a brief placeholder paragraph. Return JSON only.";
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
        if ($response === false) {
            $error = 'API request failed: ' . curl_error($ch);
        } else {
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $json = json_decode($response, true);
            if ($code >= 400 || isset($json['error'])) {
                $msg = $json['error']['message'] ?? $response;
                $error = 'API error: ' . $msg;
            } elseif (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                $text = $json['candidates'][0]['content']['parts'][0]['text'];
                $text = preg_replace('/^```\w*\n?|```$/m', '', $text);
                $start = strpos($text, '{');
                $end = strrpos($text, '}');
                if ($start !== false && $end !== false && $end >= $start) {
                    $text = substr($text, $start, $end - $start + 1);
                }
                $res = json_decode($text, true);
                if ($res) {
                    $sectionContentRaw = $res['sections'] ?? [];
                    $normalized = [];
                    foreach ($sectionContentRaw as $k => $v) {
                        $normalized[strtolower($k)] = $v;
                    }
                    $sectionContent = [];
                    foreach ($sections as $sec) {
                        $key = strtolower($sec);
                        $content = $normalized[$key] ?? '';
                        if (is_array($content)) {
                            $content = $content['content'] ?? '';
                        }
                        if (!is_string($content) || !trim(strip_tags($content))) {
                            $content = '<p>Content pending...</p>';
                        }
                        $sectionContent[$sec] = $content;
                    }
                    $title = trim($res['meta_title'] ?? '');
                    $desc  = trim($res['meta_description'] ?? '');
                    $slug  = slugify($res['slug'] ?? '');
                    $sectionMedia = [];
                    foreach ($sectionContent as $secName => $html) {
                        $sectionMedia[$secName] = suggestMedia($html);
                    }
                    $pageData[$page] = [
                        'meta_title' => $title,
                        'meta_description' => $desc,
                        'slug' => $slug,
                        'sections' => $sectionContent,
                        'media' => $sectionMedia
                    ];
                    if (mb_strlen($desc) < 110 || mb_strlen($desc) > 140) {
                        $generated = 'Content generated, description outside 110-140 chars. Review before saving.';
                    } else {
                        $generated = 'Content generated. Review before saving.';
                    }
                } else {
                    $error = 'Failed to parse generated content.';
                }
            } else {
                $error = 'Unexpected API response.';
            }
        }
        curl_close($ch);
    } elseif (isset($_POST['generate_meta_title'])) {
        $page = $_POST['page'] ?? '';
        $openPage = $page;
        $apiKey = 'AIzaSyD4GbyZjZjMAvqLJKFruC1_iX07n8u18x0';
        $metaInstr = metaInstr(['meta_title']);
        $prompt = "Using the following source text:\n{$client['core_text']}\n\nPage name: {$page}\nMeta instructions:\n{$metaInstr}\nReturn JSON with key meta_title only.";
        $current = trim($_POST['current'] ?? ($pageData[$page]['meta_title'] ?? ''));
        if ($current !== '') {
            $prompt .= "\nCurrent meta title:\n{$current}";
        }
        $userPrompt = trim($_POST['prompt'] ?? '');
        if ($userPrompt !== '') {
            $prompt .= "\nAdditional instructions:\n{$userPrompt}";
        }
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
        if ($response === false) {
            $error = 'API request failed: ' . curl_error($ch);
        } else {
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $json = json_decode($response, true);
            if ($code >= 400 || isset($json['error'])) {
                $msg = $json['error']['message'] ?? $response;
                $error = 'API error: ' . $msg;
            } elseif (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                $text = $json['candidates'][0]['content']['parts'][0]['text'];
                $text = preg_replace('/^```\w*\n?|```$/m', '', $text);
                $start = strpos($text, '{');
                $end = strrpos($text, '}');
                if ($start !== false && $end !== false && $end >= $start) {
                    $text = substr($text, $start, $end - $start + 1);
                }
                $res = json_decode($text, true);
                if ($res) {
                    $title = trim($res['meta_title'] ?? '');
                    if (!isset($pageData[$page])) {
                        $pageData[$page] = ['meta_title' => '', 'meta_description' => '', 'slug' => '', 'sections' => []];
                    }
                    $pageData[$page]['meta_title'] = $title;
                    $generated = 'Meta title regenerated.';
                } else {
                    $error = 'Failed to parse generated meta title.';
                }
            } else {
                $error = 'Unexpected API response.';
            }
        }
        curl_close($ch);
        if ($isAjax) {
            header('Content-Type: application/json');
            if ($error) {
                echo json_encode(['error' => $error]);
            } else {
                echo json_encode(['meta_title' => $title]);
            }
            exit;
        }
    } elseif (isset($_POST['generate_meta_description'])) {
        $page = $_POST['page'] ?? '';
        $openPage = $page;
        $apiKey = 'AIzaSyD4GbyZjZjMAvqLJKFruC1_iX07n8u18x0';
        $metaInstr = metaInstr(['meta_description']);
        $prompt = "Using the following source text:\n{$client['core_text']}\n\nPage name: {$page}\nMeta instructions:\n{$metaInstr}\nReturn JSON with key meta_description only.";
        $current = trim($_POST['current'] ?? ($pageData[$page]['meta_description'] ?? ''));
        if ($current !== '') {
            $prompt .= "\nCurrent meta description:\n{$current}";
        }
        $userPrompt = trim($_POST['prompt'] ?? '');
        if ($userPrompt !== '') {
            $prompt .= "\nAdditional instructions:\n{$userPrompt}";
        }
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
        if ($response === false) {
            $error = 'API request failed: ' . curl_error($ch);
        } else {
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $json = json_decode($response, true);
            if ($code >= 400 || isset($json['error'])) {
                $msg = $json['error']['message'] ?? $response;
                $error = 'API error: ' . $msg;
            } elseif (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                $text = $json['candidates'][0]['content']['parts'][0]['text'];
                $text = preg_replace('/^```\w*\n?|```$/m', '', $text);
                $start = strpos($text, '{');
                $end = strrpos($text, '}');
                if ($start !== false && $end !== false && $end >= $start) {
                    $text = substr($text, $start, $end - $start + 1);
                }
                $res = json_decode($text, true);
                if ($res) {
                    $desc = trim($res['meta_description'] ?? '');
                    if (!isset($pageData[$page])) {
                        $pageData[$page] = ['meta_title' => '', 'meta_description' => '', 'slug' => '', 'sections' => []];
                    }
                    $pageData[$page]['meta_description'] = $desc;
                    if (mb_strlen($desc) < 110 || mb_strlen($desc) > 140) {
                        $generated = 'Meta description regenerated outside 110-140 chars.';
                    } else {
                        $generated = 'Meta description regenerated.';
                    }
                } else {
                    $error = 'Failed to parse generated meta description.';
                }
            } else {
                $error = 'Unexpected API response.';
            }
        }
        curl_close($ch);
        if ($isAjax) {
            header('Content-Type: application/json');
            if ($error) {
                echo json_encode(['error' => $error]);
            } else {
                echo json_encode(['meta_description' => $desc]);
            }
            exit;
        }
    } elseif (isset($_POST['generate_slug'])) {
        $page = $_POST['page'] ?? '';
        $openPage = $page;
        $apiKey = 'AIzaSyD4GbyZjZjMAvqLJKFruC1_iX07n8u18x0';
        $metaInstr = metaInstr(['slug']);
        $prompt = "Using the following source text:\n{$client['core_text']}\n\nPage name: {$page}\nMeta instructions:\n{$metaInstr}\nReturn JSON with key slug only.";
        $current = trim($_POST['current'] ?? ($pageData[$page]['slug'] ?? ''));
        if ($current !== '') {
            $prompt .= "\nCurrent slug:\n{$current}";
        }
        $userPrompt = trim($_POST['prompt'] ?? '');
        if ($userPrompt !== '') {
            $prompt .= "\nAdditional instructions:\n{$userPrompt}";
        }
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
        if ($response === false) {
            $error = 'API request failed: ' . curl_error($ch);
        } else {
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $json = json_decode($response, true);
            if ($code >= 400 || isset($json['error'])) {
                $msg = $json['error']['message'] ?? $response;
                $error = 'API error: ' . $msg;
            } elseif (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                $text = $json['candidates'][0]['content']['parts'][0]['text'];
                $text = preg_replace('/^```\w*\n?|```$/m', '', $text);
                $start = strpos($text, '{');
                $end = strrpos($text, '}');
                if ($start !== false && $end !== false && $end >= $start) {
                    $text = substr($text, $start, $end - $start + 1);
                }
                $res = json_decode($text, true);
                if ($res) {
                    $slug = slugify($res['slug'] ?? '');
                    if (!isset($pageData[$page])) {
                        $pageData[$page] = ['meta_title' => '', 'meta_description' => '', 'slug' => '', 'sections' => []];
                    }
                    $pageData[$page]['slug'] = $slug;
                    $generated = 'Slug regenerated.';
                } else {
                    $error = 'Failed to parse generated slug.';
                }
            } else {
                $error = 'Unexpected API response.';
            }
        }
        curl_close($ch);
        if ($isAjax) {
            header('Content-Type: application/json');
            if ($error) {
                echo json_encode(['error' => $error]);
            } else {
                echo json_encode(['slug' => $slug]);
            }
            exit;
        }
    } elseif (isset($_POST['generate_section'])) {
        $page = $_POST['page'] ?? '';
        $section = $_POST['section'] ?? '';
        $openPage = $page;
        $sectionInstr = sectionInstr([$section]);
        $apiKey = 'AIzaSyD4GbyZjZjMAvqLJKFruC1_iX07n8u18x0';
        $prompt = "Using the following source text:\n{$client['core_text']}\n\nPage name: {$page}\nSection: {$section}\n\nInstructions:\n{$sectionInstr}\nGenerate JSON with key 'content' containing HTML for the section using only <h3>, <h4>, and <p> tags. Provide non-empty content. Return JSON only.";
        $current = trim($_POST['current'] ?? ($pageData[$page]['sections'][$section] ?? ''));
        if ($current !== '') {
            $prompt .= "\nCurrent content:\n{$current}";
        }
        $userPrompt = trim($_POST['prompt'] ?? '');
        if ($userPrompt !== '') {
            $prompt .= "\nAdditional instructions:\n{$userPrompt}";
        }
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
        if ($response === false) {
            $error = 'API request failed: ' . curl_error($ch);
        } else {
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $json = json_decode($response, true);
            if ($code >= 400 || isset($json['error'])) {
                $msg = $json['error']['message'] ?? $response;
                $error = 'API error: ' . $msg;
            } elseif (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                $text = $json['candidates'][0]['content']['parts'][0]['text'];
                $text = preg_replace('/^```\w*\n?|```$/m', '', $text);
                $start = strpos($text, '{');
                $end = strrpos($text, '}');
                if ($start !== false && $end !== false && $end >= $start) {
                    $text = substr($text, $start, $end - $start + 1);
                }
                $res = json_decode($text, true);
                if ($res && !empty($res['content'])) {
                    $content = $res['content'];
                    if (is_array($content)) {
                        $content = $content['content'] ?? '';
                    }
                    if (!isset($pageData[$page])) {
                        $pageData[$page] = ['meta_title' => '', 'meta_description' => '', 'slug' => '', 'sections' => [], 'media' => []];
                    }
                    $pageData[$page]['sections'][$section] = $content;
                    $media = suggestMedia($content);
                    $pageData[$page]['media'][$section] = $media;
                    $generated = 'Section regenerated.';
                } else {
                    $error = 'Failed to parse generated section.';
                }
            } else {
                $error = 'Unexpected API response.';
            }
        }
        curl_close($ch);
        if ($isAjax) {
            header('Content-Type: application/json');
            if ($error) {
                echo json_encode(['error' => $error]);
            } else {
                echo json_encode(['content' => $content, 'media' => $media]);
            }
            exit;
        }
    } elseif (isset($_POST['save_meta'])) {
        $page = $_POST['page'] ?? '';
        $openPage = $page;
        $mt = trim($_POST['meta_title'] ?? '');
        $md = trim($_POST['meta_description'] ?? '');
        $sl = slugify($_POST['slug'] ?? '');
        $data = $pageData[$page] ?? ['meta_title' => '', 'meta_description' => '', 'slug' => '', 'sections' => [], 'media' => []];
        $data['meta_title'] = $mt;
        $data['meta_description'] = $md;
        $data['slug'] = $sl;
        $contentJson = json_encode($data);
        $stmt = $pdo->prepare('INSERT INTO client_pages (client_id, page, content) VALUES (?,?,?) ON DUPLICATE KEY UPDATE content=VALUES(content)');
        $stmt->execute([$client_id, $page, $contentJson]);
        $pageData[$page] = $data;
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'saved']);
            exit;
        } else {
            $saved = 'Meta saved.';
        }
    } elseif (isset($_POST['save_section'])) {
        $page = $_POST['page'] ?? '';
        $section = $_POST['section'] ?? '';
        $content = $_POST['content'] ?? '';
        $media = $_POST['media'] ?? '';
        $openPage = $page;
        $data = $pageData[$page] ?? ['meta_title' => '', 'meta_description' => '', 'slug' => '', 'sections' => [], 'media' => []];
        $data['sections'][$section] = $content;
        $mediaArr = json_decode($media, true) ?: [];
        $data['media'][$section] = $mediaArr;
        $contentJson = json_encode($data);
        $stmt = $pdo->prepare('INSERT INTO client_pages (client_id, page, content) VALUES (?,?,?) ON DUPLICATE KEY UPDATE content=VALUES(content)');
        $stmt->execute([$client_id, $page, $contentJson]);
        $pageData[$page] = $data;
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'saved']);
            exit;
        } else {
            $saved = 'Section saved.';
        }
    } elseif (isset($_POST['save_page'])) {
        $page = $_POST['page'] ?? '';
        $openPage = $page;
        $content = $_POST['page_content'] ?? '';
        $data = json_decode($content, true) ?: [];
        if (isset($data['meta_title'])) {
            $data['meta_title'] = trim($data['meta_title']);
        }
        if (isset($data['meta_description'])) {
            $data['meta_description'] = trim($data['meta_description']);
        }
        if (isset($data['slug'])) {
            $data['slug'] = slugify($data['slug']);
        }
        $content = json_encode($data);
        $stmt = $pdo->prepare('INSERT INTO client_pages (client_id, page, content) VALUES (?,?,?) ON DUPLICATE KEY UPDATE content=VALUES(content)');
        $stmt->execute([$client_id, $page, $content]);
        $pageData[$page] = $data;
        $saved = 'Page content saved.';
    }
}


$title = 'Wordprseo Content Builder';
require __DIR__ . '/../header.php';
?>
<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link" href="sitemap.php?client_id=<?= $client_id ?>&tab=source">Source</a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="sitemap.php?client_id=<?= $client_id ?>&tab=sitemap">Site Map</a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="structure.php?client_id=<?= $client_id ?>">Structure</a>
  </li>
  <li class="nav-item">
    <a class="nav-link active" href="#">Content</a>
  </li>
</ul>
<?php if ($saved): ?><div class="alert alert-success"><?= htmlspecialchars($saved) ?></div><?php endif; ?>
<?php if ($generated): ?><div class="alert alert-info"><?= htmlspecialchars($generated) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php
function flattenPages(array $items, array &$list, int $level = 0) {
    foreach ($items as $it) {
        $list[] = [
            'title' => $it['title'],
            'type' => $it['type'] ?? 'page',
            'level' => $level
        ];
        if (!empty($it['children'])) flattenPages($it['children'], $list, $level + 1);
    }
}
$pages = [];
flattenPages($sitemap, $pages);
?>
<div class="row">
  <div class="col-md-4">
    <ul class="list-group position-sticky" style="top: 70px;">
      <?php foreach ($pages as $p): ?>
      <?php $paddingClass = $p['level'] > 0 ? 'ps-4' : 'ps-2'; ?>
      <li class="list-group-item d-flex justify-content-between align-items-center mb-2 <?= $paddingClass ?> page-item<?= ($openPage === $p['title']) ? ' active' : '' ?>" data-page="<?= htmlspecialchars($p['title']) ?>">
        <span>
          <?= htmlspecialchars($p['title']) ?>
          <span class="badge bg-secondary ms-1"><?= htmlspecialchars($p['type']) ?></span>
        </span>
        <span class="btn-group btn-group-sm d-none">
          <button type="button" class="btn btn-secondary generate-btn" data-bs-toggle="tooltip" data-bs-title="Generate">&#x21bb;</button>
          <button type="button" class="btn btn-danger prompt-generate-btn" data-bs-toggle="tooltip" data-bs-title="Generate with prompt">&#x2728;</button>
          <button type="button" class="btn btn-success save-btn" data-bs-toggle="tooltip" data-bs-title="Save">&#x1f4be;</button>
        </span>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div class="col-md-8">
    <div id="genProgress" class="progress mb-3 d-none"><div class="progress-bar" role="progressbar" style="width:0%"></div></div>
    <div id="contentContainer" class="mb-3"></div>
  </div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3"></div>

<div class="modal fade" id="sectionImageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Section Image</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <img id="sectionImage" class="img-fluid" alt="Section preview">
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="promptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Enter Prompt</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <textarea id="promptInput" class="form-control" rows="4" placeholder="Enter prompt"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" id="promptSubmit">Generate</button>
      </div>
    </div>
  </div>
</div>

<form id="actionForm" method="post" class="d-none"></form>

<script>
var pageData = <?= json_encode($pageData) ?>;
var pageStructures = <?= json_encode($pageStructures) ?>;
var allPages = <?= json_encode(array_column($pages, 'title')) ?>;
var currentPage = <?= $openPage ? json_encode($openPage) : 'null' ?>;

var imageModal, promptModal, promptInput, promptResolve;
document.addEventListener('DOMContentLoaded', function(){
  imageModal = new bootstrap.Modal(document.getElementById('sectionImageModal'));
  promptModal = new bootstrap.Modal(document.getElementById('promptModal'));
  promptInput = document.getElementById('promptInput');
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.forEach(function(el){ new bootstrap.Tooltip(el); });
});
var imageEl = document.getElementById('sectionImage');
var toastContainer = document.querySelector('.toast-container');

function showToast(msg, type){
  var toast = document.createElement('div');
  var cls = type || 'secondary';
  toast.className = 'toast align-items-center text-bg-' + cls + ' border-0';
  toast.role = 'alert';
  toast.innerHTML = '<div class="d-flex"><div class="toast-body">'+msg+'</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
  toastContainer.appendChild(toast);
  var t = new bootstrap.Toast(toast);
  t.show();
  toast.addEventListener('hidden.bs.toast', () => toast.remove());
}

function sanitizeHtml(html){
  return String(html || '').replace(/<(?!\/?(h3|h4|p)\b)[^>]*>/gi, '');
}

function mediaSuggestions(html){
  const text = html.replace(/<[^>]+>/g, ' ');
  const words = Array.from(new Set(text.toLowerCase().split(/\W+/).filter(w => w.length > 3)));
  const icons = words.slice(0,3).map(w => 'fa-' + w.replace(/[^a-z0-9]+/g,'-'));
  const phrases = [];
  for (let i=0; i < words.length - 1 && phrases.length < 6; i++) {
    phrases.push(words[i] + ' ' + words[i+1]);
  }
  return {icons: icons, images: phrases.slice(0,3), videos: phrases.slice(3,6)};
}

function askPrompt(title){
  return new Promise(resolve => {
    promptResolve = resolve;
    document.querySelector('#promptModal .modal-title').textContent = title || 'Enter Prompt';
    promptInput.value = '';
    promptModal.show();
  });
}
document.getElementById('promptSubmit').addEventListener('click', function(){
  promptModal.hide();
  if (promptResolve) promptResolve(promptInput.value.trim());
});

function updateSuggestions(section, html, media){
  const sugg = document.getElementById('sugg-' + section);
  if (!sugg) return;
  media = media || mediaSuggestions(html);
  sugg.innerHTML = '';
  function addGroup(label, items){
    if (!items || !items.length) return;
    const p = document.createElement('p');
    p.append(label + ': ');
    items.forEach((item, idx) => {
      const span = document.createElement('span');
      span.className = 'text-primary me-2';
      span.style.cursor = 'pointer';
      span.textContent = item;
      span.addEventListener('click', () => {
        navigator.clipboard.writeText(item).then(() => {
          showToast('Copied to clipboard', 'info');
        });
      });
      p.append(span);
      if (idx < items.length - 1) p.append(', ');
    });
    sugg.appendChild(p);
  }
  addGroup('Recommended icons', media.icons);
  addGroup('Recommended images', media.images);
  addGroup('Recommended videos', media.videos);
}

function regenSection(section, prompt){
  const prog = document.getElementById('prog-' + section);
  if (prog) prog.classList.remove('d-none');
  const params = {ajax: '1', generate_section: '1', page: currentPage, section: section};
  const currentDiv = document.querySelector('.section-field[data-section="' + section + '"]');
  if (currentDiv) params.current = sanitizeHtml(currentDiv.innerHTML);
  if (prompt) params.prompt = prompt;
  return fetch('', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams(params)
  })
  .then(r => r.json())
  .then(res => {
    if (res.content !== undefined) {
      const div = document.querySelector('.section-field[data-section="' + section + '"]');
      if (div) {
        const html = sanitizeHtml(res.content);
        div.innerHTML = html;
        updateSuggestions(section, html, res.media);
        if (!pageData[currentPage]) pageData[currentPage] = {media:{}};
        if (!pageData[currentPage].media) pageData[currentPage].media = {};
        pageData[currentPage].media[section] = res.media;
      }
    } else if (res.error) {
      alert(res.error);
    }
  })
  .finally(() => { if (prog) prog.classList.add('d-none'); });
}

function saveSection(section){
  const div = document.querySelector('.section-field[data-section="' + section + '"]');
  if (!div) return;
  const content = sanitizeHtml(div.innerHTML);
  const media = JSON.stringify((pageData[currentPage] && pageData[currentPage].media && pageData[currentPage].media[section]) || {});
  fetch('', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ajax: '1', save_section: '1', page: currentPage, section: section, content: content, media: media})
  })
  .then(r => r.json())
  .then(res => {
    if (res.status !== 'saved') {
      alert('Save failed');
    } else {
      showToast('Section saved', 'success');
    }
  });
}

function saveMeta(){
  const mt = document.getElementById('metaTitle').value.trim();
  const md = document.getElementById('metaDescription').value.trim();
  const sl = document.getElementById('slug').value.trim();
  fetch('', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ajax: '1', save_meta: '1', page: currentPage, meta_title: mt, meta_description: md, slug: sl})
  })
  .then(r => r.json())
  .then(res => {
    if (res.status !== 'saved') {
      alert('Save failed');
    } else {
      showToast('Meta saved', 'success');
    }
  });
}

function checkMeta(){
  const mt = document.getElementById('metaTitle');
  const md = document.getElementById('metaDescription');
  const mtNote = document.getElementById('metaTitleNote');
  const mdNote = document.getElementById('metaDescNote');
  if (mt && mtNote) {
    mtNote.textContent = mt.value.length > 60 ? 'Title exceeds 60 characters' : '';
  }
  if (md && mdNote) {
    const len = md.value.length;
    mdNote.textContent = (len < 110 || len > 140) ? 'Description should be 110-140 characters' : '';
  }
}

function regenMeta(field, prompt){
  const params = {ajax: '1', page: currentPage};
  params['generate_' + field] = '1';
  if (prompt) params.prompt = prompt;
  if (field === 'meta_title') params.current = document.getElementById('metaTitle').value;
  else if (field === 'meta_description') params.current = document.getElementById('metaDescription').value;
  else if (field === 'slug') params.current = document.getElementById('slug').value;
  return fetch('', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams(params)
  })
  .then(r => r.json())
  .then(res => {
    if (res.meta_title !== undefined) document.getElementById('metaTitle').value = res.meta_title;
    else if (res.meta_description !== undefined) document.getElementById('metaDescription').value = res.meta_description;
    else if (res.slug !== undefined) document.getElementById('slug').value = res.slug;
    else if (res.error) alert(res.error);
    checkMeta();
  });
}

async function generatePage(page){
  loadPage(page);
  const sections = pageStructures[page] || [];
  const total = sections.length + 3;
  let done = 0;
  const prog = document.getElementById('genProgress');
  const bar = prog.querySelector('.progress-bar');
  prog.classList.remove('d-none');
  function step(){ done++; bar.style.width = (done/total*100)+'%'; }
  await regenMeta('meta_title'); step();
  await regenMeta('meta_description'); step();
  await regenMeta('slug'); step();
  for (const sec of sections) {
    await regenSection(sec); step();
  }
  prog.classList.add('d-none');
  bar.style.width = '0%';
  showToast('Generation complete', 'info');
}

async function generatePagePrompt(page){
  const userPrompt = await askPrompt('Prompt for page');
  if (!userPrompt) return;
  loadPage(page);
  const sections = pageStructures[page] || [];
  const total = sections.length + 3;
  let done = 0;
  const prog = document.getElementById('genProgress');
  const bar = prog.querySelector('.progress-bar');
  prog.classList.remove('d-none');
  function step(){ done++; bar.style.width = (done/total*100)+'%'; }
  await regenMeta('meta_title', userPrompt); step();
  await regenMeta('meta_description', userPrompt); step();
  await regenMeta('slug', userPrompt); step();
  for (const sec of sections) {
    await regenSection(sec, userPrompt); step();
  }
  prog.classList.add('d-none');
  bar.style.width = '0%';
  showToast('Generation complete', 'info');
}

function loadPage(page){
  currentPage = page;
  document.querySelectorAll('.page-item').forEach(li => {
    const isActive = li.dataset.page === page;
    li.classList.toggle('active', isActive);
    const btns = li.querySelector('.btn-group');
    if (btns) btns.classList.toggle('d-none', !isActive);
  });
  const container = document.getElementById('contentContainer');
  container.innerHTML = '';
  const data = pageData[page] || {};
  const mtGroup = document.createElement('div');
  mtGroup.className = 'd-flex mb-2';
  const metaTitle = document.createElement('input');
  metaTitle.type = 'text';
  metaTitle.id = 'metaTitle';
  metaTitle.className = 'form-control';
  metaTitle.placeholder = 'Meta Title';
  metaTitle.value = data.meta_title || '';
  const mtBtn = document.createElement('button');
  mtBtn.type = 'button';
  mtBtn.className = 'btn btn-sm btn-outline-secondary ms-2';
  mtBtn.textContent = '\u21bb';
  mtBtn.setAttribute('data-bs-toggle','tooltip');
  mtBtn.setAttribute('data-bs-title','Regenerate meta title');
  mtBtn.addEventListener('click', function(){
    regenMeta('meta_title');
  });
  mtGroup.append(metaTitle, mtBtn);
  const mtNote = document.createElement('div');
  mtNote.id = 'metaTitleNote';
  mtNote.className = 'form-text text-danger';

  const mdGroup = document.createElement('div');
  mdGroup.className = 'd-flex mb-2';
  const metaDesc = document.createElement('textarea');
  metaDesc.id = 'metaDescription';
  metaDesc.rows = 3;
  metaDesc.className = 'form-control';
  metaDesc.placeholder = 'Meta Description';
  metaDesc.value = data.meta_description || '';
  const mdBtn = document.createElement('button');
  mdBtn.type = 'button';
  mdBtn.className = 'btn btn-sm btn-outline-secondary ms-2';
  mdBtn.textContent = '\u21bb';
  mdBtn.setAttribute('data-bs-toggle','tooltip');
  mdBtn.setAttribute('data-bs-title','Regenerate meta description');
  mdBtn.addEventListener('click', function(){
    regenMeta('meta_description');
  });
  mdGroup.append(metaDesc, mdBtn);
  const mdNote = document.createElement('div');
  mdNote.id = 'metaDescNote';
  mdNote.className = 'form-text text-danger';

  const slugGroup = document.createElement('div');
  slugGroup.className = 'd-flex mb-3';
  const slugInput = document.createElement('input');
  slugInput.type = 'text';
  slugInput.id = 'slug';
  slugInput.maxLength = 80;
  slugInput.className = 'form-control';
  slugInput.placeholder = 'Slug (lowercase, hyphen-separated)';
  slugInput.value = data.slug || '';
  const slugBtn = document.createElement('button');
  slugBtn.type = 'button';
  slugBtn.className = 'btn btn-sm btn-outline-secondary ms-2';
  slugBtn.textContent = '\u21bb';
  slugBtn.setAttribute('data-bs-toggle','tooltip');
  slugBtn.setAttribute('data-bs-title','Regenerate slug');
  slugBtn.addEventListener('click', function(){
    regenMeta('slug');
  });
  const metaSaveBtn = document.createElement('button');
  metaSaveBtn.type = 'button';
  metaSaveBtn.className = 'btn btn-sm btn-outline-success ms-2';
  metaSaveBtn.innerHTML = '\u{1F4BE}';
  metaSaveBtn.id = 'saveMetaBtn';
  metaSaveBtn.setAttribute('data-bs-toggle','tooltip');
  metaSaveBtn.setAttribute('data-bs-title','Save meta');
  metaSaveBtn.addEventListener('click', function(){
    saveMeta();
  });
  slugGroup.append(slugInput, slugBtn, metaSaveBtn);

  const metaWrap = document.createElement('div');
  metaWrap.className = 'mb-3';
  metaWrap.id = 'metaSection';
  metaWrap.append(mtGroup, mtNote, mdGroup, mdNote, slugGroup);
  container.append(metaWrap);
  const hr = document.createElement('hr');
  hr.className = 'my-4';
  container.append(hr);
  metaTitle.addEventListener('input', checkMeta);
  metaDesc.addEventListener('input', checkMeta);
  checkMeta();
  let sections = pageStructures[page] || [];
  if (!Array.isArray(sections)) {
    sections = Object.values(sections);
  }
  const secData = data.sections || {};
  sections.forEach(sec => {
    const wrap = document.createElement('div');
    wrap.className = 'd-flex justify-content-between align-items-center mb-2';
    const label = document.createElement('label');
    label.className = 'form-label mb-0';
    label.textContent = sec;
    const btnGroup = document.createElement('div');
    btnGroup.className = 'd-flex';
    const viewBtn = document.createElement('button');
    viewBtn.type = 'button';
    viewBtn.className = 'btn btn-sm btn-outline-info me-2 view-section';
    viewBtn.dataset.section = sec;
    viewBtn.innerHTML = '\u{1F441}';
    viewBtn.setAttribute('data-bs-toggle','tooltip');
    viewBtn.setAttribute('data-bs-title','View section image');
    const promptBtn = document.createElement('button');
    promptBtn.type = 'button';
    promptBtn.className = 'btn btn-sm btn-outline-primary me-2 prompt-section';
    promptBtn.dataset.section = sec;
    promptBtn.innerHTML = '\u2728';
    promptBtn.setAttribute('data-bs-toggle','tooltip');
    promptBtn.setAttribute('data-bs-title','Generate with prompt');
    const regen = document.createElement('button');
    regen.type = 'button';
    regen.className = 'btn btn-sm btn-outline-secondary regen-section';
    regen.dataset.section = sec;
    regen.innerHTML = '\u21bb';
    regen.setAttribute('data-bs-toggle','tooltip');
    regen.setAttribute('data-bs-title','Regenerate section');
    const saveBtn = document.createElement('button');
    saveBtn.type = 'button';
    saveBtn.className = 'btn btn-sm btn-outline-success ms-2 save-section';
    saveBtn.dataset.section = sec;
    saveBtn.innerHTML = '\u{1F4BE}';
    saveBtn.setAttribute('data-bs-toggle','tooltip');
    saveBtn.setAttribute('data-bs-title','Save section');
    btnGroup.append(viewBtn, promptBtn, regen, saveBtn);
    wrap.append(label, btnGroup);
    const div = document.createElement('div');
    div.className = 'form-control mb-3 section-field';
    div.contentEditable = 'true';
    div.dataset.section = sec;
    div.style.minHeight = '6em';
    div.innerHTML = sanitizeHtml(secData[sec] || '');
    const prog = document.createElement('div');
    prog.className = 'progress mb-2 d-none';
    prog.id = 'prog-' + sec;
    prog.innerHTML = '<div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div>';
    const sugg = document.createElement('div');
    sugg.className = 'small text-muted mb-3';
    sugg.style.minHeight = '3em';
    sugg.id = 'sugg-' + sec;
    sugg.textContent = '...';
    const media = (data.media && data.media[sec]) || null;
    container.append(wrap, prog, div, sugg);
    updateSuggestions(sec, div.innerHTML, media);
  });
  container.querySelectorAll('.regen-section').forEach(btn => {
    btn.addEventListener('click', function(e){
      e.preventDefault();
      regenSection(this.dataset.section).then(() => showToast('Section regenerated', 'info'));
    });
  });
  container.querySelectorAll('.save-section').forEach(btn => {
    btn.addEventListener('click', function(e){
      e.preventDefault();
      saveSection(this.dataset.section);
    });
  });
  container.querySelectorAll('.prompt-section').forEach(btn => {
    btn.addEventListener('click', function(e){
      e.preventDefault();
      askPrompt('Prompt for ' + this.dataset.section + ' section').then(p => {
        if (p) regenSection(this.dataset.section, p).then(() => showToast('Section generated', 'info'));
      });
    });
  });
  container.querySelectorAll('.view-section').forEach(btn => {
    btn.addEventListener('click', function(e){
      e.preventDefault();
      imageEl.src = 'https://wordprseo.greenmindagency.com/wp-content/themes/wordprseo/acf-images/' + this.dataset.section + '.jpg';
      imageModal.show();
    });
  });
  var tooltipTriggerList = [].slice.call(container.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.forEach(function(el){ new bootstrap.Tooltip(el); });
}

document.querySelectorAll('.page-item').forEach(li => {
  li.addEventListener('click', function(e){
    if (e.target.closest('.generate-btn') || e.target.closest('.save-btn') || e.target.closest('.prompt-generate-btn')) return;
    loadPage(this.dataset.page);
  });
  li.querySelector('.generate-btn').addEventListener('click', function(e){
    e.stopPropagation();
    generatePage(li.dataset.page);
  });
  li.querySelector('.prompt-generate-btn').addEventListener('click', function(e){
    e.stopPropagation();
    generatePagePrompt(li.dataset.page);
  });
  li.querySelector('.save-btn').addEventListener('click', function(e){
    e.stopPropagation();
    submitAction(li.dataset.page, 'save_page');
  });
});

function submitAction(page, action){
  const form = document.getElementById('actionForm');
  form.innerHTML = '';
  const pageInput = document.createElement('input');
  pageInput.type = 'hidden';
  pageInput.name = 'page';
  pageInput.value = page;
  form.appendChild(pageInput);
  if (action === 'save_page') {
    const mt = document.getElementById('metaTitle').value.trim();
    const md = document.getElementById('metaDescription').value.trim();
    const obj = {
      meta_title: mt,
      meta_description: md,
      slug: document.getElementById('slug').value.trim(),
      sections: {},
      media: {}
    };
    document.querySelectorAll('.section-field').forEach(div => {
      const sec = div.dataset.section;
      obj.sections[sec] = sanitizeHtml(div.innerHTML);
      if (pageData[page] && pageData[page].media && pageData[page].media[sec]) {
        obj.media[sec] = pageData[page].media[sec];
      }
    });
    const contentInput = document.createElement('input');
    contentInput.type = 'hidden';
    contentInput.name = 'page_content';
    contentInput.value = JSON.stringify(obj);
    form.appendChild(contentInput);
  }
  const actionInput = document.createElement('input');
  actionInput.type = 'hidden';
  actionInput.name = action;
  actionInput.value = '1';
  form.appendChild(actionInput);
  form.submit();
}

if (currentPage === null) {
  currentPage = allPages.length ? allPages[0] : null;
}
if (currentPage) {
  loadPage(currentPage);
}
</script>
<?php include __DIR__ . '/../footer.php'; ?>