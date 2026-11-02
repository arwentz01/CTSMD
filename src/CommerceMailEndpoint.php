<?php
declare(strict_types=1);
require_once __DIR__.'/Database.php';require_once __DIR__.'/CommerceDeliveryService.php';
/** Signed adapter contract for an approved provider/DSN bridge, not an unauthenticated bounce inbox. */
final class CommerceMailEndpoint
{
    public static function render():never
    {
        header('Content-Type: application/json');header('Cache-Control: no-store');header('X-Robots-Tag: noindex, nofollow');if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');echo '{"error":"method_not_allowed"}';exit;}
        try{$db=Database::connect(dirname(__DIR__));if(strlen((string)getenv('COMMERCE_MAIL_WEBHOOK_SECRET'))<32||!CommerceAccess::schemaReady($db)){http_response_code(503);echo '{"error":"delivery_bridge_unavailable"}';exit;}$q=$db->prepare('SELECT id FROM commerce_organizations WHERE organization_key=?');$q->execute([CommerceAccess::primaryKey()]);$org=(int)$q->fetchColumn();if(!$org)throw new RuntimeException('Owner unavailable');CommerceDeliveryService::receiveEvent($db,$org,(string)file_get_contents('php://input',false,null,0,65537),(string)($_SERVER['HTTP_X_COMMERCE_MAIL_SIGNATURE']??''));echo '{"received":true}';}catch(PDOException){http_response_code(503);echo '{"error":"retry_later"}';}catch(Throwable){http_response_code(400);echo '{"error":"invalid_event"}';}exit;
    }
}
