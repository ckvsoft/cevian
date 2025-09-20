<?php

namespace ckvsoft;

class geoPlugin
{

    // GeoPlugin server URL template
    private string $host = 'http://www.geoplugin.net/php.gp?ip={IP}&base_currency={CURRENCY}&lang={LANG}';
    private string $currency = 'USD';
    private string $lang = 'en';
    // GeoPlugin data variables
    public ?string $ip = null;
    public ?string $city = null;
    public ?string $region = null;
    public ?string $regionCode = null;
    public ?string $regionName = null;
    public ?string $dmaCode = null;
    public ?string $countryCode = null;
    public ?string $countryName = null;
    public ?bool $inEU = null;
    public bool|float $euVATrate = false;
    public ?string $continentCode = null;
    public ?string $continentName = null;
    public ?float $latitude = null;
    public ?float $longitude = null;
    public ?int $locationAccuracyRadius = null;
    public ?string $timezone = null;
    public ?string $currencyCode = null;
    public ?string $currencySymbol = null;
    public ?float $currencyConverter = null;

    /**
     * Constructor: optional currency and language
     */
    public function __construct(string $currency = 'USD', string $lang = 'en')
    {
        $this->currency = $currency;
        $this->lang = $lang;
    }

    /**
     * Locate the IP address and fetch geo data
     * @param string|null $ip The IP address to locate (must be provided)
     */
    public function locate(?string $ip = null): void
    {
        if ($ip === null) {
            trigger_error('geoPlugin class Error: No IP address provided.', E_USER_ERROR);
            return;
        }

        $host = str_replace('{IP}', $ip, $this->host);
        $host = str_replace('{CURRENCY}', $this->currency, $host);
        $host = str_replace('{LANG}', $this->lang, $host);

        $response = $this->fetch($host);
        $data = unserialize($response);

        // Set geoPlugin variables
        $this->ip = $ip;
        $this->city = $data['geoplugin_city'] ?? null;
        $this->region = $data['geoplugin_region'] ?? null;
        $this->regionCode = $data['geoplugin_regionCode'] ?? null;
        $this->regionName = $data['geoplugin_regionName'] ?? null;
        $this->dmaCode = $data['geoplugin_dmaCode'] ?? null;
        $this->countryCode = $data['geoplugin_countryCode'] ?? null;
        $this->countryName = $data['geoplugin_countryName'] ?? null;
        $this->inEU = $data['geoplugin_inEU'] ?? null;
        $this->euVATrate = $data['euVATrate'] ?? false;
        $this->continentCode = $data['geoplugin_continentCode'] ?? null;
        $this->continentName = $data['geoplugin_continentName'] ?? null;
        $this->latitude = $data['geoplugin_latitude'] ?? null;
        $this->longitude = $data['geoplugin_longitude'] ?? null;
        $this->locationAccuracyRadius = $data['geoplugin_locationAccuracyRadius'] ?? null;
        $this->timezone = $data['geoplugin_timezone'] ?? null;
        $this->currencyCode = $data['geoplugin_currencyCode'] ?? null;
        $this->currencySymbol = $data['geoplugin_currencySymbol'] ?? null;
        $this->currencyConverter = $data['geoplugin_currencyConverter'] ?? null;
    }

    /**
     * Fetch remote URL using cURL or file_get_contents
     */
    private function fetch(string $host): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $host);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_USERAGENT, 'geoPlugin PHP Class v1.2');
            $response = curl_exec($ch);
            curl_close($ch);
        } elseif (ini_get('allow_url_fopen')) {
            $response = file_get_contents($host);
        } else {
            trigger_error(
                    'geoPlugin class Error: Cannot retrieve data. Either compile PHP with cURL support or enable allow_url_fopen.',
                    E_USER_ERROR
            );
            return '';
        }

        return $response;
    }

    /**
     * Convert an amount to geolocated currency
     */
    public function convert(float|int $amount, int $float = 2, bool $symbol = true): float|string
    {
        if (!is_numeric($this->currencyConverter) || $this->currencyConverter == 0) {
            trigger_error('geoPlugin class Notice: currencyConverter has no value.', E_USER_NOTICE);
            return $amount;
        }
        if (!is_numeric($amount)) {
            trigger_error('geoPlugin class Warning: Amount is not numeric.', E_USER_WARNING);
            return $amount;
        }

        $converted = round($amount * $this->currencyConverter, $float);
        return $symbol ? ($this->currencySymbol . $converted) : $converted;
    }

    /**
     * Find nearby locations based on latitude and longitude
     */
    public function nearby(int $radius = 10, ?int $limit = null): array
    {
        if (!is_numeric($this->latitude) || !is_numeric($this->longitude)) {
            trigger_error('geoPlugin class Warning: Incorrect latitude or longitude values.', E_USER_NOTICE);
            return [[]];
        }

        $host = "http://www.geoplugin.net/extras/nearby.gp?lat={$this->latitude}&long={$this->longitude}&radius={$radius}";
        if (is_numeric($limit)) {
            $host .= "&limit={$limit}";
        }

        return unserialize($this->fetch($host));
    }
}
