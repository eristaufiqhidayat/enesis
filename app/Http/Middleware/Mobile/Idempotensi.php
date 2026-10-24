<?php

namespace App\Http\Middleware\Mobile;

use App\Support\Mobile\ApiError;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

// POST transaksi wajib membawa header Idempotency-Key (UUID dari HP). Respons sukses disimpan;
// kunci yang sama dikirim ulang => respons yang sama, status 200, meta.duplicate = true.
// Field client_ref yang kosong diisi dari kunci ini.
class Idempotensi
{
    public function handle(Request $request, Closure $next): Response
    {
        $kunci = (string) $request->header('Idempotency-Key');

        if (! Str::isUuid($kunci)) {
            return ApiError::respons('idempotency_key_required', 'Header Idempotency-Key (UUID) wajib untuk transaksi ini.', 422);
        }

        $userId = $request->user()->id;
        $endpoint = $request->method().' '.$request->route()->uri();
        $lama = DB::table('mobile_idempotency')->where('user_id', $userId)->where('kunci', $kunci)->first();

        if ($lama) {
            if ($lama->endpoint !== $endpoint) {
                return ApiError::respons('idempotency_key_reused', 'Idempotency-Key ini sudah dipakai untuk transaksi lain.', 409);
            }

            return $this->duplikat(json_decode($lama->respons, true));
        }

        if (! $request->filled('client_ref')) {
            $request->merge(['client_ref' => $kunci]);
        }

        $response = $next($request);

        if ($response->isSuccessful() && $response instanceof JsonResponse) {
            try {
                DB::table('mobile_idempotency')->insert([
                    'user_id' => $userId,
                    'kunci' => $kunci,
                    'endpoint' => $endpoint,
                    'status' => $response->getStatusCode(),
                    'respons' => $response->getContent(),
                    'created_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                // Dua kiriman bersamaan: yang pertama tersimpan, data unik client_ref menjaga transaksi.
            }
        }

        return $response;
    }

    private function duplikat(?array $isi): JsonResponse
    {
        $isi ??= [];
        $isi['meta'] = array_merge($isi['meta'] ?? [], ['duplicate' => true]);

        return response()->json($isi, 200);
    }
}
