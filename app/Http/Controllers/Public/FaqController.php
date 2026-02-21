<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class FaqController extends Controller
{
    public function index(): View
    {
        $articles = $this->articles();

        return view('public.faq.index', [
            'articles' => $articles,
        ]);
    }

    public function show(string $slug): View
    {
        $articles = $this->articles();
        $article = collect($articles)->firstWhere('slug', $slug);

        abort_if(! is_array($article), 404);

        $relatedSlugs = Arr::wrap($article['related'] ?? []);
        $relatedArticles = collect($articles)
            ->filter(fn (array $item): bool => in_array($item['slug'], $relatedSlugs, true))
            ->values()
            ->all();

        return view('public.faq.show', [
            'article' => $article,
            'relatedArticles' => $relatedArticles,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function articles(): array
    {
        $articles = config('faq.articles', []);

        return collect(is_array($articles) ? $articles : [])
            ->filter(function ($article): bool {
                return is_array($article)
                    && is_string($article['slug'] ?? null)
                    && Str::of($article['slug'])->trim()->toString() !== ''
                    && is_string($article['question'] ?? null)
                    && is_string($article['answer'] ?? null);
            })
            ->values()
            ->all();
    }
}
