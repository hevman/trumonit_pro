<?php

namespace App\Services\SeoRisk;

use App\Models\SeoCoreUpdateEvent;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CoreUpdateSignalService
{
    public function syncFeed(): int
    {
        if (! (bool) config('wids.seo_risk.core_updates.enabled', true)) {
            return 0;
        }

        $feedUrl = trim((string) config('wids.seo_risk.core_updates.feed_url', ''));
        if ($feedUrl === '') {
            throw new RuntimeException('Core updates feed URL is missing.');
        }

        $response = Http::timeout((int) config('wids.seo_risk.core_updates.feed_timeout_seconds', 12))
            ->get($feedUrl);

        if (! $response->successful()) {
            throw new RuntimeException('Core updates feed request failed (status='.$response->status().').');
        }

        $xml = trim((string) $response->body());
        if ($xml === '') {
            return 0;
        }

        $feed = @simplexml_load_string($xml);
        if (! $feed) {
            throw new RuntimeException('Core updates feed XML could not be parsed.');
        }

        $provider = trim((string) config('wids.seo_risk.core_updates.provider', 'feed'));
        $keywords = $this->keywords();
        $defaultActiveDays = max(3, (int) config('wids.seo_risk.core_updates.default_active_days', 14));
        $defaultImpactScore = max(1, min(100, (int) config('wids.seo_risk.core_updates.default_impact_score', 75)));
        $synced = 0;

        foreach ($this->feedEntries($feed) as $entry) {
            $title = trim((string) ($entry['title'] ?? ''));
            $summary = trim((string) ($entry['summary'] ?? ''));

            if (! $this->containsAnyKeyword($title.' '.$summary, $keywords)) {
                continue;
            }

            $publishedAt = $this->parseDate((string) ($entry['published_at'] ?? ''));
            $effectiveFrom = $publishedAt?->toDateString();
            $effectiveTo = $publishedAt?->addDays($defaultActiveDays)->toDateString();
            $sourceUrl = trim((string) ($entry['source_url'] ?? ''));

            $hash = hash('sha256', implode('|', [
                $provider,
                mb_strtolower($title),
                $sourceUrl,
                $publishedAt?->toDateString() ?? '',
            ]));

            $event = SeoCoreUpdateEvent::query()->firstOrNew(['source_hash' => $hash]);
            $event->provider = $provider !== '' ? $provider : 'feed';
            $event->title = $title !== '' ? $title : 'Google update';
            $event->summary = $summary !== '' ? $summary : null;
            $event->source_url = $sourceUrl !== '' ? $sourceUrl : null;
            $event->published_at = $publishedAt;
            $event->effective_from = $effectiveFrom;
            $event->effective_to = $effectiveTo;
            $event->impact_score = $defaultImpactScore;
            $event->metadata = [
                'source' => 'rss',
                'matched_keywords' => $this->matchedKeywords($title.' '.$summary, $keywords),
            ];

            if ($event->isDirty()) {
                $event->save();
                $synced++;
            }
        }

        return $synced;
    }

    /**
     * @return array<int, SeoCoreUpdateEvent>
     */
    public function eventsAroundDate(CarbonInterface $metricDate): array
    {
        if (! (bool) config('wids.seo_risk.core_updates.enabled', true)) {
            return [];
        }

        $date = CarbonImmutable::parse($metricDate->toDateString())->startOfDay();
        $windowDays = max(0, (int) config('wids.seo_risk.core_updates.context_window_days', 10));
        $windowStart = $date->subDays($windowDays)->toDateString();
        $windowEnd = $date->addDays($windowDays)->toDateString();

        return SeoCoreUpdateEvent::query()
            ->where(function ($query) use ($date, $windowStart, $windowEnd): void {
                $query->where(function ($q) use ($date): void {
                    $q->whereNotNull('effective_from')
                        ->whereDate('effective_from', '<=', $date->toDateString())
                        ->where(function ($qq) use ($date): void {
                            $qq->whereNull('effective_to')
                                ->orWhereDate('effective_to', '>=', $date->toDateString());
                        });
                })->orWhere(function ($q) use ($windowStart, $windowEnd): void {
                    $q->whereNotNull('published_at')
                        ->whereBetween('published_at', [$windowStart.' 00:00:00', $windowEnd.' 23:59:59']);
                });
            })
            ->orderByDesc('impact_score')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $feed
     * @return array<int, array{title: string, summary: string, source_url: string, published_at: string}>
     */
    private function feedEntries(\SimpleXMLElement $feed): array
    {
        $entries = [];

        if (isset($feed->channel->item)) {
            foreach ($feed->channel->item as $item) {
                $entries[] = [
                    'title' => (string) ($item->title ?? ''),
                    'summary' => strip_tags((string) ($item->description ?? '')),
                    'source_url' => (string) ($item->link ?? ''),
                    'published_at' => (string) ($item->pubDate ?? ''),
                ];
            }
        } elseif (isset($feed->entry)) {
            foreach ($feed->entry as $entry) {
                $link = '';
                if (isset($entry->link)) {
                    $attributes = $entry->link->attributes();
                    $link = (string) ($attributes['href'] ?? '');
                }

                $entries[] = [
                    'title' => (string) ($entry->title ?? ''),
                    'summary' => strip_tags((string) ($entry->summary ?? $entry->content ?? '')),
                    'source_url' => $link,
                    'published_at' => (string) ($entry->updated ?? $entry->published ?? ''),
                ];
            }
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function keywords(): array
    {
        $raw = explode(',', (string) config('wids.seo_risk.core_updates.title_keywords', 'core update,broad core,google update'));

        return array_values(array_filter(array_map(
            static fn (string $item): string => mb_strtolower(trim($item)),
            $raw
        )));
    }

    /**
     * @param  list<string>  $keywords
     */
    private function containsAnyKeyword(string $haystack, array $keywords): bool
    {
        $needle = mb_strtolower($haystack);

        foreach ($keywords as $keyword) {
            if ($keyword !== '' && str_contains($needle, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $keywords
     * @return list<string>
     */
    private function matchedKeywords(string $haystack, array $keywords): array
    {
        $needle = mb_strtolower($haystack);
        $matched = [];

        foreach ($keywords as $keyword) {
            if ($keyword !== '' && str_contains($needle, $keyword)) {
                $matched[] = $keyword;
            }
        }

        return array_values(array_unique($matched));
    }

    private function parseDate(string $value): ?CarbonImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
