<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\SettingRepository;

/** Every setting, for admin.settings. Values are checked by type before anything is saved. */
final class SettingsService
{
    public const RUBRIC_ROWS = 8;

    // key => label, type, help, and the allowed range for numbers.
    public const FIELDS = [
        'Organization' => [
            'org.full_name' => ['label' => 'Organization name', 'type' => 'text', 'help' => 'Shown in the footer of every email.'],
            'org.contact_email' => ['label' => 'Contact email', 'type' => 'email', 'help' => 'Shown in every email. Public pages and error pages use ORG_CONTACT_EMAIL in .env, so keep the two the same.'],
            'org.facebook_url' => ['label' => 'Facebook page', 'type' => 'url', 'help' => 'Optional. A full web address.'],
            'org.mission' => ['label' => 'Mission', 'type' => 'textarea'],
            'org.vision' => ['label' => 'Vision', 'type' => 'textarea'],
        ],
        'Email signature' => [
            'email.signatory_name' => ['label' => 'Name', 'type' => 'text'],
            'email.signatory_position' => ['label' => 'Position', 'type' => 'text'],
            'email.signatory_committee' => ['label' => 'Committee', 'type' => 'text'],
        ],
        'System' => [
            'upload.max_mb' => ['label' => 'Upload limit in MB', 'type' => 'int', 'min' => 1, 'max' => 20, 'help' => 'For each file. php.ini must allow this size too.'],
            'session.idle_minutes' => ['label' => 'Session timeout in minutes', 'type' => 'int', 'min' => 5, 'max' => 480, 'help' => 'People are logged out after this long with no activity.'],
            'eligibility.grace_days' => ['label' => 'Grace days for new terms', 'type' => 'int', 'min' => 0, 'max' => 60, 'help' => 'Extra days after a screening deadline before pending officers are flagged.'],
            'welfare.guest_submission' => ['label' => 'Guests can submit welfare concerns', 'type' => 'bool', 'help' => 'On by default. When off, Ask for Help sends people to log in.'],
        ],
        'Landing page' => [
            'landing.headline' => ['label' => 'Headline', 'type' => 'text', 'default' => LandingService::DEFAULT_HEADLINE],
            'landing.subheadline' => ['label' => 'Sub-headline', 'type' => 'text', 'default' => LandingService::DEFAULT_SUBHEADLINE],
            'landing.taglines' => ['label' => 'Taglines', 'type' => 'taglines', 'default' => LandingService::DEFAULT_TAGLINES, 'help' => 'Separate them with commas. They show as one small line under the headline.'],
            'landing.hero_image' => ['label' => 'Hero image', 'type' => 'image', 'help' => 'JPG or PNG. Shown in a circle in place of the AGILE logo. Leave empty to keep the logo.'],
            'landing.join_steps' => ['label' => 'How to join steps', 'type' => 'join_steps', 'default' => LandingService::DEFAULT_JOIN_STEPS, 'help' => 'Four lines, one for each step, written as Title | Description.'],
            'landing.show_sections' => ['label' => 'Sections to show', 'type' => 'sections', 'help' => 'Gallery is off by default and shows up to 6 photos. A section with nothing to show stays hidden even when ticked.'],
        ],
    ];

    public function __construct(
        private readonly SettingRepository $settings = new SettingRepository(),
        private readonly FileStorageService $files = new FileStorageService(),
    ) {
    }

    /** @return array{values: array<string, string>, rubric: array<string, mixed>} */
    public function current(): array
    {
        authorize('admin.settings');

        $values = [];

        foreach (self::FIELDS as $fields) {
            foreach ($fields as $key => $field) {
                $values[$key] = (string) $this->settings->value($key, $field['default'] ?? '');
            }
        }

        $landing = new LandingService();
        $values['landing.show_sections'] = json_encode($landing->shownSections(), JSON_THROW_ON_ERROR);
        $values['landing.join_steps'] = $landing->joinStepsText();

        return ['values' => $values, 'rubric' => json_decode((string) $this->settings->value('evaluation.rubric', '{}'), true) ?: []];
    }

    /**
     * Saves nothing unless everything is valid.
     *
     * @param array<string, mixed> $input Field names use underscores for the dots in each key.
     * @param array<string, mixed>|null $heroImage An uploaded file, or null to keep the current one.
     * @return array<string, string> Errors by field name. Empty when saved.
     */
    public function save(array $input, ?array $heroImage = null): array
    {
        authorize('admin.settings');

        $errors = [];
        $clean = [];

        foreach (self::FIELDS as $fields) {
            foreach ($fields as $key => $field) {
                $name = self::fieldName($key);

                if ($field['type'] === 'image') {
                    continue;
                }

                if ($field['type'] === 'sections') {
                    $clean[$key] = $this->sectionsFrom($input[$name] ?? []);

                    continue;
                }

                $value = is_string($input[$name] ?? null) ? trim($input[$name]) : '';
                $error = $this->check($field, $value);

                if ($error !== null) {
                    $errors[$name] = $error;
                }

                $clean[$key] = $field['type'] === 'bool' ? ($value === '1' ? '1' : '0') : $value;
            }
        }

        [$rubric, $rubricError] = $this->rubricFrom($input);

        if ($rubricError !== null) {
            $errors['rubric'] = $rubricError;
        }

        $imageField = self::fieldName('landing.hero_image');

        if ($heroImage !== null) {
            try {
                $this->files->validate($heroImage, ['jpg', 'jpeg', 'png']);
            } catch (FileRejectedException $exception) {
                $errors[$imageField] = $exception->getMessage();
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        $previousImage = (string) $this->settings->value('landing.hero_image', '');

        if ($heroImage !== null) {
            $clean['landing.hero_image'] = $this->files->store($heroImage, 'media', ['jpg', 'jpeg', 'png'])['stored_path'];
        } elseif (($input[$imageField . '_remove'] ?? '') === '1') {
            $clean['landing.hero_image'] = '';
        }

        $clean['evaluation.rubric'] = json_encode($rubric, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        Database::transaction(function () use ($clean): void {
            $changed = [];

            foreach ($clean as $key => $value) {
                $before = (string) $this->settings->value($key, '');

                if ($before !== $value) {
                    $this->settings->set($key, $value, (int) current_user_id());
                    $changed[$key] = ['before' => $before, 'after' => $value];
                }
            }

            if ($changed !== []) {
                AuditService::log('settings.updated', 'settings', null, $changed);
            }
        });

        if ($previousImage !== '' && isset($clean['landing.hero_image']) && $clean['landing.hero_image'] !== $previousImage) {
            $this->files->delete($previousImage);
        }

        return [];
    }

    public static function fieldName(string $key): string
    {
        return str_replace('.', '_', $key);
    }

    /** @param array<string, mixed> $field */
    private function check(array $field, string $value): ?string
    {
        return match ($field['type']) {
            'text' => $value === '' ? 'Please fill this in.' : (mb_strlen($value) > 200 ? 'Please keep this to 200 characters.' : null),
            'textarea' => $value === '' ? 'Please fill this in.' : null,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) === false ? 'Please enter a valid email address.' : null,
            'url' => $value !== '' && preg_match('#^https?://[^\s]+$#i', $value) !== 1 ? 'Please enter a full web address that starts with https://.' : null,
            'taglines' => LandingService::taglinesFrom($value) === [] ? 'Please enter at least one tagline.' : (mb_strlen($value) > 200 ? 'Please keep this to 200 characters.' : null),
            'join_steps' => $this->checkJoinSteps($value),
            'int' => ctype_digit($value) && (int) $value >= $field['min'] && (int) $value <= $field['max'] ? null : "Please enter a whole number from {$field['min']} to {$field['max']}.",
            default => null,
        };
    }

    private function checkJoinSteps(string $value): ?string
    {
        $steps = LandingService::joinStepsFrom($value);

        if (count($steps) !== LandingService::JOIN_STEP_COUNT) {
            return 'Please write exactly ' . LandingService::JOIN_STEP_COUNT . ' lines, one for each step.';
        }

        foreach ($steps as $step) {
            if ($step['title'] === '' || $step['text'] === '') {
                return 'Please write each line as Title | Description.';
            }
        }

        return null;
    }

    private function sectionsFrom(mixed $value): string
    {
        $chosen = is_array($value) ? $value : [];

        return json_encode(array_values(array_intersect(array_keys(LandingService::SECTIONS), $chosen)), JSON_THROW_ON_ERROR);
    }

    /**
     * Weights must add up to 100. A row keeps its key, because saved evaluations refer to it.
     *
     * @param array<string, mixed> $input
     * @return array{0: array<string, mixed>, 1: ?string}
     */
    private function rubricFrom(array $input): array
    {
        $min = is_string($input['rubric_min'] ?? null) ? trim($input['rubric_min']) : '';
        $max = is_string($input['rubric_max'] ?? null) ? trim($input['rubric_max']) : '';
        $labels = is_array($input['rubric_label'] ?? null) ? $input['rubric_label'] : [];
        $weights = is_array($input['rubric_weight'] ?? null) ? $input['rubric_weight'] : [];
        $keys = is_array($input['rubric_key'] ?? null) ? $input['rubric_key'] : [];
        $criteria = [];

        foreach ($labels as $index => $label) {
            $label = is_string($label) ? trim($label) : '';
            $weight = is_string($weights[$index] ?? null) ? trim($weights[$index]) : '';

            if ($label === '' && $weight === '') {
                continue;
            }

            if ($label === '' || !ctype_digit($weight) || (int) $weight < 1) {
                return [[], 'Each criterion needs a name and a whole number weight.'];
            }

            $key = is_string($keys[$index] ?? null) && $keys[$index] !== '' ? $keys[$index] : trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($label)), '_');
            $criteria[] = ['key' => $key, 'label' => $label, 'weight' => (int) $weight];
        }

        if ($criteria === []) {
            return [[], 'Add at least one criterion.'];
        }

        if (count(array_unique(array_column($criteria, 'key'))) !== count($criteria)) {
            return [[], 'Each criterion needs a different name.'];
        }

        if (array_sum(array_column($criteria, 'weight')) !== 100) {
            return [[], 'The weights must add up to 100.'];
        }

        if (!ctype_digit($min) || !ctype_digit($max) || (int) $min >= (int) $max) {
            return [[], 'The lowest score must be a whole number below the highest score.'];
        }

        return [['scale' => ['min' => (int) $min, 'max' => (int) $max], 'criteria' => $criteria], null];
    }
}
