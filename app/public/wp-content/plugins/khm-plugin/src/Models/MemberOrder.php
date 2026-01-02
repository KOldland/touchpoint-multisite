<?php
namespace KHM\Models;

class MemberOrder {

    public $id;
    public $user_id;
    public $membership_id;
    public $total;
    public $status;
    public $gateway;
    public $payment_transaction_id;
    public $subscription_transaction_id;
    public $timestamp;

    public function __construct( array $data = [] ) {
        foreach ( $data as $k => $v ) {
            if ( property_exists($this, $k) ) {
                $this->$k = $v;
            }
        }
    }

    public function toArray() {
        return get_object_vars($this);
    }
}
