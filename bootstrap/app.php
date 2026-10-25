<?php

use App\Http\Middleware\CekHak;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\Mobile\Idempotensi;
use App\Http\Middleware\Mobile\PeranMobile;
use App\Http\Middleware\WajibGantiPassword;
use App\Support\Mobile\ApiError;
use App\Support\Mobile\TokenPerangkat;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
        $middleware->alias([
            'wajib-ganti-password' => WajibGantiPassword::class,
            'hak' => CekHak::class,
            'mobile.peran' => PeranMobile::class,
            'mobile.idempoten' => Idempotensi::class,
        ]);
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/login');
        $middleware->redirectUsersTo('/home');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // API mobile: semua error berbentuk {"error": {"code", "message", "fields"}}.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/mobile/*') || $e instanceof ApiError) {
                return null;
            }

            if ($e instanceof AuthenticationException) {
                [$kode, $pesan] = TokenPerangkat::pesanDicabut($request->attributes->get(TokenPerangkat::ATRIBUT_DICABUT));

                return ApiError::respons($kode, $pesan, 401);
            }

            return match (true) {
                $e instanceof ValidationException => ApiError::respons('validation_failed', $e->validator->errors()->first(), 422, $e->errors()),
                $e instanceof UniqueConstraintViolationException => ApiError::respons('duplicate', 'Data yang sama sudah pernah dikirim.', 409),
                $e instanceof HttpExceptionInterface => ApiError::respons(match ($e->getStatusCode()) {
                    403 => 'forbidden', 404 => 'not_found', 405 => 'method_not_allowed', 429 => 'too_many_requests', default => 'http_error',
                }, match ($e->getStatusCode()) {
                    404 => 'Data atau alamat tidak ditemukan.', 429 => 'Terlalu banyak permintaan. Coba lagi sebentar.', default => 'Permintaan tidak bisa diproses.',
                }, $e->getStatusCode(), [], $e->getHeaders()),
                config('app.debug') => null,
                default => ApiError::respons('server_error', 'Terjadi gangguan di server. Data disimpan di HP dan akan dikirim ulang.', 500),
            };
        });
    })->create();
