<?php

namespace App\Content\Cms;

/**
 * Client-approval flag of every record, block and interface string (docs/briefs/design-2.1-and-admin-cms.md §8.7).
 */
final class CopySource
{
    /** Seeded from the approved DOCX files (black text). */
    public const DOCX = 'docx';

    /** Written by the team (tool texts, new interface labels): pending client approval. */
    public const PENDING = 'pending';

    /** Approved by the client after it was pending. */
    public const CLIENT_APPROVED = 'client_approved';

    public const ALL = [self::DOCX, self::PENDING, self::CLIENT_APPROVED];
}
