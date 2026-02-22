<?php

declare(strict_types=1);

/**
 * One-off helper to migrate TruMonit FAQ content from config/lang to per-locale files.
 *
 * Usage (from repo root):
 *   php tools/generate-marketing-content.php
 */

$root = dirname(__DIR__);
$faqConfigPath = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'faq.php';
$langEnPath = $root . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . 'en' . DIRECTORY_SEPARATOR . 'faq.php';
$langPlPath = $root . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . 'pl' . DIRECTORY_SEPARATOR . 'faq.php';

$faqConfig = is_file($faqConfigPath) ? require $faqConfigPath : [];
$langEn = is_file($langEnPath) ? require $langEnPath : [];
$langPl = is_file($langPlPath) ? require $langPlPath : [];

$articles = is_array($faqConfig['articles'] ?? null) ? $faqConfig['articles'] : [];

$outBase = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'faq';
$outEn = $outBase . DIRECTORY_SEPARATOR . 'en';
$outPl = $outBase . DIRECTORY_SEPARATOR . 'pl';

@mkdir($outEn, 0777, true);
@mkdir($outPl, 0777, true);

$writePhpMap = static function (string $path, array $data): void {
    $dir = dirname($path);
    if (! is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    // var_export produces ASCII-safe output; content strings are assumed UTF-8.
    $content = "<?php\n\nreturn " . var_export($data, true) . ";\n";
    file_put_contents($path, $content);
};

$resolveLang = static function (array $lang, string $key): string {
    $normalized = $key;
    if (str_starts_with($normalized, 'faq.')) {
        $normalized = substr($normalized, 4);
    }

    $value = $lang[$normalized] ?? null;
    if (is_string($value) && trim($value) !== '') {
        return $value;
    }

    return $key;
};

$metaFromAnswer = static function (string $question, string $answerHtml, string $brand): array {
    $plain = trim(strip_tags($answerHtml));
    $plain = preg_replace('/\s+/', ' ', $plain) ?? $plain;
    $desc = mb_substr($plain, 0, 160);
    if (mb_strlen($plain) > 160) {
        $desc = rtrim($desc) . '...';
    }

    return [
        'meta_title' => $question . ' | ' . $brand,
        'meta_description' => $desc,
    ];
};

$brand = 'TruMonit';
$count = 0;

foreach ($articles as $idx => $row) {
    if (! is_array($row)) {
        continue;
    }

    $slug = (string) ($row['slug'] ?? '');
    if (trim($slug) === '') {
        continue;
    }

    $questionKey = (string) ($row['question'] ?? '');
    $answerKey = (string) ($row['answer'] ?? '');
    $related = is_array($row['related'] ?? null) ? array_values($row['related']) : [];

    $enQ = $resolveLang($langEn, $questionKey);
    $enA = $resolveLang($langEn, $answerKey);
    $plQ = $resolveLang($langPl, $questionKey);
    $plA = $resolveLang($langPl, $answerKey);

    $enMeta = $metaFromAnswer($enQ, $enA, $brand);
    $plMeta = $metaFromAnswer($plQ, $plA, $brand);

    $common = [
        'order' => (int) ($idx + 1),
        'related' => $related,
        'related_solutions' => [],
    ];

    $writePhpMap($outEn . DIRECTORY_SEPARATOR . $slug . '.php', $common + [
        'question' => $enQ,
        'answer' => $enA,
    ] + $enMeta);

    $writePhpMap($outPl . DIRECTORY_SEPARATOR . $slug . '.php', $common + [
        'question' => $plQ,
        'answer' => $plA,
    ] + $plMeta);

    $count++;
}

echo "Generated FAQ files for {$count} slugs.\n";
