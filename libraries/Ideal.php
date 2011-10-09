<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter iDeal
 *
 * A CodeIgniter library to interact with the iDeal online payment method through the XML API.
 *
 * @package        	CodeIgniter
 * @category    	Libraries
 * @author        	Joël Cox
 * @link 			https://github.com/joelcox/codeigniter-ideal
 * @link			http://joelcox.nl		
 * @license         http://www.opensource.org/licenses/mit-license.html
 */
class Ideal {

	/**
	 * @var	holds the CodeIgniter super object
	 */
	private $_ci;
	
	/**
	 * These constants will probably never change, just for good measure
	 */
	const CURRENCY = 'EUR';
	const LANGUAGE = 'nl';
	const PAYMENT_TYPE = 'ideal';
	const NEW_LINE = "\n";

	/**
	 * Constructor
	 */
	public function __construct()
	{
		
		log_message('debug', 'iDeal Class Initialized');
		$this->_ci = &get_instance();

		// Load all config items
		$this->_ci->load->config('ideal');
		
		// Set payment time
		$this->valid_until = time() + $this->_ci->config->item('ideal_valid_until');
	
	}
	
	/**
	 * Directory request
	 *
	 * Requests a directory with available banks
	 * @return 	array
	 */
	public function get_directory()
	{
		
		// Check if the directory is in our cache
		$this->_ci->load->driver('cache', array('adapter' => 'file'));
		if ($issuers = $this->_ci->cache->get('ideal_directory'))
		{			
		    return $issuers;
		}	
		
		$timestamp = date('Y-m-d\TH:i:s') . '.0Z';	
				
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . self::NEW_LINE;
		$xml .= '<DirectoryReq xmlns="http://www.idealdesk.com/Message" version="1.1.0">' . self::NEW_LINE;
		$xml .= '<createDateTimeStamp>' . $timestamp . '</createDateTimeStamp>' . self::NEW_LINE;
		$xml .= '<Merchant>' . self::NEW_LINE;
		$xml .= '<merchantID>' . $this->_ci->config->item('ideal_merchant_id') . '</merchantID>' . self::NEW_LINE;
		$xml .= '<subID>' . $this->_ci->config->item('ideal_sub_id') . '</subID> ' . self::NEW_LINE;
		$xml .= '<authentication>SHA1_RSA</authentication>' . self::NEW_LINE;
		$xml .= '<token>' . $this->_get_fingerprint() . '</token>' . self::NEW_LINE;
		$xml .= '<tokenCode>' . $this->_get_signature($timestamp, $this->_ci->config->item('ideal_merchant_id'), $this->_ci->config->item('ideal_sub_id')) . '</tokenCode>' . self::NEW_LINE;
		$xml .= '</Merchant>' . self::NEW_LINE;
		$xml .= '</DirectoryReq>' . self::NEW_LINE;
			
		// Fire off the request		
		$tree = $this->_request($xml);

		$issuers['pick_bank'] = 'Kies uw bank...';
		$flag = FALSE;
		
		foreach ($tree->Directory->Issuer as $issuer)
		{			
			// Check if this is the start of the long list
			if ($flag === FALSE AND (string) $issuer->issuerList === 'Long')
			{
				$issuers['more_banks'] = '---Overige banken---';
				$flag = TRUE;
			}
					
			// Add the new issuer to our list
			$issuer_id = (string) $issuer->issuerID;
			$issuers[$issuer_id] = (string) $issuer->issuerName; 
		}
		
		// Cache and return
		$this->_ci->cache->save('ideal_directory', $issuers, 86400);
		return $issuers;
					
	}
	
	/**
	 * Create a new transaction
	 * @param	int		identifier for the issuer (bank)
	 * @param	int		price in cents
	 * @param	string	internal identifier for purchases
	 * @param	string	unique entrance code
	 * @param	string	description for the purchase
	 * @return
	 */
	public function get_transaction($issuer_id, $amount, $purchase_id, $entrance_code, $description)
	{
		
		$timestamp = date('Y-m-d\TH:i:s') . '.0Z';
		$signature = $this->_get_signature($timestamp, $issuer_id, $this->_ci->config->item('ideal_merchant_id'), $this->_ci->config->item('ideal_sub_id'), $this->_ci->config->item('ideal_return_url'), $purchase_id, $amount, self::CURRENCY, self::LANGUAGE, $description, $entrance_code);
		
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . self::NEW_LINE;
		$xml .= '<AcquirerTrxReq xmlns="http://www.idealdesk.com/Message" version="1.1.0">' . self::NEW_LINE;
		$xml .= '<createDateTimeStamp>' . $timestamp . '</createDateTimeStamp>' . self::NEW_LINE;
		$xml .= '<Issuer>' . self::NEW_LINE;
		$xml .= '<issuerID>' . $issuer_id . '</issuerID>' . self::NEW_LINE;
		$xml .= '</Issuer>' . self::NEW_LINE;
		$xml .= '<Merchant>' . self::NEW_LINE;
		$xml .= '<merchantID>' . $this->_ci->config->item('ideal_merchant_id') . '</merchantID>' . self::NEW_LINE;
		$xml .= '<subID>' . $this->_ci->config->item('ideal_sub_id') . '</subID> ' . self::NEW_LINE;
		$xml .= '<authentication>SHA1_RSA</authentication>' . self::NEW_LINE;
		$xml .= '<token>' . $this->_get_fingerprint() . '</token>' . self::NEW_LINE;
		$xml .= '<tokenCode>' . $signature . '</tokenCode>' . self::NEW_LINE;
		$xml .= '<merchantReturnURL>' . $this->_ci->config->item('ideal_return_url') . '</merchantReturnURL>' . self::NEW_LINE;
		$xml .= '</Merchant>' . self::NEW_LINE;
		$xml .= '<Transaction>' . self::NEW_LINE;
		$xml .= '<purchaseID>' . $purchase_id . '</purchaseID>' . self::NEW_LINE;
		$xml .= '<amount>' . $amount . '</amount>' . self::NEW_LINE;
		$xml .= '<currency>' . self::CURRENCY . '</currency>' . self::NEW_LINE;
		$xml .= '<expirationPeriod>PT' . $this->_ci->config->item('ideal_expiration_period') . 'M</expirationPeriod>' . self::NEW_LINE;
		$xml .= '<language>' . self::LANGUAGE . '</language>' . self::NEW_LINE;
		$xml .= '<description>' . htmlentities($description, ENT_QUOTES) . '</description>' . self::NEW_LINE;
		$xml .= '<entranceCode>' . $entrance_code . '</entranceCode>' . self::NEW_LINE;
		$xml .= '</Transaction>' . self::NEW_LINE;
		$xml .= '</AcquirerTrxReq>' . self::NEW_LINE;

		$tree = $this->_request($xml);
		
		return array(
			'url' => (string) $tree->Issuer->issuerAuthenticationURL,
			'id' => (string) $tree->Transaction->transactionID
		);
		
	}
	
	
	public function get_status($transaction_id)
	{
		
		$timestamp = date('Y-m-d\TH:i:s') . '.0Z';
		$signature = $this->_get_signature($timestamp, $this->_ci->config->item('ideal_merchant_id'), $this->_ci->config->item('ideal_sub_id'), $transaction_id);
		
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . self::NEW_LINE . self::NEW_LINE;
		$xml .= '<AcquirerStatusReq xmlns="http://www.idealdesk.com/Message" version="1.1.0">' . self::NEW_LINE;
		$xml .= '<createDateTimeStamp>' . $timestamp . '</createDateTimeStamp>' . self::NEW_LINE;
		$xml .= '<Merchant>' . self::NEW_LINE;
		$xml .= '<merchantID>' . $this->_ci->config->item('ideal_merchant_id') . '</merchantID>' . self::NEW_LINE;
		$xml .= '<subID>' . $this->_ci->config->item('ideal_sub_id') . '</subID>' . self::NEW_LINE;
		$xml .= '<authentication>SHA1_RSA</authentication>' . self::NEW_LINE;
		$xml .= '<token>' . $this->_get_fingerprint() . '</token>' . self::NEW_LINE;
		$xml .= '<tokenCode>' . $signature . '</tokenCode>' . self::NEW_LINE;
		$xml .= '</Merchant>' . self::NEW_LINE;
		$xml .= '<Transaction>' . self::NEW_LINE;
		$xml .= '<transactionID>' . $transaction_id . '</transactionID>' . self::NEW_LINE;
		$xml .= '</Transaction>' . self::NEW_LINE;
		$xml .= '</AcquirerStatusReq>' . self::NEW_LINE;
		
		$tree = $this->_request($xml);
				
		if ($this->_verify_response($tree) === FALSE)
		{
			log_message('error', 'iDeal error: Can\'t verify status response.');
			return FALSE;
		}	
		
		return (string) strtolower($tree->Transaction->status);
		
	}
	
	/**
	 * Get the fingerprint from the public certificate
	 * @param 	the owner of the certificate, merchant or issuer
	 * @return 	string
	 */
	protected function _get_fingerprint($owner = 'merchant')
	{
		
		if ( ! file_exists($this->_ci->config->item('ideal_' . $owner . '_public_key')))
		{
			show_error('Public key certificate could not be found.');
		}
		
		// Open up the cert and extract the key
		$resource = openssl_x509_read(file_get_contents($this->_ci->config->item('ideal_' . $owner . '_public_key')));
		openssl_x509_export($resource, $key);
		
		$key = str_replace('-----BEGIN CERTIFICATE-----', '', $key);
		$key = str_replace('-----END CERTIFICATE-----', '', $key);
		
		return strtoupper(sha1(base64_decode($key)));
		
	}
	
	/**
	 * Create a base64 encoded signature from n number of arguments
	 * @param	mixed	n amount of arguments
	 * @return 	string
	 */
	protected function _get_signature()
	{
		
		$args = func_get_args();
		$message = '';
		
		foreach ($args as $arg)
		{
			$message .= $arg;	
		}

		$whitespace = array("\t", "\n", "\r", " ");
		$message = str_replace($whitespace, '', html_entity_decode($message));

		if ( ! $resource = file_get_contents($this->_ci->config->item('ideal_merchant_private_key')))
		{
			show_error('Private key certificate could not be found.');
		}
				
		if ( ! $key = openssl_get_privatekey($resource, $this->_ci->config->item('ideal_merchant_private_key_pass')))
		{
			show_error('Private key certificate password is incorrect.');
		}
				
		// Sign our message to get our signature
		openssl_sign($message, $signature, $key);
		return base64_encode($signature);
		
	}
	
	/**
	 * Verify a signature from a message using a public certificate
	 * @param 	string	signature to be verified
	 * @param 	array 	pieces to be concetenated
	 * @return 	bool
	 */
	public function _verify_signature($signature, $data)
	{
		$message = '';
		
		foreach ($data as $items)
		{
			$message .= (string) $items;	
		}
		
		if ( ! $resource = file_get_contents($this->_ci->config->item('ideal_issuer_public_key')))
		{
			show_error('Public key certificate could not be found.');
		}

		$key = openssl_get_publickey($resource);
		return (bool) openssl_verify($message, base64_decode($signature), $key);
				
	}
	
	/**
	 * Verify the response from the issuer
	 * @param	object	a parsed XML document
	 * @return 	bool
	 */
	protected function _verify_response($tree)
	{

		if ((string) $tree->Signature->fingerprint !== $this->_get_fingerprint('issuer'))
		{			
			log_message('error', 'iDeal error: Fingerprints from response and certificate do not match.');
			return FALSE;
		}
		
		// Prep our message
		$data = array($tree->createDateTimeStamp, $tree->Transaction->transactionID, $tree->Transaction->status);
		
		if ($tree->Transaction->status == 'Success')
		{
			$data[] = $tree->Transaction->consumerAccountNumber;
		}
		
		if ($this->_get_signature((string) $tree->Signature->signatureValue, $data) === FALSE)
		{			
			log_message('error', 'iDeal error: Signature from response and certificate do not match.');
			return FALSE;
		}
		
		return TRUE;
		
	}
	
	/**
	 * Make a request to the XML API
	 * @param 	string	a formatted XML documented
	 * @param	object	a parsed XML tree
	 */
	protected function _request($data)
	{
		
		$this->_ci->load->library('curl');
		$this->_ci->curl->create($this->_ci->config->item('ideal_endpoint'))->ssl(FALSE);
		$this->_ci->curl->post($data);		
		
		// Did we get a proper response?
		if ($data = $this->_ci->curl->execute())
		{		
			$xml = new SimpleXMLElement($data);

			// Is this an error response?
			if ( ! isset($xml->Error))
			{
				return $xml;
			}

			log_message('error', 'iDeal error: ' . (string) $xml->Error->errorCode . ' - ' . (string) $xml->Error->errorMessage . '. ' . (string) $xml->Error->errorDetail);
		}
			
		show_error('Couldn\'t properly connect to payment gateway.');
		
	}
	
}