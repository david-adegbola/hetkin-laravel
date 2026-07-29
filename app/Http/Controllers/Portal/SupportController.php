<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mirrors the Next.js app's src/app/(portal)/dashboard/support/page.tsx —
 * restored per MIGRATION_PLAN.md's post-Stage-12 ADR (deferred at Stage 5
 * by ADR-33). Static content, no query of its own — same as v2's own
 * comment on this page ("No requirePortalContext() call here"), the auth
 * gate and organization resolution are already handled by
 * `organization.context` middleware (routes/portal.php) before this ever
 * runs.
 */
class SupportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Portal/Support');
    }
}
