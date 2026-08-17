<?php

declare(strict_types=1);

final class IcsImportService
{
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
                    if ($event !== null) $events[] = $event;
                }
                $insideEvent = false;
                $eventLines = [];
                continue;
            }
            if ($insideEvent) $eventLines[] = $line;
        }

        if (!$events) throw new RuntimeException('No calendar events were found in that .ics file.');
        if (count($events) > 500) throw new RuntimeException('This calendar contains more than 500 events. Import a smaller calendar or date range.');

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
        ];
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
