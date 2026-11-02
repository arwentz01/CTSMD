<?php

declare(strict_types=1);

require_once __DIR__.'/ThirdParty/tcpdf-qrcode/qrcode.php';

final class PlaybillQrService
{
    public static function svg(string $url,bool $transparent=false,int $scale=12):string
    {
        if($url===''||strlen($url)>1800)throw new RuntimeException('The public Playbill URL is invalid.');
        $barcode=(new QRcode($url,'M'))->getBarcodeArray();
        $rows=$barcode['bcode']??[];$modules=(int)($barcode['num_cols']??0);if(!$rows||$modules<1)throw new RuntimeException('The QR code could not be generated.');
        $quiet=4;$size=($modules+$quiet*2)*max(4,min(32,$scale));$paths=[];
        foreach($rows as $y=>$row)foreach($row as $x=>$dark)if($dark)$paths[]='M'.($x+$quiet).' '.($y+$quiet).'h1v1h-1z';
        $background=$transparent?'':'<rect width="100%" height="100%" fill="#fff"/>';
        return '<?xml version="1.0" encoding="UTF-8"?>'.
            '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 '.($modules+$quiet*2).' '.($modules+$quiet*2).'" shape-rendering="crispEdges" role="img" aria-label="QR code for public Playbill">'.
            $background.'<path d="'.implode('',$paths).'" fill="#111"/></svg>';
    }
}
