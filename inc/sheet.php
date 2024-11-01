<?php

defined( 'ABSPATH' ) || exit;
if ( !class_exists( 'WPSE_WC_Coupons_Sheet' ) ) {
    class WPSE_WC_Coupons_Sheet extends WPSE_Sheet_Factory {
        public $post_type = 'shop_coupon';

        function __construct() {
            $allowed_columns = array();
            $allowed_columns = array(
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
            parent::__construct( array(
                'fs_object'          => wpsewcc_fs(),
                'post_type'          => array('shop_coupon'),
                'post_type_label'    => array(__( 'Coupons', 'woocommerce' )),
                'serialized_columns' => array(),
                'allowed_columns'    => $allowed_columns,
                'remove_columns'     => array('view_post', 'expiry_date', 'post_name'),
            ) );
            add_filter(
                'vg_sheet_editor/add_new_posts/create_new_posts',
                array($this, 'create_new_rows'),
                10,
                3
            );
            add_action( 'vg_sheet_editor/editor/register_columns', array($this, 'register_columns'), 60 );
            add_filter( 'vg_sheet_editor/options_page/options', array($this, 'add_settings_page_options') );
            add_filter(
                'vg_sheet_editor/duplicate/new_post_data',
                array($this, 'set_new_code_when_duplicating_coupons'),
                10,
                2
            );
            add_filter(
                'vg_sheet_editor/duplicate/existing_post_data',
                array($this, 'remove_unique_data_after_duplicating_coupons'),
                10,
                3
            );
            add_action( 'vg_sheet_editor/duplicate/above_form_fields', array($this, 'render_instructions_for_duplicating_coupons') );
            add_action( 'vg_sheet_editor/duplicate/after_fields', array($this, 'render_duplication_prefix_field') );
            add_filter(
                'vg_sheet_editor/custom_columns/columns_detected_settings_before_cache',
                array($this, 'remove_private_columns'),
                10,
                2
            );
        }

        function remove_private_columns( $columns, $post_type ) {
            if ( $post_type !== $this->post_type ) {
                return $columns;
            }
            if ( !empty( $columns['serialized'] ) ) {
                if ( !empty( $columns['serialized']['exclude_product_categories'] ) ) {
                    unset($columns['serialized']['exclude_product_categories']);
                }
                if ( !empty( $columns['serialized']['product_categories'] ) ) {
                    unset($columns['serialized']['product_categories']);
                }
                if ( !empty( $columns['serialized']['customer_email'] ) ) {
                    unset($columns['serialized']['customer_email']);
                }
            }
            return $columns;
        }

        function remove_unique_data_after_duplicating_coupons( $post, $post_id, $extra_data ) {
            if ( $post['post']['post_type'] === $this->post_type ) {
                $post['meta'] = array_diff_key( $post['meta'], array_flip( array('_used_by', 'usage_count') ) );
            }
            return $post;
        }

        function render_duplication_prefix_field( $post_type ) {
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

        function render_instructions_for_duplicating_coupons( $post_type ) {
            if ( $post_type !== $this->post_type ) {
                return;
            }
            _e( '<p style="text-align: left;">1. When you duplicate coupons, we will copy all the info of the coupon (including amount, restrictions, etc.) except the date and coupon code.<br>2. The new coupons will have the current date and a new coupon code.</p>', vgse_wc_coupons()->textname );
        }

        function set_new_code_when_duplicating_coupons( $post_data, $extra_data = array() ) {
            if ( $post_data['post_type'] === $this->post_type ) {
                $prefix = ( !empty( $extra_data['coupon_code_prefix'] ) ? $extra_data['coupon_code_prefix'] : null );
                $post_data['post_title'] = $this->get_new_coupon_code( $prefix );
                // Set a unique slug even though the post_name is not used by WC, to avoid the expensive calls that wp makes to generate unique slugs, because it generates tens or hundreds of sql calls when creating a lot of coupons
                $post_data['post_name'] = $post_data['post_title'];
                $post_data['post_status'] = 'publish';
            }
            return $post_data;
        }

        /**
         * Add fields to options page
         * @param array $sections
         * @return array
         */
        function add_settings_page_options( $sections ) {
            $fields = array(array(
                'id'    => 'coupon_prefix',
                'type'  => 'text',
                'title' => __( 'Prefix used for new coupon codes', vgse_wc_coupons()->textname ),
                'desc'  => __( 'When you use the "Add new" tool in our spreadsheet, we create many coupons using "NEW-<6 random characters>". This option allows you to change the NEW- prefix to anything you want. It is mandatory to use a prefix, if you leave this option empty we will use the default NEW-', vgse_wc_coupons()->textname ),
            ), array(
                'id'    => 'coupon_number_characters',
                'type'  => 'text',
                'title' => __( 'Number of random characters for coupon codes', vgse_wc_coupons()->textname ),
                'desc'  => __( 'When you use the "Add new" tool in our spreadsheet, we generate coupon codes using the prefix and 4 random characters.', vgse_wc_coupons()->textname ),
            ));
            $sections[] = array(
                'icon'   => 'el-icon-cogs',
                'title'  => __( 'Coupons sheet', vgse_wc_coupons()->textname ),
                'fields' => $fields,
            );
            return $sections;
        }

        /**
         * Register spreadsheet columns
         */
        function register_columns( $editor ) {
            $post_type = $this->post_type;
            if ( $editor->args['provider'] !== $post_type ) {
                return;
            }
            $editor->args['columns']->register_item( 'discount_type', $post_type, array(
                'data_type'         => 'meta_data',
                'column_width'      => 150,
                'title'             => __( 'Discount type', vgse_wc_coupons()->textname ),
                'supports_formulas' => true,
                'formatted'         => array(
                    'editor'        => 'select',
                    'selectOptions' => array(
                        'fixed_cart'    => __( 'Fixed cart', 'woocommerce' ),
                        'percent'       => __( 'Percentage discount', 'woocommerce' ),
                        'fixed_product' => __( 'Fixed product discount', 'woocommerce' ),
                    ),
                ),
                'default_value'     => 'fixed_cart',
            ) );
            $editor->args['columns']->register_item( 'customer_email', $post_type, array(
                'data_type'           => 'meta_data',
                'column_width'        => 150,
                'title'               => __( 'Allowed emails', 'woocommerce' ),
                'supports_formulas'   => true,
                'formatted'           => array(
                    'data' => 'customer_email',
                ),
                'get_value_callback'  => array($this, 'get_array_to_comma_string_for_column'),
                'save_value_callback' => array($this, 'save_comma_string_to_array_for_column'),
                'value_type'          => 'email',
            ) );
            $editor->args['columns']->register_item( 'coupon_amount', $post_type, array(
                'data_type'         => 'meta_data',
                'column_width'      => 120,
                'title'             => __( 'Coupon amount', 'woocommerce' ),
                'supports_formulas' => true,
            ) );
            $editor->args['columns']->register_item( 'usage_limit', $post_type, array(
                'data_type'         => 'meta_data',
                'column_width'      => 120,
                'title'             => __( 'Usage limit per coupon', 'woocommerce' ),
                'supports_formulas' => true,
            ) );
            $editor->args['columns']->register_item( 'usage_limit_per_user', $post_type, array(
                'data_type'         => 'meta_data',
                'column_width'      => 120,
                'title'             => __( 'Usage limit per user', 'woocommerce' ),
                'supports_formulas' => true,
            ) );
            $editor->args['columns']->register_item( 'limit_usage_to_x_items', $post_type, array(
                'data_type'         => 'meta_data',
                'column_width'      => 120,
                'title'             => __( 'Limit usage to X items', 'woocommerce' ),
                'supports_formulas' => true,
            ) );
            $editor->args['columns']->register_item( 'date_expires', $post_type, array(
                'data_type'                  => 'meta_data',
                'column_width'               => 150,
                'title'                      => __( 'Coupon expiry date', 'woocommerce' ),
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
                'get_value_callback'         => array($this, 'get_expiration_date'),
                'prepare_value_for_database' => array($this, 'prepare_expiration_date_for_database'),
            ) );
            $editor->args['columns']->register_item( 'post_excerpt', $post_type, array(
                'data_type'         => 'post_data',
                'column_width'      => 400,
                'title'             => __( 'Description', 'woocommerce' ),
                'supports_formulas' => true,
            ) );
            $editor->args['columns']->register_item( 'free_shipping', $post_type, array(
                'data_type'         => 'meta_data',
                'column_width'      => 150,
                'title'             => __( 'Allow free shipping', 'woocommerce' ),
                'supports_formulas' => true,
                'formatted'         => array(
                    'type'              => 'checkbox',
                    'checkedTemplate'   => 'yes',
                    'uncheckedTemplate' => '',
                ),
                'default_value'     => '',
            ) );
            $editor->args['columns']->register_item( 'individual_use', $post_type, array(
                'data_type'         => 'meta_data',
                'column_width'      => 150,
                'title'             => __( 'Individual use only', 'woocommerce' ),
                'supports_formulas' => true,
                'formatted'         => array(
                    'type'              => 'checkbox',
                    'checkedTemplate'   => 'yes',
                    'uncheckedTemplate' => '',
                ),
                'default_value'     => '',
            ) );
            $editor->args['columns']->register_item( 'exclude_sale_items', $post_type, array(
                'data_type'         => 'meta_data',
                'column_width'      => 150,
                'title'             => __( 'Exclude sale items', 'woocommerce' ),
                'supports_formulas' => true,
                'formatted'         => array(
                    'type'              => 'checkbox',
                    'checkedTemplate'   => 'yes',
                    'uncheckedTemplate' => '',
                ),
                'default_value'     => '',
            ) );
            $editor->args['columns']->register_item( 'usage_count', $post_type, array(
                'data_type'         => 'meta_data',
                'column_width'      => 130,
                'title'             => __( 'Usage', vgse_wc_coupons()->textname ),
                'supports_formulas' => true,
                'allow_to_save'     => true,
                'is_locked'         => true,
                'lock_template_key' => 'enable_lock_cell_template',
            ) );
            $editor->args['columns']->register_item( 'product_categories', $post_type, array(
                'data_type'                  => 'meta_data',
                'column_width'               => 75,
                'title'                      => __( 'Product categories', 'woocommerce' ),
                'supports_formulas'          => true,
                'formatted'                  => array(
                    'editor'        => 'wp_chosen',
                    'selectOptions' => array(),
                    'chosenOptions' => array(
                        'multiple'                 => true,
                        'search_contains'          => true,
                        'create_option'            => true,
                        'skip_no_results'          => true,
                        'persistent_create_option' => true,
                        'data'                     => array(),
                        'ajaxParams'               => array(
                            'action'       => 'vgse_get_taxonomy_terms',
                            'taxonomy_key' => 'product_cat',
                        ),
                    ),
                ),
                'prepare_value_for_display'  => array($this, 'prepare_categories_for_display'),
                'prepare_value_for_database' => array($this, 'prepare_categories_for_database'),
            ) );
            $editor->args['columns']->register_item( 'exclude_product_categories', $post_type, array(
                'data_type'                  => 'meta_data',
                'column_width'               => 75,
                'title'                      => __( 'Exclude categories', 'woocommerce' ),
                'supports_formulas'          => true,
                'formatted'                  => array(
                    'editor'        => 'wp_chosen',
                    'selectOptions' => array(),
                    'chosenOptions' => array(
                        'multiple'                 => true,
                        'search_contains'          => true,
                        'create_option'            => true,
                        'skip_no_results'          => true,
                        'persistent_create_option' => true,
                        'data'                     => array(),
                        'ajaxParams'               => array(
                            'action'       => 'vgse_get_taxonomy_terms',
                            'taxonomy_key' => 'product_cat',
                        ),
                    ),
                ),
                'prepare_value_for_display'  => array($this, 'prepare_categories_for_display'),
                'prepare_value_for_database' => array($this, 'prepare_categories_for_database'),
            ) );
            $editor->args['columns']->register_item( '_used_by', $post_type, array(
                'data_type'                 => 'meta_data',
                'unformatted'               => array(
                    'readOnly' => true,
                ),
                'column_width'              => 75,
                'title'                     => __( 'Used by', vgse_wc_coupons()->textname ),
                'supports_formulas'         => false,
                'allow_to_save'             => false,
                'formatted'                 => array(
                    'readOnly' => true,
                ),
                'is_locked'                 => true,
                'prepare_value_for_display' => array($this, 'prepare_used_by_for_display'),
            ) );
            $editor->args['columns']->register_item( 'product_ids', $post_type, array(
                'data_type'           => 'meta_data',
                'column_width'        => 75,
                'title'               => __( 'Products', 'woocommerce' ),
                'supports_formulas'   => true,
                'formatted'           => array(
                    'comment' => array(
                        'value' => __( 'Enter product/variation titles or skus separated by commas.', vgse_wc_coupons()->textname ),
                    ),
                ),
                'get_value_callback'  => array($this, 'get_post_titles_from_ids_for_column'),
                'save_value_callback' => array($this, 'save_post_ids_from_titles_for_column'),
            ) );
            $editor->args['columns']->register_item( 'exclude_product_ids', $post_type, array(
                'data_type'           => 'meta_data',
                'column_width'        => 75,
                'title'               => __( 'Exclude products', 'woocommerce' ),
                'supports_formulas'   => true,
                'formatted'           => array(
                    'comment' => array(
                        'value' => __( 'Enter product/variation titles or skus separated by commas.', vgse_wc_coupons()->textname ),
                    ),
                ),
                'get_value_callback'  => array($this, 'get_post_titles_from_ids_for_column'),
                'save_value_callback' => array($this, 'save_post_ids_from_titles_for_column'),
            ) );
        }

        function prepare_categories_for_database(
            $post_id,
            $cell_key,
            $data_to_save,
            $post_type,
            $cell_args,
            $spreadsheet_columns
        ) {
            return VGSE()->data_helpers->prepare_post_terms_for_saving( $data_to_save, 'product_cat' );
        }

        function prepare_categories_for_display(
            $value,
            $post,
            $cell_key,
            $cell_args
        ) {
            if ( empty( $value ) ) {
                return '';
            }
            $value = VGSE()->data_helpers->prepare_post_terms_for_display( get_terms( array(
                'taxonomy'               => 'product_cat',
                'hide_empty'             => false,
                'include'                => $value,
                'update_term_meta_cache' => false,
            ) ) );
            return $value;
        }

        function prepare_used_by_for_display(
            $value,
            $post,
            $cell_key,
            $cell_args
        ) {
            if ( !empty( $value ) ) {
                $user_emails = array();
                $user_ids = get_post_meta( $post->ID, '_used_by' );
                if ( is_array( $user_ids ) ) {
                    foreach ( $user_ids as $user ) {
                        if ( is_numeric( $user ) ) {
                            $user_data = get_userdata( (int) $user );
                            if ( $user_data ) {
                                $user_emails[] = $user_data->user_email;
                            }
                        } else {
                            $user_emails[] = $user;
                        }
                    }
                }
                $value = ( empty( $user_emails ) ? '' : implode( ', ', array_filter( array_unique( $user_emails ) ) ) );
            } else {
                $value = '';
            }
            return $value;
        }

        function get_post_titles_from_ids_for_column( $post, $cell_key, $cell_args ) {
            $value = VGSE()->helpers->get_current_provider()->get_item_meta(
                $post->ID,
                $cell_key,
                true,
                'read'
            );
            if ( !empty( $value ) ) {
                if ( !empty( VGSE()->options['wc_coupons_use_product_ids'] ) ) {
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
        ) {
            global $wpdb;
            if ( empty( $data_to_save ) ) {
                $ids = array();
            } else {
                if ( !empty( VGSE()->options['wc_coupons_use_product_ids'] ) ) {
                    $ids = array_map( 'intval', array_map( 'trim', explode( ',', $data_to_save ) ) );
                } else {
                    $titles = array_map( 'trim', explode( VGSE()->helpers->get_term_separator(), $data_to_save ) );
                    $post_types_for_search = array('product', 'product_variation');
                    // Compatibility with LearnPress - Woo Payment Gateway, which has a mode that includes courses in the coupon restrictions
                    if ( class_exists( 'LP_Gateway_Woo' ) && !LP_Gateway_Woo::is_by_courses_via_product() ) {
                        $post_types_for_search[] = 'lp_course';
                    }
                    $titles_in_query_placeholders = implode( ', ', array_fill( 0, count( $titles ), '%s' ) );
                    $titles_for_prepare = array_merge( $post_types_for_search, $titles );
                    if ( version_compare( WC()->version, '3.6.0' ) >= 0 ) {
                        $lookup_join = ' LEFT JOIN ' . $wpdb->prefix . 'wc_product_meta_lookup lookup ON lookup.product_id = ' . $wpdb->posts . '.ID ';
                        $lookup_where = " OR lookup.sku IN ({$titles_in_query_placeholders}) ";
                        $titles_for_prepare = array_merge( $titles_for_prepare, $titles );
                    } else {
                        $lookup_join = $lookup_where = '';
                    }
                    $post_types_in_query_placeholders = implode( ', ', array_fill( 0, count( $post_types_for_search ), '%s' ) );
                    $sql = $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} {$lookup_join} WHERE post_type IN ({$post_types_in_query_placeholders}) AND ( post_title IN ({$titles_in_query_placeholders}) {$lookup_where} ) ", $titles_for_prepare );
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
        ) {
            if ( empty( $data_to_save ) ) {
                $new_value = array();
            } else {
                $new_value = array_map( 'trim', explode( ',', $data_to_save ) );
            }
            VGSE()->helpers->get_current_provider()->update_item_meta( $post_id, $cell_key, $new_value );
        }

        function get_array_to_comma_string_for_column( $post, $cell_key, $cell_args ) {
            $value = VGSE()->helpers->get_current_provider()->get_item_meta(
                $post->ID,
                $cell_key,
                true,
                'read'
            );
            if ( !empty( $value ) && is_array( $value ) ) {
                $value = implode( ', ', $value );
            }
            return $value;
        }

        function get_expiration_date( $post, $cell_key, $cell_args ) {
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
        ) {
            if ( !empty( $data_to_save ) ) {
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
        public function create_new_rows( $post_ids, $post_type, $number ) {
            if ( $post_type !== $this->post_type || !empty( $post_ids ) ) {
                return $post_ids;
            }
            for ($i = 0; $i < $number; $i++) {
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

        function get_new_coupon_code( $prefix = null ) {
            if ( empty( $prefix ) ) {
                $prefix = ( empty( VGSE()->options['coupon_prefix'] ) ? 'NEW-' : VGSE()->options['coupon_prefix'] );
            }
            $characters = ( !empty( VGSE()->options['coupon_number_characters'] ) && VGSE()->options['coupon_number_characters'] > 1 ? (int) VGSE()->options['coupon_number_characters'] : 5 );
            $coupon_code = $prefix . wp_generate_password( $characters, false );
            return $coupon_code;
        }

    }

    new WPSE_WC_Coupons_Sheet();
}