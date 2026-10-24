<?php

namespace App\Support\Marketama;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

// Klien baca-saja ke aplikasi lama Marketama, memakai sesi login admin seperti browser.
// Pengaman: hanya GET, POST login, dan POST ke endpoint DataTables (/.../datatable...).
// Endpoint simpan/ubah/hapus (edit_action, store, delete, update, ...) tidak bisa dipanggil.
class MarketamaClient
{
    private CookieJar $cookies;

    /** @var array<string, array{token:?string, url:?string, kolom:list<string>}> */
    private array $halaman = [];

    /** @var array<string, true> endpoint DataTables yang hanya menerima GET */
    private array $metodeGet = [];

    public function __construct(
        private readonly string $baseUrl,
        private readonly int $jedaMs = 300,
        private readonly int $timeout = 60,
    ) {
        $this->cookies = new CookieJar;
    }

    public static function dariConfig(): self
    {
        $cfg = config('marketama_sumber');

        return new self($cfg['url'], $cfg['jeda_ms'], $cfg['timeout']);
    }

    private function http(): PendingRequest
    {
        return Http::withOptions(['cookies' => $this->cookies, 'allow_redirects' => true])
            ->timeout($this->timeout)
            // Ulangi hanya bila koneksi putus atau server sibuk/error (5xx, 429), bukan 404/405.
            // Ulangi bila koneksi putus atau server error (5xx, 429). Timeout tidak diulang di sini:
            // Penarik akan mengulang dengan halaman yang lebih kecil.
            ->retry(3, 2000, fn ($e) => ($e instanceof ConnectionException && ! self::timeout($e))
                || ($e instanceof RequestException && ($e->response->serverError() || $e->response->status() === 429)), throw: false)
            ->withHeaders(['User-Agent' => 'Enesis-Migrasi/1.0 (baca saja)']);
    }

    private function url(string $path): string
    {
        return str_starts_with($path, 'http') ? $path : $this->baseUrl.'/'.ltrim($path, '/');
    }

    private function jeda(): void
    {
        if ($this->jedaMs > 0) {
            usleep($this->jedaMs * 1000);
        }
    }

    public static function timeout(\Throwable $e): bool
    {
        return $e instanceof ConnectionException && (bool) preg_match('/cURL error 28|timed out/i', $e->getMessage());
    }

    public static function postDiizinkan(string $path): bool
    {
        $path = strtolower((string) parse_url($path, PHP_URL_PATH));

        return $path === '/login' || (bool) preg_match('#/datatable[a-z_]*$#', $path);
    }

    public function login(string $username, string $password): void
    {
        $halaman = $this->get('/login');
        $form = HalamanParser::formLogin($halaman);

        if (! $form['pass']) {
            // Sudah dalam keadaan login (cookie sesi masih berlaku).
            return;
        }

        $action = $form['action'] ? (string) parse_url($form['action'], PHP_URL_PATH) : '/login';

        if (! self::postDiizinkan($action)) {
            throw new RuntimeException("Form login mengarah ke alamat tak terduga: {$action}");
        }

        $data = $form['fields'] + [($form['user'] ?? 'username') => $username, $form['pass'] => $password];
        $this->http()->asForm()->post($this->url($action), $data);
        $this->jeda();

        // Berhasil bila halaman beranda tidak lagi berisi form password.
        if (HalamanParser::formLogin($this->get('/home'))['pass']) {
            throw new RuntimeException('Login ke Marketama gagal. Periksa MARKETAMA_USERNAME / MARKETAMA_PASSWORD.');
        }
    }

    public function get(string $path): string
    {
        $res = $this->http()->get($this->url($path));
        $this->jeda();
        $this->pastikan($res, $path);

        return $res->body();
    }

    public function getJson(string $path): array
    {
        $res = $this->http()->acceptJson()->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get($this->url($path));
        $this->jeda();
        $this->pastikan($res, $path);

        return (array) $res->json();
    }

    /** Status HTTP halaman (tanpa melempar error), untuk mendeteksi form edit yang tidak ada. */
    public function coba(string $path): ?string
    {
        $res = $this->http()->get($this->url($path));
        $this->jeda();

        return $res->successful() ? $res->body() : null;
    }

    private function pastikan(Response $res, string $path): void
    {
        if (! $res->successful()) {
            throw new RuntimeException("Gagal mengambil {$path}: HTTP {$res->status()}");
        }
    }

    /**
     * Info halaman menu: token CSRF, endpoint DataTables, judul kolom.
     *
     * @return array{token:?string, url:?string, kolom:list<string>}
     */
    public function infoMenu(string $menu, ?string $tableId = null): array
    {
        $kunci = $menu.'|'.$tableId;

        return $this->halaman[$kunci] ??= (function () use ($menu, $tableId) {
            $html = $this->get('/'.$menu);

            return [
                'token' => HalamanParser::csrfToken($html),
                'url' => HalamanParser::datatableUrl($html),
                'kolom' => HalamanParser::kolom($html, $tableId),
            ];
        })();
    }

    /**
     * Satu halaman DataTables (maks. 100 baris).
     *
     * @return array{total:int, filtered:int, data:list<array>}
     */
    public function datatable(string $url, string $token, int $start, int $length, array $extra = []): array
    {
        if (! self::postDiizinkan($url)) {
            throw new RuntimeException("POST ke {$url} ditolak: hanya endpoint DataTables yang boleh.");
        }

        $param = ['draw' => 1, 'start' => $start, 'length' => $length, 'search[value]' => ''] + $extra;
        $headers = ['X-CSRF-TOKEN' => $token, 'X-Requested-With' => 'XMLHttpRequest'];

        if (! isset($this->metodeGet[$url])) {
            $res = $this->http()->asForm()->withHeaders($headers)->post($this->url($url), $param);
            $this->jeda();

            // Sebagian menu (mis. master_depo) memakai GET untuk DataTables.
            if ($res->status() === 405) {
                $this->metodeGet[$url] = true;
            }
        }

        if (isset($this->metodeGet[$url])) {
            $res = $this->http()->withHeaders($headers)->get($this->url($url), $param);
            $this->jeda();
        }
        $this->pastikan($res, $url);

        $json = $res->json();

        if (! is_array($json) || ! array_key_exists('data', $json)) {
            throw new RuntimeException("Respons DataTables {$url} tidak dikenali (sesi habis?).");
        }

        return [
            'total' => (int) ($json['recordsTotal'] ?? 0),
            'filtered' => (int) ($json['recordsFiltered'] ?? $json['recordsTotal'] ?? 0),
            'data' => array_values((array) $json['data']),
        ];
    }
}
