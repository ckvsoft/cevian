<?php

namespace ckvsoft;

class Hash
{

    /**
     * create - Create an encryption key with a special algorithm and key
     *
     * @param string $algo The algorithm to use
     * @param string $string The string to encrypt
     * @param string $key A salt to apply to the encryption
     *
     * return string
     */
    public static function create($algo, $string, $key = null)
    {
        if ($key == null)
            $ctx = hash_init($algo);
        else
            $ctx = hash_init($algo, HASH_HMAC, $key);

        /** Finalize the output */
        hash_update($ctx, $string);
        return hash_final($ctx);
    }
}
