<?php
// Add "Apply Auto Pricing" button
add_action('manage_posts_extra_tablenav', 'mysite_add_auto_pricing_button');
function mysite_add_auto_pricing_button($which) {
    global $typenow;
    
    if ($typenow == 'product' && $which == 'top') {
        echo '<div class="alignleft actions">';
        echo '<button type="button" id="apply-auto-pricing" class="button button-primary">اعمال قیمت خودکار</button>';
        echo '<button type="button" id="reset-auto-pricing" class="button button-secondary" style="margin-right: 10px;">ریست اعمال قیمت</button>';
        echo '<span id="pricing-status" style="margin-right: 10px; display: none;"></span>';
        echo '</div>';
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#apply-auto-pricing').click(function() {
                var button = $(this);
                var status = $('#pricing-status');
                
                button.prop('disabled', true).text('در حال اعمال...');
                status.show().html('<span style="color: blue;">در حال پردازش...</span>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mysite_apply_auto_pricing',
                        nonce: '<?php echo wp_create_nonce('auto_pricing_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            status.html('<span style="color: green;">' + response.data.message + '</span>');
                            setTimeout(function() { location.reload(); }, 2000);
                        } else {
                            status.html('<span style="color: red;">خطا: ' + response.data.message + '</span>');
                        }
                        button.prop('disabled', false).text('اعمال قیمت خودکار');
                    },
                    error: function() {
                        status.html('<span style="color: red;">خطا در ارتباط با سرور</span>');
                        button.prop('disabled', false).text('اعمال قیمت خودکار');
                    }
                });
            });
            
            $('#reset-auto-pricing').click(function() {
                if (!confirm('آیا مطمئن هستید؟ این عمل قابل بازگشت نیست!')) return;
                
                var button = $(this);
                var status = $('#pricing-status');
                
                button.prop('disabled', true).text('در حال ریست...');
                status.show().html('<span style="color: blue;">در حال ریست...</span>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mysite_reset_auto_pricing',
                        nonce: '<?php echo wp_create_nonce('reset_pricing_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            status.html('<span style="color: green;">' + response.data.message + '</span>');
                            setTimeout(function() { location.reload(); }, 2000);
                        } else {
                            status.html('<span style="color: red;">خطا: ' + response.data.message + '</span>');
                        }
                        button.prop('disabled', false).text('ریست اعمال قیمت');
                    }
                });
            });
        });
        </script>
        <?php
    }
}

// Other management functions (apply_auto_pricing, reset_auto_pricing, etc.)
// Same as previous code but with new function names to avoid conflicts


add_action('wp_ajax_mysite_apply_auto_pricing', 'mysite_handle_auto_pricing_request');
function mysite_handle_auto_pricing_request() {
    if (!wp_verify_nonce($_POST['nonce'], 'auto_pricing_nonce')) {
        wp_die('دسترسی غیرمجاز');
    }
    
    if (!current_user_can('manage_woocommerce')) {
        wp_die('عدم دسترسی کافی');
    }
    
    $updated_count = 0;
    $skipped_count = 0;
    
    $args = [
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => [
            [
                'key' => '_regular_price',
                'value' => '',
                'compare' => '!='
            ]
        ]
    ];
    
    $products = get_posts($args);
    
    foreach ($products as $product_post) {
        $product_id = $product_post->ID;
        
        if (mysite_is_product_excluded($product_id)) {
            $skipped_count++;
            continue;
        }
        
        $auto_pricing_applied = get_post_meta($product_id, '_auto_pricing_applied', true);
        if ($auto_pricing_applied == 'yes') {
            $skipped_count++;
            continue;
        }
        
        $product = wc_get_product($product_id);
        $base_price = $product->get_regular_price();
        
        if (empty($base_price) || $base_price <= 0) {
            $skipped_count++;
            continue;
        }
        
        $retail_price = mysite_calculate_retail_price($base_price);
        
        $product->set_regular_price($retail_price);
        $product->set_price($retail_price);
        $product->save();
        
        update_post_meta($product_id, '_auto_pricing_applied', 'yes');
        update_post_meta($product_id, '_original_base_price', $base_price);
        
        $updated_count++;
    }
    
    $message = sprintf(
        'تکمیل! %d محصول به‌روزرسانی شد، %d محصول رد شد',
        $updated_count,
        $skipped_count
    );
    
    wp_send_json_success(['message' => $message]);
}

add_action('wp_ajax_mysite_reset_auto_pricing', 'mysite_handle_reset_pricing_request');
function mysite_handle_reset_pricing_request() {
    if (!wp_verify_nonce($_POST['nonce'], 'reset_pricing_nonce')) {
        wp_die('دسترسی غیرمجاز');
    }
    
    if (!current_user_can('manage_woocommerce')) {
        wp_die('عدم دسترسی کافی');
    }
    
    $reset_count = 0;
    
    $args = [
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => [
            [
                'key' => '_auto_pricing_applied',
                'value' => 'yes',
                'compare' => '='
            ]
        ]
    ];
    
    $products = get_posts($args);
    
    foreach ($products as $product_post) {
        $product_id = $product_post->ID;
        $original_price = get_post_meta($product_id, '_original_base_price', true);
        
        if (empty($original_price)) continue;
        
        $product = wc_get_product($product_id);
        $product->set_regular_price($original_price);
        $product->set_price($original_price);
        $product->save();
        
        delete_post_meta($product_id, '_auto_pricing_applied');
        delete_post_meta($product_id, '_original_base_price');
        
        $reset_count++;
    }
    
    wp_send_json_success([
        'message' => sprintf('ریست تکمیل! %d محصول بازگردانده شد', $reset_count)
    ]);
}

// Add status column

add_filter('manage_edit-product_columns', 'mysite_add_auto_pricing_column');
function mysite_add_auto_pricing_column($columns) {
    $columns['auto_pricing_status'] = 'قیمت خودکار';
    return $columns;
}

add_action('manage_product_posts_custom_column', 'mysite_show_auto_pricing_status', 10, 2);
function mysite_show_auto_pricing_status($column, $post_id) {
    if ($column == 'auto_pricing_status') {
        if (mysite_is_product_excluded($post_id)) {
            echo '<span style="color: orange;">مستثنی</span>';
        } else {
            $applied = get_post_meta($post_id, '_auto_pricing_applied', true);
            if ($applied == 'yes') {
                echo '<span style="color: green;">✓ اعمال شده</span>';
            } else {
                echo '<span style="color: red;">✗ اعمال نشده</span>';
            }
        }
    }
}
?>