<?php
declare(strict_types=1);
require_once __DIR__.'/CommerceShoppingCartService.php';

/** One scoped ticket promotion per cart; claims are serialized before Stripe checkout. */
final class CommercePromoService
{
    public static function available(PDO $db):bool
    {
        try{$q=$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('commerce_promo_codes','commerce_promo_cart_codes','commerce_promo_claims')");return (int)$q->fetchColumn()===3;}catch(PDOException){return false;}
    }
    private static function normalize(string $code):string
    {
        $code=strtoupper(trim($code));
        if(!preg_match('/^[A-Z0-9][A-Z0-9_-]{2,47}$/D',$code))throw new RuntimeException('Enter a promo code containing 3–48 letters, numbers, hyphens, or underscores.');
        return $code;
    }
    private static function row(PDO $db,int $org,int $id,bool $lock=false):array
    {
        $q=$db->prepare('SELECT * FROM commerce_promo_codes WHERE organization_id=? AND id=?'.($lock?' FOR UPDATE':''));
        $q->execute([$org,$id]);return $q->fetch()?:throw new RuntimeException('This promo code is unavailable.');
    }
    public static function selected(PDO $db,int $org,int $cart):?array
    {
        if(!self::available($db))return null;
        $q=$db->prepare('SELECT p.* FROM commerce_promo_cart_codes x JOIN commerce_promo_codes p ON p.id=x.promo_id AND p.organization_id=x.organization_id WHERE x.organization_id=? AND x.cart_id=?');
        $q->execute([$org,$cart]);return $q->fetch()?:null;
    }
    private static function valid(array $p):void
    {
        $now=gmdate('Y-m-d H:i:s');
        if(!(int)$p['enabled']||($p['starts_at_utc']!==null&&$p['starts_at_utc']>$now)||$p['expires_at_utc']<$now)throw new RuntimeException('This promo code is no longer available.');
    }
    public static function apply(PDO $db,array $user,int $org,int $cart,string $code):void
    {
        CommerceAccess::demand($db,$user,$org,'test');
        if(!self::available($db))throw new RuntimeException('Promo codes are not configured yet.');
        $code=trim($code);
        CommerceStore::tx($db,function()use($db,$user,$org,$cart,$code){
            $q=$db->prepare('SELECT state,expires_at_utc FROM commerce_shopping_carts WHERE organization_id=? AND id=? AND owner_user_id=? FOR UPDATE');
            $q->execute([$org,$cart,$user['id']]);$c=$q->fetch();
            if(!$c||$c['state']!=='open'||$c['expires_at_utc']===null||$c['expires_at_utc']<=gmdate('Y-m-d H:i:s'))throw new RuntimeException('Your cart is no longer available.');
            if($code===''){$db->prepare('DELETE FROM commerce_promo_cart_codes WHERE organization_id=? AND cart_id=?')->execute([$org,$cart]);return;}
            $q=$db->prepare('SELECT * FROM commerce_promo_codes WHERE organization_id=? AND code=?');
            $q->execute([$org,self::normalize($code)]);$p=$q->fetch();
            if(!$p)throw new RuntimeException('This promo code is unavailable.');
            self::valid($p);
            $q=$db->prepare("SELECT COUNT(*) FROM commerce_shopping_cart_items i JOIN commerce_performances f ON f.id=i.performance_id AND f.organization_id=i.organization_id WHERE i.organization_id=? AND i.cart_id=? AND i.state='held' AND (? IS NULL OR f.production_id=?)");
            $q->execute([$org,$cart,$p['production_id'],$p['production_id']]);
            if(!(int)$q->fetchColumn())throw new RuntimeException('This promo code does not apply to tickets in your cart.');
            $db->prepare('INSERT INTO commerce_promo_cart_codes(cart_id,organization_id,promo_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE promo_id=VALUES(promo_id)')->execute([$cart,$org,$p['id']]);
        });
    }
    /** Groups keyed by performance ID, with subtotal and existing discount in minor units. */
    public static function quote(PDO $db,int $org,int $cart,array $groups):array
    {
        $p=self::selected($db,$org,$cart);
        if(!$p)return ['code'=>null,'discount'=>0,'allocations'=>[],'promo'=>null];
        self::valid($p);
        $eligible=[];$base=0;
        foreach($groups as $performance=>$g){
            $q=$db->prepare('SELECT production_id FROM commerce_performances WHERE organization_id=? AND id=?');
            $q->execute([$org,(int)$performance]);$production=(int)$q->fetchColumn();
            if($p['production_id']!==null&&$production!==(int)$p['production_id'])continue;
            if((int)$g['discount']>0)throw new RuntimeException('Promo codes cannot be combined with an existing per-performance discount. Ask the box office to remove the legacy discount.');$n=max(0,(int)$g['subtotal']-(int)$g['discount']);
            if($n>0){$eligible[(int)$performance]=$n;$base+=$n;}
        }
        if($base===0)throw new RuntimeException('This promo code does not apply to the current tickets.');if($base<(int)($p['minimum_subtotal_minor']??0))throw new RuntimeException('This promo code requires a larger eligible ticket subtotal.');
        $amount=$p['discount_kind']==='percent'?intdiv($base*(int)$p['discount_value']+5000,10000):min($base,(int)$p['discount_value']);if((int)($p['max_discount_minor']??0)>0)$amount=min($amount,(int)$p['max_discount_minor']);
        if($amount<=0)throw new RuntimeException('This promo code cannot reduce the current ticket total.');
        $alloc=[];$remainders=[];$distributed=0;
        foreach($eligible as $id=>$n){$product=$amount*$n;$alloc[$id]=intdiv($product,$base);$remainders[$id]=$product%$base;$distributed+=$alloc[$id];}
        arsort($remainders,SORT_NUMERIC);
        foreach(array_keys($remainders) as $id){if($distributed >= $amount)break;$alloc[$id]++;$distributed++;}
        return ['code'=>$p['code'],'discount'=>$amount,'allocations'=>$alloc,'promo'=>$p];
    }
    /** Called inside the locked checkout transaction, before creating a remote Stripe session. */
    public static function claim(PDO $db,int $org,int $purchase,array $quote):void
    {
        if(!$quote['promo'])return;
        if(!$db->inTransaction())throw new LogicException('Promo claim requires a transaction.');
        $p=self::row($db,$org,(int)$quote['promo']['id'],true);self::valid($p);
        $q=$db->prepare("SELECT COUNT(*) FROM commerce_promo_claims WHERE organization_id=? AND promo_id=? AND state IN ('reserved','redeemed')");
        $q->execute([$org,$p['id']]);$used=(int)$q->fetchColumn();
        if((int)$p['max_redemptions']!==0&&$used>=(int)$p['max_redemptions'])throw new RuntimeException('This promo code has reached its usage limit.');if((int)($p['per_customer_limit']??0)>0){$q=$db->prepare("SELECT COUNT(*) FROM commerce_promo_claims c JOIN commerce_shopping_purchases s ON s.id=c.purchase_id AND s.organization_id=c.organization_id JOIN commerce_shopping_purchases here ON here.organization_id=s.organization_id AND here.id=? WHERE c.organization_id=? AND c.promo_id=? AND c.state IN ('reserved','redeemed') AND s.owner_user_id=here.owner_user_id");$q->execute([$purchase,$org,$p['id']]);if((int)$q->fetchColumn()>=(int)$p['per_customer_limit'])throw new RuntimeException('This promo code has reached your account redemption limit.');}
        $db->prepare("INSERT INTO commerce_promo_claims(purchase_id,organization_id,promo_id,discount_minor,state) VALUES(?,?,?,?,'reserved')")->execute([$purchase,$org,$p['id'],$quote['discount']]);
    }
    public static function redeem(PDO $db,int $org,int $purchase):void
    {
        if(!self::available($db))return;
        $db->prepare("UPDATE commerce_promo_claims SET state='redeemed' WHERE purchase_id=? AND organization_id=? AND state='reserved'")->execute([$purchase,$org]);
    }
    public static function release(PDO $db,int $org,int $purchase):void
    {
        if(!self::available($db))return;
        $db->prepare("UPDATE commerce_promo_claims SET state='released' WHERE purchase_id=? AND organization_id=? AND state='reserved'")->execute([$purchase,$org]);
    }
    public static function create(PDO $db,array $user,int $org,array $input):void
    {
        CommerceAccess::demand($db,$user,$org,'configure');
        if(!self::available($db))throw new RuntimeException('Apply the Commerce promo migration before creating codes.');
        $code=self::normalize((string)($input['code']??''));$kind=(string)($input['kind']??'');
        if(!in_array($kind,['amount','percent'],true))throw new RuntimeException('Choose an amount or percentage.');
        $value=trim((string)($input['value']??''));
        if(!preg_match('/^(?:0|[1-9][0-9]{0,5})(?:\\.[0-9]{1,2})?$/D',$value))throw new RuntimeException('Enter a valid discount amount or percentage.');
        $parts=explode('.',$value);$minor=(int)$parts[0]*100+(int)str_pad($parts[1]??'',2,'0');
        if($minor<1||($kind==='percent'&&$minor>10000)||($kind==='amount'&&$minor>10000000))throw new RuntimeException('Enter a valid nonzero discount.');
        $limit=filter_var($input['limit']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>1000000]]);
        if($limit===false)throw new RuntimeException('Usage limit must be zero for unlimited or a positive number.');
        $production=(int)($input['production']??0);
        if($production){$q=$db->prepare("SELECT COUNT(*) FROM productions WHERE id=? AND lifecycle_stage IN ('upcoming','planning','in_production','performing')");$q->execute([$production]);if(!(int)$q->fetchColumn())throw new RuntimeException('Choose a current or upcoming production.');}
        $money=static function(string $value,string $label):int{if(!preg_match('/^(?:0|[1-9][0-9]{0,6})(?:\\.[0-9]{1,2})?$/D',$value))throw new RuntimeException('Enter a valid '.$label.'.');$p=explode('.',$value);return (int)$p[0]*100+(int)str_pad($p[1]??'',2,'0');};$minimum=$money(trim((string)($input['minimum_amount']??'0')),'minimum purchase');$maxDiscount=$money(trim((string)($input['max_discount_amount']??'0')),'maximum discount');$perCustomer=filter_var($input['per_customer_limit']??'0',FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>1000000]]);if($perCustomer===false)throw new RuntimeException('Per-customer limit must be zero for unlimited or a positive number.');if($kind!=='percent'&&$maxDiscount>0)throw new RuntimeException('A discount cap applies only to percentage promotions.');$date=(string)($input['expires']??'');
        if(!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D',$date)||!checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))||$date<gmdate('Y-m-d'))throw new RuntimeException('Choose a valid future expiration date.');
        $db->prepare('INSERT INTO commerce_promo_codes(organization_id,code,production_id,discount_kind,discount_value,max_redemptions,expires_at_utc,created_by_user_id,minimum_subtotal_minor,per_customer_limit,max_discount_minor) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([$org,$code,$production?:null,$kind,$minor,$limit,$date.' 23:59:59',$user['id'],$minimum,$perCustomer,$maxDiscount]);
        CommerceStore::audit($db,$org,(int)$user['id'],'commerce.promo_created','organization',$org,['code_id'=>(int)$db->lastInsertId(),'scope'=>$production?:'all','max_redemptions'=>$limit]);
    }
    public static function disable(PDO $db,array $user,int $org,int $id):void
    {
        CommerceAccess::demand($db,$user,$org,'configure');
        $db->prepare('UPDATE commerce_promo_codes SET enabled=0 WHERE organization_id=? AND id=?')->execute([$org,$id]);
        CommerceStore::audit($db,$org,(int)$user['id'],'commerce.promo_disabled','organization',$org,['code_id'=>$id]);
    }
}
