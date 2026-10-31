<?php
declare(strict_types=1);
require_once __DIR__.'/CommerceStorefrontService.php';
/** Transport boundary: prepare a safe capture; never enqueue or send audience mail. */
final class CommerceDeliveryService
{
    public static function prepare(PDO $db,array $u,int $org,int $order,string $ownership,string $basePath):array
    {
        $o=CommerceInventoryService::own($db,$u,$org,$order,$ownership);
        if($o['state']!=='paid'||!str_ends_with(strtolower($o['customer_email']),'.invalid'))throw new RuntimeException('Delivery capture is limited to confirmed synthetic recipients.');
        $token=CommerceStorefrontService::link($db,$u,$org,$order,$ownership);$url=$basePath.'/tickets/order?'.http_build_query(['token'=>$token]);
        CommerceStore::audit($db,$org,(int)$u['id'],'commerce.delivery_capture_prepared','order',$order,['transport'=>'capture_only','sent'=>false]);
        return ['transport'=>'capture_only','sent'=>false,'recipient'=>$o['customer_email'],'subject'=>'Sandbox tickets · '.$o['public_reference'],'retrieval_path'=>$url,'expires_in_hours'=>24];
    }
}
