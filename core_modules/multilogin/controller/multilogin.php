<?php

/**
 * MultiLogin user-mapping admin UI.
 *
 * Routes:
 *   /multilogin                       overview (matrix of fw users x modules)
 *   /multilogin/edit/{fwUserId}/{mod} picker dialog for one cell
 *   /multilogin/save                  POST handler (sets/clears one mapping)
 *
 * Auth: requires login + role 'admin'. Both checks are folded into
 * Auth::isNotLogged('admin') -- not-logged-in users get bounced to
 * the home page, logged-in non-admins get bounced to /dashboard with
 * a flash message.
 */
class Multilogin extends \ckvsoft\mvc\BaseController
{

    public function __construct()
    {
        parent::__construct();
        \ckvsoft\Auth::isNotLogged('admin');
    }

    public function index()
    {
        $this->overview();
    }

    public function overview()
    {
        $model = $this->loadModel('multilogin');

        $fwUsers  = $model->listFrameworkUsers();
        $modules  = $model->listModules();
        $mappings = $model->listMappings();

        // Index mappings as $byUser[fwUserId][moduleKey] = moduleUserId
        $byUser = [];
        foreach ($mappings as $m) {
            $byUser[(int) $m['framework_user_id']][(string) $m['module_name']]
                    = (int) $m['module_user_id'];
        }

        // Resolve module-user labels for the cells that have a mapping
        // so the matrix shows a name not just a number.
        $labels = [];  // [fwUserId][moduleKey] => label
        foreach ($byUser as $fw => $mods) {
            foreach ($mods as $mod => $uid) {
                $resolved = $model->resolveModuleUser($mod, $uid);
                $labels[$fw][$mod] = $resolved
                        ? ($resolved['label'] . ' (#' . $uid . ')')
                        : '#' . $uid . ' (?)';
            }
        }

        $this->renderPage([
            ['view' => '/inc/header', 'data' => ['title' => __('MultiLogin Mapping')]],
            ['view' => 'multilogin/multilogin/overview', 'data' => ['data' => [
                'fwUsers' => $fwUsers,
                'modules' => $modules,
                'byUser'  => $byUser,
                'labels'  => $labels,
            ]]],
            ['view' => '/inc/footer'],
        ]);
    }

    /**
     * Picker dialog for one (fwUser, module) cell. Shows the list of
     * candidate module users plus a "Clear mapping" option.
     */
    public function edit($fwUserId = 0, $module = '')
    {
        $fwUserId = (int) $fwUserId;
        $module   = (string) $module;
        if ($fwUserId <= 0 || $module === '') {
            $this->location(BASE_URI . 'multilogin');
        }

        $model = $this->loadModel('multilogin');

        $fwUser = $model->getFrameworkUser($fwUserId);
        if (!$fwUser) {
            $this->location(BASE_URI . 'multilogin');
        }

        $modules = $model->listModules();
        if (!isset($modules[$module])) {
            $this->location(BASE_URI . 'multilogin');
        }

        $candidates = $model->listModuleUsers($module);
        $current    = $model->getMapping($fwUserId, $module);

        $this->renderPage([
            ['view' => '/inc/header', 'data' => ['title' => __('MultiLogin Mapping')]],
            ['view' => 'multilogin/multilogin/edit', 'data' => ['data' => [
                'fwUser'      => $fwUser,
                'module'      => $module,
                'moduleLabel' => $modules[$module],
                'candidates'  => $candidates,
                'current'     => $current ? (int) $current['module_user_id'] : null,
            ]]],
            ['view' => '/inc/footer'],
        ]);
    }

    /**
     * POST: framework_user_id, module, module_user_id (0 = clear).
     */
    public function save()
    {
        $fwUserId   = (int) ($_POST['framework_user_id'] ?? 0);
        $module     = (string) ($_POST['module'] ?? '');
        $moduleUser = (int) ($_POST['module_user_id'] ?? 0);

        if ($fwUserId <= 0 || $module === '') {
            $this->location(BASE_URI . 'multilogin');
        }

        $model = $this->loadModel('multilogin');

        if ($moduleUser <= 0) {
            $model->deleteMapping($fwUserId, $module);
        } else {
            $model->setMapping($fwUserId, $module, $moduleUser);
        }
        $this->location(BASE_URI . 'multilogin');
    }
}
