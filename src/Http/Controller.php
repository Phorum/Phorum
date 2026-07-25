<?php
declare(strict_types=1);

namespace Phorum\Http;

use Phorum\Core\Auth;
use Phorum\Core\Config;
use Phorum\Core\CsrfGuard;
use Phorum\Core\FeedStatus;
use Phorum\Core\Impersonation;
use Phorum\Core\Lang;
use Phorum\Core\SiteSettings;
use Phorum\Core\SiteStatus;
use Phorum\Model\Forum;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

abstract class Controller
{
    public function __construct(
        protected readonly Config      $config,
        protected readonly Environment $twig,
    ) {}

    protected function render(string $template, array $data = []): string
    {
        $merged = array_merge($this->baseData(), $data);
        $this->activateTheme($merged['theme'] ?? 'emerald');
        return $this->twig->render($template, $merged);
    }

    /**
     * Prepend the theme's template directory to the Twig loader so that
     * theme-specific templates are found first, with the default templates/
     * directory as a fallback for anything the theme doesn't override.
     */
    private function activateTheme(string $theme): void
    {
        $loader = $this->twig->getLoader();
        if (!$loader instanceof FilesystemLoader) {
            return;
        }

        $themePath = ROOT_PATH . '/themes/' . $theme . '/templates';
        if (!is_dir($themePath)) {
            return;
        }

        // Only prepend once per request (getPaths() includes all registered paths)
        if (!in_array($themePath, $loader->getPaths(), strict: true)) {
            $loader->prependPath($themePath);
        }
    }

    protected function baseData(): array
    {
        return [
            'site_name'      => SiteSettings::name(),
            'user'           => Auth::user(),
            'theme'          => $this->resolveTheme(),
            'lang_locale'    => Lang::locale(),
            'lang_dir'       => Lang::dir(),
            'impersonating'  => Impersonation::isActive(),
            'impersonator'   => Impersonation::admin(),
            'site_read_only' => SiteStatus::isReadOnly(),
            'feeds_enabled'  => FeedStatus::enabled(),
        ];
    }

    /**
     * Resolve the active theme name, highest priority first:
     *   1. The forum's own override, if given and set.
     *   2. The current user's personal theme preference, if set.
     *   3. The site-wide config default.
     */
    protected function resolveTheme(?Forum $forum = null): string
    {
        if ($forum !== null && $forum->template !== '') {
            return $forum->template;
        }
        $userTemplate = Auth::user()?->user_template ?? '';
        if ($userTemplate !== '') {
            return $userTemplate;
        }
        return (string) $this->config->get('template', 'emerald');
    }

    protected function respond(string $html, int $status = 200): Response
    {
        return new Response($html, $status);
    }

    protected function basePath(): string
    {
        return (string) $this->config->get('base_path', '');
    }

    protected function redirect(string $url, int $status = 302): Response
    {
        if (str_starts_with($url, '/')) {
            $url = $this->basePath() . $url;
        }
        return new Response('', $status, ['Location' => $url]);
    }

    /**
     * Validate the CSRF token submitted with a POST form.
     * Returns null on success; returns a 403 Response on failure.
     * Call at the top of every POST handler: if ($r = $this->checkCsrf($request)) { return $r; }
     */
    protected function checkCsrf(Request $request): ?Response
    {
        $token = $request->post[CsrfGuard::fieldName()] ?? '';
        if (!CsrfGuard::validate($token)) {
            return $this->respond($this->render('error/403.html.twig'), 403);
        }
        return null;
    }

    protected function notFound(): Response
    {
        return $this->respond($this->render('error/404.html.twig'), 404);
    }

    protected function forbidden(): Response
    {
        return $this->respond($this->render('error/403.html.twig'), 403);
    }
}
