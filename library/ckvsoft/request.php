<?php

namespace ckvsoft;

class Request
{

    private array $server = [];
    private array $get = [];
    private array $post = [];
    private array $cookie = [];

    public function __construct(?array $server = null, ?array $get = null, ?array $post = null, ?array $cookie = null)
    {
        // Server-Variablen
        $this->server = $server ?? $this->loadInput(INPUT_SERVER);

        // GET, POST, COOKIE
        $this->get = $get ?? $this->loadInput(INPUT_GET);
        $this->post = $post ?? $this->loadInput(INPUT_POST);
        $this->cookie = $cookie ?? $this->loadInput(INPUT_COOKIE);
    }

    /**
     * Liest Daten über filter_input_array, fallback auf leeres Array
     */
    private function loadInput(int $type): array
    {
        $data = filter_input_array($type, FILTER_UNSAFE_RAW, true);
        return is_array($data) ? $data : [];
    }

    // ---------------- Server ----------------
    public function getServerVar(string $name, mixed $default = null): mixed
    {
        return $this->server[$name] ?? $default;
    }

    public function getBaseUri(): string
    {
        // Server-Pfad zum Webroot
        $frontControllerDir = dirname($_SERVER['SCRIPT_FILENAME']); // z. B. /home/.../web/mbv
        $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');           // z. B. /home/.../web
        $baseUri = '/' . trim(str_replace($docRoot, '', $frontControllerDir), '/') . '/';
        return $baseUri === '//' ? '/' : $baseUri;
    }

    public function isMobile(): bool
    {
        $ua = $this->getServerVar('HTTP_USER_AGENT', '');
        return stripos($ua, 'mobile') !== false;
    }

    public function getRequestUri(): string
    {
        return $this->getServerVar('REQUEST_URI', '/');
    }

    public function getQueryString(): string
    {
        return $this->getServerVar('QUERY_STRING', '');
    }

    // ---------------- GET ----------------
    public function getQuery(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    public function allGet(): array
    {
        return $this->get;
    }

    // ---------------- POST ----------------
    public function getPost(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function allPost(): array
    {
        return $this->post;
    }

    // ---------------- COOKIE ----------------
    public function getCookie(string $key, mixed $default = null): mixed
    {
        return $this->cookie[$key] ?? $default;
    }

    public function allCookie(): array
    {
        return $this->cookie;
    }

    // ---------------- REQUEST ----------------

    /**
     * Ersetzt $_REQUEST – GET überschreibt POST, POST überschreibt COOKIE
     */
    public function allRequest(): array
    {
        return array_merge($this->cookie, $this->post, $this->get);
    }

    public function getRequest(string $key, mixed $default = null): mixed
    {
        $all = $this->allRequest();
        return $all[$key] ?? $default;
    }
}
