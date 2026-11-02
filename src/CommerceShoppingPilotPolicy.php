<?php
declare(strict_types=1);

/** Shared gate for the restricted combined-ticket Stripe test pilot. */
final class CommerceShoppingPilotPolicy
{
    public static function canStartPayment(bool $local,bool $applicationAdministrator):bool
    {
        if(getenv('COMMERCE_REBUILT_CART_PAYMENT_ENABLED')!=='1'
            ||getenv('COMMERCE_STRIPE_CHECKOUT_ENABLED')!=='1'
            ||getenv('COMMERCE_STRIPE_TEST_ENABLED')!=='1')return false;
        return $local||($applicationAdministrator&&getenv('COMMERCE_HOSTED_CART_PAYMENT_PILOT_ENABLED')==='1');
    }
}
