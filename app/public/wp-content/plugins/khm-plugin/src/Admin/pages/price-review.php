<?php
$esc = static function ( $value ): string {
    if ( function_exists( 'esc_html' ) ) {
        return esc_html( (string) $value );
    }
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
};

$escAttr = static function ( $value ): string {
    if ( function_exists( 'esc_attr' ) ) {
        return esc_attr( (string) $value );
    }
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
};

$defaults = [
    [ 'key' => 'creative_setup', 'label' => 'Creative setup', 'amount_cents' => 4500 ],
    [ 'key' => 'campaign_management', 'label' => 'Campaign management', 'amount_cents' => 8500 ],
    [ 'key' => 'reporting', 'label' => 'Reporting', 'amount_cents' => 2500 ],
];

$payload = isset( $GLOBALS['khm_price_review_payload'] ) && is_array( $GLOBALS['khm_price_review_payload'] )
    ? $GLOBALS['khm_price_review_payload']
    : [ 'reference_id' => 'demo-price-review', 'currency' => 'AUD', 'items' => $defaults ];
$items = isset( $payload['items'] ) && is_array( $payload['items'] ) ? $payload['items'] : $defaults;
?>
<div class="wrap">
    <h1><?php echo $esc( 'Price Review' ); ?></h1>
    <p><?php echo $esc( 'Adjust demo pricing before checkout. Overrides are validated server-side and stored for the current reference.' ); ?></p>

    <input type="hidden" name="reference_id" value="<?php echo $escAttr( (string) ( $payload['reference_id'] ?? 'demo-price-review' ) ); ?>">
    <input type="hidden" name="currency" value="<?php echo $escAttr( (string) ( $payload['currency'] ?? 'AUD' ) ); ?>">

    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php echo $esc( 'Item' ); ?></th>
                <th><?php echo $esc( 'Amount (cents)' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $items as $item ) : ?>
                <tr data-price-review-row data-key="<?php echo $escAttr( (string) ( $item['key'] ?? '' ) ); ?>" data-label="<?php echo $escAttr( (string) ( $item['label'] ?? '' ) ); ?>">
                    <td><?php echo $esc( (string) ( $item['label'] ?? '' ) ); ?></td>
                    <td><input type="number" min="0" max="5000000" value="<?php echo $escAttr( (string) ( $item['amount_cents'] ?? 0 ) ); ?>"></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p>
        <button type="button" class="button button-primary" id="khm-price-review-save"><?php echo $esc( 'Save overrides' ); ?></button>
        <span id="khm-price-review-status" style="margin-left:12px;"></span>
    </p>
</div>
