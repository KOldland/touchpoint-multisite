<?php

namespace KHM\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use KHM\Services\LevelRepository;

class InvoiceService {
    private OrderRepository $orders;
    private LevelRepository $levels;

    public function __construct(OrderRepository $orders, ?LevelRepository $levels = null) {
        $this->orders = $orders;
        $this->levels = $levels ?: new LevelRepository();
    }

    /**
     * Generate a PDF invoice for the given order.
     *
     * @param object $order
     * @return string PDF bytes
     */
    public function generatePDF(object $order): string {
        $html = $this->render_invoice_html($order);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
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
                body { font-family: DejaVu Sans, sans-serif; color:#222; margin: 24px; font-size: 12px; }
                .khm-invoice { max-width: 100%; margin: 0 auto; }
                .khm-invoice header { margin-bottom: 16px; }
                .khm-invoice h1 { font-size: 18px; margin:0 0 4px 0; }
                .khm-meta { color:#666; font-size: 10px; }
                .khm-section { margin: 16px 0; }
                .khm-grid { display:table; width:100%; }
                .khm-grid > div { display:table-cell; width:50%; vertical-align:top; }
                table { width:100%; border-collapse: collapse; }
                th, td { padding: 8px; border-bottom: 1px solid #eee; text-align:left; }
                .khm-total { font-weight: 600; }
                .khm-footer { margin-top: 24px; color:#666; font-size: 10px; }
            </style>
        </head>
        <body>
            <div class="khm-invoice">
                <header>
                    <h1><?php echo esc_html($site); ?></h1>
                    <div class="khm-meta">Invoice #<?php echo esc_html($invoiceId); ?> &middot; <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($date))); ?> &middot; <?php echo esc_html($status); ?></div>
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
