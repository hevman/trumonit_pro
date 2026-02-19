<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MonitoredUrl;
use App\Models\StatusPage;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StatusPageController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');

        $isOwner = $request->user()?->role !== 'member';

        $statusPages = StatusPage::query()
            ->where('tenant_id', $tenantId)
            ->withCount('monitoredUrls')
            ->orderByDesc('id')
            ->get();

        $summary = [
            'total' => $statusPages->count(),
            'public' => $statusPages->where('is_public', true)->count(),
            'private' => $statusPages->where('is_public', false)->count(),
            'custom_domain' => $statusPages->filter(fn (StatusPage $statusPage): bool => is_string($statusPage->custom_domain) && trim($statusPage->custom_domain) !== '')->count(),
            'monitors_exposed' => (int) $statusPages->sum('monitored_urls_count'),
        ];

        $summary['avg_monitors_per_page'] = $summary['total'] > 0
            ? round($summary['monitors_exposed'] / $summary['total'], 1)
            : null;

        return view('app.status-pages.index', [
            'statusPages' => $statusPages,
            'isOwner' => $isOwner,
            'summary' => $summary,
        ]);
    }

    public function show(Request $request, StatusPage $statusPage)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');
        abort_if($statusPage->tenant_id !== $tenantId, 404);

        $isOwner = $request->user()?->role !== 'member';

        $statusPage->loadMissing(['monitoredUrls']);

        return view('app.status-pages.show', [
            'statusPage' => $statusPage,
            'isOwner' => $isOwner,
        ]);
    }

    public function create(Request $request)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');

        $monitoredUrls = MonitoredUrl::query()
            ->where('tenant_id', $tenantId)
            ->orderByRaw('COALESCE(display_name, url) asc')
            ->get();

        return view('app.status-pages.create', [
            'monitoredUrls' => $monitoredUrls,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');

        $request->merge([
            'custom_domain' => $this->normalizeCustomDomain($request->input('custom_domain')),
        ]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('status_pages', 'slug')],
            'custom_domain' => ['nullable', 'string', 'max:253', 'regex:/^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', Rule::unique('status_pages', 'custom_domain')],
            'cache_ttl_seconds' => ['nullable', 'integer', 'min:5', 'max:600'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_public' => ['nullable', 'boolean'],
            'monitored_url_ids' => ['required', 'array', 'min:1', 'max:200'],
            'monitored_url_ids.*' => ['integer', 'min:1'],
        ]);

        $monitoredUrlIds = $this->validateMonitoredUrlIds($tenantId, $data['monitored_url_ids'] ?? []);

        $slug = is_string($data['slug'] ?? null) ? trim((string) $data['slug']) : '';
        $slug = $slug !== '' ? $slug : $this->generateUniqueSlug((string) $data['title']);

        $statusPage = StatusPage::query()->create([
            'tenant_id' => $tenantId,
            'created_by_user_id' => $request->user()?->id,
            'title' => $data['title'],
            'slug' => $slug,
            'custom_domain' => $data['custom_domain'] ?? null,
            'cache_ttl_seconds' => $this->normalizeCacheTtl($data['cache_ttl_seconds'] ?? null),
            'description' => $data['description'] ?? null,
            'is_public' => (bool) ($data['is_public'] ?? false),
        ]);

        $statusPage->monitoredUrls()->sync($this->syncPayloadForMonitoredUrls($monitoredUrlIds));

        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: $request->user()?->id,
            action: 'status_page.created',
            auditableType: StatusPage::class,
            auditableId: $statusPage->id,
            data: [
                'slug' => $statusPage->slug,
                'custom_domain' => $statusPage->custom_domain,
                'cache_ttl_seconds' => $statusPage->cache_ttl_seconds,
                'is_public' => (bool) $statusPage->is_public,
                'monitored_url_ids' => $monitoredUrlIds,
            ],
            request: $request,
        );

        return redirect()
            ->route('status-pages.show', $statusPage)
            ->with('status', 'Status page created.');
    }

    public function edit(Request $request, StatusPage $statusPage)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');
        abort_if($statusPage->tenant_id !== $tenantId, 404);

        $statusPage->loadMissing('monitoredUrls');

        $monitoredUrls = MonitoredUrl::query()
            ->where('tenant_id', $tenantId)
            ->orderByRaw('COALESCE(display_name, url) asc')
            ->get();

        $selectedIds = $statusPage->monitoredUrls->pluck('id')->values()->all();

        return view('app.status-pages.edit', [
            'statusPage' => $statusPage,
            'monitoredUrls' => $monitoredUrls,
            'selectedIds' => $selectedIds,
        ]);
    }

    public function update(Request $request, StatusPage $statusPage)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');
        abort_if($statusPage->tenant_id !== $tenantId, 404);

        $before = $statusPage->only(['title', 'slug', 'custom_domain', 'cache_ttl_seconds', 'description', 'is_public']);
        $before['monitored_url_ids'] = $statusPage->monitoredUrls()->pluck('monitored_urls.id')->values()->all();

        $request->merge([
            'custom_domain' => $this->normalizeCustomDomain($request->input('custom_domain')),
        ]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('status_pages', 'slug')->ignore($statusPage->id)],
            'custom_domain' => ['nullable', 'string', 'max:253', 'regex:/^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', Rule::unique('status_pages', 'custom_domain')->ignore($statusPage->id)],
            'cache_ttl_seconds' => ['nullable', 'integer', 'min:5', 'max:600'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_public' => ['nullable', 'boolean'],
            'monitored_url_ids' => ['required', 'array', 'min:1', 'max:200'],
            'monitored_url_ids.*' => ['integer', 'min:1'],
        ]);

        $monitoredUrlIds = $this->validateMonitoredUrlIds($tenantId, $data['monitored_url_ids'] ?? []);

        $statusPage->fill([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'custom_domain' => $data['custom_domain'] ?? null,
            'cache_ttl_seconds' => $this->normalizeCacheTtl($data['cache_ttl_seconds'] ?? null),
            'description' => $data['description'] ?? null,
            'is_public' => (bool) ($data['is_public'] ?? false),
        ]);
        $statusPage->save();

        $statusPage->monitoredUrls()->sync($this->syncPayloadForMonitoredUrls($monitoredUrlIds));

        $after = $statusPage->only(['title', 'slug', 'custom_domain', 'cache_ttl_seconds', 'description', 'is_public']);
        $after['monitored_url_ids'] = $monitoredUrlIds;

        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: $request->user()?->id,
            action: 'status_page.updated',
            auditableType: StatusPage::class,
            auditableId: $statusPage->id,
            data: [
                'before' => $before,
                'after' => $after,
            ],
            request: $request,
        );

        return redirect()
            ->route('status-pages.show', $statusPage)
            ->with('status', 'Status page updated.');
    }

    public function destroy(Request $request, StatusPage $statusPage)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');
        abort_if($statusPage->tenant_id !== $tenantId, 404);

        $slug = $statusPage->slug;

        $statusPage->delete();

        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: $request->user()?->id,
            action: 'status_page.deleted',
            auditableType: StatusPage::class,
            auditableId: $statusPage->id,
            data: [
                'slug' => $slug,
            ],
            request: $request,
        );

        return redirect()
            ->route('status-pages.index')
            ->with('status', 'Status page deleted.');
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<int>
     */
    private function validateMonitoredUrlIds(int $tenantId, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_values(array_filter($ids, fn ($id) => $id > 0));

        if ($ids === []) {
            throw ValidationException::withMessages([
                'monitored_url_ids' => ['Select at least one monitor.'],
            ]);
        }

        $existing = MonitoredUrl::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        $existingSet = array_fill_keys($existing, true);
        $missing = array_values(array_filter($ids, fn ($id) => ! isset($existingSet[$id])));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'monitored_url_ids' => ['Some selected monitors do not exist.'],
            ]);
        }

        return $ids;
    }

    /**
     * @param  list<int>  $monitoredUrlIds
     * @return array<int, array<string, int>>
     */
    private function syncPayloadForMonitoredUrls(array $monitoredUrlIds): array
    {
        $sync = [];

        foreach (array_values($monitoredUrlIds) as $position => $monitoredUrlId) {
            $sync[(int) $monitoredUrlId] = ['position' => (int) $position];
        }

        return $sync;
    }

    private function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $base = $base !== '' ? $base : 'status';
        $base = strtolower(substr($base, 0, 60));

        $slug = $base;

        for ($i = 0; $i < 20; $i++) {
            if (! StatusPage::query()->where('slug', $slug)->exists()) {
                return $slug;
            }

            $suffix = strtolower(Str::random(6));
            $slug = substr($base, 0, 70).'-'.$suffix;
        }

        return $base.'-'.strtolower(Str::random(8));
    }

    private function normalizeCacheTtl(mixed $value): int
    {
        $ttl = is_numeric($value) ? (int) $value : 30;

        return min(600, max(5, $ttl));
    }

    private function normalizeCustomDomain(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $domain = strtolower(trim($value));

        if ($domain === '') {
            return null;
        }

        if (str_contains($domain, '://')) {
            $parsedHost = parse_url($domain, PHP_URL_HOST);

            if (is_string($parsedHost) && $parsedHost !== '') {
                $domain = strtolower(trim($parsedHost));
            }
        }

        if (str_contains($domain, '/')) {
            $domain = explode('/', $domain, 2)[0];
        }

        $domain = rtrim($domain, '.');

        return $domain !== '' ? $domain : null;
    }
}
