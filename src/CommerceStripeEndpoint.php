<?php
declare(strict_types=1);
require_once __DIR__.'/Database.php';require_once __DIR__.'/CommerceStripeService.php';
final class CommerceStripeEndpoint
{
    public static function render():never
    {
        header('Content-Type: application/json');header('Cache-Control: no-store');header('X-Robots-Tag: noindex, nofollow');if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');echo '{"error":"method_not_allowed"}';exit;}
        try{$db=Database::connect(dirname(__DIR__));if(!CommerceStripeProvider::configured()||!CommerceAccess::schemaReady($db)){http_response_code(503);echo '{"error":"provider_unavailable"}';exit;}$q=$db->prepare('SELECT id FROM commerce_organizations WHERE organization_key=?');$q->execute([CommerceAccess::primaryKey()]);$org=(int)$q->fetchColumn();if(!$org)throw new RuntimeException('Owner unavailable');$raw=file_get_contents('php://input',false,null,0,1048577);CommerceStripeService::receive($db,$org,(string)$raw,(string)($_SERVER['HTTP_STRIPE_SIGNATURE']??''));echo '{"received":true}';}
        catch(PDOException){http_response_code(503);echo '{"error":"retry_later"}';}catch(Throwable){http_response_code(400);echo '{"error":"invalid_event"}';}exit;
    }
}
