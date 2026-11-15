<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\Organization;
use App\Models\User;
use App\Service\PermissionStore;
use App\Service\ValoWeeklySummaryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use LogicException;

/**
 * VALO's Time: the one-page weekly summary. Returns its HTML; the browser turns it into the PDF.
 */
class ValoWeeklySummaryController extends Controller
{
    public function show(Request $request, string $organizationId, ValoWeeklySummaryService $service, PermissionStore $permissions): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        /** @var Organization $organization */
        $organization = Organization::query()->findOrFail($organizationId);
        abort_unless($permissions->has($organization, 'time-entries:view:all'), 403);

        /** @var array{week_start: string} $data */
        $data = $request->validate([
            'week_start' => ['required', 'date_format:Y-m-d'],
        ]);
        $timezone = $user->timezone;
        $weekStart = CarbonImmutable::createFromFormat('Y-m-d', $data['week_start'], $timezone)->startOfDay();
        $summary = $service->build($organization, $weekStart, $timezone);

        $view = file_get_contents(resource_path('views/reports/valo-weekly/pdf.blade.php'));
        if ($view === false) {
            throw new LogicException('View file not found');
        }

        return response()->json([
            'html' => Blade::render($view, ['summary' => $summary, 'timezone' => $timezone]),
            'filename' => 'resumen-semana-'.$summary['week_number'].'-'.$weekStart->format('Y-m-d').'.pdf',
        ]);
    }
}
