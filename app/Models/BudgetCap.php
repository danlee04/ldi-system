<?php

namespace App\Models;

use Database\Factories\BudgetCapFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * How much may be spent from one budget source in one year.
 *
 * Going over is a warning, never a block — the money is committed outside
 * this system and HR needs to record what actually happened.
 *
 * @property int $id
 * @property int $year
 * @property string $budget_source
 * @property string $amount
 */
class BudgetCap extends Model
{
    /** @use HasFactory<BudgetCapFactory> */
    use HasFactory;

    protected $fillable = ['year', 'budget_source', 'amount'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * What the plans already committed against this cap.
     */
    public function committed(): float
    {
        // A plan may be carried by two funds, so what counts against this
        // cap is the part this fund carried, not the plan's whole budget.
        return (float) LdiTraining::query()
            ->where(fn ($query) => $query
                ->where('budget_source', $this->budget_source)
                ->orWhere('other_budget_source', $this->budget_source))
            ->whereYear('date_start', $this->year)
            ->get()
            ->sum(fn (LdiTraining $plan): float => $plan->fundedBy($this->budget_source));
    }

    public function remaining(): float
    {
        return (float) $this->amount - $this->committed();
    }

    /**
     * The cap covering a source in a year, if one was set.
     */
    public static function forSourceAndYear(?string $budgetSource, ?int $year): ?self
    {
        if ($budgetSource === null || $budgetSource === '' || $year === null) {
            return null;
        }

        return self::query()->where('budget_source', $budgetSource)->where('year', $year)->first();
    }
}
