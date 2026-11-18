<?php

namespace App\Services\AI;

use App\Models\AiSetting;
use App\Models\AiUsage;
use Illuminate\Support\Facades\Schema;

class AiSettings
{
    public function keyConfigured(): bool
    {
        return trim((string) config('openai.api_key')) !== '';
    }

    public function model(): string
    {
        $chosen = Schema::hasTable('ai_settings') ? AiSetting::find('model')?->value : null;

        return in_array($chosen, config('ai.models'), true) ? $chosen : (string) config('ai.model');
    }

    public function setModel(string $model): void
    {
        AiSetting::updateOrCreate(['key' => 'model'], ['value' => $model]);
    }

    public function monthlyUsage(): array
    {
        $rows = AiUsage::where('created_at', '>=', now()->startOfMonth())->get();

        return [
            'requests' => $rows->count(),
            'failed' => $rows->where('status', '!=', 'ok')->count(),
            'input_tokens' => (int) $rows->sum('input_tokens'),
            'output_tokens' => (int) $rows->sum('output_tokens'),
            'cost_usd' => round((float) $rows->sum('cost_usd'), 4),
            'by_feature' => $rows->groupBy('feature')->map(fn ($g) => ['requests' => $g->count(), 'cost_usd' => round((float) $g->sum('cost_usd'), 4)])->all(),
        ];
    }
}
