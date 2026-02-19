<?php

namespace App\Services;

use Illuminate\Support\Facades\Lang;

class MonitorTemplateService
{
    /**
     * @return array<string, array{name: string, description: string, defaults: array<string, mixed>}>
     */
    public function all(): array
    {
        $configured = config('wids.monitor_templates', []);

        if (! is_array($configured)) {
            return [];
        }

        $templates = [];

        foreach ($configured as $key => $template) {
            if (! is_string($key) || trim($key) === '' || ! is_array($template)) {
                continue;
            }

            $defaultName = is_string($template['name'] ?? null) && trim((string) $template['name']) !== ''
                ? (string) $template['name']
                : $key;
            $defaultDescription = is_string($template['description'] ?? null)
                ? trim((string) $template['description'])
                : '';

            $templates[$key] = [
                'name' => $this->localizedTemplateField($key, 'name', $defaultName),
                'description' => $this->localizedTemplateField($key, 'description', $defaultDescription),
                'defaults' => is_array($template['defaults'] ?? null)
                    ? $template['defaults']
                    : [],
            ];
        }

        return $templates;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /**
     * @return array{name: string, description: string, defaults: array<string, mixed>}|null
     */
    public function find(?string $key): ?array
    {
        if (! is_string($key) || trim($key) === '') {
            return null;
        }

        $templates = $this->all();

        return $templates[$key] ?? null;
    }

    /**
     * @return list<array{key: string, name: string, description: string, defaults: array<string, mixed>}>
     */
    public function frontendTemplates(): array
    {
        $items = [];

        foreach ($this->all() as $key => $template) {
            $items[] = [
                'key' => $key,
                'name' => $template['name'],
                'description' => $template['description'],
                'defaults' => $template['defaults'],
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function applyDefaults(array $data, ?string $templateKey): array
    {
        $template = $this->find($templateKey);

        if (! $template) {
            return $data;
        }

        foreach ($template['defaults'] as $field => $value) {
            $currentValue = $data[$field] ?? null;

            if ($currentValue !== null && $currentValue !== '') {
                continue;
            }

            if (in_array($field, ['journey_steps', 'content_rules'], true) && is_array($value)) {
                $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $data[$field] = is_string($encoded) ? $encoded : null;
                continue;
            }

            $data[$field] = $value;
        }

        return $data;
    }

    private function localizedTemplateField(string $templateKey, string $field, string $fallback): string
    {
        $langKey = 'messages.onboarding.templates.'.$templateKey.'.'.$field;

        if (! Lang::has($langKey)) {
            return $fallback;
        }

        $translated = (string) __($langKey);

        return trim($translated) !== '' ? $translated : $fallback;
    }
}
