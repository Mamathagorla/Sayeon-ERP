<?php

namespace App\Modules\Auth\Models;

use CodeIgniter\Model;

class RoleModel extends Model
{
    protected $table            = 'roles';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = ['name', 'slug', 'description'];
    protected $useTimestamps    = true;

    public function optionsList(): array
    {
        return $this->orderBy('name', 'ASC')->findAll();
    }

    public function permissionIds(int $roleId): array
    {
        return array_column(
            db_connect()->table('role_permissions')->select('permission_id')->where('role_id', $roleId)->get()->getResultArray(),
            'permission_id'
        );
    }

    public function syncPermissions(int $roleId, array $permissionIds): void
    {
        $db = db_connect();
        $db->table('role_permissions')->where('role_id', $roleId)->delete();

        $rows = array_map(static fn ($pid) => ['role_id' => $roleId, 'permission_id' => (int) $pid], $permissionIds);

        if ($rows !== []) {
            $db->table('role_permissions')->insertBatch($rows);
        }
    }
}
