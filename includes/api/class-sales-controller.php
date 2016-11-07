<?php
namespace WeDevs\ERP\API;

use WP_REST_Server;
use WP_REST_Response;
use WP_Error;

class Sales_Controller extends REST_Controller {
    /**
     * Endpoint namespace.
     *
     * @var string
     */
    protected $namespace = 'erp';

    /**
     * Route base.
     *
     * @var string
     */
    protected $rest_base = 'accounting/sales';

    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base, [
            [
                'methods'  => WP_REST_Server::READABLE,
                'callback' => [ $this, 'get_sales' ],
                'args'     => $this->get_collection_params(),
            ],
            [
                'methods'  => WP_REST_Server::CREATABLE,
                'callback' => [ $this, 'create_sale' ],
                'args'     => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
            ],
            'schema' => [ $this, 'get_public_item_schema' ],
        ] );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            [
                'methods'  => WP_REST_Server::READABLE,
                'callback' => [ $this, 'get_sale' ],
                'args'     => [
                    'context' => $this->get_context_param( [ 'default' => 'view' ] ),
                ],
            ],
            [
                'methods'  => WP_REST_Server::EDITABLE,
                'callback' => [ $this, 'update_sale' ],
                'args'     => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
            ],
            [
                'methods'  => WP_REST_Server::DELETABLE,
                'callback' => [ $this, 'delete_sale' ],
            ],
            'schema' => [ $this, 'get_public_item_schema' ],
        ] );
    }

    /**
     * Get a collection of sales
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function get_sales( $request ) {
        $args = [
            'number' => $request['per_page'],
            'offset' => ( $request['per_page'] * ( $request['page'] - 1 ) ),
            'type'   => 'sales',
            'join'   => ['items'],
        ];

        $items       = erp_ac_get_all_transaction( $args );
        $total_items = erp_ac_get_transaction_count( $args['type'] );

        $formated_items = [];
        foreach ( $items as $item ) {
            $additional_fields = [];

            $data = $this->prepare_item_for_response( $item, $request, $additional_fields );
            $formated_items[] = $this->prepare_response_for_collection( $data );
        }

        $response = rest_ensure_response( $formated_items );
        $response = $this->format_collection_response( $response, $request, $total_items );

        return $response;
    }

    /**
     * Get a specific sale
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function get_sale( $request ) {
        $id   = (int) $request['id'];
        $item = \WeDevs\ERP\Accounting\Model\Transaction::find( $id );

        if ( empty( $id ) || empty( $item->id ) ) {
            return new WP_Error( 'rest_sale_invalid_id', __( 'Invalid resource id.' ), [ 'status' => 404 ] );
        }

        $item->items = $item->items->toArray();

        $additional_fields = [];

        $item     = $this->prepare_item_for_response( $item, $request, $additional_fields );
        $response = rest_ensure_response( $item );

        return $response;
    }

    /**
     * Create a sale
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Request
     */
    public function create_sale( $request ) {
        $trans_data = $this->prepare_item_for_database( $request );

        $items = $this->prepare_trans_items_for_database( $request );

        $tax_total = array_reduce( $items, function( $total, $value ) {
            return $total + $value['tax_rate'];
        } );

        $trans_data['sub_total'] = array_reduce( $items, function( $total, $value ) {
            return $total + $value['line_total'];
        } );

        $trans_data['trans_total'] = $trans_data['sub_total'] + $tax_total;
        $trans_data['total']       = $trans_data['trans_total'];

        $id = erp_ac_insert_transaction( $trans_data, $items );

        if ( is_wp_error( $id ) ) {
            return $id;
        }

        $transaction = (object) erp_ac_get_transaction( $id );

        $request->set_param( 'context', 'edit' );
        $response = $this->prepare_item_for_response( $transaction, $request );
        $response = rest_ensure_response( $response );
        $response->set_status( 201 );
        $response->header( 'Location', rest_url( sprintf( '/%s/%s/%d', $this->namespace, $this->rest_base, $id ) ) );

        return $response;
    }

    /**
     * Update a sale
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Request
     */
    public function update_sale( $request ) {
        $id = (int) $request['id'];

        $item = (object) erp_ac_get_transaction( $id );
        if ( ! $item ) {
            return new WP_Error( 'rest_sale_invalid_id', __( 'Invalid resource id.' ), [ 'status' => 400 ] );
        }

        $trans_data = $this->prepare_item_for_database( $request );

        $tax_total = array_reduce( $items, function( $total, $value ) {
            return $total + $value['tax_rate'];
        } );

        $trans_data['sub_total'] = array_reduce( $items, function( $total, $value ) {
            return $total + $value['line_total'];
        } );

        $trans_data['trans_total'] = $trans_data['sub_total'] + $tax_total;
        $trans_data['total']       = $trans_data['trans_total'];

        $id = erp_ac_insert_transaction( $trans_data, $items );

        if ( is_wp_error( $id ) ) {
            return $id;
        }

        $transaction = (object) erp_ac_get_transaction( $id );
        $response    = $this->prepare_item_for_response( $transaction, $request );
        $response    = rest_ensure_response( $response );
        $response->set_status( 201 );
        $response->header( 'Location', rest_url( sprintf( '/%s/%s/%d', $this->namespace, $this->rest_base, $id ) ) );

        return $response;
    }

    /**
     * Delete a sale
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Request
     */
    public function delete_sale( $request ) {
        $id = (int) $request['id'];

        $result = erp_ac_remove_transaction( $id );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( true, 204 );
    }

    /**
     * Prepare a single item for create or update
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return array $prepared_item
     */
    protected function prepare_item_for_database( $request ) {
        $prepared_item = [];

        $prepared_item['type']            = 'sales';
        $prepared_item['form_type']       = isset( $request['form_type'] ) ? sanitize_text_field( $request['form_type'] ) : '';
        $prepared_item['account_id']      = isset( $request['account_id'] ) ? intval( $request['account_id'] ) : 0;
        $prepared_item['status']          = isset( $request['status'] ) ? sanitize_text_field( $request['status'] ) : '';
        $prepared_item['user_id']         = isset( $request['customer'] ) ? intval( $request['customer'] ) : 0;
        $prepared_item['billing_address'] = isset( $request['billing_address'] ) ? wp_kses_post( $request['billing_address'] ) : '';
        $prepared_item['ref']             = isset( $request['reference'] ) ? sanitize_text_field( $request['reference'] ) : '';
        $prepared_item['issue_date']      = isset( $request['issue_date'] ) ? sanitize_text_field( $request['issue_date'] ) : '';
        $prepared_item['due_date']        = isset( $request['due_date'] ) ? sanitize_text_field( $request['due_date'] ) : '';
        $prepared_item['summary']         = isset( $request['summary'] ) ? wp_kses_post( $request['summary'] ) : '';
        $prepared_item['currency']        = isset( $request['currency'] ) ? sanitize_text_field( $request['currency'] ) : 'USD';

        return $prepared_item;
    }

    /**
     * Prepare a single user output for response
     *
     * @param object $item
     * @param WP_REST_Request $request Request object.
     * @param array $additional_fields (optional)
     *
     * @return WP_REST_Response $response Response data.
     */
    public function prepare_item_for_response( $item, $request, $additional_fields = [] ) {
        $data = [
            'id'                => (int) $item->id,
            'form_type'         => $item->form_type,
            'status'            => $item->status,
            'billing_address'   => $item->billing_address,
            'reference'         => $item->ref,
            'summary'           => $item->summary,
            'issue_date'        => $item->issue_date,
            'due_date'          => $item->due_date,
            'currency'          => $item->currency,
            'items'             => $this->format_transaction_items( $item->items ),
            'sub_total'         => (float) $item->sub_total,
            'total'             => (float) $item->total,
            'due'               => (float) $item->due,
            'trans_total'       => (float) $item->trans_total,
            'invoice'           => erp_ac_get_invoice_number( $item->invoice_number, $item->invoice_format ),
            'parent'            => (int) $item->parent,
            'created_at'        => $item->created_at,
        ];

        if ( isset( $request['include'] ) ) {
            $include_params = explode( ',', str_replace( ' ', '', $request['include'] ) );

            if ( in_array( 'customer', $include_params ) ) {
                $customers_controller = new Customers_Controller();

                $customer_id  = (int) $item->user_id;
                $data['customer'] = null;

                if ( $customer_id ) {
                    $customer = $customers_controller->get_customer( ['id' => $customer_id ] );
                    $data['customer'] = ! is_wp_error( $customer ) ? $customer->get_data() : null;
                }
            }

            if ( in_array( 'created_by', $include_params ) ) {
                $data['created_by'] = $this->get_user( intval( $item->created_by ) );
            }
        }

        $data = array_merge( $data, $additional_fields );

        // Wrap the data in a response object
        $response = rest_ensure_response( $data );

        $response = $this->add_links( $response, $item );

        return $response;
    }

    /**
     * Prepare transaction items for database
     *
     * @param  array $request
     *
     * @return array
     */
    protected function prepare_trans_items_for_database( $request ) {
        $taxes = erp_ac_get_tax_info();
        $taxes = array_pluck( $taxes, 'rate', 'id' );

        foreach ( $request['items'] as $item ) {
            $unit_price = (float) erp_ac_format_decimal( $item['unit_price'] );
            $discount   = (int) erp_ac_format_decimal( $item['discount'] );
            $tax        = isset( $item['tax'] ) ? $item['tax'] : 0;

            $items[] = [
                'journal_id'  => isset( $item['journal_id'] ) ? $item['journal_id'] : 0,
                'account_id'  => (int) $item['account_id'],
                'description' => sanitize_text_field( $item['description'] ),
                'qty'         => intval( $item['qty'] ),
                'unit_price'  => $unit_price,
                'discount'    => $discount,
                'line_total'  => ( $unit_price - $discount ),
                'tax'         => $tax,
                'tax_rate'    => isset( $taxes[ $tax ] ) ? $taxes[ $tax ] : 0,
                'tax_journal' => isset( $item['tax_journal'] ) ? $item['tax_journal'] : 0
            ];
        }

        return $items;
    }

    /**
     * Format the transaction's items
     *
     * @param  array $items
     *
     * @return array
     */
    protected function format_transaction_items( $items ) {
        return array_map( function( $item ) {
            return [
                'id'          => (int) $item['id'],
                'journal_id'  => (int) $item['journal_id'],
                'product_id'  => (int) $item['product_id'],
                'description' => $item['description'],
                'qty'         => (int) $item['qty'],
                'unit_price'  => (float) $item['unit_price'],
                'discount'    => (float) $item['discount'],
                'tax'         => (float) $item['tax'],
                'tax_rate'    => (float) $item['tax_rate'],
                'tax_journal' => (float) $item['tax_journal'],
                'line_total'  => (float) $item['line_total'],
                'order'       => (int) $item['order'],
            ];
        }, $items );
    }

    /**
     * Get the User's schema, conforming to JSON Schema
     *
     * @return array
     */
    public function get_item_schema() {
        $schema = [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'sale',
            'type'       => 'object',
            'properties' => [
                'id'          => [
                    'description' => __( 'Unique identifier for the resource.' ),
                    'type'        => 'integer',
                    'context'     => [ 'embed', 'view', 'edit' ],
                    'readonly'    => true,
                ],
                'type'  => [
                    'description' => __( 'Type for the resource.' ),
                    'type'        => 'string',
                    'context'     => [ 'edit' ],
                    'arg_options' => [
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
                'status'  => [
                    'description' => __( 'Status for the resource.' ),
                    'type'        => 'string',
                    'context'     => [ 'edit' ],
                    'arg_options' => [
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ];

        return $schema;
    }
}