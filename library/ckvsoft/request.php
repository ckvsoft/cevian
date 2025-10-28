<?php

/*
 * The MIT License
 *
 * Copyright 2025 chris.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace ckvsoft;

/**
 * Class for managing and accessing request data ($_SERVER, $_GET, $_POST, $_COOKIE).
 */
class Request
{

    private array $server = [];
    private array $get = [];
    private array $post = [];
    private array $cookie = [];

    /**
     * Constructor. Initializes the internal request data arrays.
     * * @param array|null $server Optional array to use instead of $_SERVER.
     * @param array|null $get Optional array to use instead of $_GET.
     * @param array|null $post Optional array to use instead of $_POST.
     * @param array|null $cookie Optional array to use instead of $_COOKIE.
     */
    public function __construct(?array $server = null, ?array $get = null, ?array $post = null, ?array $cookie = null)
    {
        // Server variables
        // Using $_SERVER directly for the initial load is acceptable if filter_input is insufficient for all keys.
        // For a framework-level class, relying on the actual superglobal fallback is common practice.
        $this->server = $server ?? $_SERVER;

        // GET, POST, COOKIE (using filter_input_array for safer initial load)
        $this->get = $get ?? $this->loadInput(INPUT_GET);
        $this->post = $post ?? $this->loadInput(INPUT_POST);
        $this->cookie = $cookie ?? $this->loadInput(INPUT_COOKIE);
    }

    /**
     * Reads data via filter_input_array, falls back to an empty array.
     *
     * @param int $type The type of input to retrieve (INPUT_GET, INPUT_POST, INPUT_COOKIE).
     * @return array The filtered input data.
     */
    private function loadInput(int $type): array
    {
        // FILTER_UNSAFE_RAW to read input as-is for later processing/validation
        $data = filter_input_array($type, FILTER_UNSAFE_RAW, true);
        return is_array($data) ? $data : [];
    }

    // ---------------- Server ----------------

    /**
     * Returns a Server variable.
     *
     * @param string $name The name of the server variable.
     * @param mixed $default The default value to return if the variable is not set.
     * @return mixed The server variable value or the default value.
     */
    public function getServerVar(string $name, mixed $default = null): mixed
    {
        return $this->server[$name] ?? $default;
    }

    /**
     * Checks if the request is an AJAX request.
     *
     * @return bool True if it's an AJAX request (by 'HTTP_X_REQUESTED_WITH' header set to 'fetch' or similar convention).
     */
    public function isAjaxRequest(): bool
    {
        $header = $this->getServerVar('HTTP_X_REQUESTED_WITH');
        // Note: Checking for 'fetch' specifically might be too restrictive;
        // often, 'XMLHttpRequest' is used. 'fetch' is used here as per the original German comment.
        return is_string($header) && strtolower($header) === 'fetch';
    }

    /**
     * Returns the base URI (the path to the front controller).
     *
     * @return string The calculated base URI, ending with a slash.
     */
    public function getBaseUri(): string
    {
        // Fallback to direct $_SERVER access for SCRIPT_FILENAME/DOCUMENT_ROOT if the class was initialized without them
        $scriptFilename = $this->getServerVar('SCRIPT_FILENAME', $_SERVER['SCRIPT_FILENAME'] ?? '');
        $docRoot = rtrim($this->getServerVar('DOCUMENT_ROOT', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

        if (empty($scriptFilename) || empty($docRoot)) {
            return '/';
        }

        $frontControllerDir = dirname($scriptFilename);
        $baseUri = '/' . trim(str_replace($docRoot, '', $frontControllerDir), '/') . '/';
        // Ensure that a double-slash result like "//" for the document root itself becomes a single slash "/"
        return $baseUri === '//' ? '/' : $baseUri;
    }

    /**
     * Checks the User-Agent for mobile keywords.
     *
     * @return bool True if the User-Agent string contains 'mobile' (case-insensitive).
     */
    public function isMobile(): bool
    {
        $ua = $this->getServerVar('HTTP_USER_AGENT', '');
        return stripos($ua, 'mobile') !== false;
    }

    /**
     * Returns the full request URI.
     *
     * @return string The full request URI (e.g., '/path/to/resource?query=string').
     */
    public function getRequestUri(): string
    {
        return $this->getServerVar('REQUEST_URI', '/');
    }

    /**
     * Returns the query string.
     *
     * @return string The raw query string (e.g., 'query=string&id=123').
     */
    public function getQueryString(): string
    {
        return $this->getServerVar('QUERY_STRING', '');
    }

    // ---------------- GET ----------------

    /**
     * Returns a value from the GET parameters.
     *
     * @param string $key The key of the GET parameter.
     * @param mixed $default The default value to return if the key is not set.
     * @return mixed The GET parameter value or the default value.
     */
    public function getQuery(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    /**
     * Returns all GET parameters.
     *
     * @return array All GET parameters.
     */
    public function allGet(): array
    {
        return $this->get;
    }

    // ---------------- POST ----------------

    /**
     * Returns a value from the POST parameters.
     *
     * @param string $key The key of the POST parameter.
     * @param mixed $default The default value to return if the key is not set.
     * @return mixed The POST parameter value or the default value.
     */
    public function getPost(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    /**
     * Returns all POST parameters.
     *
     * @return array All POST parameters.
     */
    public function allPost(): array
    {
        return $this->post;
    }

    // ---------------- COOKIE ----------------

    /**
     * Returns a Cookie value.
     *
     * @param string $key The key of the Cookie.
     * @param mixed $default The default value to return if the key is not set.
     * @return mixed The Cookie value or the default value.
     */
    public function getCookie(string $key, mixed $default = null): mixed
    {
        return $this->cookie[$key] ?? $default;
    }

    /**
     * Returns all Cookies.
     *
     * @return array All Cookies.
     */
    public function allCookie(): array
    {
        return $this->cookie;
    }

    // ---------------- REQUEST ----------------

    /**
     * Replaces $_REQUEST – GET overwrites POST, POST overwrites COOKIE.
     * The order of precedence is: COOKIE < POST < GET.
     *
     * @return array Merged array of COOKIE, POST, and GET parameters.
     */
    public function allRequest(): array
    {
        return array_merge($this->cookie, $this->post, $this->get);
    }

    /**
     * Returns a value from the REQUEST collection (GET, POST, COOKIE).
     * Precedence: GET > POST > COOKIE.
     *
     * @param string $key The key to look for.
     * @param mixed $default The default value to return if the key is not found.
     * @return mixed The parameter value or the default value.
     */
    public function getRequest(string $key, mixed $default = null): mixed
    {
        $all = $this->allRequest();
        return $all[$key] ?? $default;
    }
}
