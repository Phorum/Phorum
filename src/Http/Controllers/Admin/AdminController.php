<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers\Admin;

use Phorum\Core\AdminAuth;
use Phorum\Core\Themes;
use Phorum\Core\Version;
use Phorum\Http\Controller;
use Phorum\Http\Response;
use Phorum\Model\User;

abstract class AdminController extends Controller
{
    /**
     * Check admin auth; redirect to login if not authenticated.
     * Returns null on success (caller may proceed); returns a redirect Response on failure.
     * Usage: if ($r = $this->requireAdmin()) { return $r; }
     */
    protected function requireAdmin(): ?Response
    {
        if (AdminAuth::user() === null) {
            return $this->redirect('/admin/login');
        }
        return null;
    }

    /**
     * Return [directory_name => display_name] for every installed theme.
     * With $withDefault, includes a leading blank entry so selects can
     * represent "use site default".
     *
     * @return array<string,string>
     */
    protected function loadThemes(bool $withDefault = false): array
    {
        return Themes::available($withDefault);
    }

    /** Render an admin template with the standard admin base data merged in. */
    protected function renderAdmin(string $template, array $data = []): string
    {
        $addonSections = phorum_api_hook('addon', []);
        return $this->render($template, array_merge([
            'admin_user'     => AdminAuth::user(),
            'addon_sections' => is_array($addonSections) ? $addonSections : [],
            'phorum_version' => Version::CURRENT,
        ], $data));
    }
}
