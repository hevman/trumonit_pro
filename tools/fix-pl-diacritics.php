<?php

/**
 * Pragmatic diacritics pass for PL marketing content.
 *
 * This is intentionally a limited, explicit mapping (not "AI transliteration").
 * It is designed to remove the most visible ASCII-only copy in PL content files.
 *
 * Usage:
 *   php tools/fix-pl-diacritics.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$targets = [
    $root.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'content'.DIRECTORY_SEPARATOR.'faq'.DIRECTORY_SEPARATOR.'pl',
    $root.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'content'.DIRECTORY_SEPARATOR.'solutions'.DIRECTORY_SEPARATOR.'pl',
];

// Order matters: longer tokens first to avoid partial overlaps.
// We replace exact phrases first, then whole words (Unicode-aware).
$phraseMap = [
    'na biezaco' => 'na bieżąco',
    'Na biezaco' => 'Na bieżąco',
    'wiecej niz' => 'więcej niż',
    'Wiecej niz' => 'Więcej niż',
];

$wordMap = [
    // Common short words (high frequency).
    'moge' => 'mogę',
    'Moge' => 'Mogę',
    'musze' => 'muszę',
    'Musze' => 'Muszę',
    'zeby' => 'żeby',
    'Zeby' => 'Żeby',
    'tez' => 'też',
    'Tez' => 'Też',
    'wiecej' => 'więcej',
    'Wiecej' => 'Więcej',
    'najprosciej' => 'najprościej',
    'Najprosciej' => 'Najprościej',
    'prosciej' => 'prościej',
    'Prosciej' => 'Prościej',

    // Common "który/która/które" forms.
    'ktory' => 'który',
    'ktora' => 'która',
    'ktore' => 'które',
    'ktorej' => 'której',
    'ktorych' => 'których',
    'ktorym' => 'którym',
    'ktorymi' => 'którymi',
    'sa' => 'są',
    'Sa' => 'Są',
    'byc' => 'być',
    'Byc' => 'Być',

    // Team / workflow.
    'zespolowego' => 'zespołowego',
    'zespolowy' => 'zespołowy',
    'zespolowa' => 'zespołowa',
    'zespolowe' => 'zespołowe',
    'zespolach' => 'zespołach',
    'zespolami' => 'zespołami',
    'zespolu' => 'zespołu',
    'zespoly' => 'zespoły',
    'zespol' => 'zespół',

    // Users.
    'uzytkownikow' => 'użytkowników',
    'uzytkownicy' => 'użytkownicy',
    'uzytkownika' => 'użytkownika',
    'uzytkownikowi' => 'użytkownikowi',
    'uzytkownikiem' => 'użytkownikiem',
    'uzytkownik' => 'użytkownik',
    'uzytkown' => 'użytkown',

    // Common verbs.
    'monitorowac' => 'monitorować',
    'sprawdzac' => 'sprawdzać',
    'uzywac' => 'używać',
    'uzywaj' => 'używaj',
    'uzyj' => 'użyj',
    'uzywa' => 'używa',
    'zwieksza' => 'zwiększa',
    'zwiekszyc' => 'zwiększyć',
    'zwieksz' => 'zwiększ',
    'zwiekszal' => 'zwiększał',
    'zwiekszala' => 'zwiększała',
    'zwiekszaly' => 'zwiększały',
    'zwiekszaj' => 'zwiększaj',
    'zwiekszaja' => 'zwiększają',

    // Frequent nouns/adjectives.
    'falszywych' => 'fałszywych',
    'falszywymi' => 'fałszywymi',
    'falszywej' => 'fałszywej',
    'falszywe' => 'fałszywe',
    'falszywy' => 'fałszywy',
    'falszywa' => 'fałszywa',
    'wazny' => 'ważny',
    'wazna' => 'ważna',
    'wazne' => 'ważne',
    'waznych' => 'ważnych',
    'czesto' => 'często',
    'alertow' => 'alertów',

    // Infra / nouns.
    'uslugach' => 'usługach',
    'uslugami' => 'usługami',
    'uslugi' => 'usługi',
    'usluga' => 'usługa',
    'uslug' => 'usług',
    'wewnetrzne' => 'wewnętrzne',
    'wewnetrzny' => 'wewnętrzny',
    'wewnetrzna' => 'wewnętrzna',
    'wewnetrznych' => 'wewnętrznych',
    'zewnetrzne' => 'zewnętrzne',
    'zewnetrzny' => 'zewnętrzny',
    'zewnetrzna' => 'zewnętrzna',
    'zewnetrznych' => 'zewnętrznych',
    'zewnetrznymi' => 'zewnętrznymi',
    'srodowiska' => 'środowiska',
    'srodowisko' => 'środowisko',
    'srodowisk' => 'środowisk',
    'dostepy' => 'dostępy',
    'niedostepnosci' => 'niedostępności',
    'niedostepnosc' => 'niedostępność',

    // Other common words.
    'kanalow' => 'kanałów',
    'kanaly' => 'kanały',
    'kanal' => 'kanał',
    'polityke' => 'politykę',
    'prywatnosci' => 'prywatności',
    'prywatnosc' => 'prywatność',
    'wybrac' => 'wybrać',
    'przywroc' => 'przywróć',
    'sygnalu' => 'sygnału',
    'sygnal' => 'sygnał',
    'odstepach' => 'odstępach',
    'odstep' => 'odstęp',
    'czestotliwosci' => 'częstotliwości',
    'czestotliwosc' => 'częstotliwość',
    'najczestsze' => 'najczęstsze',
    'najczesciej' => 'najczęściej',
    'najczestszy' => 'najczęstszy',
    'powtarzalnosc' => 'powtarzalność',
    'komunikacje' => 'komunikację',
    'odciaza' => 'odciąża',
    'odciazyc' => 'odciążyć',
    'bledow' => 'błędów',
    'bledy' => 'błędy',
    'bledny' => 'błędny',
    'bledna' => 'błędna',
    'reguly' => 'reguły',
    'regula' => 'reguła',
    'zaleznosci' => 'zależności',
    'zaleznosc' => 'zależność',
    'lancuchy' => 'łańcuchy',
    'lancuch' => 'łańcuch',
    'platnosci' => 'płatności',
    'dlugo' => 'długo',
    'dlugi' => 'długi',
    'dluzszy' => 'dłuższy',
    'dluzsze' => 'dłuższe',
    'dluzsza' => 'dłuższa',
    'kazdym' => 'każdym',
    'kazdy' => 'każdy',
    'kazda' => 'każda',
    'kazde' => 'każde',
    'kazdego' => 'każdego',
    'kazdej' => 'każdej',
    'kazdemu' => 'każdemu',
    'Okresl' => 'Określ',
    'okresl' => 'określ',
    'krytycznosci' => 'krytyczności',
    'konca' => 'końca',
    'miec' => 'mieć',
    'ukrywac' => 'ukrywać',
    'incydentow' => 'incydentów',

    // Consent / toggles.
    'wlaczony' => 'włączony',
    'wlaczone' => 'włączone',
    'wlaczona' => 'włączona',
    'wlacz' => 'włącz',
];

function eachPhpFile(string $dir): array
{
    if (!is_dir($dir)) {
        return [];
    }

    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        if (strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $files[] = $file->getPathname();
    }

    sort($files);
    return $files;
}

$changed = 0;

foreach ($targets as $dir) {
    foreach (eachPhpFile($dir) as $path) {
        $before = file_get_contents($path);
        if (!is_string($before)) {
            fwrite(STDERR, "Failed to read: {$path}\n");
            continue;
        }

        $after = $before;

        foreach ($phraseMap as $from => $to) {
            $after = str_replace($from, $to, $after);
        }

        foreach ($wordMap as $from => $to) {
            $pattern = '~\b'.preg_quote($from, '~').'\b~u';
            $after = preg_replace($pattern, $to, $after) ?? $after;
        }

        if ($after === $before) {
            continue;
        }

        file_put_contents($path, $after);
        $changed++;
    }
}

fwrite(STDOUT, "Updated files: {$changed}\n");
