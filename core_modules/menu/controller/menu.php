<?php

class Menu extends ckvsoft\mvc\BaseController
{

    public $model;
    private $menu;

    public function __construct()
    {
        parent::__construct();
        \ckvsoft\Auth::isNotLogged('admin');
    }

    public function index()
    {

        $this->renderPage([
            ['view' => '/inc/header', 'data' => ['title' => 'Menu']],
            ['view' => 'menu/index'],
            ['view' => '/inc/footer'],
        ]);
    }

    public function menuList()
    {
        $model = $this->loadModel('menu');
        $menu = $model->generateMenuArray(0);
        $this->view->render("menu/menu_snippet", ['generatedMenuTable' => $this->generateMenuTable($menu)]);
    }

    /**
     * Generiert die verschachtelte HTML-Struktur (DIV-Karten) für die Menüverwaltung.
     * Statt einer Tabelle wird eine kartenbasierte Ansicht erzeugt, die für Mobilgeräte optimiert ist.
     *
     * @param array $menu Die Menüstruktur.
     * @param int $depth Die aktuelle Verschachtelungstiefe zur Einrückung.
     * @return string Der generierte HTML-Code.
     */
    private function generateMenuTable($menu, $depth = 0)
    {
        // Verwenden der BASE_URI Konstante
        $baseUri = BASE_URI;
        $html = '';

        $indentSize = 20;
        $paddingLeft = ($depth * $indentSize) . 'px';

        foreach ($menu as $item) {
            $is_public = $item['is_public'] == 1 ? 'Ja' : 'Nein';

            $html .= '<div class="card menu-card depth-' . $depth . '" data-menu-id="' . htmlspecialchars($item['id']) . '">';

            $html .= '<div class="menu-details" style="padding-left: ' . $paddingLeft . ';">';

            $html .= '<p class="detail-line">';
            $html .= '<strong>ID:</strong> ' . htmlspecialchars($item['id']);
            $html .= '</p>';

            $labelPrefix = ($depth > 0) ? '↳ ' : '';
            $html .= '<p class="detail-line">';
            $html .= '<strong>Eintrag:</strong> ' . $labelPrefix . htmlspecialchars($item['label']);
            $html .= '</p>';

            $html .= '<p class="detail-line">';
            $html .= '<strong>Öffentlich:</strong> ' . $is_public;
            $html .= '</p>';

            $html .= '</div>';
            $html .= '<div class="actions" style="display: flex; flex-direction: column; gap: 10px; margin-top: 10px;">';

            // Bearbeiten Button
            $html .= '<a class="button small-action edit" href="' . htmlspecialchars($baseUri . 'menu/edit/' . $item['id']) . '">Bearbeiten</a>';

            // Löschen Button
            $html .= '<a class="button small-action delete" href="' . htmlspecialchars($baseUri . 'menu/delete/' . $item['id']) . '">Löschen</a>';

            $html .= '</div>';

            $html .= '</div>';
            if (isset($item['submenu'])) {
                $html .= $this->generateMenuTable($item['submenu'], $depth + 1);
            }
        }
        return $html;
    }

    public function create()
    {
        $input = new \ckvsoft\Input();
        try {
            $input->post('label', true)
                    ->post('link', true)
                    ->post('parent', false)
                    ->post('sort', false)
                    ->post('role', false)
                    ->post('is_public', false);
            $input->submit();

            // If the form has no errors, lets try the.
            // model and check if its a real user!
            $model = $this->loadModel('menu');
            $result = $model->create($input->fetch());
            if (is_string($result)) {
                \ckvsoft\Output::error([$result]);
            } else {
                \ckvsoft\Output::success();
            }
        } catch (\ckvsoft\CkvException $e) {
            \ckvsoft\Output::error($input->fetchErrors());
        }
    }

    public function edit($id)
    {
        $this->view->title = 'Edit Menuentry';
        $this->model = $this->loadModel("menu");
        $this->view->menuList = $this->model->menuSingleList($id);
        $menuhelper = $this->loadHelper("menu/menu");
        $params = [
            'method' => 'getCss',
            'args' => ['/inc/css/style.css']
        ];

        if ($this->mobile) {
            $params = [
                'method' => 'getCss',
                'args' => ['/inc/css/mobile.css']
            ];
        }

        $css = "<style>" . $this->loadHelper("css", $params) . "</style>";

        $script = '<script>' . $this->loadScript("/inc/js/ajax-list-pagination.js");
        $script .= $this->loadScript("/inc/js/menuscript.js");
        $script .= $this->loadScript("/inc/js/x-notify.js") . '</script>';

        $menuhelper = $this->loadHelper("menu/menu");
        $this->view->render('inc/header', ['base_css' => $css, 'base_scripts' => $script, 'menuitems' => $menuhelper->getMenu(0)]);

        $this->view->render('menu/edit', ['script' => $script]);
        $this->view->render('inc/footer');
    }

    public function editSave($id)
    {
        $input = new \ckvsoft\Input();
        try {
            $input->post('label', true)
                    ->post('link', true)
                    ->post('parent', false)
                    ->post('sort', false)
                    ->post('role', false)
                    ->post('is_public', false);
            $input->submit();

            // If the form has no errors, lets try the.
            // model and check if its a real user!
            $model = $this->loadModel('menu');
            $result = $model->update($id, $input->fetch());
            if ($result == false) {
                ckvsoft\Output::error(["Changes not saved"]);
            } else {
                // When we output success, I set jQuery in the view
                // which does a window.location.href redirect
                ckvsoft\Output::success();
            }
        } catch (\ckvsoft\CkvException $e) {
            // This will output our precious form errors
            ckvsoft\Output::error($input->fetchErrors());
        }
    }

    public function delete($id)
    {
        $this->model = $this->loadModel('menu');
        $this->model->delete($id);
        header('location: ' . BASE_URI . 'menu');
    }
}
