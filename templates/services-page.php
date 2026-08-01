<?php
/**
 * Frontend Template — Services & Plans Page
 *
 * @package BusinessVance_Services_Manager
 * @since   1.0.0
 *
 * @var array    $services       Array of service objects.
 * @var array    $plans          Array of plan objects (with ->features).
 * @var array    $categories     Array of category objects for the filter.
 * @var array    $settings       Plugin settings from BV_Settings::get_all().
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$show_cats   = $settings['services_show_categories'] === 'yes' && ! empty( $categories );
$animations  = $settings['services_enable_animations'] === 'yes';
$symbol      = esc_html( $settings['services_currency_symbol'] );
$pos         = $settings['services_currency_position'];

/**
 * Format a price with the configured currency symbol.
 *
 * @param float|string $price The price.
 * @return string
 */
function bv_format_price( $price, $symbol, $pos ) {
    $formatted = number_format( (float) $price, 2, '.', ',' );
    if ( $pos === 'after' ) {
        return $formatted . ' ' . $symbol;
    }
    return $symbol . ' ' . $formatted;
}
?>

<div class="bv-frontend-wrap<?php echo $animations ? ' bv-animate' : ''; ?>">

    <!-- Page Title -->
    <div class="bv-page-header">
        <h2 class="bv-title"><?php echo esc_html( $settings['services_page_title'] ); ?></h2>
    </div>

    <!-- Category Filter -->
    <?php if ( $show_cats ) : ?>
    <div class="bv-category-filter">
        <button class="bv-cat-btn bv-cat-btn-active" data-category="all">All</button>
        <?php foreach ( $categories as $cat ) : ?>
            <button class="bv-cat-btn" data-category="<?php echo esc_attr( $cat->slug ); ?>" data-cat-id="<?php echo esc_attr( $cat->id ); ?>" style="border-color:<?php echo esc_attr( $cat->color ); ?>;">
                <?php echo esc_html( $cat->name ); ?>
            </button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Services Table -->
    <?php if ( ! empty( $services ) ) : ?>
    <div class="bv-services-section">
        <div class="bv-table-wrapper">
            <table class="bv-services-table">
                <thead>
                    <tr>
                        <th class="bv-col-service">Service</th>
                        <th class="bv-col-price">Price</th>
                        <th class="bv-col-action">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $services as $svc ) : ?>
                    <tr data-category-id="<?php echo esc_attr( $svc->category_id ); ?>">
                        <td class="bv-col-service">
                            <div class="bv-service-name">
                                <span class="bv-icon bv-icon-<?php echo esc_attr( strtolower( $svc->icon ) ); ?>"></span>
                                <?php echo esc_html( $svc->name ); ?>
                                <?php if ( $svc->featured ) : ?>
                                    <span class="bv-featured-badge">Featured</span>
                                <?php endif; ?>
                            </div>
                            <div class="bv-service-desc"><?php echo esc_html( $svc->description ); ?></div>
                        </td>
                        <td class="bv-col-price">
                            <span class="bv-price"><?php echo bv_format_price( $svc->price, $symbol, $pos ); ?></span>
                        </td>
                        <td class="bv-col-action">
                            <a href="<?php echo esc_url( $svc->button_url_rendered ); ?>" class="bv-btn bv-btn-primary bv-btn-sm">
                                <?php echo esc_html( $svc->button_label ); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Plans Cards -->
    <?php if ( ! empty( $plans ) ) : ?>
    <div class="bv-plans-section">
        <div class="bv-plans-grid">
            <?php foreach ( $plans as $plan ) : ?>
            <div class="bv-plan-card" data-category-id="<?php echo esc_attr( $plan->category_id ); ?>" style="border-top: 4px solid <?php echo esc_attr( $plan->color ); ?>;">
                <div class="bv-plan-header" style="background-color: <?php echo esc_attr( $plan->color ); ?>;">
                    <?php if ( $plan->featured ) : ?>
                        <span class="bv-plan-featured-label">Featured</span>
                    <?php endif; ?>
                    <h3 class="bv-plan-name"><?php echo esc_html( $plan->name ); ?></h3>
                    <?php if ( $plan->subtitle ) : ?>
                        <p class="bv-plan-subtitle"><?php echo esc_html( $plan->subtitle ); ?></p>
                    <?php endif; ?>
                    <div class="bv-plan-price">
                        <span class="bv-plan-price-amount"><?php echo bv_format_price( $plan->price, $symbol, $pos ); ?></span>
                        <span class="bv-plan-price-period">/ month</span>
                    </div>
                    <a href="<?php echo esc_url( $plan->button_url_rendered ); ?>" class="bv-btn bv-btn-gold bv-btn-sm">
                        <?php echo esc_html( $plan->button_label ); ?>
                    </a>
                </div>
                <div class="bv-plan-body">
                    <?php if ( ! empty( $plan->features ) ) : ?>
                    <ul class="bv-plan-features">
                        <?php foreach ( $plan->features as $feat ) : ?>
                            <li><?php echo esc_html( $feat->text ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else : ?>
                        <p class="bv-no-features">No features listed.</p>
                    <?php endif; ?>
                </div>
                <div class="bv-plan-footer">
                    <a href="<?php echo esc_url( $plan->button_url_rendered ); ?>" class="bv-btn bv-btn-plan-cta" style="background-color: <?php echo esc_attr( $plan->color ); ?>;">
                        <?php echo esc_html( $plan->button_label ); ?>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ( empty( $services ) && empty( $plans ) ) : ?>
        <p class="bv-no-items">No services or plans are currently available.</p>
    <?php endif; ?>

</div>