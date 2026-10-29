<?php
declare(strict_types=1);

/** Deliberately ignores presentation, not PHP authorization/data/action logic. */
function operationsPhpContract(string $source): string
{
    $source = preg_replace("~require_once\s+__DIR__\s*\.\s*['\"]/WorkspaceUI\.php['\"];~", '', $source);
    $source = preg_replace('~WorkspaceUI::(?:operationsStyles|styles)\(\$(?:basePath|base)\);~', '', $source);
    $source = str_replace("<?php if(!\$print) {  } ?>", '', $source);
    $source = str_replace("<?php if(!\$print) {  } else {  } ?>", '', $source);
    $source = str_replace("<?=\$print?'':' ux-workspace ux-operations'?>", '', $source);
    $source = str_replace("<?=\$print?'':' ux-operations'?>", '', $source);
    // Hub decoration removed from inline HTML; these expressions never made decisions.
    $source = str_replace(['$e($eyebrow)', '$e($item[\'eyebrow\'])', '$e($item[\'icon\'])'], '', $source);
    $tokens = [];
    foreach (token_get_all($source) as $token) {
        if (is_array($token)) {
            if (in_array($token[0], [T_INLINE_HTML,T_WHITESPACE,T_COMMENT,T_DOC_COMMENT,T_OPEN_TAG,T_CLOSE_TAG,T_OPEN_TAG_WITH_ECHO], true)) continue;
            $value = $token[1];
            if ($token[0] === T_CONSTANT_ENCAPSED_STRING && str_contains($value, 'class=')) {
                $value = preg_replace('/ ops-(?:page|heading|panel|form|toolbar|status|empty)\b/', '', $value);
            }
            $tokens[] = [$token[0], $value];
        } else $tokens[] = $token;
    }
    return hash('sha256', serialize($tokens));
}

/** Preserve form/control protocol attributes independently of visual class changes. */
function operationsFormContract(string $source): string
{
    $source = preg_replace('~\sclass="[^"]*"~', '', $source);
    preg_match_all('~<(?:form|input|select|textarea|button|option)\b[^>]*>~i', $source, $tags);
    return hash('sha256', json_encode(array_map(static fn(string $tag): string => preg_replace('/\s+/', ' ', trim($tag)), $tags[0]), JSON_THROW_ON_ERROR));
}

if (realpath($_SERVER['SCRIPT_FILENAME']) !== __FILE__) return;
$root = dirname(__DIR__);
$inventory = json_decode(file_get_contents($root.'/tests/fixtures/operations-routes.json'), true, flags: JSON_THROW_ON_ERROR);
$contracts = json_decode(file_get_contents($root.'/tests/fixtures/operations-php-contracts.json'), true, flags: JSON_THROW_ON_ERROR);
$forms = json_decode(file_get_contents($root.'/tests/fixtures/operations-form-contracts.json'), true, flags: JSON_THROW_ON_ERROR);
$front = file_get_contents($root.'/front.php');
$registered = [];
preg_match_all("~\\['(\\w+)',\\[([^\\]]+)\\]~", $front, $entries, PREG_SET_ORDER);
foreach ($entries as $entry) {
    preg_match_all("~'(/[^']+)'~", $entry[2], $routes);
    foreach ($routes[1] as $route) $registered[$route] ??= $entry[1];
}
$count = 0;
foreach ($contracts as $owner => $hash) {
    $source = file_get_contents($root.'/src/'.$owner.'.php');
    if (!hash_equals($hash, operationsPhpContract($source))) throw new RuntimeException($owner.': PHP behavior changed; review against c6c7852.');
    if (!hash_equals($forms[$owner], operationsFormContract($source))) throw new RuntimeException($owner.': baseline form/control protocol attributes changed.');
    if (preg_match('/(?<![a-z0-9-])ops-(page|heading|panel|form) ops-\1\b/', $source)) throw new RuntimeException($owner.': duplicate component adoption.');
    if (preg_match('/(WorkspaceUI::operationsStyles\([^;]+;\s*\?>)\s*<\?php\s*WorkspaceUI::operationsStyles/', $source)) throw new RuntimeException($owner.': duplicate style/script inclusion.');
    ++$count;
}
$script = file_get_contents($root.'/assets/js/ux-operations.js');
foreach (['fetch(', 'XMLHttpRequest', '.submit(', '.requestSubmit('] as $mutation) {
    if (str_contains($script, $mutation)) throw new RuntimeException('Table enhancement must not submit or request data.');
}
foreach ($inventory['rows'] as $row) {
    if (($registered[$row['route']] ?? null) !== $row['owner']) throw new RuntimeException('Dispatch-order owner mismatch: '.$row['route']);
    if ($row['kind'] === 'screen') {
        $owner = $row['renderOwner'] ?? $row['owner'];
        $source = file_get_contents($root.'/src/'.$owner.'.php');
        foreach (['WorkspaceUI::operationsStyles(', 'ux-operations', 'ops-page'] as $adoption) {
            if (!str_contains($source, $adoption)) throw new RuntimeException($row['route'].': missing explicit presentation adoption '.$adoption);
        }
        // Do not mistake legacy "regops-page" / "emailops-page" for an ops-page class token.
        if (!preg_match('~class="(?:[^"\r\n]* )?ops-page(?: |")~', $source)) throw new RuntimeException($row['route'].': missing exact ops-page token.');
    }
}
echo "PASS: $count Operations renderers preserve baseline PHP and form/control protocol contracts; registered-route inventory checked. Presentation and actual interactions require browser tests.\n";
