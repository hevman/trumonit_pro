<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\MarketingContentRepository;
use Illuminate\Contracts\View\View;

class SolutionsController extends Controller
{
    public function __construct(
        private readonly MarketingContentRepository $content
    ) {
    }

    public function index(): View
    {
        $pages = $this->content->solutionPages(app()->getLocale());

        return view('public.solutions.index', [
            'pages' => $pages,
        ]);
    }

    public function show(string $locale, string $slug): View
    {
        $page = $this->content->findSolution($locale, $slug);
        abort_if(! is_array($page), 404);

        $relatedPages = $this->content->relatedSolutions($locale, $page, 3);
        $relatedFaq = $this->content->relatedFaqForSolution($locale, $page, 4);

        return view('public.solutions.show', [
            'page' => $page,
            'relatedPages' => $relatedPages,
            'relatedFaq' => $relatedFaq,
        ]);
    }
}

