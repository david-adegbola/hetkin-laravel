<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Models\AuditLogEntry;
use App\Domain\Audit\Services\AuditChainIntegrityChecker;
use App\Domain\Organizations\Models\Organization;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mirrors the Next.js app's src/app/admin/(protected)/audit-log/page.tsx —
 * fully read-only (v2 has no actions.ts in that directory; every entry is
 * written by App\Domain\Audit\Services\AuditLogService from elsewhere). No
 * role gate — any active admin can view it.
 *
 * The hash-chain integrity banner runs unconditionally on every page load
 * (App\Domain\Audit\Services\AuditChainIntegrityChecker, built ahead of
 * time in Stage 1 anticipating this page) — there's no "verify" button in
 * v2, matching that here.
 */
class AuditLogController extends Controller
{
    private const PAGE_SIZE = 50;

    public function index(Request $request, AuditChainIntegrityChecker $integrityChecker): Response
    {
        $organizationId = $request->string('organization_id')->toString() ?: null;
        $actorType = $request->string('actor_type')->toString() ?: null;
        $entityType = $request->string('entity_type')->toString() ?: null;
        $action = $request->string('action')->toString() ?: null;
        $from = $request->string('from')->toString() ?: null;
        $to = $request->string('to')->toString() ?: null;
        $page = max(1, $request->integer('page', 1));

        $query = AuditLogEntry::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->when($actorType, fn ($q) => $q->where('actor_type', $actorType))
            ->when($entityType, fn ($q) => $q->where('entity_type', $entityType))
            ->when($action, fn ($q) => $q->whereRaw('LOWER(action) LIKE ?', ['%'.strtolower($action).'%']))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', "{$to} 23:59:59.999999"));

        $total = (clone $query)->count();

        $entries = $query->orderByDesc('created_at')
            ->forPage($page, self::PAGE_SIZE)
            ->with('organization')
            ->get();

        $integrity = $integrityChecker->verify();

        return Inertia::render('Admin/AuditLog', [
            'entries' => $entries->map(fn (AuditLogEntry $entry) => [
                'id' => $entry->id,
                'organization_id' => $entry->organization_id,
                'organization_name' => $entry->organization?->name,
                'actor_type' => $entry->actor_type->value,
                'actor_id' => $entry->actor_id,
                'action' => $entry->action,
                'entity_type' => $entry->entity_type,
                'entity_id' => $entry->entity_id,
                'metadata' => $entry->metadata,
                'created_at' => $entry->created_at->toIso8601String(),
            ]),
            'total' => $total,
            'page' => $page,
            'page_size' => self::PAGE_SIZE,
            'filters' => [
                'organization_id' => $organizationId,
                'actor_type' => $actorType,
                'entity_type' => $entityType,
                'action' => $action,
                'from' => $from,
                'to' => $to,
            ],
            'filtered_organization_name' => $organizationId
                ? Organization::query()->find($organizationId)?->name
                : null,
            'integrity' => $integrity,
        ]);
    }
}
