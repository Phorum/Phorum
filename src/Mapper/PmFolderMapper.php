<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use Phorum\Model\PmFolder;

class PmFolderMapper extends AbstractPhorumMapper
{
    public const MAPPED_CLASS = PmFolder::class;
    public const PRIMARY_KEY  = 'pm_folder_id';
    public const TABLE_BASE   = 'pm_folders';

    public const MAPPING = [
        'pm_folder_id' => ['read_only' => true],
        'user_id'      => [],
        'foldername'   => [],
    ];

    /** Return all custom folders owned by a user, ordered by name. */
    public function findByUser(int $userId): array
    {
        return $this->find(
            filter: ['user_id' => $userId],
            order:  'foldername ASC'
        ) ?? [];
    }
}
