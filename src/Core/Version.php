<?php
declare(strict_types=1);

namespace Phorum\Core;

/**
 * The application's release version — ported from Phorum 6's `define("PHORUM", "6.0.3")`
 * in common.php. Bumped automatically by release.sh when tagging a release
 * (see the "Bumping version" step there); don't hand-edit it outside of a release.
 */
class Version
{
    public const CURRENT = '10.0.0-alpha-3';
}
