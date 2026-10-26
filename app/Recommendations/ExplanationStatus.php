<?php

namespace App\Recommendations;

/**
 * Whether personalized AI explanations could be shown.
 */
enum ExplanationStatus: string
{
    /** The AI wrote explanations and they passed the fact check. */
    case Generated = 'generated';

    /** No API key is configured, so the AI was not called. */
    case NotConfigured = 'not_configured';

    /** The AI was called but failed, refused, or its answer failed the fact check. */
    case Failed = 'failed';
}
