<?php
/*
Plugin Name: WooCommerce SKU Generator
Description: Generate unique numeric SKUs for products in batches using AJAX.
Version: 1.1
Author: Faisal
*/

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generate SKU
 */
function generate_unique_numeric_sku($length = 13) {
    return str_pad(mt_rand(1, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

/**
 * Batch Update
 */
function update_product_skus_batch($batch_size = 100, $offset = 0) {

    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => $batch_size,
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'offset'         => $offset,
    );

    $products = get_posts($args);
    $updated_count = 0;

    foreach ($products as $product_id) {

        $product = wc_get_product($product_id);

        if ($product) {
            $new_sku = generate_unique_numeric_sku();
            $product->set_sku($new_sku);
            $product->save();
            $updated_count++;
        }
    }

    return [
        'processed' => count($products),
        'updated'   => $updated_count,
    ];
}

/**
 * AJAX: Update SKUs
 */
add_action('wp_ajax_update_skus', function () {

    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized');
    }

    $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 100;
    $offset     = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

    $result = update_product_skus_batch($batch_size, $offset);

    wp_send_json_success($result);
});

/**
 * AJAX: Total Count
 */
add_action('wp_ajax_update_skus_total', function () {

    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized');
    }

    $total_products = wp_count_posts('product')->publish;

    wp_send_json_success(['total' => $total_products]);
});

/**
 * Admin Menu
 */
add_action('admin_menu', function () {

    add_menu_page(
        'SKU Generator',
        'SKU Generator',
        'manage_woocommerce',
        'sku-generator',
        'render_sku_update_page',
        'dashicons-update',
        20
    );
});

/**
 * Admin Page
 */
function render_sku_update_page() {
    ?>
    <div class="wrap">
        <h1>Generate Product SKUs</h1>

        <p><strong>⚠️ Warning:</strong> This will overwrite existing SKUs.</p>

        <div style="margin:20px 0;">
            <p>Progress: <span id="progress-percentage">0%</span></p>
            <div style="width:100%;background:#eee;">
                <div id="progress-bar" style="width:0%;height:20px;background:#4caf50;"></div>
            </div>
        </div>

        <button id="start-btn" class="button button-primary">Start</button>
        <p id="status"></p>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {

        let offset = 0;
        const batchSize = 100;
        let total = 0;

        const btn = document.getElementById('start-btn');
        const bar = document.getElementById('progress-bar');
        const percent = document.getElementById('progress-percentage');
        const status = document.getElementById('status');

        btn.addEventListener('click', function () {
            btn.disabled = true;

            fetch(ajaxurl + '?action=update_skus_total')
                .then(res => res.json())
                .then(data => {
                    total = data.data.total;
                    processBatch();
                });
        });

        function processBatch() {

            fetch(ajaxurl + '?action=update_skus', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `offset=${offset}&batch_size=${batchSize}`
            })
            .then(res => res.json())
            .then(data => {

                offset += batchSize;

                let done = offset > total ? total : offset;
                let progress = Math.round((done / total) * 100);

                bar.style.width = progress + '%';
                percent.textContent = progress + '%';

                if (done >= total) {
                    status.textContent = 'Done ✅';
                    btn.disabled = false;
                } else {
                    processBatch();
                }
            });
        }
    });
    </script>
    <?php
}