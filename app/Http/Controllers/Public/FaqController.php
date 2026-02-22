<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\MarketingContentRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;

class FaqController extends Controller
{
    public function __construct(
        private readonly MarketingContentRepository $content
    ) {
    }

    public function index(): View
    {
        $articles = $this->content->faqArticles(app()->getLocale());

        return view('public.faq.index', [
            'articles' => $articles,
        ]);
    }

    public function show(string $locale, string $slug): View
    {
        $articles = $this->content->faqArticles($locale);
        $article = $this->content->findFaqArticle($locale, $slug);

        abort_if(! is_array($article), 404);

        $relatedSlugs = Arr::wrap($article['related'] ?? []);
        $relatedArticles = collect($articles)
            ->filter(fn (array $item): bool => in_array($item['slug'], $relatedSlugs, true))
            ->values()
            ->all();
        $relatedSolutions = $this->content->relatedSolutionsForFaq($locale, $article, 3);

        return view('public.faq.show', [
            'article' => $article,
            'relatedArticles' => $relatedArticles,
            'relatedSolutions' => $relatedSolutions,
        ]);
    }
}
