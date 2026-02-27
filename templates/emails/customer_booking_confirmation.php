<?php
/**
 * Customer Booking Confirmation Email Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<h2><?php esc_html_e('Booking Confirmation', 'domilocus'); ?></h2>
<p><?php 
/* translators: %s: customer name */
echo esc_html(sprintf(__('Dear %s,', 'domilocus'), esc_html($customer_name))); 
?></p>
<p><?php esc_html_e('Thank you for your booking! Here are the details:', 'domilocus'); ?></p>

<h3><?php esc_html_e('Booking Details', 'domilocus'); ?></h3>
<ul>
    <li><strong><?php esc_html_e('Booking ID:', 'domilocus'); ?></strong> #<?php echo esc_html($booking_id); ?></li>
    <li><strong><?php esc_html_e('Apartment:', 'domilocus'); ?></strong> <?php echo esc_html($apartment_title); ?></li>
    <li><strong><?php esc_html_e('Check-in:', 'domilocus'); ?></strong> <?php echo esc_html($check_in); ?></li>
    <li><strong><?php esc_html_e('Check-out:', 'domilocus'); ?></strong> <?php echo esc_html($check_out); ?></li>
    <li><strong><?php esc_html_e('Guests:', 'domilocus'); ?></strong> <?php echo esc_html($guests); ?>
        <?php if (!is_null($adults) || !is_null($children)) : ?>
            <ul style="margin: 0.25em 0 0 1.1em; list-style: disc;">
                <?php if (!is_null($adults)) : ?>
                    <li><?php 
                    /* translators: %d: number of adults */
                    echo esc_html(sprintf(_n('%d adult', '%d adults', $adults, 'domilocus'), $adults)); 
                    ?></li>
                <?php endif; ?>
                <?php if (!is_null($children) && $children > 0) : ?>
                    <li><?php 
                    /* translators: %d: number of children */
                    echo esc_html(sprintf(_n('%d child', '%d children', $children, 'domilocus'), $children)); 
                    ?></li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    </li>
    <?php if (!empty($tourist_tax)) : ?>
        <li><strong><?php esc_html_e('Tourist Tax:', 'domilocus'); ?></strong> <?php echo esc_html($tourist_tax); ?></li>
    <?php endif; ?>
    <li><strong><?php esc_html_e('Total Amount:', 'domilocus'); ?></strong> <?php echo esc_html($total_amount); ?></li>
</ul>

<?php if (!empty($confirmation_url)) : ?>
    <p>
        <a href="<?php echo esc_url($confirmation_url); ?>" class="button" style="display:inline-block;padding:10px 20px;background:#2c3e50;color:#fff;text-decoration:none;border-radius:4px;">
            <?php esc_html_e('View Booking Details', 'domilocus'); ?>
        </a>
    </p>
<?php endif; ?>

<?php if (!empty($payment_method) && $payment_method === 'bank_transfer' && !empty($bank_transfer_instructions)) : ?>
    <h3><?php esc_html_e('Bank Transfer Instructions', 'domilocus'); ?></h3>
    <?php echo wp_kses_post($bank_transfer_instructions); ?>
<?php endif; ?>

<p><?php esc_html_e('We will contact you soon to confirm your booking.', 'domilocus'); ?></p>

<p><?php esc_html_e('Best regards,', 'domilocus'); ?><br><?php echo esc_html($site_name); ?></p>


