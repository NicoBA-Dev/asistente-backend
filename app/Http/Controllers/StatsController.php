<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\AiLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StatsController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        // 1. Eventos por Categoría (PostgreSQL)
        $categories = Event::where('user_id', $userId)
            ->select('category', DB::raw('count(*) as total'))
            ->groupBy('category')
            ->get();

        // 2. Actividad de Voz últimos 7 días (MongoDB)
        // Agrupamos por fecha para ver cuántas veces usaste la IA
        $voiceActivity = AiLog::where('created_at', '>=', now()->subDays(7))
            ->get()
            ->groupBy(function ($log) {
                return Carbon::parse($log->created_at)->format('Y-m-d');
            })
            ->map(function ($group, $date) {
                return [
                    'fecha' => $date,
                    'cantidad' => $group->count()
                ];
            })->values();

        // 3. Resumen rápido
        $summary = [
            'total_eventos' => Event::where('user_id', $userId)->count(),
            'eventos_proximos' => Event::where('user_id', $userId)
                ->where('event_date_utc', '>', now())->count(),
            'total_interacciones_ia' => AiLog::count()
        ];

        return response()->json([
            'categories' => $categories,
            'voiceActivity' => $voiceActivity,
            'summary' => $summary
        ]);
    }
}