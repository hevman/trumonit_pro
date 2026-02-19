<?php

namespace App\Services;

use App\Models\MonitoredUrl;
use App\Models\Tag;

class TagService
{
    public const int MAX_TAGS = 20;

    public const int MAX_TAG_LENGTH = 32;

    public function normalize(?string $tag): ?string
    {
        if (! is_string($tag)) {
            return null;
        }

        $tag = strtolower(trim($tag));

        if ($tag === '') {
            return null;
        }

        if (strlen($tag) > self::MAX_TAG_LENGTH) {
            return null;
        }

        if (! preg_match('/\A[a-z0-9][a-z0-9:_-]{0,31}\z/', $tag)) {
            return null;
        }

        return $tag;
    }

    /**
     * @return list<string>
     */
    public function normalizeManyFromString(?string $input): array
    {
        if (! is_string($input) || trim($input) === '') {
            return [];
        }

        $parts = preg_split('/[,\n]+/', $input) ?: [];
        $parts = array_values(array_filter(array_map(fn ($p) => is_string($p) ? trim($p) : '', $parts), fn ($p) => $p !== ''));

        if (count($parts) > self::MAX_TAGS) {
            throw new \InvalidArgumentException('Too many tags (max '.self::MAX_TAGS.').');
        }

        $names = [];
        $invalid = [];

        foreach ($parts as $part) {
            $name = $this->normalize($part);

            if ($name === null) {
                $invalid[] = $part;
                continue;
            }

            $names[$name] = true;
        }

        if ($invalid !== []) {
            throw new \InvalidArgumentException('Invalid tag(s): '.implode(', ', array_slice($invalid, 0, 10)).'. Use a-z0-9:_- (max 32 chars).');
        }

        return array_values(array_keys($names));
    }

    /**
     * @param  mixed  $tags
     * @return list<string>
     */
    public function normalizeManyFromArray(mixed $tags): array
    {
        if (! is_array($tags)) {
            return [];
        }

        $tags = array_values($tags);

        if (count($tags) > self::MAX_TAGS) {
            throw new \InvalidArgumentException('Too many tags (max '.self::MAX_TAGS.').');
        }

        $names = [];
        $invalid = [];

        foreach ($tags as $tag) {
            if (! is_string($tag)) {
                $invalid[] = (string) json_encode($tag);
                continue;
            }

            $name = $this->normalize($tag);

            if ($name === null) {
                $invalid[] = $tag;
                continue;
            }

            $names[$name] = true;
        }

        if ($invalid !== []) {
            throw new \InvalidArgumentException('Invalid tag(s): '.implode(', ', array_slice($invalid, 0, 10)).'. Use a-z0-9:_- (max 32 chars).');
        }

        return array_values(array_keys($names));
    }

    /**
     * @param  list<string>  $tagNames
     */
    public function syncMonitoredUrlTags(MonitoredUrl $monitoredUrl, array $tagNames): void
    {
        $tagNames = array_values(array_unique(array_filter($tagNames, fn ($t) => is_string($t) && $t !== '')));

        if ($tagNames === []) {
            $monitoredUrl->tags()->sync([]);

            return;
        }

        $tagIds = [];

        foreach (array_slice($tagNames, 0, self::MAX_TAGS) as $name) {
            $tag = Tag::query()->firstOrCreate([
                'tenant_id' => $monitoredUrl->tenant_id,
                'name' => $name,
            ]);

            $tagIds[] = $tag->id;
        }

        $monitoredUrl->tags()->sync($tagIds);
    }
}
