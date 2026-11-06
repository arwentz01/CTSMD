<?php
declare(strict_types=1);

/**
 * Shared, email-client-safe Commerce visual language. Markup-only:
 * no sender, tracking, external fonts, remote image pixels, or queue changes.
 */
final class CommerceEmailTemplate
{
    private static function e(string $value):string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function start(array $brand,string $eyebrow,string $heading,string $description):string
    {
        $name=self::e((string)$brand['name']);
        $primary=preg_match('/^#[0-9a-f]{6}$/i',(string)($brand['primary']??''))?$brand['primary']:'#8b233b';
        $accent=preg_match('/^#[0-9a-f]{6}$/i',(string)($brand['accent']??''))?$brand['accent']:'#b58a33';
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>'.self::e($heading).' · '.$name.'</title>'
            .'<style>@media screen and (max-width:600px){.ctsmd-email-wrap{padding:16px 10px!important}.ctsmd-email-card{width:100%!important}.ctsmd-email-pad{padding:25px 22px!important}.ctsmd-email-heading{font-size:29px!important}.ctsmd-email-cta{display:block!important;text-align:center!important}}</style>'
            .'</head><body style="margin:0;padding:0;background:#f4f0eb;color:#292528;font-family:Arial,Helvetica,sans-serif">'
            .'<div style="display:none;font-size:1px;color:#f4f0eb;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden">'.self::e($description).'</div>'
            .'<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f4f0eb"><tr><td align="center" class="ctsmd-email-wrap" style="padding:32px 18px">'
            .'<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" class="ctsmd-email-card" style="width:600px;max-width:100%;background:#fff;border:1px solid #e8ded8;border-radius:10px">'
            .'<tr><td style="background:'.$primary.';padding:20px 32px;border-radius:9px 9px 0 0">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="color:#fff;font-family:Georgia,serif;font-weight:bold;font-size:24px;letter-spacing:-.5px">'.$name.'</td>'
            .'<td align="right" style="color:#fff;font-size:10px;text-transform:uppercase;letter-spacing:1.6px">CONNECT</td></tr></table></td></tr>'
            .'<tr><td class="ctsmd-email-pad" style="padding:36px 38px 22px">'
            .'<p style="margin:0 0 12px;font-size:11px;font-weight:bold;text-transform:uppercase;letter-spacing:1.8px;color:'.$primary.'">'.self::e($eyebrow).'</p>'
            .'<h1 class="ctsmd-email-heading" style="font-family:Georgia,Times New Roman,serif;font-size:35px;font-weight:normal;letter-spacing:-.8px;line-height:1.16;margin:0 0 15px;color:#292528">'.self::e($heading).'</h1>'
            .'<p style="font-size:15px;color:#655b57;line-height:1.65;margin:0 0 20px">'.self::e($description).'</p>'
            .'<div style="height:3px;width:52px;background:'.$accent.';margin:0 0 24px"></div>';
    }

    public static function section(string $heading,string $html):string
    {
        return '<h2 style="font:normal 23px Georgia,Times New Roman,serif;margin:24px 0 12px;color:#292528">'.self::e($heading).'</h2>'.$html;
    }

    public static function ticketRow(string $seat,string $category,string $extra=''):string
    {
        return '<tr><td style="padding:13px 8px 13px 0;border-bottom:1px solid #eee6e0;font-size:14px;font-weight:bold;color:#292528">'.self::e($seat).'</td>'
            .'<td style="padding:13px 0;border-bottom:1px solid #eee6e0;text-align:right;font-size:13px;color:#6a605c">'.self::e($category.($extra!==''?' · '.$extra:'')).'</td></tr>';
    }

    public static function ticketTable(string $rows):string
    {
        return '<table role="table" cellpadding="0" cellspacing="0" width="100%" style="border-top:1px solid #e6dbd4;border-collapse:collapse">'.$rows.'</table>';
    }

    public static function amountRow(string $label,string $amount,bool $total=false):string
    {
        $style=$total?'font-size:17px;font-weight:bold;color:#292528;border-top:2px solid #d6c4b8;padding-top:15px':'font-size:13px;color:#655b57';
        return '<tr><td style="padding:7px 0;'.$style.'">'.self::e($label).'</td><td align="right" style="padding:7px 0;'.$style.'">'.self::e($amount).'</td></tr>';
    }

    public static function finish(array $brand,string $buttonLabel,string $url,string $instruction,string $footer):string
    {
        $primary=preg_match('/^#[0-9a-f]{6}$/i',(string)($brand['primary']??''))?$brand['primary']:'#8b233b';
        $eurl=self::e($url);
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:30px 0 0"><tr><td align="center" style="border-radius:6px;background:'.$primary.'">'
            .'<a class="ctsmd-email-cta" href="'.$eurl.'" style="display:block;color:#fff;text-decoration:none;font-weight:bold;font-size:15px;padding:16px 22px">'.self::e($buttonLabel).'</a>'
            .'</td></tr></table>'
            .'<p style="margin:17px 0 0;color:#665c59;font-size:13px;line-height:1.6">'.self::e($instruction).'</p>'
            .'<p style="margin:14px 0 0;color:#716661;font-size:12px;line-height:1.5">Button not working? Copy this link:<br><a style="color:'.$primary.';overflow-wrap:anywhere;word-break:break-all" href="'.$eurl.'">'.$eurl.'</a></p>'
            .'</td></tr><tr><td style="border-top:1px solid #eee6e0;padding:22px 38px 28px;background:#fcfaf8;border-radius:0 0 9px 9px">'
            .'<p style="margin:0 0 8px;font-size:12px;color:#726862;line-height:1.6">'.self::e($footer).'</p>'
            .'<p style="margin:0;font-size:11px;color:#8a807b">Sent by '.self::e((string)$brand['name']).' · CTSMD Connect</p>'
            .'</td></tr></table></td></tr></table></body></html>';
    }
}
