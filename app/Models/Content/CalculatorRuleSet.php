<?php

namespace App\Models\Content;

use App\Models\Concerns\HasCmsFields;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * A versioned rule set of a calculator (gratuity | rent) with an effective date (brief §8.2, §8.11; super admin only in
 * Phase 4). rules is JSON text; legal_basis is translatable. Pending client and legal approval.
 */
final class CalculatorRuleSet extends Model
{
    use HasCmsFields;
    use HasTranslations;

    public const CALCULATORS = ['gratuity', 'rent'];

    protected $table = 'calculator_rule_sets';

    protected $guarded = ['id'];

    public array $translatable = ['legal_basis'];

    protected $casts = [
        'version' => 'integer',
        'effective_from' => 'date',
        'rules' => 'array',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
