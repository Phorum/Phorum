<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers\Admin;

use Phorum\Core\Config;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\CustomFieldConfigMapper;
use Phorum\Mapper\UserCustomFieldMapper;
use Phorum\Model\CustomFieldConfig;
use Twig\Environment;

class CustomFieldController extends AdminController
{
    private const NAME_PATTERN = '/^[a-z][a-z0-9_]*$/i';
    private const MAX_LENGTH   = 65000;

    private readonly CustomFieldConfigMapper $configs;
    private readonly UserCustomFieldMapper   $userFields;

    public function __construct(
        Config                    $config,
        Environment               $twig,
        ?CustomFieldConfigMapper  $configs    = null,
        ?UserCustomFieldMapper    $userFields = null,
    ) {
        parent::__construct($config, $twig);
        $this->configs    = $configs    ?? new CustomFieldConfigMapper();
        $this->userFields = $userFields ?? new UserCustomFieldMapper();
    }

    // -------------------------------------------------------------------------
    // List
    // -------------------------------------------------------------------------

    public function index(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $fields = $this->configs->findAll(includeDeleted: true);

        return $this->respond($this->renderAdmin('admin/custom_fields/index.html.twig', [
            'fields' => $fields,
        ]));
    }

    // -------------------------------------------------------------------------
    // Create
    // -------------------------------------------------------------------------

    public function create(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $config = new CustomFieldConfig();
        $errors = [];

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $errors = $this->applyPost($config, isNew: true, request: $request);

            if (empty($errors)) {
                $this->configs->save($config);
                return $this->redirect('/admin/custom-fields');
            }
        }

        return $this->respond($this->renderAdmin('admin/custom_fields/edit.html.twig', [
            'config' => $config,
            'errors' => $errors,
            'is_new' => true,
        ]));
    }

    // -------------------------------------------------------------------------
    // Edit
    // -------------------------------------------------------------------------

    public function edit(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $id     = (int) ($request->tokens['id'] ?? 0);
        $config = $this->configs->load($id);
        if ($config === null) { return $this->notFound(); }

        $errors = [];

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $errors = $this->applyPost($config, isNew: false, request: $request);

            if (empty($errors)) {
                $this->configs->save($config);
                return $this->redirect('/admin/custom-fields');
            }
        }

        return $this->respond($this->renderAdmin('admin/custom_fields/edit.html.twig', [
            'config' => $config,
            'errors' => $errors,
            'is_new' => false,
        ]));
    }

    // -------------------------------------------------------------------------
    // Soft-delete / restore / hard-delete
    // -------------------------------------------------------------------------

    public function delete(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $id     = (int) ($request->tokens['id'] ?? 0);
        $config = $this->configs->load($id);
        if ($config === null) { return $this->notFound(); }

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $config->deleted = 1;
            $this->configs->save($config);
            return $this->redirect('/admin/custom-fields');
        }

        return $this->respond($this->renderAdmin('admin/custom_fields/delete_confirm.html.twig', [
            'config' => $config,
            'action' => 'delete',
        ]));
    }

    public function restore(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $id     = (int) ($request->tokens['id'] ?? 0);
        $config = $this->configs->load($id);
        if ($config === null) { return $this->notFound(); }

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $config->deleted = 0;
            $this->configs->save($config);
            return $this->redirect('/admin/custom-fields');
        }

        return $this->redirect('/admin/custom-fields');
    }

    public function purge(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $id     = (int) ($request->tokens['id'] ?? 0);
        $config = $this->configs->load($id);
        if ($config === null) { return $this->notFound(); }

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $this->userFields->deleteForConfig($config->id);
            $this->configs->delete($id);
            return $this->redirect('/admin/custom-fields');
        }

        return $this->respond($this->renderAdmin('admin/custom_fields/delete_confirm.html.twig', [
            'config' => $config,
            'action' => 'purge',
        ]));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Validate and apply POST data onto a CustomFieldConfig.
     * Returns array of error strings (empty on success).
     */
    private function applyPost(CustomFieldConfig $config, bool $isNew, Request $request): array
    {
        $errors = [];
        $name   = trim(strtolower($request->post['name'] ?? ''));
        $length = (int) ($request->post['length'] ?? 255);

        if ($name === '') {
            $errors[] = 'Field name is required.';
        } elseif (!preg_match(self::NAME_PATTERN, $name)) {
            $errors[] = 'Field name must start with a letter and contain only letters, numbers, and underscores.';
        } elseif (mb_strlen($name) > 50) {
            $errors[] = 'Field name must be 50 characters or fewer.';
        } elseif ($isNew) {
            $existing = $this->configs->findByName($name);
            if ($existing !== null) {
                $errors[] = 'A field with that name already exists.';
            }
        }

        if ($length < 1 || $length > self::MAX_LENGTH) {
            $errors[] = 'Max length must be between 1 and ' . self::MAX_LENGTH . '.';
        }

        if (empty($errors)) {
            if ($isNew) {
                $config->name = $name;
            }
            $config->length        = $length;
            $config->html_disabled = !empty($request->post['html_disabled']) ? 1 : 0;
            $config->show_in_admin = !empty($request->post['show_in_admin']) ? 1 : 0;
        }

        return $errors;
    }
}
