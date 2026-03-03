<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\MarketingContentRepository;
use Illuminate\Contracts\View\View;

class AlternativesHubController extends Controller
{
    /** @var array<int, string> */
    private const ALTERNATIVE_SLUGS = [
        'uptimerobot-alternative-for-smb',
        'statuscake-alternative-with-status-pages',
        'pingdom-alternative-for-smb',
        'better-stack-alternative-for-smb',
        'datadog-synthetics-alternative-for-smb',
        'new-relic-synthetics-alternative',
        'site24x7-alternative-for-teams',
        'uptrends-alternative-for-smb',
        'freshping-alternative-for-small-teams',
        'hetrixtools-alternative-for-agencies',
        'hyperping-alternative-with-incident-workflow',
        'checkly-alternative-for-website-monitoring',
        'sematext-alternative-for-uptime-monitoring',
        'appsignal-uptime-alternative',
        'statuspage-alternative-for-saas',
        'cachet-alternative-with-hosted-status-page',
        'updown-io-alternative-for-growing-teams',
        'cronitor-alternative-for-cron-monitoring',
        'ohdear-alternative-for-smb',
        'supermonitoring-alternative-for-agencies',
    ];

    public function __construct(
        private readonly MarketingContentRepository $content
    ) {
    }

    public function index(string $locale): View
    {
        $pages = [];
        foreach (self::ALTERNATIVE_SLUGS as $slug) {
            $page = $this->content->findSolution($locale, $slug);
            if (is_array($page)) {
                $pages[] = $page;
            }
        }

        return view('public.solutions.alternatives_hub', [
            'pages' => $pages,
        ]);
    }
}

