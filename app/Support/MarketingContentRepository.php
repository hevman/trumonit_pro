<?php

namespace App\Support;

class MarketingContentRepository
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $solutionsCache = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $faqCache = [];

    public function normalizeLocale(string $locale): string
    {
        return $locale === 'pl' ? 'pl' : 'en';
    }

    /**
     * @return array<int, array{slug:string,title:string,meta_title:string,meta_description:string,summary:string,body:string,problem:string,approach:array<int,string>,proof:array<int,string>,checklist:array<int,string>,pitfalls:array<int,string>,related_faq:array<int,string>,order:int}>
     */
    public function solutionPages(string $locale): array
    {
        $locale = $this->normalizeLocale($locale);

        if (isset($this->solutionsCache[$locale])) {
            return $this->solutionsCache[$locale];
        }

        $fallback = $this->loadPhpMap(resource_path('content/solutions/en'));
        $localized = $locale === 'en'
            ? $fallback
            : $this->loadPhpMap(resource_path("content/solutions/{$locale}"));

        $slugs = array_values(array_unique(array_merge(array_keys($fallback), array_keys($localized))));
        $pages = [];

        foreach ($slugs as $slug) {
            $raw = $localized[$slug] ?? $fallback[$slug] ?? null;
            if (! is_array($raw)) {
                continue;
            }

            $title = trim((string) ($raw['title'] ?? ''));
            $summary = trim((string) ($raw['summary'] ?? ''));
            if ($title === '' || $summary === '') {
                continue;
            }

            $pages[] = [
                'slug' => $slug,
                'title' => $title,
                'meta_title' => trim((string) ($raw['meta_title'] ?? $title)),
                'meta_description' => trim((string) ($raw['meta_description'] ?? $summary)),
                'summary' => $summary,
                'body' => trim((string) ($raw['body'] ?? '')),
                'problem' => trim((string) ($raw['problem'] ?? $summary)),
                'approach' => $this->normalizeStringList($raw['approach'] ?? []),
                'proof' => $this->normalizeStringList($raw['proof'] ?? []),
                'checklist' => $this->normalizeStringList($raw['checklist'] ?? []),
                'pitfalls' => $this->normalizeStringList($raw['pitfalls'] ?? []),
                'related_faq' => $this->normalizeStringList($raw['related_faq'] ?? []),
                'order' => (int) ($raw['order'] ?? 9999),
            ];
        }

        usort($pages, static function (array $a, array $b): int {
            $orderCmp = $a['order'] <=> $b['order'];
            if ($orderCmp !== 0) {
                return $orderCmp;
            }

            return strcmp($a['slug'], $b['slug']);
        });

        $this->solutionsCache[$locale] = $pages;

        return $pages;
    }

    /**
     * @return array{slug:string,title:string,meta_title:string,meta_description:string,summary:string,body:string,problem:string,approach:array<int,string>,proof:array<int,string>,checklist:array<int,string>,pitfalls:array<int,string>,related_faq:array<int,string>,order:int}|null
     */
    public function findSolution(string $locale, string $slug): ?array
    {
        $normalizedSlug = trim(rawurldecode($slug));
        if ($normalizedSlug === '') {
            return null;
        }

        foreach ($this->solutionPages($locale) as $page) {
            if (strcasecmp($page['slug'], $normalizedSlug) === 0) {
                return $page;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function solutionSlugs(): array
    {
        return array_values(array_map(
            static fn (array $page): string => $page['slug'],
            $this->solutionPages('en')
        ));
    }

    /**
     * @return array<int, array{slug:string,question:string,answer:string,meta_title:string,meta_description:string,related:array<int,string>,related_solutions:array<int,string>,order:int}>
     */
    public function faqArticles(string $locale): array
    {
        $locale = $this->normalizeLocale($locale);

        if (isset($this->faqCache[$locale])) {
            return $this->faqCache[$locale];
        }

        $fallback = $this->loadPhpMap(resource_path('content/faq/en'));
        $localized = $locale === 'en'
            ? $fallback
            : $this->loadPhpMap(resource_path("content/faq/{$locale}"));

        $slugs = array_values(array_unique(array_merge(array_keys($fallback), array_keys($localized))));
        $articles = [];

        foreach ($slugs as $slug) {
            $raw = $localized[$slug] ?? $fallback[$slug] ?? null;
            if (! is_array($raw)) {
                continue;
            }

            $question = trim((string) ($raw['question'] ?? ''));
            $answer = trim((string) ($raw['answer'] ?? ''));
            if ($question === '' || $answer === '') {
                continue;
            }

            $articles[] = [
                'slug' => $slug,
                'question' => $question,
                'answer' => $answer,
                'meta_title' => trim((string) ($raw['meta_title'] ?? $question)),
                'meta_description' => trim((string) ($raw['meta_description'] ?? strip_tags($answer))),
                'related' => $this->normalizeStringList($raw['related'] ?? []),
                'related_solutions' => $this->normalizeStringList($raw['related_solutions'] ?? []),
                'order' => (int) ($raw['order'] ?? 9999),
            ];
        }

        usort($articles, static function (array $a, array $b): int {
            $orderCmp = $a['order'] <=> $b['order'];
            if ($orderCmp !== 0) {
                return $orderCmp;
            }

            return strcmp($a['slug'], $b['slug']);
        });

        $knownSlugs = array_flip(array_map(static fn (array $a): string => $a['slug'], $articles));
        $knownSolutionSlugs = array_flip($this->solutionSlugs());

        $articles = array_map(static function (array $article) use ($knownSlugs, $knownSolutionSlugs): array {
            $article['related'] = array_values(array_filter($article['related'], static function (string $slug) use ($knownSlugs): bool {
                return isset($knownSlugs[$slug]);
            }));
            $article['related_solutions'] = array_values(array_filter($article['related_solutions'], static function (string $slug) use ($knownSolutionSlugs): bool {
                return isset($knownSolutionSlugs[$slug]);
            }));

            return $article;
        }, $articles);

        $this->faqCache[$locale] = $articles;

        return $articles;
    }

    /**
     * @return array{slug:string,question:string,answer:string,meta_title:string,meta_description:string,related:array<int,string>,related_solutions:array<int,string>,order:int}|null
     */
    public function findFaqArticle(string $locale, string $slug): ?array
    {
        $normalizedSlug = trim(rawurldecode($slug));
        if ($normalizedSlug === '') {
            return null;
        }

        foreach ($this->faqArticles($locale) as $article) {
            if (strcasecmp($article['slug'], $normalizedSlug) === 0) {
                return $article;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function faqSlugs(): array
    {
        return array_values(array_map(
            static fn (array $article): string => $article['slug'],
            $this->faqArticles('en')
        ));
    }

    /**
     * @param array{slug:string,related_faq?:array<int,string>} $solution
     * @return array<int, array{slug:string,question:string,answer:string,meta_title:string,meta_description:string,related:array<int,string>,related_solutions:array<int,string>,order:int}>
     */
    public function relatedFaqForSolution(string $locale, array $solution, int $limit = 4): array
    {
        $faq = $this->faqArticles($locale);
        if ($faq === []) {
            return [];
        }

        $faqBySlug = [];
        foreach ($faq as $article) {
            $faqBySlug[$article['slug']] = $article;
        }

        $manual = [];
        foreach (($solution['related_faq'] ?? []) as $slug) {
            if (isset($faqBySlug[$slug])) {
                $manual[] = $faqBySlug[$slug];
            }
        }

        if ($manual !== []) {
            return array_slice($manual, 0, max(1, $limit));
        }

        $tokens = $this->tokenize((string) ($solution['slug'] ?? ''));
        if ($tokens === []) {
            return array_slice($faq, 0, max(1, $limit));
        }

        $scored = [];
        foreach ($faq as $article) {
            $haystack = strtolower($article['slug'] . ' ' . ($article['question'] ?? ''));
            $score = 0;
            foreach ($tokens as $token) {
                if ($token !== '' && str_contains($haystack, $token)) {
                    $score++;
                }
            }

            if ($score > 0) {
                $article['_score'] = $score;
                $scored[] = $article;
            }
        }

        if ($scored === []) {
            return array_slice($faq, 0, max(1, $limit));
        }

        usort($scored, static fn (array $a, array $b): int => ($b['_score'] <=> $a['_score']) ?: (($a['order'] ?? 9999) <=> ($b['order'] ?? 9999)));

        return array_slice(array_map(static function (array $article): array {
            unset($article['_score']);

            return $article;
        }, $scored), 0, max(1, $limit));
    }

    /**
     * @param array{slug:string,title?:string,summary?:string,related_faq?:array<int,string>} $solution
     * @return array<int, array{slug:string,title:string,meta_title:string,meta_description:string,summary:string,body:string,problem:string,approach:array<int,string>,proof:array<int,string>,checklist:array<int,string>,pitfalls:array<int,string>,related_faq:array<int,string>,order:int}>
     */
    public function relatedSolutions(string $locale, array $solution, int $limit = 3): array
    {
        $pages = $this->solutionPages($locale);
        if ($pages === []) {
            return [];
        }

        $limit = max(1, $limit);
        $currentSlug = trim((string) ($solution['slug'] ?? ''));
        $tokens = $this->tokenize((string) ($solution['slug'] ?? '') . ' ' . (string) ($solution['title'] ?? '') . ' ' . (string) ($solution['summary'] ?? ''));
        $relatedFaq = array_flip($this->normalizeStringList($solution['related_faq'] ?? []));

        $fallback = [];
        $scored = [];

        foreach ($pages as $page) {
            if ($currentSlug !== '' && strcasecmp($page['slug'], $currentSlug) === 0) {
                continue;
            }

            $fallback[] = $page;
            $score = 0;

            if ($tokens !== []) {
                $candidateTokens = $this->tokenize($page['slug'] . ' ' . $page['title'] . ' ' . $page['summary']);
                $score += count(array_intersect($tokens, $candidateTokens)) * 3;
            }

            if ($relatedFaq !== [] && $page['related_faq'] !== []) {
                $candidateFaq = array_flip($page['related_faq']);
                $score += count(array_intersect_key($relatedFaq, $candidateFaq)) * 5;
            }

            if ($score > 0) {
                $page['_score'] = $score;
                $scored[] = $page;
            }
        }

        if ($scored === []) {
            return array_slice($fallback, 0, $limit);
        }

        usort($scored, static fn (array $a, array $b): int => ($b['_score'] <=> $a['_score']) ?: (($a['order'] ?? 9999) <=> ($b['order'] ?? 9999)));

        return array_slice(array_map(static function (array $page): array {
            unset($page['_score']);

            return $page;
        }, $scored), 0, $limit);
    }

    /**
     * @param array{slug:string,question?:string,related?:array<int,string>,related_solutions?:array<int,string>} $article
     * @return array<int, array{slug:string,title:string,meta_title:string,meta_description:string,summary:string,body:string,problem:string,approach:array<int,string>,proof:array<int,string>,checklist:array<int,string>,pitfalls:array<int,string>,related_faq:array<int,string>,order:int}>
     */
    public function relatedSolutionsForFaq(string $locale, array $article, int $limit = 3): array
    {
        $pages = $this->solutionPages($locale);
        if ($pages === []) {
            return [];
        }

        $limit = max(1, $limit);
        $pagesBySlug = [];
        foreach ($pages as $page) {
            $pagesBySlug[$page['slug']] = $page;
        }

        $manual = [];
        foreach (($article['related_solutions'] ?? []) as $slug) {
            if (isset($pagesBySlug[$slug])) {
                $manual[] = $pagesBySlug[$slug];
            }
        }

        if ($manual !== []) {
            return array_slice($manual, 0, $limit);
        }

        $tokens = $this->tokenize((string) ($article['slug'] ?? '') . ' ' . (string) ($article['question'] ?? ''));
        if ($tokens === []) {
            return array_slice($pages, 0, $limit);
        }

        $scored = [];
        foreach ($pages as $page) {
            $candidateTokens = $this->tokenize($page['slug'] . ' ' . $page['title'] . ' ' . $page['summary']);
            $score = count(array_intersect($tokens, $candidateTokens));
            if ($score > 0) {
                $page['_score'] = $score;
                $scored[] = $page;
            }
        }

        if ($scored === []) {
            return array_slice($pages, 0, $limit);
        }

        usort($scored, static fn (array $a, array $b): int => ($b['_score'] <=> $a['_score']) ?: (($a['order'] ?? 9999) <=> ($b['order'] ?? 9999)));

        return array_slice(array_map(static function (array $page): array {
            unset($page['_score']);

            return $page;
        }, $scored), 0, $limit);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadPhpMap(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files);

        $result = [];
        foreach ($files as $file) {
            $slug = pathinfo($file, PATHINFO_FILENAME);
            if (! is_string($slug) || trim($slug) === '') {
                continue;
            }

            $data = require $file;
            if (! is_array($data)) {
                continue;
            }

            $result[$slug] = $data;
        }

        return $result;
    }

    /**
     * @param mixed $items
     * @return array<int, string>
     */
    private function normalizeStringList($items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($item): string {
            return trim((string) $item);
        }, $items), static function (string $value): bool {
            return $value !== '';
        }));
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $value): array
    {
        $tokens = preg_split('/[^a-z0-9]+/i', strtolower($value)) ?: [];
        $tokens = array_values(array_filter(array_map(static fn (string $token): string => trim($token), $tokens), static fn (string $token): bool => strlen($token) > 2));

        return array_values(array_unique($tokens));
    }
}
