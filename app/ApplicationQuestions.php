<?php
declare(strict_types=1);
namespace Agile;

/** Server-authoritative, position-specific application questions. */
final class ApplicationQuestions {
    public static function forRole(string $role): array {
        return match ($role) {
            'General Member' => [
                ['key'=>'interests','label'=>'Which IT topics or AGILE activities interest you?','type'=>'textarea','required'=>true],
            ],
            'Committee Member' => [
                ['key'=>'preferred_committee','label'=>'Preferred AGILE committee','type'=>'text','required'=>true],
                ['key'=>'relevant_skills','label'=>'Relevant skills or experience','type'=>'textarea','required'=>true],
            ],
            'Deputy Committee Head' => [
                ['key'=>'preferred_committee','label'=>'Committee you wish to help lead','type'=>'text','required'=>true],
                ['key'=>'leadership_experience','label'=>'Previous leadership or team experience','type'=>'textarea','required'=>true],
                ['key'=>'availability','label'=>'Availability and ability to support the committee head','type'=>'textarea','required'=>true],
            ],
            'Committee Head' => [
                ['key'=>'preferred_committee','label'=>'Committee you wish to lead','type'=>'text','required'=>true],
                ['key'=>'leadership_experience','label'=>'Relevant leadership experience','type'=>'textarea','required'=>true],
                ['key'=>'proposed_programs','label'=>'Programs or improvements you propose','type'=>'textarea','required'=>true],
            ],
            'Executive Officer' => [
                ['key'=>'executive_position','label'=>'Executive Committee position applied for','type'=>'text','required'=>true],
                ['key'=>'leadership_experience','label'=>'Prior leadership experience','type'=>'textarea','required'=>true],
                ['key'=>'organizational_vision','label'=>'Your vision for AGILE OUS','type'=>'textarea','required'=>true],
            ],
            'The Source Code' => [
                ['key'=>'publication_position','label'=>'The Source Code position applied for','type'=>'select','required'=>true,
                 'options'=>['Managing Editor','News Editor','Feature Editor','Writer','Head Cartoonist','Cartoonist','Layout Editor','Head Photojournalist','Photojournalist','Videographer/Editor']],
                ['key'=>'publication_experience','label'=>'Relevant writing, editing, creative or production experience','type'=>'textarea','required'=>true],
                ['key'=>'portfolio_url','label'=>'Portfolio URL (optional)','type'=>'url','required'=>false],
            ],
            default => [],
        };
    }

    public static function normalize(string $role, mixed $data): array {
        if (!is_array($data)) { $data = []; }
        $out = [];
        foreach (self::forRole($role) as $question) {
            $raw = $data[$question['key']] ?? '';
            $out[$question['key']] = is_string($raw) ? trim($raw) : '';
        }
        return $out;
    }

    public static function validate(string $role, mixed $data): array {
        $answers = self::normalize($role, $data);
        $errors = [];
        foreach (self::forRole($role) as $question) {
            $value = $answers[$question['key']];
            if ($question['required'] && $value === '') {
                $errors[] = $question['label'] . ' is required.';
                continue;
            }
            if (strlen($value) > 1200) {
                $errors[] = $question['label'] . ' must be 1,200 characters or fewer.';
            }
            if ($value !== '' && ($question['type'] ?? '') === 'select' &&
                !in_array($value, $question['options'], true)) {
                $errors[] = 'Invalid selection for ' . $question['label'] . '.';
            }
            if ($value !== '' && ($question['type'] ?? '') === 'url' &&
                (!filter_var($value, FILTER_VALIDATE_URL) ||
                 !in_array(strtolower((string)parse_url($value, PHP_URL_SCHEME)), ['https'], true))) {
                $errors[] = $question['label'] . ' must use HTTPS.';
            }
        }
        return $errors;
    }
}
