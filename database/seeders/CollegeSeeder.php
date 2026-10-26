<?php

namespace Database\Seeders;

use App\Enums\MeritAidAvailability;
use App\Enums\VerificationStatus;
use App\Models\College;
use App\Models\CollegeSource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Loads the verified college data from database/data/colleges.json.
 *
 * Safe to run again after editing the file: colleges are matched by their
 * federal IPEDS unit ID and updated, and their sources are replaced.
 */
class CollegeSeeder extends Seeder
{
    public const DATA_PATH = 'data/colleges.json';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path(self::DATA_PATH)), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($data): void {
            foreach ($data['colleges'] as $record) {
                $this->validate($record);

                $college = College::updateOrCreate(
                    ['ipeds_unit_id' => $record['ipeds_unit_id']],
                    [
                        ...Arr::except($record, ['ipeds_unit_id', 'sources']),
                        'last_verified_on' => $data['accessed_on'],
                    ],
                );

                $college->sources()->delete();

                foreach ($record['sources'] as $source) {
                    $college->sources()->create([
                        'topic' => $source['topic'],
                        'url' => $source['url'],
                        'data_period' => $source['data_period'],
                        'accessed_on' => $data['accessed_on'],
                        'note' => $source['note'] ?? null,
                    ]);
                }
            }
        });
    }

    /**
     * Reject a record that is malformed or claims facts without the required sources.
     *
     * @param  array<string, mixed>  $record
     */
    private function validate(array $record): void
    {
        $money = ['nullable', 'integer', 'min:0'];
        $program = ['present', 'nullable', 'boolean'];

        Validator::make($record, [
            'ipeds_unit_id' => ['required', 'integer'],
            'name' => ['required', 'string'],
            'short_name' => ['required', 'string'],
            'city' => ['required', 'string'],
            'state' => ['required', 'string', 'size:2'],
            'ncaa_division' => ['present', 'nullable', Rule::in([1, 2, 3])],
            'conference' => ['present', 'nullable', 'string'],
            'has_active_womens_soccer' => ['required', 'boolean'],
            'womens_soccer_verified_season' => ['present', 'nullable', 'string', 'required_if_accepted:has_active_womens_soccer'],
            'federal_locale' => ['present', 'nullable', 'regex:/^(City|Suburb|Town|Rural): \w+$/'],
            'undergraduate_enrollment' => ['present', 'nullable', 'integer', 'min:0'],
            'enrollment_term' => ['nullable', 'string', 'required_with:undergraduate_enrollment'],
            'offers_business' => $program,
            'offers_nursing' => $program,
            'offers_psychology' => $program,
            'offers_biology' => $program,
            'offers_education' => $program,
            'cost_academic_year' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'tuition_fees_in_state' => $money,
            'tuition_fees_out_of_state' => $money,
            'cost_of_attendance_in_state' => $money,
            'cost_of_attendance_out_of_state' => $money,
            'cost_of_attendance_is_complete' => ['nullable', 'boolean'],
            'merit_aid' => ['required', Rule::enum(MeritAidAvailability::class)],
            'verification_status' => ['required', Rule::enum(VerificationStatus::class)],
            'sources' => ['required', 'array', 'min:1'],
            'sources.*.topic' => ['required', Rule::in(CollegeSource::TOPICS)],
            'sources.*.url' => ['required', 'url:https'],
            'sources.*.data_period' => ['required', 'string'],
        ], [], ['ipeds_unit_id' => "IPEDS ID {$record['ipeds_unit_id']}"])->validate();

        $topics = array_column($record['sources'], 'topic');

        foreach (['athletics' => $record['has_active_womens_soccer'], 'federal_data' => true] as $topic => $required) {
            if ($required && ! in_array($topic, $topics, true)) {
                throw new \InvalidArgumentException("{$record['name']} is missing a '{$topic}' source.");
            }
        }

        $hasCosts = collect(['tuition_fees_in_state', 'tuition_fees_out_of_state', 'cost_of_attendance_in_state', 'cost_of_attendance_out_of_state'])
            ->contains(fn (string $field): bool => $record[$field] !== null);

        if ($hasCosts && ($record['cost_academic_year'] === null || ! in_array('costs', $topics, true))) {
            throw new \InvalidArgumentException("{$record['name']} has costs without an academic year and a 'costs' source.");
        }
    }
}
