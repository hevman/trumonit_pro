<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use Illuminate\Http\Request;

class SiemController extends Controller
{
    public function incidents(Request $request)
    {
        $tenantId = $request->user()?->tenant_id;

        if ($tenantId === null) {
            return response()->json(['message' => 'User has no tenant.'], 400);
        }

        $format = strtolower((string) $request->query('format', 'json'));
        $allowed = ['json', 'cef', 'leef'];

        if (! in_array($format, $allowed, true)) {
            return response()->json(['message' => 'Invalid format.'], 422);
        }

        $since = $request->query('since');
        $sinceAt = null;

        if (is_string($since) && trim($since) !== '') {
            try {
                $sinceAt = \Carbon\CarbonImmutable::parse($since);
            } catch (\Throwable) {
                return response()->json(['message' => 'Invalid since. Use ISO-8601.'], 422);
            }
        }

        $limit = (int) $request->query('limit', 200);
        $limit = min(1000, max(1, $limit));

        $query = Incident::query()
            ->with(['tenant', 'monitoredUrl'])
            ->where('tenant_id', $tenantId)
            ->when($sinceAt !== null, fn ($q) => $q->where('updated_at', '>=', $sinceAt))
            ->orderBy('updated_at')
            ->limit($limit);

        $incidents = $query->get();

        if ($format === 'json') {
            return response()->json([
                'tenant_id' => $tenantId,
                'since' => $sinceAt?->toISOString(),
                'count' => $incidents->count(),
                'incidents' => $incidents->map(function (Incident $incident): array {
                    return [
                        'id' => $incident->id,
                        'tenant_id' => $incident->tenant_id,
                        'tenant_slug' => $incident->tenant?->slug,
                        'monitored_url_id' => $incident->monitored_url_id,
                        'url' => $incident->monitoredUrl?->url,
                        'display_name' => $incident->monitoredUrl?->display_name,
                        'kind' => $incident->kind,
                        'severity' => $incident->severity,
                        'status' => $incident->status,
                        'summary' => $incident->summary,
                        'details' => $incident->details,
                        'detected_at' => $incident->detected_at?->toISOString(),
                        'last_seen_at' => $incident->last_seen_at?->toISOString(),
                        'occurrences' => $incident->occurrences,
                        'fingerprint' => $incident->fingerprint,
                        'updated_at' => $incident->updated_at?->toISOString(),
                    ];
                })->values(),
            ]);
        }

        $lines = $incidents->map(fn (Incident $incident) => $format === 'cef'
            ? $this->toCefLine($incident)
            : $this->toLeefLine($incident)
        )->implode("\n");

        return response($lines."\n", 200)->header('Content-Type', 'text/plain; charset=utf-8');
    }

    private function toCefLine(Incident $incident): string
    {
        $vendor = 'TruMonit';
        $product = 'wids';
        $version = '0.1';

        $signature = $this->cefEscape($incident->kind);
        $name = $this->cefEscape($incident->summary);
        $severity = (int) round(((int) $incident->severity) / 10);
        $severity = min(10, max(0, $severity));

        $rt = $incident->detected_at ? (string) $incident->detected_at->getTimestampMs() : null;

        $extensions = array_filter([
            'rt' => $rt,
            'cs1Label' => 'monitored_url',
            'cs1' => $incident->monitoredUrl?->url,
            'cs2Label' => 'incident_id',
            'cs2' => (string) $incident->id,
            'cs3Label' => 'status',
            'cs3' => $incident->status,
            'cs4Label' => 'tenant',
            'cs4' => $incident->tenant?->slug ?? (string) $incident->tenant_id,
            'cs5Label' => 'kind',
            'cs5' => $incident->kind,
            'cnt' => $incident->occurrences !== null ? (string) $incident->occurrences : null,
        ], fn ($v) => $v !== null && $v !== '');

        $extParts = [];
        foreach ($extensions as $k => $v) {
            $extParts[] = $k.'='.$this->cefExtensionEscape((string) $v);
        }

        $extension = implode(' ', $extParts);

        return 'CEF:0|'.$vendor.'|'.$product.'|'.$version.'|'.$signature.'|'.$name.'|'.$severity.'|'.$extension;
    }

    private function toLeefLine(Incident $incident): string
    {
        $vendor = 'TruMonit';
        $product = 'wids';
        $version = '0.1';
        $eventId = $incident->kind;

        $parts = array_filter([
            'incidentId='.$incident->id,
            'tenantId='.$incident->tenant_id,
            'tenantSlug='.($incident->tenant?->slug ?? ''),
            'url='.($incident->monitoredUrl?->url ?? ''),
            'displayName='.($incident->monitoredUrl?->display_name ?? ''),
            'severity='.$incident->severity,
            'status='.$incident->status,
            'summary='.$this->leefEscape($incident->summary),
            'detectedAt='.($incident->detected_at?->toISOString() ?? ''),
            'lastSeenAt='.($incident->last_seen_at?->toISOString() ?? ''),
            'occurrences='.($incident->occurrences ?? ''),
            'fingerprint='.($incident->fingerprint ?? ''),
        ], fn ($v) => ! str_ends_with($v, '='));

        return 'LEEF:2.0|'.$vendor.'|'.$product.'|'.$version.'|'.$this->leefEscape($eventId).'|'.implode("\t", $parts);
    }

    private function cefEscape(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace('|', '\\|', $value);

        return $value;
    }

    private function cefExtensionEscape(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace('=', '\\=', $value);
        $value = str_replace(["\r", "\n"], ['\\r', '\\n'], $value);

        return $value;
    }

    private function leefEscape(string $value): string
    {
        return str_replace(["\r", "\n", "\t"], [' ', ' ', ' '], $value);
    }
}

