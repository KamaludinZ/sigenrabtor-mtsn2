<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Kolom jsonb berbentuk objek (CHECK jsonb_typeof = 'object'): dibaca sebagai array asosiatif, ditulis selalu sebagai
 * objek JSON — array kosong menjadi `{}` (bukan `[]`) dan kunci angka (mis. id akun) tetap kunci objek; null tetap null.
 *
 * @implements CastsAttributes<array<array-key, mixed>|null, array<array-key, mixed>|null>
 */
class ObjekJson implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        return $value === null ? null : (array) json_decode((string) $value, true);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : json_encode((object) $value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }
}
