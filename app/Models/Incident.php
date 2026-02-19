<?php

namespace App\Models;

use App\Jobs\SendIncidentEmailJob;
use App\Jobs\SendIncidentDiscordJob;
use App\Jobs\SendIncidentSlackJob;
use App\Jobs\SendIncidentTeamsJob;
use App\Jobs\SendIncidentWebhookJob;
use App\Services\BillingUsageService;
use App\Services\TenantNotificationChannelService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Incident extends Model
{
    /** @use HasFactory<\Database\Factories\IncidentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'monitored_url_id',
        'assigned_user_id',
        'previous_snapshot_id',
        'current_snapshot_id',
        'kind',
        'severity',
        'status',
        'summary',
        'details',
        'detected_at',
        'fingerprint',
        'last_seen_at',
        'occurrences',
        'resolved_at',
        'snoozed_until',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'detected_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
            'snoozed_until' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Incident $incident): void {
            app(BillingUsageService::class)->recordIncidentCreated($incident);

            $respectMute = (bool) config('wids.notifications.respect_mute', true);
            $respectCooldown = (bool) config('wids.notifications.respect_cooldown', true) && Schema::hasColumn('monitored_urls', 'alert_cooldown_minutes');

            $monitoredUrl = null;
            $cooldownMinutes = 0;

            if ($respectMute || $respectCooldown) {
                $columns = ['id'];
                if ($respectMute) {
                    $columns[] = 'muted_until';
                }
                if ($respectCooldown) {
                    $columns[] = 'alert_cooldown_minutes';
                }

                $monitoredUrl = MonitoredUrl::query()
                    ->where('tenant_id', $incident->tenant_id)
                    ->where('id', $incident->monitored_url_id)
                    ->first($columns);

                if ($respectMute && $monitoredUrl?->muted_until && $monitoredUrl->muted_until->isFuture()) {
                    return;
                }

                if ($respectCooldown) {
                    $cooldownMinutes = (int) ($monitoredUrl?->alert_cooldown_minutes ?? 0);
                    $cooldownMinutes = min(10080, max(0, $cooldownMinutes));
                }
            }

            if ((bool) config('wids.notifications.respect_maintenance', true) && Schema::hasTable('maintenance_windows')) {
                $now = now();

                $direct = MaintenanceWindow::query()
                    ->where('tenant_id', $incident->tenant_id)
                    ->where('starts_at', '<=', $now)
                    ->where('ends_at', '>=', $now)
                    ->where(function ($q) use ($incident): void {
                        $q->where(function ($qq): void {
                            $qq->whereNull('monitored_url_id')
                                ->whereNull('tag');
                        })->orWhere('monitored_url_id', $incident->monitored_url_id);
                    })
                    ->exists();

                if ($direct) {
                    return;
                }

                $tagNames = Tag::query()
                    ->select('tags.name')
                    ->join('monitored_url_tag', 'monitored_url_tag.tag_id', '=', 'tags.id')
                    ->where('tags.tenant_id', $incident->tenant_id)
                    ->where('monitored_url_tag.monitored_url_id', $incident->monitored_url_id)
                    ->pluck('tags.name')
                    ->values()
                    ->all();

                if ($tagNames !== []) {
                    $tagWindow = MaintenanceWindow::query()
                        ->where('tenant_id', $incident->tenant_id)
                        ->where('starts_at', '<=', $now)
                        ->where('ends_at', '>=', $now)
                        ->whereIn('tag', $tagNames)
                        ->exists();

                    if ($tagWindow) {
                        return;
                    }
                }
            }

            if ($respectCooldown && $cooldownMinutes > 0) {
                $key = 'wids:notify:cooldown:tenant:'.$incident->tenant_id.':monitored_url:'.$incident->monitored_url_id;

                if (! Cache::add($key, 1, now()->addMinutes($cooldownMinutes))) {
                    return;
                }
            }

            $channels = app(TenantNotificationChannelService::class)->channelsForTenantId((int) $incident->tenant_id);
            $severity = max(1, min(100, (int) $incident->severity));
            $canDispatch = static function (string $key) use ($channels, $severity): bool {
                $threshold = max(1, min(100, (int) ($channels[$key.'_min_severity'] ?? 1)));

                return $severity >= $threshold;
            };

            if ((bool) config('wids.notifications.webhook.enabled', false) && $canDispatch('webhook')) {
                $urls = $channels['webhook_urls'] ?? [];
                $webhookHumanReadableEnabled = (bool) ($channels['webhook_human_readable_enabled'] ?? false);

                if (is_array($urls)) {
                    foreach ($urls as $url) {
                        if (is_string($url) && trim($url) !== '') {
                            SendIncidentWebhookJob::dispatch($incident->id, $url, $webhookHumanReadableEnabled);
                        }
                    }
                }
            }

            if ((bool) config('wids.notifications.slack.enabled', false) && $canDispatch('slack')) {
                $urls = $channels['slack_webhook_urls'] ?? [];

                if (is_array($urls)) {
                    foreach ($urls as $url) {
                        if (is_string($url) && trim($url) !== '') {
                            SendIncidentSlackJob::dispatch($incident->id, $url);
                        }
                    }
                }
            }

            if ((bool) config('wids.notifications.teams.enabled', false) && $canDispatch('teams')) {
                $urls = $channels['teams_webhook_urls'] ?? [];

                if (is_array($urls)) {
                    foreach ($urls as $url) {
                        if (is_string($url) && trim($url) !== '') {
                            SendIncidentTeamsJob::dispatch($incident->id, $url);
                        }
                    }
                }
            }

            if ((bool) config('wids.notifications.discord.enabled', false) && $canDispatch('discord')) {
                $urls = $channels['discord_webhook_urls'] ?? [];

                if (is_array($urls)) {
                    foreach ($urls as $url) {
                        if (is_string($url) && trim($url) !== '') {
                            SendIncidentDiscordJob::dispatch($incident->id, $url);
                        }
                    }
                }
            }

            if ((bool) config('wids.notifications.email.enabled', false) && $canDispatch('email')) {
                $recipients = $channels['email_recipients'] ?? [];

                if (is_array($recipients)) {
                    foreach ($recipients as $recipient) {
                        if (is_string($recipient) && trim($recipient) !== '') {
                            SendIncidentEmailJob::dispatch($incident->id, $recipient);
                        }
                    }
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function monitoredUrl(): BelongsTo
    {
        return $this->belongsTo(MonitoredUrl::class);
    }

    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function previousSnapshot(): BelongsTo
    {
        return $this->belongsTo(UrlSnapshot::class, 'previous_snapshot_id');
    }

    public function currentSnapshot(): BelongsTo
    {
        return $this->belongsTo(UrlSnapshot::class, 'current_snapshot_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(IncidentComment::class);
    }
}
