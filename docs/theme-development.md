# Phorum Theme Development Guide

## Overview

A Phorum theme lives entirely inside one directory under `themes/`. It can
contain a stylesheet, arbitrary assets (images, fonts, JavaScript), and
optionally a subset of Twig template overrides. You only need to ship the
files that differ from the defaults — everything else falls back automatically.

---

## Directory Structure

```
themes/
└── mytheme/
    ├── config.php          required — theme metadata
    ├── phorum.css          main stylesheet (referenced from base.html.twig)
    ├── templates/          optional — Twig template overrides
    │   ├── base.html.twig
    │   └── forum/
    │       └── index.html.twig
    ├── images/
    │   └── logo.png
    └── fonts/
        └── myfont.woff2
```

The theme directory name becomes the theme's identifier. It may contain only
letters, digits, hyphens, and underscores.

---

## config.php

Every theme must have a `config.php` that returns a plain PHP array:

```php
<?php
declare(strict_types=1);

return [
    'name'    => 'My Theme',
    'version' => '1.0',
    'author'  => 'Your Name',
    // 'hidden' => true,   // uncomment to hide from the admin theme selector
];
```

| Key | Required | Description |
|-----|----------|-------------|
| `name` | yes | Human-readable theme name shown in the admin panel |
| `version` | yes | Version string (free-form) |
| `author` | yes | Author name |
| `hidden` | no | Set `true` to exclude the theme from the admin selection list |

---

## Stylesheet

The main stylesheet must be named `phorum.css` and placed directly inside the
theme directory. It is referenced in `base.html.twig` as:

```twig
<link rel="stylesheet" href="{{ path('/theme/' ~ theme ~ '/phorum.css') }}">
```

The emerald theme (`themes/emerald/phorum.css`) is the recommended starting
point. It defines all layout, component, and color styles using CSS custom
properties in a `:root` block, making it straightforward to reskin by
overriding only those variables.

### CSS Custom Properties (Emerald)

```css
:root {
    --accent:            #4d824d;   /* table headers, buttons, badges */
    --accent-header:     #78ad78;   /* logo / site header background */
    --accent-dark:       #355f35;   /* links, focus ring */
    --accent-light:      #edf2ed;   /* alternating row background */
    --accent-highlight:  #f0f7f0;   /* hover highlight */
    --info-bg:           #e6ffe6;   /* info/notice boxes */
    --info-bd:           #62a762;   /* info/notice borders */
    --white:            #ffffff;
    --text:             #000000;
    --link:             #355f35;
    --link-hover:       #709ccc;
    /* … */
}
```

A simple color-scheme theme can import the emerald stylesheet and override just
the custom properties. `themes/ruby/`, `themes/sapphire/`, `themes/diamond/`,
`themes/amethyst/`, and `themes/topaz/` all follow exactly this pattern —
`themes/ruby/phorum.css` is a good concrete example to copy from:

```css
@import url('../emerald/phorum.css');   /* relative — see note below */

:root {
    --accent:           #1a5276;
    --accent-header:    #2e86c1;
    --accent-dark:      #154360;
    --accent-light:     #eaf4fb;
    --accent-highlight: #d6eaf8;
    --info-bg:          #d6eaf8;
    --info-bd:          #2e86c1;
    --link:             #154360;
    --link-hover:       #2e86c1;
    --border:           #1a5276;
}
```

> **Use a relative `@import` URL, not a root-relative one.** `ThemeController`
> serves every theme's files at `/theme/{name}/{file}` with no other path
> rewriting, and the main stylesheet `<link>` is built with the `path()` Twig
> helper so it resolves correctly even when Phorum is installed in a
> subfolder. A CSS file can't call `path()`, so `@import url('/theme/emerald/phorum.css')`
> (root-relative) would always point at the domain root and break under a
> subfolder install. `@import url('../emerald/phorum.css')` (relative)
> resolves against the *importing* stylesheet's own URL instead, so it
> automatically inherits whatever prefix was used to fetch it — safe either way.

> **`--border` and `--link` aren't independent of `--accent`.** In this design
> language, `--border` is chosen to closely match `--accent` (so borders read
> as the same color family as filled/header elements), and `--link` is meant
> to equal `--accent-dark`. Override both alongside the `--accent-*` group —
> otherwise your theme will still show emerald's green borders and links.

> **Contrast:** Any color used as a background behind white text must achieve at
> least 4.5:1 contrast ratio (WCAG 2.2 AA) — this applies to both `--accent`
> and `--accent-dark`, since both back button/table-header text and its
> `:hover` state. The emerald `--accent` (#4d824d) is calibrated to 4.55:1.

---

## Assets

All files inside `themes/{name}/` are served at `/theme/{name}/{file}` by the
built-in `ThemeController`. Subdirectory paths work:

| File on disk | URL |
|---|---|
| `themes/mytheme/phorum.css` | `/theme/mytheme/phorum.css` |
| `themes/mytheme/images/logo.png` | `/theme/mytheme/images/logo.png` |
| `themes/mytheme/fonts/myfont.woff2` | `/theme/mytheme/fonts/myfont.woff2` |

In Twig templates, always build asset URLs through the `path()` helper so that
subfolder installs stay correct:

```twig
<img src="{{ path('/theme/' ~ theme ~ '/images/logo.png') }}" alt="Logo">
```

### Allowed file types

`css`, `js`, `png`, `jpg`, `jpeg`, `gif`, `svg`, `ico`, `woff`, `woff2`, `ttf`

Any other extension returns 403. Path traversal attempts (`../`) are blocked —
`realpath()` is used to resolve the file and any path outside the theme
directory is rejected.

### Caching

Assets are served with `Cache-Control: public, max-age=86400` and standard
`ETag` / `Last-Modified` headers. Conditional requests (`If-None-Match`,
`If-Modified-Since`) return 304 when the file has not changed.

---

## Template Overrides

When a page renders, Phorum prepends `themes/{name}/templates/` to Twig's
template search path. Twig finds your override first; for any template your
theme does not provide, it falls back to the default `templates/` directory.

**You only need to include templates you actually change.** A theme that only
reskins colors via CSS needs no `templates/` directory at all.

### Example

To override just the forum index page:

```
themes/mytheme/
└── templates/
    └── forum/
        └── index.html.twig   ← your version
```

Every other template — `base.html.twig`, `message/thread.html.twig`, etc. —
is served unchanged from the default `templates/` directory.

### Extending the base layout

Theme templates can extend or include the default templates normally:

```twig
{# themes/mytheme/templates/base.html.twig #}
{% extends 'base.html.twig' %}   {# this resolves to the DEFAULT base #}
```

> **Caution:** If your theme ships its own `base.html.twig`, it replaces the
> default entirely — `{% extends 'base.html.twig' %}` inside that file would
> create infinite recursion. Name the file something else (e.g. `_base.html.twig`)
> if you need to extend the default while also overriding it.

### Overriding the Header/Footer

After color/CSS changes, the header (top nav) and footer are the next most
common thing a theme wants to change. Both live inside `base.html.twig`
itself:

- Header: the `{% block nav %}...{% endblock %}` block, inside `<nav id="phorum-nav">`.
- Footer: the `<footer id="phorum-footer">...</footer>` markup, right after the `before_footer` hook.

Because every page template does `{% extends 'base.html.twig' %}`, and a
theme's own `base.html.twig` can't `{% extends %}` the default one without
recursing into itself (see the caution above), Twig's normal block-inheritance
trick doesn't work here — a theme can't override just the `nav` block in
isolation. **The only way to change the header or footer markup is to copy
the whole file:**

```
themes/mytheme/
└── templates/
    └── base.html.twig   ← full copy of the default, nav/footer edited
```

Since this file becomes the entire page shell for every template that
extends it, keep everything else intact when you copy it:

- The `{% block title %}`, `{% block head %}`, `{% block content %}`, and
  `{% block scripts %}` blocks — every other template fills these in, and
  removing one will break pages that rely on it.
- Every `{{ hook(...) }}` call (`css_register`, `css_filter`, `start_output`,
  `after_header`, `before_footer`, `javascript_filter`, `end_output`) —
  these are how mods inject markup/assets; dropping one silently breaks any
  installed mod that relies on it.
- The viewport `<meta>` tag, the stylesheet `<link>`, and the skip-link —
  removing any of these regresses mobile layout, theming, or accessibility.

If you only need to *add* something near the header or footer — a promo
banner, extra footer links — without changing the existing nav/footer
markup, use the `after_header` / `before_footer` hooks instead of copying
`base.html.twig`. These are part of the procedural mod hook system
(`src/Hook/functions.php`), not theme-specific, but they're the lighter-weight
option when you don't need to restyle what's already there.

---

## Activating a Theme

### Site-wide

Set the `template` key in `etc/phorum.php`:

```php
'template' => 'mytheme',
```

### Per-forum

Individual forums can override the site-wide theme. Set the `template` column
on the forum row via the admin panel (Forum → Edit → Template). An empty string
means "use the site default".

---

## Distributing a Theme

A theme is self-contained in its directory. To distribute one:

1. Zip the directory: `zip -r mytheme.zip themes/mytheme/`
2. Recipients extract it into their own `themes/` directory.
3. They select it in the admin panel or set `'template' => 'mytheme'` in
   `etc/phorum.php`.

No database changes and no code changes are required to install a theme.
