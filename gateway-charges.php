<?php

/**
 * Plugin Name: CGF Gateway Charge
 * Description: Add gateway charge for CHIP for Gravity Forms
 * Version: 1.0.0
 * 
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

add_filter( 'gf_chip_plugin_settings_fields', 'cgf_advance_global_fields' );
add_filter( 'gf_chip_feed_settings_fields', 'cgf_advance_form_fields' );
add_filter( 'gf_chip_purchases_api_parameters', 'cgf_api_parameters', 10, 2);

function cgf_advance_form_fields( $fields ) {
  $new_fields = cgf_advance_fields();
 
  $new_fields['dependency'] = array(
    'field'  => 'chipConfigurationType',
    'values' => array( 'form' )
  );

  $fields_6 = $fields[6];
  $fields_7 = $fields[7];

  unset($fields[6]);
  unset($fields[7]);

  $fields[] = $new_fields;

  $fields[] = $fields_6;
  $fields[] = $fields_7;

  return $fields;
}

function cgf_advance_global_fields( $fields ) {
  $fields[] = cgf_advance_fields();
  return $fields;
}

function cgf_advance_fields() {

  return array(
    'title'       => esc_html__( 'Gateway Charges', 'gravityformschip' ),
    'description' => esc_html__( 'Add additional gateway charges. This enables you to further add fee to pass the charges to customer.', 'gravityformschip' ),
    'fields'      => array(
      array(
        'name'    => 'charges_label',
        'label'   => esc_html__( 'Label for gateway charges', 'cgfgatewaycharge' ),
        'type'    => 'select',
        'required' => true,
        'tooltip'  => '<h6>' . esc_html__( 'Charges Label', 'cgfgatewaycharge' ) . '</h6>' . esc_html__( 'This will appear on CHIP invoice and receipt.', 'cgfgatewaycharge' ),
        'choices'  => array(
          array(
            'label' => esc_html__( 'Select charges label', 'cgfgatewaycharge' ),
            'value' => ''
          ),
          array(
            'label' => esc_html__( 'Handling Fee', 'cgfgatewaycharge' ),
            'value' => 'Handling Fee'
          ),
          array(
            'label' => esc_html__( 'Administrative Fee', 'cgfgatewaycharge' ),
            'value' => 'Administrative Fee'
          ),
          array(
            'label' => esc_html__( 'Administration Fee', 'cgfgatewaycharge' ),
            'value' => 'Administration Fee'
          ),
          array(
            'label' => esc_html__( 'Platform Fee', 'cgfgatewaycharge' ),
            'value' => 'Platform Fee'
          ),
        ),
      ),
      array(
        'name'    => 'pass_fixed_charges',
        'label'   => esc_html__( 'Pass fixed charges to customer (cents)', 'cgfgatewaycharge' ),
        'type'    => 'text',
        'tooltip' => '<h6>' . esc_html__( 'Pass Fixed Charges', 'cgfgatewaycharge' ) . '</h6>' . esc_html__( 'Pass fixed charges to the customer', 'cgfgatewaycharge' ),
        'placeholder' => esc_html__('Set 100 for RM 1 charges', 'cgfgatewaycharge'),
      ),
      array(
        'name'    => 'pass_percentage_charges',
        'label'   => esc_html__( 'Pass percentage charges to customer (basis point)', 'cgfgatewaycharge' ),
        'type'    => 'text',
        'tooltip' => '<h6>' . esc_html__( 'Pass Percentage Charges', 'cgfgatewaycharge' ) . '</h6>' . esc_html__( 'Pass percentage charges to the customer', 'cgfgatewaycharge' ),
        'placeholder' => esc_html__('Set 101 for 1.01% charges', 'cgfgatewaycharge'),
      ),
      array(
        'name'    => 'pass_lowest_charges',
        'label'   => esc_html__( 'Pass lowest charges to customer (cents)', 'cgfgatewaycharge' ),
        'type'    => 'text',
        'tooltip' => '<h6>' . esc_html__( 'Pass Lowest Charges', 'cgfgatewaycharge' ) . '</h6>' . esc_html__( 'Pass lowest charges to the customer', 'cgfgatewaycharge' ),
        'placeholder' => esc_html__('Set 100 for RM 1 charges', 'cgfgatewaycharge'),
      )
    )
  );
}

function cgf_api_parameters($params, $array) {
  $feed = $array[0];
  $submission_data = $array[1];

  if ( $gf_global_settings = get_option( 'gravityformsaddon_gravityformschip_settings' ) ) {
    $charg_label  = rgar( $gf_global_settings, 'charges_label' );
    $fix_charges  = rgar( $gf_global_settings, 'pass_fixed_charges' );
    $per_charges  = rgar( $gf_global_settings, 'pass_percentage_charges' );
    $low_charges  = rgar( $gf_global_settings, 'pass_lowest_charges' );
  }

  $configuration_type = rgars( $feed, 'meta/chipConfigurationType', 'global' );

  if ($configuration_type == 'form'){
    $charg_label  = rgars( $feed, 'meta/charges_label' );
    $fix_charges  = rgars( $feed, 'meta/pass_fixed_charges' );
    $per_charges  = rgars( $feed, 'meta/pass_percentage_charges' );
    $low_charges  = rgars( $feed, 'meta/pass_lowest_charges' );
  }

  $payment_amount_location = rgars( $feed, 'meta/paymentAmount'); // location for payment amount

  // This if the total amount choose to form total
  if ($payment_amount_location == 'form_total'){
    $amount       = rgar( $submission_data, 'payment_amount' ) * 100;
  } else {
    // This if the total amount choose to specific product.
    $items = rgar( $submission_data, 'line_items');
    foreach ($items as $item){
      if ($item['id'] == $payment_amount_location){
        // It is important to multiply with quantity to get real amount
        $amount       = $item['unit_price'] * $item['quantity'] * 100;
        break;
      }
    }
  }

  // add gateway charges calculation
  $price_charges = 0;

  if (!empty(round($amount))) {
    $price_charges += round($amount) * ( $per_charges / 100 / 100 ); // 101 for 1.01%
  }      

  if (!empty(round($fix_charges))) {
    $price_charges += $fix_charges;

    if (!empty(round($low_charges))) {
      if (round($low_charges) > round($price_charges)) {
        $price_charges = $low_charges;
      }
    }
  
    if (round($price_charges) > 0) {
      $params['purchase']['products'][] = array(
        'name'  => $charg_label,
        'price' => round($price_charges),
      );
    }
  }

  return $params;
}