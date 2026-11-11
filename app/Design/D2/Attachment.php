<?php

namespace App\Design\D2;

use App\Http\Requests\StoreEnquiryRequest;

/**
 * Design 2: whether the consultation form shows the optional document ("Attach a Document" and its hint).
 *
 * Design 2.1 brief §5.2 and both Word files (homepage note 0253, Contact note 0133 and rule 8): the field may exist
 * only if the backend checks type and size from the file content, scans the file (ClamAV or equivalent) and
 * restricts access; otherwise the field and its hint are removed and the form works without a file.
 *
 * What the shared enquiry backend does today (EnquiryController, StoreEnquiryRequest; never changed by Design 2):
 *   - count, size (summit.enquiries.attachment_max_bytes) and type read from the content with finfo: yes;
 *   - storage on the private `local` disk (storage/app/private, no public URL): yes;
 *   - a malware scan (ClamAV or equivalent): NO. Nothing scans an upload before it is stored.
 *
 * So Design 2 never renders the field, whatever SUMMIT_ATTACHMENTS_ENABLED says (Design 1 keeps its own rule).
 * The dropzone (<x-d2.upload.dropzone>) and the wizard's document path stay in the code, dormant: once the backend
 * scans every upload, set config('design.d2.attachment_scanned') to true (in config/design.php, with the scanner
 * named in its comment) together with summit.features.attachments_enabled. Tests set it to exercise the path.
 */
final class Attachment
{
    public static function enabled(): bool
    {
        return (bool) config('design.d2.attachment_scanned', false) && StoreEnquiryRequest::attachmentsEnabled();
    }
}
