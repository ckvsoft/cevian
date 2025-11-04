<?php

namespace ckvsoft\mvc;

/**
 * We handle models ourselves.
 */
class Model extends \ckvsoft\mvc\Config
{

    /**
     * Konstruktor – stellt sicher, dass $this->db verfügbar ist
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Fängt Aufrufe an nicht existierende Methoden ab
     */
    public function __call($name, $arg)
    {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            // Entwicklungsmodus → direkt sichtbar machen
            error_log("Model Error: Methode {$name} is not defined in Class: " . get_class($this));
            die("<div style='color:red;'>
                <b>Model Error:</b> Methode <b>{$name}</b> is not defined<br>
                In Class: <b>" . get_class($this) . "</b>
            </div>");
        } else {
            // Produktionsmodus → Exception werfen und zentral abfangen
            throw new \ckvsoft\CkvException(
                            "Model Error: Methode {$name} is not defined in " . get_class($this)
                    );
        }
    }
}
