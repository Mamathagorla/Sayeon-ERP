<?php

namespace App\Modules\Auth\Models;

use CodeIgniter\Model;

class PermissionModel extends Model
{
    protected $table            = 'permissions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = ['slug', 'module', 'description'];
    protected $useTimestamps    = false;

    /**
     * All permissions grouped by module, for the role-edit checkbox matrix.
     */
    public function groupedByModule(): array
    {
        $all = $this->orderBy('module', 'ASC')->orderBy('slug', 'ASC')->findAll();

        $grouped = [];
        foreach ($all as $permission) {
            $grouped[$permission['module']][] = $permission;
        }

        return $grouped;
    }
}
