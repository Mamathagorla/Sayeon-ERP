<?php

namespace App\Modules\Company\Models;

use CodeIgniter\Model;

/**
 * Shared reference data (see the CreateStatesTable migration) — used by
 * both the Company form's State dropdown and the Invoice form's Customer
 * State dropdown. Only countries with rows here get a real dropdown; the
 * forms fall back to a free-text State/Province box for any country that
 * has none (see the inline JS in both views).
 */
class StateModel extends Model
{
    protected $table         = 'states';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['country_id', 'name', 'gst_state_code'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Every state, for embedding as a small country_id => [states] JSON
     * blob the Company/Invoice forms filter client-side — cheap enough
     * (well under 40 rows today) to avoid an AJAX round trip.
     */
    public function allGroupedByCountry(): array
    {
        $grouped = [];
        foreach ($this->orderBy('name', 'ASC')->findAll() as $state) {
            // gst_state_code is only ever set for India's own states/UTs
            // (see CreateStatesTable's seed data) — its presence is what
            // the Customer form uses to decide whether to auto-fill/lock
            // the GSTIN's state-code prefix at all, so every consumer of
            // this method gets it for free rather than needing a second
            // lookup.
            $grouped[(int) $state['country_id']][] = [
                'id'            => (int) $state['id'],
                'name'          => $state['name'],
                'gstStateCode'  => $state['gst_state_code'],
            ];
        }

        return $grouped;
    }
}
