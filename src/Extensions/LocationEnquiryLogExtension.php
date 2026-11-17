<?php

namespace Antlion\SilverwindSite\Extensions;

use Antlion\SilverwindSite\Models\FormSubmission;
use SilverStripe\Core\Extension;

/**
 * Logs antlion/store-location contact enquiries to this project's
 * FormSubmission admin. The module fires onAfterLocationEnquiry() after each
 * send attempt (store-location 2.x no longer writes FormSubmission itself,
 * so it doesn't depend on this project's model).
 */
class LocationEnquiryLogExtension extends Extension
{
    public function onAfterLocationEnquiry(array $enquiry): void
    {
        $raw = $enquiry['RawData'] ?? [];

        FormSubmission::create([
            'FormName' => $enquiry['FormName'] ?? 'Location contact',
            'FormAction' => $enquiry['FormAction'] ?? '',
            'PageID' => $enquiry['PageID'] ?? 0,
            'Context' => $enquiry['Context'] ?? '',
            'ContextLink' => $enquiry['ContextLink'] ?? '',
            'SubmitterName' => $enquiry['SubmitterName'] ?? '',
            'SubmitterEmail' => $enquiry['SubmitterEmail'] ?? '',
            'SubmitterPhone' => $enquiry['SubmitterPhone'] ?? '',
            'Message' => $enquiry['Message'] ?? '',
            'SentTo' => $enquiry['SentTo'] ?? '',
            'Status' => $enquiry['Status'] ?? 'New',
            'ErrorMessage' => $enquiry['ErrorMessage'] ?? null,
            'RawData' => is_array($raw) ? json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : (string) $raw,
        ])->write();
    }
}
