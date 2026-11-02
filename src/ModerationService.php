<?php

declare(strict_types=1);

final class ModerationService
{
    public static function evaluate(PDO $db, string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            return ['status' => 'published', 'term' => null, 'reason' => null];
        }

        $terms = $db->query("SELECT id,term,category,action,match_mode,severity,aliases_json FROM moderation_terms WHERE active=1 ORDER BY FIELD(severity,'critical','high','medium','low'), id")->fetchAll();
        foreach ($terms as $term) {
            $candidates = [(string)$term['term']];
            if (!empty($term['aliases_json'])) {
                $aliases = json_decode((string)$term['aliases_json'], true);
                if (is_array($aliases)) {
                    foreach ($aliases as $alias) {
                        if (is_string($alias) && trim($alias) !== '') $candidates[] = trim($alias);
                    }
                }
            }

            foreach (array_values(array_unique($candidates)) as $candidate) {
                if (self::matches($body, $candidate, (string)$term['match_mode'])) {
                    $action = (string)$term['action'];
                    return [
                        'status' => $action === 'block' ? 'rejected' : 'pending',
                        'term' => $term,
                        'reason' => ucfirst((string)$term['category']) . ' language matched a moderation rule.',
                    ];
                }
            }
        }

        return ['status' => 'published', 'term' => null, 'reason' => null];
    }

    public static function testRule(string $body, string $term, string $matchMode, array $aliases = []): bool
    {
        foreach (array_merge([$term], $aliases) as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '' && self::matches($body, trim($candidate), $matchMode)) return true;
        }
        return false;
    }

    private static function matches(string $body, string $term, string $matchMode): bool
    {
        $normalizedBody = self::normalize($body);
        $normalizedTerm = trim(self::normalize($term));
        if ($normalizedTerm === '') return false;

        if ($matchMode === 'exact') {
            $needle = preg_quote($normalizedTerm, '/');
            return preg_match('/(?<![\p{L}\p{N}])' . $needle . '(?![\p{L}\p{N}])/u', $normalizedBody) === 1;
        }

        $chars = preg_split('//u', preg_replace('/[^\p{L}\p{N}]+/u', '', $normalizedTerm), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (!$chars) return false;
        $parts = array_map(static fn(string $char): string => preg_quote($char, '/'), $chars);
        $pattern = '/(?<![\p{L}\p{N}])' . implode('[^\p{L}\p{N}]*', $parts) . '(?![\p{L}\p{N}])/u';
        return preg_match($pattern, $normalizedBody) === 1;
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = strtr($value, [
            '@' => 'a', '4' => 'a',
            '$' => 's', '5' => 's',
            '0' => 'o',
            '1' => 'i', '!' => 'i', '|' => 'i',
            '3' => 'e',
            '7' => 't',
        ]);
        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($ascii) && $ascii !== '') $value = mb_strtolower($ascii, 'UTF-8');
        }
        return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    }
}
