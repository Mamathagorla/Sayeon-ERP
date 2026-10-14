<?php

namespace App\Modules\Onboarding\Models;

use CodeIgniter\Model;

/** HR's structured assessment of a candidate's submission - one row per onboarding record. */
class EvaluationModel extends Model
{
    protected $table         = 'onboarding_evaluations';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'onboarding_record_id', 'evaluator_id', 'role_group_name', 'technical_assessment', 'experience_assessment',
        'strengths', 'concerns', 'hr_comments', 'decision_notes', 'criteria_scores',
    ];
    protected $useTimestamps = true;

    /** Text fields that count as "an assessment has actually been written". */
    public const TEXT_FIELDS = ['technical_assessment', 'experience_assessment', 'strengths', 'concerns', 'hr_comments', 'decision_notes'];

    public function forRecord(int $recordId): ?array
    {
        $row = $this->select('onboarding_evaluations.*, users.name as evaluator_name')
            ->join('users', 'users.id = onboarding_evaluations.evaluator_id', 'left')
            ->where('onboarding_evaluations.onboarding_record_id', $recordId)
            ->first();

        if ($row !== null) {
            $row['criteria'] = json_decode((string) $row['criteria_scores'], true) ?: [];
        }

        return $row;
    }

    /** True when HR has written at least one assessment field (scores alone do not count). */
    public function hasAssessment(int $recordId): bool
    {
        $row = $this->where('onboarding_record_id', $recordId)->first();
        if ($row === null) {
            return false;
        }
        foreach (self::TEXT_FIELDS as $field) {
            if (trim((string) $row[$field]) !== '') {
                return true;
            }
        }

        return false;
    }
}
