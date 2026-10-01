<?php

namespace App\Modules\Task\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Company-specific, human-readable identifiers for tasks and projects —
 * neither had one before (only the raw auto-increment id). Same design
 * as EmployeeProfileModel::nextEmployeeCode(): companies.short_code as
 * the prefix (e.g. 'TSK-SAY-01'), a per-company scan-based suggestion
 * (see TaskModel::nextTaskCode()/ProjectModel::nextProjectCode()), a
 * real UNIQUE column as the only actual duplicate-prevention. Nullable
 * and additive — every existing task/project keeps working with a blank
 * code until it's next edited; nothing is backfilled or renumbered.
 */
class AddTaskAndProjectCodes extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('task_code', 'tasks')) {
            $this->forge->addColumn('tasks', [
                'task_code' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'uuid'],
            ]);
            $this->forge->addUniqueKey('task_code');
            $this->forge->processIndexes('tasks');
        }

        if (! $this->db->fieldExists('project_code', 'projects')) {
            $this->forge->addColumn('projects', [
                'project_code' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'id'],
            ]);
            $this->forge->addUniqueKey('project_code');
            $this->forge->processIndexes('projects');
        }
    }

    public function down()
    {
        $this->forge->dropColumn('tasks', 'task_code');
        $this->forge->dropColumn('projects', 'project_code');
    }
}
