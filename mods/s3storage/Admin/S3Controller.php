<?php
declare(strict_types=1);

namespace Phorum\Mod\S3Storage\Admin;

use Phorum\Core\Config;
use Phorum\Http\Controllers\Admin\AdminController;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\SettingMapper;
use Twig\Environment;

/** Admin settings page for S3-backed file storage. Routed via a fully-qualified action in mods/s3storage/routes.php. */
class S3Controller extends AdminController
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

        $errors  = [];
        $success = '';

        $bucket    = (string) ($this->settings->getSetting('s3_bucket') ?? '');
        $region    = (string) ($this->settings->getSetting('s3_region') ?? '');
        $accessKey = (string) ($this->settings->getSetting('s3_access_key') ?? '');
        $hasSecret = (string) ($this->settings->getSetting('s3_secret_key') ?? '') !== '';
        $keyPrefix = (string) ($this->settings->getSetting('s3_key_prefix') ?? '');

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }

            $bucket    = trim($request->post['s3_bucket'] ?? '');
            $region    = trim($request->post['s3_region'] ?? '');
            $accessKey = trim($request->post['s3_access_key'] ?? '');
            $newSecret = trim($request->post['s3_secret_key'] ?? '');
            $keyPrefix = trim($request->post['s3_key_prefix'] ?? '');

            if ($bucket === '') {
                $errors[] = 'A bucket name is required.';
            }
            if ($region === '') {
                $errors[] = 'A region is required.';
            }

            if (empty($errors)) {
                $this->settings->saveSetting('s3_bucket', $bucket);
                $this->settings->saveSetting('s3_region', $region);
                $this->settings->saveSetting('s3_access_key', $accessKey);
                if ($newSecret !== '') {
                    $this->settings->saveSetting('s3_secret_key', $newSecret);
                    $hasSecret = true;
                }
                $this->settings->saveSetting('s3_key_prefix', $keyPrefix);
                $success = 'Settings saved.';
            }
        }

        return $this->respond($this->renderAdmin('admin/mods/s3storage/index.html.twig', [
            's3_bucket'     => $bucket,
            's3_region'     => $region,
            's3_access_key' => $accessKey,
            's3_has_secret' => $hasSecret,
            's3_key_prefix' => $keyPrefix,
            'errors'        => $errors,
            'success'       => $success,
        ]));
    }
}
