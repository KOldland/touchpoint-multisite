<?php
namespace KHM\Models;

class MembershipLevel {

    public $id;
    public $name;
    public $description;
    public $confirmation;
    public $initial_payment;
    public $billing_amount;
    public $cycle_number;
    public $cycle_period;
    public $billing_limit;
    public $trial_amount;
    public $trial_limit;
    public $allow_signups;
    public $expiration_number;
    public $expiration_period;
    public $created_at;
    public $updated_at;
    public $meta = [];

    /** @var mixed Back-compat alias - will mirror cycle_period */
    public $billing_period;

    public function __construct( array $data = [] ) {
        foreach ( $data as $k => $v ) {
            if ( property_exists($this, $k) ) {
                $this->$k = $v;
            }
        }

        if ( isset($data['cycle_period']) && ! isset($data['billing_period']) ) {
            $this->billing_period = $data['cycle_period'];
        }
    }

    public function toArray() {
        return get_object_vars($this);
    }
}
