<?php

namespace App\Models;

use App\Enums\CampusEnvironment;
use App\Enums\CollegeSize;
use App\Enums\MeritAidAvailability;
use App\Enums\VerificationStatus;
use Database\Factories\CollegeFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A women's college soccer program and its verified facts.
 *
 * Null values mean "unknown"; nothing is filled in without an official source.
 * Published costs are prices before financial aid, not what a family will pay.
 */
class College extends Model
{
    /** @use HasFactory<CollegeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ipeds_unit_id',
        'name',
        'short_name',
        'city',
        'state',
        'ncaa_division',
        'conference',
        'has_active_womens_soccer',
        'womens_soccer_verified_season',
        'federal_locale',
        'undergraduate_enrollment',
        'enrollment_term',
        'offers_business',
        'offers_nursing',
        'offers_psychology',
        'offers_biology',
        'offers_education',
        'programs_note',
        'cost_academic_year',
        'tuition_fees_in_state',
        'tuition_fees_out_of_state',
        'cost_of_attendance_in_state',
        'cost_of_attendance_out_of_state',
        'cost_of_attendance_is_complete',
        'cost_note',
        'merit_aid',
        'merit_aid_note',
        'verification_status',
        'last_verified_on',
        'research_notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ipeds_unit_id' => 'integer',
            'ncaa_division' => 'integer',
            'has_active_womens_soccer' => 'boolean',
            'undergraduate_enrollment' => 'integer',
            'offers_business' => 'boolean',
            'offers_nursing' => 'boolean',
            'offers_psychology' => 'boolean',
            'offers_biology' => 'boolean',
            'offers_education' => 'boolean',
            'tuition_fees_in_state' => 'integer',
            'tuition_fees_out_of_state' => 'integer',
            'cost_of_attendance_in_state' => 'integer',
            'cost_of_attendance_out_of_state' => 'integer',
            'cost_of_attendance_is_complete' => 'boolean',
            'merit_aid' => MeritAidAvailability::class,
            'verification_status' => VerificationStatus::class,
            'last_verified_on' => 'date',
        ];
    }

    /**
     * @return HasMany<CollegeSource, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(CollegeSource::class);
    }

    /**
     * Only colleges with a verified active women's soccer program and a confirmed
     * NCAA division may be recommended.
     */
    #[Scope]
    protected function eligibleForRecommendations(Builder $query): void
    {
        $query->where('has_active_womens_soccer', true)
            ->whereNotNull('womens_soccer_verified_season')
            ->whereIn('ncaa_division', [1, 2, 3]);
    }

    public function isEligibleForRecommendations(): bool
    {
        return $this->has_active_womens_soccer
            && $this->womens_soccer_verified_season !== null
            && in_array($this->ncaa_division, [1, 2, 3], true);
    }

    /**
     * The broad environment, derived from the official federal locale.
     */
    protected function campusEnvironment(): Attribute
    {
        return Attribute::get(fn (): ?CampusEnvironment => CampusEnvironment::fromFederalLocale($this->federal_locale));
    }

    /**
     * The size category, derived from undergraduate enrollment.
     */
    protected function size(): Attribute
    {
        return Attribute::get(fn (): ?CollegeSize => CollegeSize::fromEnrollment($this->undergraduate_enrollment));
    }
}
