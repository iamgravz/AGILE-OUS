<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Core\Validator;

/**
 * Year level, section and contact number, asked together on every form a student submits (Nov 23, 2026): wherever
 * there is a contact number, it is required, and a year level always comes with a section.
 */
final class StudentDetails
{
    /** Article V Section 3 has four year levels. */
    public const YEAR_LEVELS = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
    public const SECTION_MAX = 30;

    /** @return array{year_level: string, section: string, contact_no: string} */
    public static function fromRequest(Request $request): array
    {
        return [
            'year_level' => $request->text('year_level'),
            'section' => mb_strtoupper($request->text('section')),
            'contact_no' => $request->text('contact_no'),
        ];
    }

    /**
     * @param array{year_level?: string, section?: string, contact_no?: string} $details
     * @return array<string, string>
     */
    public static function errors(array $details): array
    {
        $errors = Validator::check($details + ['year_level' => '', 'section' => '', 'contact_no' => ''], [
            'section' => ['label' => 'Section', 'rules' => ['required', 'max:' . self::SECTION_MAX], 'message' => 'Enter your section, for example BSITOUMN 3-2.'],
            'contact_no' => ['label' => 'Contact number', 'rules' => ['required', 'regex:' . Validator::PHONE_PATTERN], 'message' => 'Enter a valid contact number, such as 09171234567.'],
        ]);

        if (!in_array($details['year_level'] ?? '', self::YEAR_LEVELS, true)) {
            $errors = ['year_level' => 'Please choose your year level.'] + $errors;
        }

        return $errors;
    }
}
