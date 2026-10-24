<?php

namespace App\Support\Mobile;

use Illuminate\Http\JsonResponse;
use RuntimeException;

// Error API mobile dengan kode stabil untuk logika aplikasi dan pesan Indonesia siap tampil.
// Bentuk respons: {"error": {"code": "...", "message": "...", "fields": {...}}}.
class ApiError extends RuntimeException
{
    public function __construct(
        public readonly string $kode,
        string $pesan,
        public readonly int $status = 422,
        public readonly array $fields = [],
        public readonly array $headers = [],
    ) {
        parent::__construct($pesan);
    }

    public static function respons(string $kode, string $pesan, int $status, array $fields = [], array $headers = []): JsonResponse
    {
        $error = ['code' => $kode, 'message' => $pesan];

        if ($fields !== []) {
            $error['fields'] = $fields;
        }

        return response()->json(['error' => $error], $status, $headers);
    }

    public function render(): JsonResponse
    {
        return self::respons($this->kode, $this->getMessage(), $this->status, $this->fields, $this->headers);
    }

    public static function konflik(string $kode, string $pesan, array $fields = []): self
    {
        return new self($kode, $pesan, 409, $fields);
    }

    public static function tidakAda(string $pesan = 'Data tidak ditemukan.'): self
    {
        return new self('not_found', $pesan, 404);
    }
}
