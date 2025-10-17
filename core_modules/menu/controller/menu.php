<?php

class Menu extends ckvsoft\mvc\BaseController
{

    public $model;
    private $menu;

    public function __construct()
    {
        parent::__construct();
        // Check if user is not logged in as 'admin'
        \ckvsoft\Auth::isNotLogged('admin');
    }

    public function index()
    {
        // Renders the main menu management page
        $this->renderPage([
            ['view' => '/inc/header', 'data' => ['title' => _('Menu')]],
            ['view' => 'menu/index'],
            ['view' => '/inc/footer'],
        ]);
    }

    public function menuList()
    {
        // Loads menu data and renders the table/card snippet
        $model = $this->loadModel('menu');
        $menu = $model->generateMenuArray(0);
        $this->view->render("menu/menu_snippet", ['generatedMenuTable' => $this->generateMenuTable($menu)]);
    }

    /**
     * Generates the nested HTML structure (DIV cards) for menu management.
     * Creates a card-based view instead of a table, optimized for mobile devices.
     *
     * @param array $menu The menu structure.
     * @param int $depth The current nesting depth for indentation.
     * @return string The generated HTML code.
     */
    private function generateMenuTable($menu, $depth = 0)
    {
        // Use the BASE_URI constant
        $baseUri = BASE_URI;
        $html = '';

        $indentSize = 20;
        $paddingLeft = ($depth * $indentSize) . 'px';

        foreach ($menu as $item) {
            // Translate status strings
            $is_public_status = $item['is_public'] == 1 ? _('Yes') : _('No');

            $html .= '<div class="card depth-' . $depth . '" data-menu-id="' . htmlspecialchars($item['id']) . '">';

            $html .= '<div class="card-details" style="padding-left: ' . $paddingLeft . ';">';

            /*
             * Original commented section (ID)
             */

            $labelPrefix = ($depth > 0) ? '↳ ' : '';
            $html .= '<p class="detail-line">';
            $html .= '<strong>' . _('Entry') . ':</strong> ' . $labelPrefix . htmlspecialchars($item['label']);
            $html .= '</p>';

            $html .= '<p class="detail-line">';
            $html .= '<strong>' . _('Public') . ':</strong> ' . $is_public_status;
            $html .= '</p>';

            $html .= '</div>';
            // Note: The inline styles here are based on the CSS issue we discussed,
            // but for clean code, this should ideally be in the stylesheet.
            $html .= '<div class="actions column">';

            // Edit Button
            $html .= '<a class="button small-action edit" href="' . htmlspecialchars($baseUri . 'menu/edit/' . $item['id']) . '">' . _('Edit') . '</a>';

            // Delete Button
            $html .= '<a class="button small-action delete" href="' . htmlspecialchars($baseUri . 'menu/delete/' . $item['id']) . '">' . _('Delete') . '</a>';

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

            // If the form has no errors, lets try the model!
            $model = $this->loadModel('menu');
            $result = $model->create($input->fetch());
            if (is_string($result)) {
                // Output model error message (which should also be translated, if possible)
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
        $this->model = $this->loadModel("menu");
        $this->renderPage([
            ['view' => '/inc/header', 'data' => ['title' => _('Edit Menuentry')]],
            ['view' => 'menu/edit', 'data' => ['menuList' => $this->model->menuSingleList($id)]],
            ['view' => '/inc/footer'],
        ]);
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

            // If the form has no errors, lets try the model!
            $model = $this->loadModel('menu');
            $result = $model->update($id, $input->fetch());
            if ($result == false) {
                // Translate error string
                ckvsoft\Output::error([_("Changes not saved")]);
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
