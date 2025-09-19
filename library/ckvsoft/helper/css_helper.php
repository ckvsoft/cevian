<?php

namespace ckvsoft\Helper;

/**
 * Description of csshelper
 *
 * @author chris
 *
 * Example call from Controller
 * $params = [
 *           'method' => 'getCss',
 *           'args' => ['inc/css/mbv.css']
 *       ];
 * $css = $this->loadHelper("css", $params);
 */
class Css_Helper extends \ckvsoft\mvc\Helper
{

    public function getCss($css)
    {
        $path = getcwd() . '/' . MODULES_URI . $this->baseController . "/view/" . $css;
        $style = preg_replace('/\s+/', ' ', file_get_contents($path));
        return str_replace(["\r", "\n"], '', preg_replace('/\/\*[\s\S]*?\*\//', '', $style));
    }
}
