<?php

namespace App\Http\Requests\Api;

use App\Models\Vault;
use App\ValueObjects\VaultContentEnvelope;
use Illuminate\Foundation\Http\FormRequest;

class UploadVaultFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'not_regex:/\.\./'],
            'base_version' => ['nullable', 'integer'],
            'content' => ['nullable', 'string'],
            'content_base64' => ['nullable', 'string'],
            'file' => ['nullable', 'file'],
            'encrypted' => ['nullable', 'boolean'],
            'is_encrypted' => ['nullable', 'boolean'],
            'iv' => ['nullable', 'string'],
            'encryption_iv' => ['nullable', 'string'],
            'tag' => ['nullable', 'string'],
            'encryption_tag' => ['nullable', 'string'],
            'format_version' => ['nullable', 'integer'],
            'ghost' => ['nullable', 'boolean'],
            'is_ghost' => ['nullable', 'boolean'],
            'plaintext_size' => ['nullable', 'integer'],
            'original_size' => ['nullable', 'integer'],
            'mime_type' => ['nullable', 'string'],
        ];
    }

    public function toEnvelope(Vault $vault): VaultContentEnvelope
    {
        $rawPayload = null;
        if ($this->hasFile('file')) {
            $file = $this->file('file');
            $contents = $file ? file_get_contents($file->getRealPath()) : false;
            $rawPayload = $contents !== false ? $contents : '';
        }

        return VaultContentEnvelope::fromValidated($this->validated(), $vault, $rawPayload);
    }
}
