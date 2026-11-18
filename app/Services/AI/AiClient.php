<?php

namespace App\Services\AI;

use App\Models\AiUsage;
use App\Models\AuditLog;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * The single gateway to OpenAI. Every call uses a strict JSON schema, is retried on transport
 * errors, and writes a usage + audit summary (never the prompt, the response body or the key).
 */
class AiClient
{
    public function __construct(private AiSettings $settings) {}

    public function configured(): bool
    {
        return $this->settings->keyConfigured();
    }

    /**
     * @param  string|array  $user  Either text or a list of chat content parts (text / image_url / file).
     * @return array The decoded structured output.
     */
    public function structured(string $feature, string $system, string|array $user, string $schemaName, array $schema, ?int $actorId = null): array
    {
        if (! $this->configured()) {
            throw new AiException('OpenAI is not configured. Add OPENAI_API_KEY to .env and run php artisan config:clear.');
        }
        $model = $this->settings->model();
        $max = max(1, (int) config('ai.max_attempts', 3));
        $last = null;
        for ($attempt = 1; $attempt <= $max; $attempt++) {
            try {
                $response = OpenAI::chat()->create([
                    'model' => $model,
                    'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
                    'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => $schemaName, 'strict' => true, 'schema' => $schema]],
                    'max_completion_tokens' => (int) config('ai.max_output_tokens'),
                ]);
                $this->record($feature, $model, 'ok', $response->usage?->promptTokens ?? 0, $response->usage?->completionTokens ?? 0, $actorId, $attempt);
                $choice = $response->choices[0] ?? null;
                if (! $choice || ($choice->message->refusal ?? null)) {
                    throw new AiException('The AI declined this request.');
                }
                $data = json_decode((string) $choice->message->content, true);
                if (! is_array($data)) {
                    throw new AiException('The AI returned output that was not valid JSON.');
                }

                return $data;
            } catch (AiException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $last = $e;
                $this->record($feature, $model, 'error', 0, 0, $actorId, $attempt);
                if ($attempt < $max) {
                    usleep((int) config('ai.retry_delay_ms') * 1000 * $attempt);
                }
            }
        }
        throw new AiException('The AI service is unavailable right now. Please try again shortly.', 0, $last);
    }

    private function record(string $feature, string $model, string $status, int $in, int $out, ?int $actor, int $attempt): void
    {
        $price = (config('ai.pricing')[$model] ?? [0, 0]);
        $cost = ($in * $price[0] + $out * $price[1]) / 1_000_000;
        AiUsage::create(['user_id' => $actor, 'feature' => $feature, 'model' => $model, 'status' => $status, 'input_tokens' => $in, 'output_tokens' => $out, 'cost_usd' => $cost]);
        AuditLog::create(['actor_id' => $actor, 'action' => 'ai.request', 'entity_type' => 'ai', 'changes' => ['feature' => $feature, 'model' => $model, 'status' => $status, 'attempt' => $attempt, 'input_tokens' => $in, 'output_tokens' => $out, 'cost_usd' => round($cost, 6)]]);
    }

    /** Generic propose → validate → retry loop. $propose receives the previous errors (or []). */
    public function proposeAndValidate(callable $propose, callable $validate, ?callable $progress = null): array
    {
        $errors = [];
        $proposal = null;
        $max = (int) config('ai.max_attempts', 3);
        for ($i = 1; $i <= $max; $i++) {
            $progress && $progress($i, $max);
            $proposal = $propose($errors, $proposal);
            $errors = $validate($proposal);
            if (! $errors) {
                break;
            }
        }

        return ['proposal' => $proposal, 'errors' => array_values($errors), 'attempts' => $i > $max ? $max : $i];
    }
}
