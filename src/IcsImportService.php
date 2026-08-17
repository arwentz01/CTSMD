<?php

declare(strict_types=1);

final class IcsImportService
{
    private const MAX_EXPANDED_EVENTS = 500;
    private const MAX_OCCURRENCES_PER_RULE = 250;
    private const DEFAULT_HORIZON_DAYS = 730;

    public static function parse(string $ics, ?string $fallbackTimezone = null): array
    {
        $ics = trim($ics);
        if ($ics === '' || stripos($ics, 'BEGIN:VCALENDAR') === false) {
            throw new RuntimeException('That file does not appear to be a valid iCalendar (.ics) file.');
        }

        $fallbackTimezone = $fallbackTimezone ?: date_default_timezone_get();
        try {
            $defaultZone = new DateTimeZone($fallbackTimezone);
        } catch (Throwable) {
            $defaultZone = new DateTimeZone('UTC');
        }

        $lines = preg_split('/\r\n|\n|\r/', $ics) ?: [];
        $unfolded = [];
        foreach ($lines as $line) {
            if (($line[0] ?? '') === ' ' || ($line[0] ?? '') === "\t") {
                if ($unfolded) $unfolded[array_key_last($unfolded)] .= substr($line, 1);
                continue;
            }
            $unfolded[] = $line;
        }

        $events = [];
        $eventLines = [];
        $insideEvent = false;
        foreach ($unfolded as $line) {
            $upper = strtoupper(trim($line));
            if ($upper === 'BEGIN:VEVENT') {
                $insideEvent = true;
                $eventLines = [];
                continue;
            }
            if ($upper === 'END:VEVENT') {
                if ($insideEvent) {
                    $event = self::parseEvent($eventLines, $defaultZone);
                    if ($event !== null) {
                        foreach (self::expandEvent($event, $defaultZone) as $expanded) {
                            $events[] = $expanded;
                            if (count($events) > self::MAX_EXPANDED_EVENTS) {
                                throw new RuntimeException('This calendar expands to more than 500 events. Import a smaller calendar or shorter date range.');
                            }
                        }
                    }
                }
                $insideEvent = false;
                $eventLines = [];
                continue;
            }
            if ($insideEvent) $eventLines[] = $line;
        }

        if (!$events) throw new RuntimeException('No calendar events were found in that .ics file.');

        usort($events, static fn(array $a, array $b): int => strcmp((string)$a['starts_at'], (string)$b['starts_at']));
        return $events;
    }

    private static function parseEvent(array $lines, DateTimeZone $defaultZone): ?array
    {
        $props = [];
        foreach ($lines as $line) {
            $colon = strpos($line, ':');
            if ($colon === false) continue;
            $left = substr($line, 0, $colon);
            $value = substr($line, $colon + 1);
            $segments = explode(';', $left);
            $name = strtoupper(array_shift($segments) ?: '');
            if ($name === '') continue;
            $params = [];
            foreach ($segments as $segment) {
                if (!str_contains($segment, '=')) continue;
                [$key, $paramValue] = explode('=', $segment, 2);
                $params[strtoupper(trim($key))] = trim($paramValue, "\"'");
            }
            $props[$name][] = ['value'=>$value, 'params'=>$params];
        }

        $summary = self::textValue($props, 'SUMMARY');
        $startProp = $props['DTSTART'][0] ?? null;
        if ($summary === '' || !$startProp) return null;

        $start = self::dateValue($startProp, $defaultZone);
        if (!$start) return null;
        $endProp = $props['DTEND'][0] ?? null;
        $end = $endProp ? self::dateValue($endProp, $defaultZone) : null;
        $allDay = strtoupper((string)($startProp['params']['VALUE'] ?? '')) === 'DATE' || preg_match('/^\d{8}$/', trim((string)$startProp['value'])) === 1;
        if ($allDay && !$end) $end = $start->modify('+1 day');
        if (!$allDay && !$end) $end = $start->modify('+1 hour');

        $recurrence = isset($props['RRULE'][0]) ? trim((string)$props['RRULE'][0]['value']) : '';
        $status = strtoupper(trim((string)($props['STATUS'][0]['value'] ?? '')));
        $exdates = [];
        foreach ((array)($props['EXDATE'] ?? []) as $property) {
            foreach (explode(',', (string)$property['value']) as $raw) {
                $candidate = $property;
                $candidate['value'] = trim($raw);
                $date = self::dateValue($candidate, $defaultZone);
                if ($date) $exdates[] = $date->format('Y-m-d H:i:s');
            }
        }

        return [
            'uid'=>self::textValue($props, 'UID'),
            'title'=>$summary,
            'starts_at'=>$start->format('Y-m-d H:i:s'),
            'ends_at'=>$end?->format('Y-m-d H:i:s'),
            'location'=>self::textValue($props, 'LOCATION'),
            'description'=>self::textValue($props, 'DESCRIPTION'),
            'all_day'=>$allDay,
            'recurring'=>$recurrence !== '',
            'rrule'=>$recurrence,
            'cancelled'=>$status === 'CANCELLED',
            'exdates'=>$exdates,
        ];
    }

    private static function expandEvent(array $event, DateTimeZone $defaultZone): array
    {
        if (empty($event['rrule'])) return [$event];
        $rule = self::parseRrule((string)$event['rrule']);
        $freq = strtoupper((string)($rule['FREQ'] ?? ''));
        if (!in_array($freq, ['DAILY','WEEKLY','MONTHLY'], true)) {
            $event['recurrence_supported'] = false;
            return [$event];
        }

        $start = new DateTimeImmutable((string)$event['starts_at'], $defaultZone);
        $end = new DateTimeImmutable((string)$event['ends_at'], $defaultZone);
        $duration = max(0, $end->getTimestamp() - $start->getTimestamp());
        $interval = max(1, (int)($rule['INTERVAL'] ?? 1));
        $countLimit = isset($rule['COUNT']) ? max(1, (int)$rule['COUNT']) : null;
        $until = isset($rule['UNTIL']) ? self::rruleUntil((string)$rule['UNTIL'], $defaultZone) : null;
        $hardHorizon = $start->modify('+' . self::DEFAULT_HORIZON_DAYS . ' days');
        if (!$until || $until > $hardHorizon) $until = $hardHorizon;
        $byDays = isset($rule['BYDAY']) ? array_values(array_filter(array_map('trim', explode(',', (string)$rule['BYDAY'])))) : [];
        $exdates = array_fill_keys((array)($event['exdates'] ?? []), true);

        $occurrences = match ($freq) {
            'DAILY' => self::expandDaily($start, $interval, $countLimit, $until),
            'WEEKLY' => self::expandWeekly($start, $interval, $countLimit, $until, $byDays),
            'MONTHLY' => self::expandMonthly($start, $interval, $countLimit, $until, $byDays),
        };

        $expanded = [];
        $index = 0;
        foreach ($occurrences as $occurrenceStart) {
            if (isset($exdates[$occurrenceStart->format('Y-m-d H:i:s')])) continue;
            $copy = $event;
            $copy['starts_at'] = $occurrenceStart->format('Y-m-d H:i:s');
            $copy['ends_at'] = $occurrenceStart->modify('+' . $duration . ' seconds')->format('Y-m-d H:i:s');
            $copy['recurring'] = true;
            $copy['recurrence_supported'] = true;
            $copy['recurrence_index'] = $index++;
            $expanded[] = $copy;
            if (count($expanded) >= self::MAX_OCCURRENCES_PER_RULE) break;
        }

        return $expanded ?: [$event + ['recurrence_supported'=>true]];
    }

    private static function expandDaily(DateTimeImmutable $start, int $interval, ?int $countLimit, DateTimeImmutable $until): array
    {
        $out = [];
        $current = $start;
        while ($current <= $until && count($out) < self::MAX_OCCURRENCES_PER_RULE) {
            $out[] = $current;
            if ($countLimit !== null && count($out) >= $countLimit) break;
            $current = $current->modify('+' . $interval . ' days');
        }
        return $out;
    }

    private static function expandWeekly(DateTimeImmutable $start, int $interval, ?int $countLimit, DateTimeImmutable $until, array $byDays): array
    {
        $dayMap = ['MO'=>1,'TU'=>2,'WE'=>3,'TH'=>4,'FR'=>5,'SA'=>6,'SU'=>7];
        $targets = [];
        foreach ($byDays as $token) {
            $token = strtoupper($token);
            $token = preg_replace('/^[+-]?\d+/', '', $token) ?: $token;
            if (isset($dayMap[$token])) $targets[] = $dayMap[$token];
        }
        if (!$targets) $targets[] = (int)$start->format('N');
        $targets = array_values(array_unique($targets));
        sort($targets);

        $weekStart = $start->modify('monday this week')->setTime((int)$start->format('H'), (int)$start->format('i'), (int)$start->format('s'));
        $out = [];
        $weekIndex = 0;
        while (count($out) < self::MAX_OCCURRENCES_PER_RULE) {
            $base = $weekStart->modify('+' . ($weekIndex * 7 * $interval) . ' days');
            if ($base > $until) break;
            foreach ($targets as $weekday) {
                $candidate = $base->modify('+' . ($weekday - 1) . ' days');
                if ($candidate < $start || $candidate > $until) continue;
                $out[] = $candidate;
                if ($countLimit !== null && count($out) >= $countLimit) break 2;
                if (count($out) >= self::MAX_OCCURRENCES_PER_RULE) break 2;
            }
            $weekIndex++;
        }
        usort($out, static fn(DateTimeImmutable $a, DateTimeImmutable $b): int => $a <=> $b);
        return $out;
    }

    private static function expandMonthly(DateTimeImmutable $start, int $interval, ?int $countLimit, DateTimeImmutable $until, array $byDays): array
    {
        $out = [];
        $monthIndex = 0;
        while (count($out) < self::MAX_OCCURRENCES_PER_RULE) {
            $monthBase = $start->modify('first day of +' . ($monthIndex * $interval) . ' month')->setTime((int)$start->format('H'), (int)$start->format('i'), (int)$start->format('s'));
            if ($monthBase > $until) break;
            $candidates = [];
            if ($byDays) {
                foreach ($byDays as $token) {
                    $candidate = self::monthlyByDay($monthBase, strtoupper($token));
                    if ($candidate) $candidates[] = $candidate;
                }
            } else {
                $day = (int)$start->format('j');
                $daysInMonth = (int)$monthBase->format('t');
                if ($day <= $daysInMonth) $candidates[] = $monthBase->setDate((int)$monthBase->format('Y'), (int)$monthBase->format('n'), $day);
            }
            usort($candidates, static fn(DateTimeImmutable $a, DateTimeImmutable $b): int => $a <=> $b);
            foreach ($candidates as $candidate) {
                if ($candidate < $start || $candidate > $until) continue;
                $out[] = $candidate;
                if ($countLimit !== null && count($out) >= $countLimit) break 2;
                if (count($out) >= self::MAX_OCCURRENCES_PER_RULE) break 2;
            }
            $monthIndex++;
        }
        return $out;
    }

    private static function monthlyByDay(DateTimeImmutable $monthBase, string $token): ?DateTimeImmutable
    {
        if (!preg_match('/^([+-]?\d+)?(MO|TU|WE|TH|FR|SA|SU)$/', $token, $m)) return null;
        $ordinal = isset($m[1]) && $m[1] !== '' ? (int)$m[1] : 1;
        $weekdayNames = ['MO'=>'monday','TU'=>'tuesday','WE'=>'wednesday','TH'=>'thursday','FR'=>'friday','SA'=>'saturday','SU'=>'sunday'];
        $weekday = $weekdayNames[$m[2]];
        if ($ordinal > 0) {
            $candidate = $monthBase->modify('first ' . $weekday . ' of this month');
            if ($ordinal > 1) $candidate = $candidate->modify('+' . ($ordinal - 1) . ' weeks');
        } else {
            $candidate = $monthBase->modify('last ' . $weekday . ' of this month');
            if ($ordinal < -1) $candidate = $candidate->modify('-' . (abs($ordinal) - 1) . ' weeks');
        }
        return $candidate->format('Y-m') === $monthBase->format('Y-m') ? $candidate : null;
    }

    private static function parseRrule(string $rrule): array
    {
        $out = [];
        foreach (explode(';', $rrule) as $part) {
            if (!str_contains($part, '=')) continue;
            [$key, $value] = explode('=', $part, 2);
            $key = strtoupper(trim($key));
            if ($key !== '') $out[$key] = trim($value);
        }
        return $out;
    }

    private static function rruleUntil(string $raw, DateTimeZone $defaultZone): ?DateTimeImmutable
    {
        $property = ['value'=>trim($raw), 'params'=>[]];
        return self::dateValue($property, $defaultZone);
    }

    private static function textValue(array $props, string $name): string
    {
        $raw = (string)($props[$name][0]['value'] ?? '');
        return trim(str_replace(['\\n','\\N','\\,','\\;','\\\\'], ["\n","\n",',',';','\\'], $raw));
    }

    private static function dateValue(array $property, DateTimeZone $defaultZone): ?DateTimeImmutable
    {
        $raw = trim((string)($property['value'] ?? ''));
        if ($raw === '') return null;
        $params = (array)($property['params'] ?? []);
        $tzid = trim((string)($params['TZID'] ?? ''));
        $zone = $defaultZone;
        if ($tzid !== '') {
            try { $zone = new DateTimeZone($tzid); } catch (Throwable) { $zone = $defaultZone; }
        }

        if (preg_match('/^\d{8}$/', $raw)) {
            $date = DateTimeImmutable::createFromFormat('!Ymd', $raw, $zone);
            return $date ?: null;
        }
        if (preg_match('/^\d{8}T\d{6}Z$/', $raw)) {
            $date = DateTimeImmutable::createFromFormat('!Ymd\THis\Z', $raw, new DateTimeZone('UTC'));
            return $date ? $date->setTimezone($defaultZone) : null;
        }
        if (preg_match('/^\d{8}T\d{6}$/', $raw)) {
            $date = DateTimeImmutable::createFromFormat('!Ymd\THis', $raw, $zone);
            return $date ?: null;
        }
        if (preg_match('/^\d{8}T\d{4}$/', $raw)) {
            $date = DateTimeImmutable::createFromFormat('!Ymd\THi', $raw, $zone);
            return $date ?: null;
        }

        try { return new DateTimeImmutable($raw, $zone); } catch (Throwable) { return null; }
    }
}
