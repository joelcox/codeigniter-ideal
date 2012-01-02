<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Config for the iDeal library
 * @see ../libraries/Ideal.php
 */
$config['ideal_endpoint'] = '';						// Endpoint for your bank
$config['ideal_merchant_id'] = '';					// Merchant id, provided by your issuer
$config['ideal_sub_id'] = '0';						// Sub account id, probably '0'

$config['ideal_merchant_public_cert'] = '';			// Path to the public certificate for the merchant
$config['ideal_merchant_private_cert'] = '';		// Path to the private certificate for the merchant
$config['ideal_merchant_private_cert_pass'] = '';	// Password for the private certificate

$config['ideal_root_public_certs'] = '';			// Path to the public root certificates
$config['ideal_issuer_public_cert'] = '';			// Path to the public certificate for the issuer (bank)

$config['ideal_expiration_period'] = 15;			// The time until a payment expires, in minutes.
$config['ideal_return_url'] = '';					// Redirect after completing the transaction