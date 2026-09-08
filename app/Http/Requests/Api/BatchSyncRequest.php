<?php

namespace App\Http\Requests\Api;

use App\Models\Vault;
use App\ValueObjects\VaultContentEnvelope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class BatchSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('changes') && $this->has('items')) {
            $this->merge(['changes' => $this->input('items')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxBatch = (int) config('synkk.max_batch_size', 100);

        return [
            'changes' => ['required', 'array', "max:{$maxBatch}"],
            'changes.*.path' => ['required', 'string', 'not_regex:/\.\./'],
            'changes.*.action' => ['nullable', 'string', 'in:upload,delete'],
            'changes.*.content' => ['nullable', 'string'],
            'changes.*.content_base64' => ['nullable', 'string'],
            'changes.*.base_version' => ['nullable', 'integer'],
            'changes.*.encrypted' => ['nullable', 'boolean'],
            'changes.*.is_encrypted' => ['nullable', 'boolean'],
            'changes.*.iv' => ['nullable', 'string'],
            'changes.*.encryption_iv' => ['nullable', 'string'],
            'changes.*.tag' => ['nullable', 'string'],
            'changes.*.encryption_tag' => ['nullable', 'string'],
            'changes.*.format_version' => ['nullable', 'integer'],
            'changes.*.ghost' => ['nullable', 'boolean'],
            'changes.*.is_ghost' => ['nullable', 'boolean'],
            'changes.*.plaintext_size' => ['nullable', 'integer'],
            'changes.*.original_size' => ['nullable', 'integer'],
            'changes.*.mime_type' => ['nullable', 'string'],
        ];
    }

    /**
     * Validate and extract envelopes for all upload actions in the batch.
     *
     * @return array<int, array{action: string, path: string, base_version: int, envelope: VaultContentEnvelope|null}>
     *
     * @throws ValidationException
     */
    public function extractBatchItems(Vault $vault): array
    {
        $validated = $this->validated();
        $items = [];

        foreach ($validated['changes'] as $index => $change) {
            $action = $change['action'] ?? 'upload';
            $path = trim(str_replace('\\', '/', $change['path']), '/');
            $baseVersion = (int) ($change['base_version'] ?? 0);
            $envelope = null;

            if ($action === 'upload') {
                try {
                    $envelope = VaultContentEnvelope::fromValidated($change, $vault);
                } catch (ValidationException $e) {
                    $errors = [];
                    foreach ($e->errors() as $key => $messages) {
                        $errors["changes.{$index}.{$key}"] = $messages;
                        $errors[$key] = $messages;
                    }

                    throw ValidationException::withMessages($errors);
                }
            }

            $items[] = [
                'action' => $action,
                'path' => $path,
                'base_version' => $baseVersion,
                'envelope' => $envelope,
            ];
        }

        return $items;
    }
}
