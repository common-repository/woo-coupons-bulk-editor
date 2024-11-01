<?php

defined( 'ABSPATH' ) || exit;
if ( !class_exists( 'WPSE_WC_Coupons_Columns' ) ) {
    /**
     * Display woocommerce item in the toolbar to tease users of the free 
     * version into purchasing the premium plugin.
     */
    class WPSE_WC_Coupons_Columns
    {
        private static  $instance = false ;
        var  $allowed_columns = array() ;
        var  $post_type = 'shop_coupon' ;
        private function __construct()
        {
        }
        
        function init()
        {
            
            if ( method_exists( 'WP_Sheet_Editor', 'allow_to_initialize' ) ) {
                if ( !WP_Sheet_Editor::allow_to_initialize() ) {
                    return;
                }
            } else {
                if ( !is_admin() && !apply_filters( 'vg_sheet_editor/allowed_on_frontend', false ) ) {
                    return;
                }
            }
            
            $this->allowed_columns = array(
                'ID',
                'post_title',
                'coupon_amount',
                'usage_count',
                'post_status',
                'post_date',
                'post_modified',
                'view_post',
                'open_wp_editor',
                'post_excerpt'
            );
            add_filter(
                'vg_sheet_editor/add_new_posts/create_new_posts',
                array( $this, 'create_new_rows' ),
                10,
                3
            );
            add_action( 'vg_sheet_editor/editor/register_columns', array( $this, 'register_columns' ), 60 );
            add_filter(
                'vg_sheet_editor/custom_columns/teaser/allow_to_lock_column',
                array( $this, 'dont_lock_allowed_columns' ),
                99,
                2
            );
            add_filter( 'vg_sheet_editor/options_page/options', array( $this, 'add_settings_page_options' ) );
            add_filter(
                'vg_sheet_editor/duplicate/new_post_data',
                array( $this, 'set_new_code_when_duplicating_coupons' ),
                10,
                2
            );
            add_filter(
                'vg_sheet_editor/duplicate/final_post_id',
                array( $this, 'remove_unique_data_after_duplicating_coupons' ),
                10,
                3
            );
            add_action( 'vg_sheet_editor/duplicate/above_form_fields', array( $this, 'render_instructions_for_duplicating_coupons' ) );
            add_action( 'vg_sheet_editor/duplicate/after_fields', array( $this, 'render_duplication_prefix_field' ) );
            add_filter(
                'vg_sheet_editor/custom_columns/columns_detected_settings_before_cache',
                array( $this, 'remove_private_columns' ),
                10,
                2
            );
        }
        
        function remove_private_columns( $columns, $post_type )
        {
            if ( $post_type !== $this->post_type ) {
                return $columns;
            }
            
            if ( !empty($columns['serialized']) ) {
                if ( !empty($columns['serialized']['exclude_product_categories']) ) {
                    unset( $columns['serialized']['exclude_product_categories'] );
                }
                if ( !empty($columns['serialized']['product_categories']) ) {
                    unset( $columns['serialized']['product_categories'] );
                }
                if ( !empty($columns['serialized']['customer_email']) ) {
                    unset( $columns['serialized']['customer_email'] );
                }
            }
            
            return $columns;
        }
        
        function remove_unique_data_after_duplicating_coupons( $coupon_id, $template_id, $post_type )
        {
            
            if ( $post_type === $this->post_type ) {
                update_post_meta( $coupon_id, '_used_by', '' );
                update_post_meta( $coupon_id, 'usage_count', '' );
            }
            
            return $coupon_id;
        }
        
        function render_duplication_prefix_field( $post_type )
        {
            if ( $post_type !== $this->post_type ) {
                return;
            }
            ?>
			<li>
				<label><?php 
            _e( 'Prefix for the coupon codes', 'vg_sheet_editor' );
            ?></label>
				<input type="text" name="coupon_code_prefix" value="NEW - ">
			</li>
			<?php 
        }
        
        function render_instructions_for_duplicating_coupons( $post_type )
        {
            if ( $post_type !== $this->post_type ) {
                return;
            }
            _e( '<p style="text-align: left;">1. When you duplicate coupons, we will copy all the info of the coupon (including amount, restrictions, etc.) except the date and coupon code.<br>2. The new coupons will have the current date and a new coupon code.</p>', vgse_wc_coupons()->textname );
        }
        
        function set_new_code_when_duplicating_coupons( $post_data, $extra_data = array() )
        {
            
            if ( $post_data['post_type'] === $this->post_type ) {
                $prefix = ( !empty($extra_data['coupon_code_prefix']) ? $extra_data['coupon_code_prefix'] : null );
                $post_data['post_title'] = $this->get_new_coupon_code( $prefix );
                $post_data['post_status'] = 'publish';
            }
            
            return $post_data;
        }
        
        /**
         * Add fields to options page
         * @param array $sections
         * @return array
         */
        function add_settings_page_options( $sections )
        {
            $fields = array( array(
                'id'    => 'coupon_prefix',
                'type'  => 'text',
                'title' => __( 'Prefix used for new coupon codes', vgse_wc_coupons()->textname ),
                'desc'  => __( 'When you use the "Add new" tool in our spreadsheet, we create many coupons using "NEW-<6 random characters>". This option allows you to change the NEW- prefix to anything you want. It is mandatory to use a prefix, if you leave this option empty we will use the default NEW-', vgse_wc_coupons()->textname ),
            ), array(
                'id'    => 'coupon_number_characters',
                'type'  => 'text',
                'title' => __( 'Number of random characters for coupon codes', vgse_wc_coupons()->textname ),
                'desc'  => __( 'When you use the "Add new" tool in our spreadsheet, we generate coupon codes using the prefix and 4 random characters.', vgse_wc_coupons()->textname ),
            ) );
            $sections[] = array(
                'icon'   => 'el-icon-cogs',
                'title'  => __( 'Coupons sheet', vgse_wc_coupons()->textname ),
                'fields' => $fields,
            );
            return $sections;
        }
        
        function dont_lock_allowed_columns( $allowed_to_lock, $column_key )
        {
            if ( !empty($this->allowed_columns) && in_array( $column_key, $this->allowed_columns ) ) {
                $allowed_to_lock = false;
            }
            return $allowed_to_lock;
        }
        
        /**
         * Register spreadsheet columns
         */
        function register_columns( $editor )
        {
            $post_type = $this->post_type;
            if ( $editor->provider->key === 'user' ) {
                return;
            }
            $editor->args['columns']->remove_item( 'view_post', $post_type );
            $editor->args['columns']->remove_item( 'expiry_date', $post_type );
            $editor->args['columns']->remove_item( 'post_name', $post_type );
            $editor->args['columns']->register_item( 'discount_type', $post_type, array(
                'data_type'         => 'meta_data',
                'unformatted'       => array(
                'data' => 'discount_type',
            ),
                'column_width'      => 150,
                'title'             => __( 'Discount type', vgse_wc_coupons()->textname ),
                'type'              => '',
                'supports_formulas' => true,
                'formatted'         => array(
                'data'          => 'discount_type',
                'editor'        => 'select',
                'selectOptions' => array(
                'fixed_cart'    => __( 'Fixed cart', 'woocommerce' ),
                'percent'       => __( 'Percentage discount', 'woocommerce' ),
                'fixed_product' => __( 'Fixed product discount', 'woocommerce' ),
            ),
            ),
                'default_value'     => 'fixed_cart',
                'allow_to_hide'     => true,
                'allow_to_rename'   => true,
            ) );
            $editor->args['columns']->register_item( 'customer_email', $post_type, array(
                'data_type'           => 'meta_data',
                'unformatted'         => array(
                'data' => 'customer_email',
            ),
                'column_width'        => 150,
                'title'               => __( 'Allowed emails', 'woocommerce' ),
                'type'                => '',
                'supports_formulas'   => true,
                'formatted'           => array(
                'data' => 'customer_email',
            ),
                'allow_to_hide'       => true,
                'allow_to_rename'     => true,
                'get_value_callback'  => array( $this, 'get_array_to_comma_string_for_column' ),
                'save_value_callback' => array( $this, 'save_comma_string_to_array_for_column' ),
                'value_type'          => 'email',
            ) );
            $editor->args['columns']->register_item( 'coupon_amount', $post_type, array(
                'data_type'         => 'meta_data',
                'unformatted'       => array(
                'data' => 'coupon_amount',
            ),
                'column_width'      => 120,
                'title'             => __( 'Coupon amount', 'woocommerce' ),
                'type'              => '',
                'supports_formulas' => true,
                'formatted'         => array(
                'data' => 'coupon_amount',
            ),
                'allow_to_hide'     => true,
                'allow_to_rename'   => true,
            ) );
            $editor->args['columns']->register_item( 'usage_limit', $post_type, array(
                'data_type'         => 'meta_data',
                'column_width'      => 120,
                'title'             => __( 'Usage limit per coupon', 'woocommerce' ),
                'type'              => '',
                'supports_formulas' => true,
                'allow_to_hide'     => true,
                'allow_to_rename'   => true,
            ) );
            $editor->args['columns']->register_item( 'usage_limit_per_user', $post_type, array(
                'data_type'         => 'meta_data',
                'column_width'      => 120,
                'title'             => __( 'Usage limit per user', 'woocommerce' ),
                'type'              => '',
                'supports_formulas' => true,
                'allow_to_hide'     => true,
                'allow_to_rename'   => true,
            ) );
            $editor->args['columns']->register_item( 'limit_usage_to_x_items', $post_type, array(
                'data_type'         => 'meta_data',
                'column_width'      => 120,
                'title'             => __( 'Limit usage to X items', 'woocommerce' ),
                'type'              => '',
                'supports_formulas' => true,
                'allow_to_hide'     => true,
                'allow_to_rename'   => true,
            ) );
            $editor->args['columns']->register_item( 'date_expires', $post_type, array(
                'data_type'                  => 'meta_data',
                'column_width'               => 150,
                'title'                      => __( 'Coupon expiry date', 'woocommerce' ),
                'type'                       => '',
                'supports_formulas'          => true,
                'formatted'                  => array(
                'type'                 => 'date',
                'customDatabaseFormat' => 'U',
                'dateFormatPhp'        => 'Y-m-d',
                'correctFormat'        => true,
                'defaultDate'          => '',
                'datePickerConfig'     => array(
                'firstDay'       => 0,
                'showWeekNumber' => true,
                'numberOfMonths' => 1,
            ),
            ),
                'allow_to_hide'              => true,
                'allow_to_rename'            => true,
                'get_value_callback'         => array( $this, 'get_expiration_date' ),
                'prepare_value_for_database' => array( $this, 'prepare_expiration_date_for_database' ),
            ) );
            $editor->args['columns']->register_item( 'post_excerpt', $post_type, array(
                'data_type'         => 'post_data',
                'unformatted'       => array(
                'data' => 'post_excerpt',
            ),
                'column_width'      => 400,
                'title'             => __( 'Description', 'woocommerce' ),
                'type'              => '',
                'supports_formulas' => true,
                'formatted'         => array(
                'data'     => 'post_excerpt',
                'renderer' => 'html',
            ),
                'allow_to_hide'     => true,
                'allow_to_rename'   => true,
            ) );
            $editor->args['columns']->register_item( 'free_shipping', $post_type, array(
                'data_type'         => 'meta_data',
                'unformatted'       => array(
                'data' => 'free_shipping',
            ),
                'column_width'      => 150,
                'title'             => __( 'Allow free shipping', 'woocommerce' ),
                'type'              => '',
                'supports_formulas' => true,
                'formatted'         => array(
                'data'              => 'free_shipping',
                'type'              => 'checkbox',
                'checkedTemplate'   => 'yes',
                'uncheckedTemplate' => '',
            ),
                'default_value'     => '',
                'allow_to_hide'     => true,
                'allow_to_rename'   => true,
            ) );
            $editor->args['columns']->register_item( 'individual_use', $post_type, array(
                'data_type'         => 'meta_data',
                'unformatted'       => array(
                'data' => 'individual_use',
            ),
                'column_width'      => 150,
                'title'             => __( 'Individual use only', 'woocommerce' ),
                'type'              => '',
                'supports_formulas' => true,
                'formatted'         => array(
                'data'              => 'individual_use',
                'type'              => 'checkbox',
                'checkedTemplate'   => 'yes',
                'uncheckedTemplate' => '',
            ),
                'default_value'     => '',
                'allow_to_hide'     => true,
                'allow_to_rename'   => true,
            ) );
            $editor->args['columns']->register_item( 'exclude_sale_items', $post_type, array(
                'data_type'         => 'meta_data',
                'unformatted'       => array(
                'data' => 'exclude_sale_items',
            ),
                'column_width'      => 150,
                'title'             => __( 'Exclude sale items', 'woocommerce' ),
                'type'              => '',
                'supports_formulas' => true,
                'formatted'         => array(
                'data'              => 'exclude_sale_items',
                'type'              => 'checkbox',
                'checkedTemplate'   => 'yes',
                'uncheckedTemplate' => '',
            ),
                'default_value'     => '',
                'allow_to_hide'     => true,
                'allow_to_rename'   => true,
            ) );
            $editor->args['columns']->register_item( 'usage_count', $post_type, array(
                'data_type'         => 'meta_data',
                'column_width'      => 130,
                'title'             => __( 'Usage', vgse_wc_coupons()->textname ),
                'type'              => '',
                'supports_formulas' => true,
                'allow_to_hide'     => true,
                'allow_to_save'     => true,
                'allow_to_rename'   => true,
                'is_locked'         => true,
                'lock_template_key' => 'enable_lock_cell_template',
            ) );
            $editor->args['columns']->register_item( 'product_categories', $post_type, array(
                'data_type'         => 'meta_data',
                'unformatted'       => array(
                'data' => 'product_categories',
            ),
                'column_width'      => 75,
                'title'             => __( 'Product categories', 'woocommerce' ),
                'type'              => '',
                'supports_formulas' => true,
                'formatted'         => array(
                'data'          => 'product_categories',
                'type'          => 'autocomplete',
                'source'        => array( VGSE()->data_helpers, 'get_taxonomy_terms' ),
                'callback_args' => array( 'product_cat' ),
            ),
                'allow_to_hide'     => true,
                'allow_to_rename'   => true,
            ) );
            $editor->args['columns']->register_item( 'exclude_product_categories', $post_type, array(
                'data_type'         => 'meta_data',
                'unformatted'       => array(
                'data' => 'exclude_product_categories',
            ),
                'column_width'      => 75,
                'title'             => __( 'Exclude categories', 'woocommerce' ),
                'type'              => '',
                'supports_formulas' => true,
                'formatted'         => array(
                'data'          => 'exclude_product_categories',
                'type'          => 'autocomplete',
                'source'        => array( VGSE()->data_helpers, 'get_taxonomy_terms' ),
                'callback_args' => array( 'product_cat' ),
            ),
                'allow_to_hide'     => true,
                'allow_to_rename'   => true,
            ) );
            $editor->args['columns']->register_item( '_used_by', $post_type, array(
                'data_type'         => 'meta_data',
                'unformatted'       => array(
                'readOnly' => true,
            ),
                'column_width'      => 75,
                'title'             => __( 'Used by', vgse_wc_coupons()->textname ),
                'type'              => '',
                'supports_formulas' => false,
                'allow_to_hide'     => true,
                'allow_to_save'     => false,
                'allow_to_rename'   => true,
                'formatted'         => array(
                'readOnly' => true,
            ),
                'is_locked'         => true,
            ) );
            $editor->args['columns']->register_item( 'product_ids', $post_type, array(
                'data_type'           => 'meta_data',
                'column_width'        => 75,
                'title'               => __( 'Products', 'woocommerce' ),
                'type'                => '',
                'supports_formulas'   => true,
                'allow_to_hide'       => true,
                'allow_to_rename'     => true,
                'formatted'           => array(
                'comment' => array(
                'value' => __( 'Enter product/variation titles or skus separated by commas.', vgse_wc_coupons()->textname ),
            ),
            ),
                'get_value_callback'  => array( $this, 'get_post_titles_from_ids_for_column' ),
                'save_value_callback' => array( $this, 'save_post_ids_from_titles_for_column' ),
            ) );
            $editor->args['columns']->register_item( 'exclude_product_ids', $post_type, array(
                'data_type'           => 'meta_data',
                'column_width'        => 75,
                'title'               => __( 'Exclude products', 'woocommerce' ),
                'type'                => '',
                'supports_formulas'   => true,
                'allow_to_hide'       => true,
                'allow_to_rename'     => true,
                'formatted'           => array(
                'comment' => array(
                'value' => __( 'Enter product/variation titles or skus separated by commas.', vgse_wc_coupons()->textname ),
            ),
            ),
                'get_value_callback'  => array( $this, 'get_post_titles_from_ids_for_column' ),
                'save_value_callback' => array( $this, 'save_post_ids_from_titles_for_column' ),
            ) );
            
            if ( !empty($this->allowed_columns) ) {
                // Increase column width for disabled columns, so the "premium" message fits
                $spreadsheet_columns = $editor->get_provider_items( $post_type );
                foreach ( $spreadsheet_columns as $key => $column ) {
                    if ( !in_array( $key, $this->allowed_columns ) ) {
                        $editor->args['columns']->register_item(
                            $key,
                            $post_type,
                            array(
                            'column_width'      => $column['column_width'] + 80,
                            'is_locked'         => true,
                            'lock_template_key' => 'lock_cell_template_pro',
                        ),
                            true
                        );
                    }
                }
            }
        
        }
        
        function get_post_titles_from_ids_for_column( $post, $cell_key, $cell_args )
        {
            $value = VGSE()->helpers->get_current_provider()->get_item_meta(
                $post->ID,
                $cell_key,
                true,
                'read'
            );
            if ( !empty($value) ) {
                
                if ( !empty(VGSE()->options['wc_coupons_use_product_ids']) ) {
                    $value = implode( ', ', array_map( 'intval', array_map( 'trim', explode( ',', $value ) ) ) );
                } else {
                    $value = html_entity_decode( implode( ', ', array_map( 'get_the_title', array_map( 'trim', explode( ',', $value ) ) ) ) );
                }
            
            }
            return $value;
        }
        
        function save_post_ids_from_titles_for_column(
            $post_id,
            $cell_key,
            $data_to_save,
            $post_type,
            $cell_args,
            $spreadsheet_columns
        )
        {
            global  $wpdb ;
            
            if ( empty($data_to_save) ) {
                $ids = array();
            } else {
                
                if ( !empty(VGSE()->options['wc_coupons_use_product_ids']) ) {
                    $ids = array_map( 'intval', array_map( 'trim', explode( ',', $data_to_save ) ) );
                } else {
                    $titles = array_map( 'trim', explode( VGSE()->helpers->get_term_separator(), $data_to_save ) );
                    $titles_in_query_placeholders = implode( ', ', array_fill( 0, count( $titles ), '%s' ) );
                    $titles_for_prepare = $titles;
                    
                    if ( version_compare( WC()->version, '3.6.0' ) >= 0 ) {
                        $lookup_join = ' LEFT JOIN ' . $wpdb->prefix . 'wc_product_meta_lookup lookup ON lookup.product_id = ' . $wpdb->posts . '.ID ';
                        $lookup_where = " OR lookup.sku IN ({$titles_in_query_placeholders}) ";
                        $titles_for_prepare = array_merge( $titles_for_prepare, $titles );
                    } else {
                        $lookup_join = $lookup_where = '';
                    }
                    
                    $sql = $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} {$lookup_join} WHERE post_type IN ('product', 'product_variation') AND ( post_title IN ({$titles_in_query_placeholders}) {$lookup_where} ) ", $titles_for_prepare );
                    $ids = $wpdb->get_col( $sql );
                }
            
            }
            
            VGSE()->helpers->get_current_provider()->update_item_meta( $post_id, $cell_key, implode( ',', $ids ) );
        }
        
        function save_comma_string_to_array_for_column(
            $post_id,
            $cell_key,
            $data_to_save,
            $post_type,
            $cell_args,
            $spreadsheet_columns
        )
        {
            
            if ( empty($data_to_save) ) {
                $new_value = array();
            } else {
                $new_value = array_map( 'trim', explode( ',', $data_to_save ) );
            }
            
            VGSE()->helpers->get_current_provider()->update_item_meta( $post_id, $cell_key, $new_value );
        }
        
        function get_array_to_comma_string_for_column( $post, $cell_key, $cell_args )
        {
            $value = VGSE()->helpers->get_current_provider()->get_item_meta(
                $post->ID,
                $cell_key,
                true,
                'read'
            );
            if ( !empty($value) && is_array( $value ) ) {
                $value = implode( ', ', $value );
            }
            return $value;
        }
        
        function get_expiration_date( $post, $cell_key, $cell_args )
        {
            $value = VGSE()->helpers->get_current_provider()->get_item_meta(
                $post->ID,
                $cell_key,
                true,
                'read'
            );
            if ( is_numeric( $value ) ) {
                $value = date( 'Y-m-d', $value );
            }
            return $value;
        }
        
        function prepare_expiration_date_for_database(
            $post_id,
            $cell_key,
            $data_to_save,
            $post_type,
            $cell_args,
            $spreadsheet_columns
        )
        {
            if ( !empty($data_to_save) ) {
                $data_to_save = ( preg_match( '/^\\d{9,10}$/', $data_to_save ) ? (int) $data_to_save : strtotime( $data_to_save ) );
            }
            return $data_to_save;
        }
        
        /**
         * Create new coupons using WC API
         * @param array $post_ids
         * @param str $post_type
         * @param int $number
         * @return array Post ids
         */
        public function create_new_rows( $post_ids, $post_type, $number )
        {
            if ( $post_type !== $this->post_type || !empty($post_ids) ) {
                return $post_ids;
            }
            for ( $i = 0 ;  $i < $number ;  $i++ ) {
                $coupon_code = $this->get_new_coupon_code();
                $api_response = VGSE()->helpers->create_rest_request( 'POST', '/wc/v1/coupons', array(
                    'code'   => $coupon_code,
                    'amount' => '10',
                ) );
                
                if ( $api_response->status === 200 || $api_response->status === 201 ) {
                    $api_data = $api_response->get_data();
                    $post_ids[] = $api_data['id'];
                }
            
            }
            return $post_ids;
        }
        
        function get_new_coupon_code( $prefix = null )
        {
            if ( empty($prefix) ) {
                $prefix = ( empty(VGSE()->options['coupon_prefix']) ? 'NEW-' : VGSE()->options['coupon_prefix'] );
            }
            $characters = ( !empty(VGSE()->options['coupon_number_characters']) && VGSE()->options['coupon_number_characters'] > 1 ? (int) VGSE()->options['coupon_number_characters'] : 5 );
            $coupon_code = $prefix . wp_generate_password( $characters, false );
            return $coupon_code;
        }
        
        /**
         * Creates or returns an instance of this class.
         *
         * 
         */
        static function get_instance()
        {
            
            if ( null == WPSE_WC_Coupons_Columns::$instance ) {
                WPSE_WC_Coupons_Columns::$instance = new WPSE_WC_Coupons_Columns();
                WPSE_WC_Coupons_Columns::$instance->init();
            }
            
            return WPSE_WC_Coupons_Columns::$instance;
        }
        
        function __set( $name, $value )
        {
            $this->{$name} = $value;
        }
        
        function __get( $name )
        {
            return $this->{$name};
        }
    
    }
}
add_action( 'vg_sheet_editor/initialized', 'vgse_init_wc_coupons_columns' );
if ( !function_exists( 'vgse_init_wc_coupons_columns' ) ) {
    function vgse_init_wc_coupons_columns()
    {
        WPSE_WC_Coupons_Columns::get_instance();
    }

}