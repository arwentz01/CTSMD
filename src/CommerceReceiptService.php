<?php
declare(strict_types=1);
require_once __DIR__.'/CommerceInventoryService.php';
require_once __DIR__.'/ThirdParty/tcpdf-qrcode/qrcode.php';
/** Safe capture only: deliberately has no queue, SMTP, webhook or send method. */
final class CommerceReceiptService
{
    public static function ownedTickets(PDO $db,array $u,int $org,int $order,string $token):array
    {CommerceInventoryService::own($db,$u,$org,$order,$token);$q=$db->prepare('SELECT t.*,l.category,l.description FROM commerce_tickets t JOIN commerce_order_lines l ON l.order_id=t.order_id AND l.seat_key=t.seat_key AND l.organization_id=t.organization_id WHERE t.organization_id=? AND t.order_id=? ORDER BY t.id');$q->execute([$org,$order]);return$q->fetchAll();}
    public static function qr(string $token):string
    {
        if(!preg_match('/^[a-f0-9]{64}$/',$token))throw new RuntimeException('Invalid ticket token.');$barcode=(new QRcode('CC1:'.$token,'M'))->getBarcodeArray();$modules=(int)$barcode['num_cols'];$paths=[];foreach($barcode['bcode'] as $y=>$row)foreach($row as $x=>$dark)if($dark)$paths[]='M'.($x+4).' '.($y+4).'h1v1h-1z';return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.($modules+8).' '.($modules+8).'" role="img" aria-label="Sandbox admission QR code" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="#fff"/><path fill="#111" d="'.implode('',$paths).'"/></svg>';
    }
    public static function preview(array $brand,array $order,array $performance):string
    {
        if($order['state']!=='paid'||!str_ends_with(strtolower($order['customer_email']),'.invalid'))throw new RuntimeException('Email preview is limited to confirmed synthetic purchases.');$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');return '<section aria-label="Email preview"><h3>'.$e($brand['name']).' · Sandbox receipt preview</h3><p>Not sent. Capture only. To: '.$e($order['customer_email']).'</p><p>Thank you, '.$e($order['customer_name']).'. Your simulated order '.$e($order['public_reference']).' is confirmed for '.$e($performance['title']).' on '.$e($performance['starts_at']).'.</p><p>Total: '.$e($order['currency']).' '.number_format((int)$order['total_minor']/100,2).'. View tickets in this authenticated sandbox session.</p></section>';
    }
}
