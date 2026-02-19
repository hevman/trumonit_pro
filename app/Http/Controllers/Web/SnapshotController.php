<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\UrlSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SnapshotController extends Controller
{
    public function show(Request $request, UrlSnapshot $snapshot)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');

        $snapshot->loadMissing('monitoredUrl');

        abort_if($snapshot->monitoredUrl?->tenant_id !== $tenantId, 404);

        $bodyPreview = null;
        $bodyTruncated = false;

        if ($snapshot->body_storage_path && ($snapshot->body_size ?? 0) <= 200_000 && Storage::exists($snapshot->body_storage_path)) {
            $body = Storage::get($snapshot->body_storage_path);
            $bodyPreview = mb_substr($body, 0, 8000);
            $bodyTruncated = mb_strlen($body) > 8000;
        }

        return view('app.snapshots.show', [
            'snapshot' => $snapshot,
            'bodyPreview' => $bodyPreview,
            'bodyTruncated' => $bodyTruncated,
        ]);
    }

    public function body(Request $request, UrlSnapshot $snapshot)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');

        $snapshot->loadMissing('monitoredUrl');

        abort_if($snapshot->monitoredUrl?->tenant_id !== $tenantId, 404);
        abort_if($snapshot->body_storage_path === null, 404);
        abort_unless(Storage::exists($snapshot->body_storage_path), 404);

        $body = Storage::get($snapshot->body_storage_path);

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function screenshot(Request $request, UrlSnapshot $snapshot)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');

        $snapshot->loadMissing('monitoredUrl');

        abort_if($snapshot->monitoredUrl?->tenant_id !== $tenantId, 404);
        abort_if($snapshot->screenshot_storage_path === null, 404);
        abort_unless(Storage::exists($snapshot->screenshot_storage_path), 404);

        $png = Storage::get($snapshot->screenshot_storage_path);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="snapshot-'.$snapshot->id.'.png"',
        ]);
    }
}
