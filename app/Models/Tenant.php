<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    /** @use HasFactory<\Database\Factories\TenantFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'plan_tier',
        'notification_channels',
        'identity_governance',
        'billing_provider',
        'billing_customer_ref',
        'billing_subscription_ref',
        'billing_subscription_status',
        'billing_plan_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'notification_channels' => 'array',
            'identity_governance' => 'array',
            'billing_plan_changed_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function monitoredUrls(): HasMany
    {
        return $this->hasMany(MonitoredUrl::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function statusPages(): HasMany
    {
        return $this->hasMany(StatusPage::class);
    }

    public function savedViews(): HasMany
    {
        return $this->hasMany(SavedView::class);
    }

    public function billingCheckoutSessions(): HasMany
    {
        return $this->hasMany(BillingCheckoutSession::class);
    }

    public function privateProbes(): HasMany
    {
        return $this->hasMany(PrivateProbe::class);
    }

    public function privateProbeScanTasks(): HasMany
    {
        return $this->hasMany(PrivateProbeScanTask::class);
    }

    public function weeklyDigestReports(): HasMany
    {
        return $this->hasMany(WeeklyDigestReport::class);
    }

    public function ssoProviders(): HasMany
    {
        return $this->hasMany(TenantSsoProvider::class);
    }

    public function userIdentities(): HasMany
    {
        return $this->hasMany(UserIdentity::class);
    }

    public function scimProvider(): HasOne
    {
        return $this->hasOne(TenantScimProvider::class);
    }

    public function scimGroups(): HasMany
    {
        return $this->hasMany(TenantScimGroup::class);
    }

    public function seoIntegrations(): HasMany
    {
        return $this->hasMany(SeoIntegration::class);
    }

    public function seoProperties(): HasMany
    {
        return $this->hasMany(SeoProperty::class);
    }

    public function seoDailyMetrics(): HasMany
    {
        return $this->hasMany(SeoDailyMetric::class);
    }

    public function seoBacklinkMetrics(): HasMany
    {
        return $this->hasMany(SeoBacklinkMetric::class);
    }

    public function seoIndexMetrics(): HasMany
    {
        return $this->hasMany(SeoIndexMetric::class);
    }
}
