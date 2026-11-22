<?php
/**
 * A committee's own initials as its mark (MSW, EER, TSC): what members already call them, instead of a stock icon.
 *
 * @var string $name
 */
$words = array_values(array_filter(preg_split('/[\s,]+/', $name) ?: [], static fn (string $word): bool => !in_array(strtolower($word), ['and', 'of', 'the', '&'], true) || $word === 'The'));
$mark = count($words) === 1 ? mb_strtoupper(mb_substr($words[0], 0, 3)) : implode('', array_map(static fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)), array_slice($words, 0, 4)));
?>
<span class="px-mark" aria-hidden="true"><?= e($mark) ?></span>
