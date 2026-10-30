<?php

namespace App\Models;

use Database\Factories\CollegeSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An official source that backs one topic of a college's data.
 */
class CollegeSource extends Model
{
    /** @use HasFactory<CollegeSourceFactory> */
    use HasFactory;

    /**
     * The topics a source can back up.
     */
    public const TOPICS = ['athletics', 'federal_data', 'programs', 'costs', 'merit_aid', 'admissions'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'college_id',
        'topic',
        'url',
        'data_period',
        'accessed_on',
        'note',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accessed_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<College, $this>
     */
    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }
}
