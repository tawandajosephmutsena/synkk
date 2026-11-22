<?php

namespace App\Services\Ai;

class VectorCodec
{
    /**
     * Pack a float embedding vector into little-endian IEEE-754 float32 binary format.
     *
     * @param  array<int, float>  $vector
     */
    public function pack(array $vector): string
    {
        if (empty($vector)) {
            return '';
        }

        $floats = array_map(fn (mixed $value): float => (float) $value, array_values($vector));

        return pack('g*', ...$floats);
    }

    /**
     * Unpack a little-endian float32 binary blob back into a float array.
     *
     * @return array<int, float>
     */
    public function unpack(?string $blob): array
    {
        if ($blob === null || $blob === '' || (strlen($blob) % 4) !== 0) {
            return [];
        }

        $unpacked = unpack('g*', $blob);
        if (! is_array($unpacked)) {
            return [];
        }

        return array_values(array_map(fn (mixed $value): float => (float) $value, $unpacked));
    }

    /**
     * Compute cosine similarity between two float vectors.
     *
     * @param  array<int, float>  $vecA
     * @param  array<int, float>  $vecB
     */
    public function cosineSimilarity(array $vecA, array $vecB): float
    {
        $count = count($vecA);
        if ($count === 0 || $count !== count($vecB)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $a = (float) $vecA[$i];
            $b = (float) $vecB[$i];

            $dotProduct += $a * $b;
            $normA += $a * $a;
            $normB += $b * $b;
        }

        if ($normA <= 0.000001 || $normB <= 0.000001) {
            return 0.0;
        }

        $similarity = $dotProduct / (sqrt($normA) * sqrt($normB));

        return max(-1.0, min(1.0, round($similarity, 4)));
    }
}
