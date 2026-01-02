<?php

namespace KHM\Rest;

use WP_REST_Request;
use WP_REST_Response;
use KHM\Services\OrderRepository;
use KHM\Services\InvoiceService;
use KHM\Services\LevelRepository;

class InvoiceController {
    private OrderRepository $orders;
    private InvoiceService $invoice_service;
    private LevelRepository $levels;

    public function __construct() {
        $this->orders = new OrderRepository();
        $this->levels = new LevelRepository();
        $this->invoice_service = new InvoiceService($this->orders, $this->levels);
    }

    public function register(): void {
        add_action('rest_api_init', function() {
            register_rest_route('khm/v1', '/orders/(?P<code>[^/]+)/invoice', [
                'methods' => 'GET',
                'callback' => [ $this, 'view_invoice' ],
                'permission_callback' => function() { return is_user_logged_in(); },
                'args' => [
                    'code' => [ 'required' => true, 'type' => 'string' ],
                ],
            ]);

            register_rest_route('khm/v1', '/orders/(?P<code>[^/]+)/invoice/pdf', [
                'methods' => 'GET',
                'callback' => [ $this, 'download_pdf' ],
                'permission_callback' => function() { return is_user_logged_in(); },
                'args' => [
                    'code' => [ 'required' => true, 'type' => 'string' ],
                ],
            ]);
        });
    }

    public function view_invoice( WP_REST_Request $request ) {
        $userId = get_current_user_id();
        $code = (string) $request->get_param('code');

        $order = $this->orders->findByCode($code);
        if (!$order || (int)$order->user_id !== (int)$userId) {
            return new WP_REST_Response([ 'message' => __('Order not found.', 'khm-membership') ], 404);
        }

        $html = $this->render_invoice_html($order);
        $resp = new WP_REST_Response($html, 200);
        $resp->header('Content-Type', 'text/html; charset=UTF-8');
        return $resp;
    }

    public function download_pdf( WP_REST_Request $request ) {
        $userId = get_current_user_id();
        $code = (string) $request->get_param('code');

        $order = $this->orders->findByCode($code);
        if (!$order || (int)$order->user_id !== (int)$userId) {
            return new WP_REST_Response([ 'message' => __('Order not found.', 'khm-membership') ], 404);
        }

        $pdf = $this->invoice_service->generatePDF($order);
        $filename = sprintf('invoice-%s.pdf', $code);

        $resp = new WP_REST_Response($pdf, 200);
        $resp->header('Content-Type', 'application/pdf');
        $resp->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $resp->header('Content-Length', strlen($pdf));
        return $resp;
    }

    private function render_invoice_html( object $order ): string {
        $level = $this->levels->get((int) $order->membership_id);
        $user = get_userdata((int)$order->user_id);

        $site = get_bloginfo('name');
        $date = $order->timestamp ?? current_time('mysql');
        $status = ucfirst($order->status ?? '');
        $levelName = $level ? $level->name : __('Membership', 'khm-membership');
        $amount = $this->format_price((float)($order->total ?? 0));
        $subtotal = $this->format_price((float)($order->subtotal ?? 0));
        $tax = $this->format_price((float)($order->tax ?? 0));
        $invoiceId = $order->payment_transaction_id ?: $order->code;

        $discountSummary = $this->discount_summary($order);
        $trialSummary = $this->trial_summary($order);
        $recurringSummary = $this->recurring_summary($order);

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8" />
            <title><?php echo esc_html(sprintf(__('Invoice %s', 'khm-membership'), $invoiceId)); ?></title>
            <style>
                body { font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:#222; margin: 24px; }
                .khm-invoice { max-width: 760px; margin: 0 auto; }
                .khm-invoice header { display:flex; justify-content:space-between; align-items:center; margin-bottom: 16px; }
                .khm-invoice h1 { font-size: 20px; margin:0; }
                .khm-meta { color:#666; font-size: 12px; }
                .khm-section { margin: 16px 0; }
                .khm-grid { display:grid; grid-template-columns: 1fr 1fr; gap: 12px; }
                table { width:100%; border-collapse: collapse; }
                th, td { padding: 8px; border-bottom: 1px solid #eee; text-align:left; }
                .khm-total { font-weight: 600; }
                .khm-footer { margin-top: 24px; color:#666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="khm-invoice">
                <header>
                    <div>
                        <h1><?php echo esc_html($site); ?></h1>
                        <div class="khm-meta">Invoice #<?php echo esc_html($invoiceId); ?> · <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($date))); ?> · <?php echo esc_html($status); ?></div>
                    </div>
                    <div class="khm-meta">
                        <?php echo esc_html(get_bloginfo('url')); ?>
                    </div>
                </header>

                <div class="khm-section khm-grid">
                    <div>
                        <strong><?php esc_html_e('Billed To', 'khm-membership'); ?></strong><br />
                        <?php echo esc_html($user ? $user->display_name : ''); ?><br />
                        <?php echo esc_html($user ? $user->user_email : ''); ?>
                    </div>
                    <div>
                        <strong><?php esc_html_e('Membership', 'khm-membership'); ?></strong><br />
                        <?php echo esc_html($levelName); ?>
                    </div>
                </div>

                <div class="khm-section">
                    <table>
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Description', 'khm-membership'); ?></th>
                                <th style="width: 140px;"><?php esc_html_e('Amount', 'khm-membership'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php esc_html_e('Membership Payment', 'khm-membership'); ?></td>
                                <td><?php echo esc_html($amount); ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e('Subtotal', 'khm-membership'); ?></td>
                                <td><?php echo esc_html($subtotal); ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e('Tax', 'khm-membership'); ?></td>
                                <td><?php echo esc_html($tax); ?></td>
                            </tr>
                            <?php if ($discountSummary): ?>
                            <tr>
                                <td><?php esc_html_e('Discount', 'khm-membership'); ?></td>
                                <td><?php echo esc_html($discountSummary); ?></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="khm-total"><?php esc_html_e('Total', 'khm-membership'); ?></td>
                                <td class="khm-total"><?php echo esc_html($amount); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <?php if ($trialSummary || $recurringSummary): ?>
                <div class="khm-section">
                    <?php if ($trialSummary): ?>
                        <div><strong><?php esc_html_e('Trial', 'khm-membership'); ?>: </strong><?php echo esc_html($trialSummary); ?></div>
                    <?php endif; ?>
                    <?php if ($recurringSummary): ?>
                        <div><strong><?php esc_html_e('Recurring', 'khm-membership'); ?>: </strong><?php echo esc_html($recurringSummary); ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="khm-footer">
                    <?php esc_html_e('Thank you for your membership!', 'khm-membership'); ?>
                </div>
            </div>
        </body>
        </html>
        <?php
        return (string) ob_get_clean();
    }

    private function format_price( float $amount ): string {
        return '$' . number_format($amount, 2);
    }

    private function discount_summary( object $order ): string {
        $parts = [];
        if (!empty($order->discount_code)) {
            $parts[] = sprintf(__('Code %s', 'khm-membership'), (string)$order->discount_code);
        }
        if (!empty($order->discount_amount)) {
            $parts[] = sprintf(__('-%s', 'khm-membership'), $this->format_price((float)$order->discount_amount));
        }
        return implode(' · ', $parts);
    }

    private function trial_summary( object $order ): string {
        $days = isset($order->trial_days) ? (int)$order->trial_days : 0;
        $amt = isset($order->trial_amount) ? (float)$order->trial_amount : 0.0;
        if ($days > 0) {
            if ($amt > 0) {
                return sprintf(__('%d-day trial at %s', 'khm-membership'), $days, $this->format_price($amt));
            }
            return sprintf(__('%d-day free trial', 'khm-membership'), $days);
        }
        return '';
    }

    private function recurring_summary( object $order ): string {
        $parts = [];
        if (!empty($order->recurring_discount_type) && !empty($order->recurring_discount_amount)) {
            if ($order->recurring_discount_type === 'percent') {
                $parts[] = sprintf(__('%s%% off recurring', 'khm-membership'), (float)$order->recurring_discount_amount);
            } else {
                $parts[] = sprintf(__('%s off recurring', 'khm-membership'), $this->format_price((float)$order->recurring_discount_amount));
            }
        }
        if (!empty($order->first_payment_only)) {
            $parts[] = __('Discount on first payment only', 'khm-membership');
        }
        return implode(' · ', $parts);
    }
}
