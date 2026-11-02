<?php
declare(strict_types=1);

/** Presentation identity only. Database::connect loads the existing environment first.
 * Future organization context can supply these values; this does not isolate tenants.
 */
final class OrganizationBranding
{
    public static function fromEnvironment():array
    {
        $value=static function(string $key,string $default=''):string{$v=getenv($key);return $v===false||trim($v)===''?$default:trim($v);};
        $color=static fn(string $v,string $fallback):string=>preg_match('/^#[0-9a-f]{6}$/i',$v)?$v:$fallback;
        $primary=$color($value('ORGANIZATION_PRIMARY_COLOR'),'#8b233b');
        return ['name'=>$value('ORGANIZATION_NAME','CTSMD'),'logo'=>self::safeUrl($value('ORGANIZATION_LOGO')),
            'primary'=>$primary,'accent'=>$color($value('ORGANIZATION_ACCENT_COLOR'),'#b58a33'),
            'on_primary'=>self::contrastInk($primary),'tagline'=>$value('ORGANIZATION_TAGLINE'),
            'welcome'=>$value('ORGANIZATION_WELCOME'),'website'=>self::safeUrl($value('ORGANIZATION_URL'))];
    }
    private static function safeUrl(string $url):string
    {
        if($url===''||preg_match('/[\x00-\x20\\\\]/',$url))return '';
        if(str_starts_with($url,'/')&&!str_starts_with($url,'//'))return $url;
        return filter_var($url,FILTER_VALIDATE_URL)&&in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['https','http'],true)?$url:'';
    }
    private static function contrastInk(string $hex):string
    {
        $l=0;foreach([.2126,.7152,.0722] as $i=>$weight){$v=hexdec(substr($hex,1+$i*2,2))/255;$l+=$weight*($v<=.04045?$v/12.92:(($v+.055)/1.055)**2.4);}
        return (1.05/($l+.05))>=(($l+.05)/.05)?'#ffffff':'#000000';
    }
    public static function css(array $brand):string
    {
        return '--organization-primary:'.$brand['primary'].';--organization-accent:'.$brand['accent'].';--organization-on-primary:'.$brand['on_primary'].';';
    }
}
