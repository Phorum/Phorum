<?php
declare(strict_types=1);

namespace Phorum\Core\Concerns;

use DealNews\DB\CRUD;

/** Shared by SchemaInstaller and SchemaPatcher: a lazily-created CRUD handle for PHORUM_DB. */
trait HasCrud
{
    private ?CRUD $crud = null;

    protected function crud(): CRUD
    {
        if ($this->crud === null) {
            $db         = defined('PHORUM_DB') ? PHORUM_DB : 'phorum';
            $this->crud = CRUD::factory($db);
        }
        return $this->crud;
    }
}
