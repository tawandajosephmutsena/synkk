<?php

namespace App\Http\Controllers;

use App\Models\VaultPortal;
use App\Services\Ai\PortalAiService;
use Illuminate\Http\Response;

class PortalLlmsController extends Controller
{
    public function show(string $slug, PortalAiService $portalAiService): Response
    {
        $portal = VaultPortal::with(['team', 'vault.aiSetting', 'vault.permissions'])
            ->where('slug', $slug)
            ->firstOrFail();

        if ($portal->vault->team_id !== $portal->team_id) {
            abort(404);
        }

        if (! $portal->llms_txt_enabled || $portal->vault->is_e2ee) {
            abort(404);
        }

        if (! $portal->is_public) {
            if (! auth()->check()) {
                abort(404);
            }

            if (! $portal->team->members()->where('users.id', auth()->id())->exists()) {
                abort(404);
            }
        }

        if ($portal->isPasswordProtected() && ! $portal->hasValidPasswordGrant()) {
            abort(403, 'This portal is password protected. Unlock the portal first.');
        }

        $body = $portalAiService->renderLlmsTxt($portal);

        $cacheControl = ($portal->is_public && ! $portal->isPasswordProtected())
            ? 'public, max-age=300'
            : 'private, no-store';

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => $cacheControl,
        ]);
    }
}
