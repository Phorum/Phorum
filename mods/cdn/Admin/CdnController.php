<?php
declare(strict_types=1);

namespace Phorum\Mod\Cdn\Admin;

use Phorum\Core\Config;
use Phorum\Http\Controllers\Admin\AdminController;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\SettingMapper;
use Twig\Environment;

/** Admin settings page for the CDN module. Routed via a fully-qualified action in mods/cdn/routes.php. */
class CdnController extends AdminController
{
    private readonly SettingMapper $settings;

    public function __construct(
        Config         $config,
        Environment    $twig,
        ?SettingMapper $settings = null,
    ) {
        parent::__construct($config, $twig);
        $this->settings = $settings ?? new SettingMapper();
    }

    public function index(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $success = '';

        $baseUrl = (string) ($this->settings->getSetting('cdn_base_url') ?? '');

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }

            $baseUrl = trim($request->post['cdn_base_url'] ?? '');
            $this->settings->saveSetting('cdn_base_url', $baseUrl);
            $success = 'Settings saved.';
        }

        return $this->respond($this->renderAdmin('admin/mods/cdn/index.html.twig', [
            'cdn_base_url' => $baseUrl,
            'success'      => $success,
        ]));
    }
}
