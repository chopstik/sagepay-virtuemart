<?php

/**
* SagePay Order Confirmation Handler
Tony Coyle / Chopstik Internet / 15/5/2009 / udon@chopstik.net
*/

if( !defined( '_VALID_MOS' ) && !defined( '_JEXEC' ) ) die( 'Direct Access to '.basename(__FILE__).' is not allowed.' );

/*************************************************************
	Send a post request with cURL
		$url = URL to send request to
		$data = POST data to send (in URL encoded Key=value pairs)
*************************************************************/
function requestPost($url, $data){
	// Set a one-minute timeout for this script
	set_time_limit(60);

	// Initialise output variable
	$output = array();

	// Open the cURL session
	$curlSession = curl_init();

	// Set the URL
	curl_setopt ($curlSession, CURLOPT_URL, $url);
	// No headers, please
	curl_setopt ($curlSession, CURLOPT_HEADER, 0);
	// It's a POST request
	curl_setopt ($curlSession, CURLOPT_POST, 1);
	// Set the fields for the POST
	curl_setopt ($curlSession, CURLOPT_POSTFIELDS, $data);
	// Return it direct, don't print it out
	curl_setopt($curlSession, CURLOPT_RETURNTRANSFER,1); 
	// This connection will timeout in 30 seconds
	curl_setopt($curlSession, CURLOPT_TIMEOUT,30); 
	//The next two lines must be present for the kit to work with newer version of cURL
	//You should remove them if you have any problems in earlier versions of cURL
    curl_setopt($curlSession, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($curlSession, CURLOPT_SSL_VERIFYHOST, 1);

	//Send the request and store the result in an array
	
	$rawresponse = curl_exec($curlSession);
	//Store the raw response for later as it's useful to see for integration and understanding 
	$_SESSION["rawresponse"]=$rawresponse;
	//Split response into name=value pairs
	$response = split(chr(10), $rawresponse);
	// Check that a connection was made
	if (curl_error($curlSession)){
		// If it wasn't...
		$output['Status'] = "FAIL";
		$output['StatusDetail'] = curl_error($curlSession);
	}

	// Close the cURL session
	curl_close ($curlSession);

	// Tokenise the response
	for ($i=0; $i<count($response); $i++){
		// Find position of first "=" character
		$splitAt = strpos($response[$i], "=");
		// Create an associative (hash) array with key/value pairs ('trim' strips excess whitespace)
		$output[trim(substr($response[$i], 0, $splitAt))] = trim(substr($response[$i], ($splitAt+1)));
	} // END for ($i=0; $i<count($response); $i++)

	// Return the output
	return $output;
	

} // END function requestPost()

// Filters unwanted characters out of an input string.  Useful for tidying up FORM field inputs
function cleanInput($strRawText,$strType)
{

	if ($strType=="Number") {
		$strClean="0123456789.";
		$bolHighOrder=false;
	}
	else if ($strType=="VendorTxCode") {
		$strClean="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_.";
		$bolHighOrder=false;
	}
	else {
  		$strClean=" ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789.,'/{}@():?-_&£$=%~<>*+\"";
		$bolHighOrder=true;
	}
	
	$strCleanedText="";
	$iCharPos = 0;
		
	do
	{
    	// Only include valid characters
		$chrThisChar=substr($strRawText,$iCharPos,1);
			
		if (strspn($chrThisChar,$strClean,0,strlen($strClean))>0) { 
			$strCleanedText=$strCleanedText . $chrThisChar;
		}
		else if ($bolHighOrder==true) {
				// Fix to allow accented characters and most high order bit chars which are harmless 
				if (bin2hex($chrThisChar)>=191) {
					$strCleanedText=$strCleanedText . $chrThisChar;
				}
			}
			
		$iCharPos=$iCharPos+1;
		}
	while ($iCharPos<strlen($strRawText));
		
  	$cleanInput = ltrim($strCleanedText);
	return $cleanInput;

}


/* Begin process of colelcting posted card details and Post to SagePay for Authorisation */

/* Load the SagePay Configuration File */ 
require_once( CLASSPATH. 'payment/ps_sagepay.cfg.php' );

/* order_id is the name of the variable that holds OUR order_number */
$order_id = vmGet( $_REQUEST, "order_id" ); 

if(!isset($order_id) || empty($order_id)){
  echo $VM_LANG->_('VM_CHECKOUT_ORDERIDNOTSET');
} else {

	// Gather order & user details
	$qv = "SELECT order_id, order_number, order_total, user_info_id, user_id FROM #__{vm}_orders WHERE order_id='".$order_id."'";
	$db = new ps_DB;
	$db->query($qv);
	$db->next_record();
	$d['order_id'] = $db->f("order_id");

	// SagePay config
	$strConnectTo = 'LIVE'; // `TEST` in development and `LIVE` when you're into production
	$strProtocol="2.23";
	$strTransactionType = "PAYMENT";
	$strVendorName = SAGEPAY_VENDOR_NAME;
	$strCurrency = "GBP";
	$strVendorTxCode = $db->f("order_number");
	
	/* For testing transactions on the Protx test server, use the following card numbers.
		NB: there are NO dummy cards to use on the Live server. Actual Live bank cards must be used.
		
		Card Type 	Protx Card Name 	Card Number 	Issue Number
		Visa 	VISA 	4929000000006 	n/a
		Visa Delta 	DELTA 	4462000000000003 	n/a
		Visa Electron UK Debit 	UKE 	4917300000000008 	n/a
		Mastercard 	MC 	5404000000000001 	n/a
		UK Maestro 	MAESTRO 	5641820000000005 	01
		International Maestro 	MAESTRO 	300000000000000004 	n/a
		Solo 	SOLO 	6334900000000005 	1
		American Express 	AMEX 	374200000000004 	n/a
		Japan Credit Bureau (JCB) 	JCB 	3569990000000009 	n/a
		Diners Club 	DC 	36000000000008 	n/a
		
		You'll also need to supply an Expiry Date in the future and the following values for CV2, Billing Address Numbers and Billing Post Code Numbers. These are the only values which will return as Matched. Any other values will return a Not Matched.
		
		CV2: 123
		Billing Address: 88
		Billing PostCode: 412
		
		You'll also need to enter the 3D Secure password as password (it's case sensitive) so that the 3D Secure authentication returns Fully Authenticated.
		Please note that the Protx test server is not integrated with any banks, therefore no monies will be transferred as a result of these tests. 
	*/
	
	if ($strConnectTo=="LIVE")
	{
	  $strAbortURL="https://live.sagepay.com/gateway/service/abort.vsp";
	  $strAuthoriseURL="https://live.sagepay.com/gateway/service/authorise.vsp";
	  $strCancelURL="https://live.sagepay.com/gateway/service/cancel.vsp";
	  $strPurchaseURL="https://live.sagepay.com/gateway/service/vspdirect-register.vsp"; 
	  $strRefundURL="https://live.sagepay.com/gateway/service/refund.vsp";
	  $strReleaseURL="https://live.sagepay.com/gateway/service/release.vsp";
	  $strRepeatURL="https://live.sagepay.com/gateway/service/repeat.vsp";
	  $strVoidURL="https://live.sagepay.com/gateway/service/void.vsp";
	  $str3DCallbackPage="https://live.sagepay.com/gateway/service/direct3dcallback.vsp";
	  $strPayPalCompletionURL="https://live.sagepay.com/gateway/service/complete.vsp";
	}
	elseif ($strConnectTo=="TEST")
	{
	  $strAbortURL="https://test.sagepay.com/gateway/service/abort.vsp";
	  $strAuthoriseURL="https://test.sagepay.com/gateway/service/authorise.vsp";
	  $strCancelURL="https://test.sagepay.com/gateway/service/cancel.vsp";
	  $strPurchaseURL="https://test.sagepay.com/gateway/service/vspdirect-register.vsp";
	  $strRefundURL="https://test.sagepay.com/gateway/service/refund.vsp";
	  $strReleaseURL="https://test.sagepay.com/gateway/service/release.vsp";
	  $strRepeatURL="https://test.sagepay.com/gateway/service/repeat.vsp";
	  $strVoidURL="https://test.sagepay.com/gateway/service/void.vsp";
	  $str3DCallbackPage="https://test.sagepay.com/gateway/service/direct3dcallback.vsp";
	  $strPayPalCompletionURL="https://test.sagepay.com/gateway/service/complete.vsp";
	}
	else
	{
	  $strAbortURL="https://test.sagepay.com/simulator/VSPServerGateway.asp?Service=VendorAbortTx";
	  $strAuthoriseURL="https://test.sagepay.com/simulator/VSPServerGateway.asp?Service=VendorAuthoriseTx";
	  $strCancelURL="https://test.sagepay.com/simulator/VSPServerGateway.asp?Service=VendorCancelTx";
	  $strPurchaseURL="https://test.sagepay.com/simulator/VSPDirectGateway.asp";
	  $strRefundURL="https://test.sagepay.com/simulator/VSPServerGateway.asp?Service=VendorRefundTx";
	  $strReleaseURL="https://test.sagepay.com/simulator/VSPServerGateway.asp?Service=VendorReleaseTx";
	  $strRepeatURL="https://test.sagepay.com/simulator/VSPServerGateway.asp?Service=VendorRepeatTx";
	  $strVoidURL="https://test.sagepay.com/simulator/VSPServerGateway.asp?Service=VendorVoidTx";
	  $str3DCallbackPage="https://test.sagepay.com/simulator/VSPDirectCallback.asp";
	  $strPayPalCompletionURL="https://test.sagepay.com/simulator/paypalcomplete.asp";
	}
	
	// get our user details from the joomla / vm tables
	$q  = "SELECT * FROM #__vm_user_info WHERE user_id='".$db->f("user_id")."' AND address_type='BT'"; 
	$dbbt = new ps_DB;
	$dbbt->setQuery($q);
	$dbbt->query();
	$dbbt->next_record();
	
	// Get ship_to information
	$q2  = "SELECT * FROM #__vm_user_info WHERE user_id='".$db->f("user_id")."' AND address_type='ST'";; 
	$dbst = new ps_DB;
	$dbst->setQuery($q2);
	$dbst->query();
	if($dbst->num_rows() >= 1){
		$dbst->next_record();
	}else{
		$dbst = $dbbt;
	}
	
	// Extract Card Details from the page
	$strCardType=cleanInput($_REQUEST["CardType"],"Text");	
	$strCardHolder=substr($_REQUEST["CardHolder"],0,100);
	$strCardNumber=cleanInput($_REQUEST["CardNumber"],"Number");
	$strStartDate=cleanInput($_REQUEST["StartDate"],"Number");
	$strExpiryDate=cleanInput($_REQUEST["ExpiryDate"],"Number");
	$strIssueNumber=cleanInput($_REQUEST["IssueNumber"],"Number");
	$strCV2=cleanInput($_REQUEST["CV2"],"Number");
	
	// Now create the Sage Pay Direct POST

	/* Now to build the Sage Pay Direct POST.  For more details see the Sage Pay Direct Protocol 2.23
	** NB: Fields potentially containing non ASCII characters are URLEncoded when included in the POST */
	$strPost="VPSProtocol=" . $strProtocol;
	$strPost=$strPost . "&TxType=" . $strTransactionType; //PAYMENT by default.  You can change this in the includes file
	$strPost=$strPost . "&Vendor=" . $strVendorName;
	$strPost=$strPost . "&VendorTxCode=" . $strVendorTxCode; //As generated above
	$strPost=$strPost . "&Amount=" . number_format($db->f("order_total"),2); //Formatted to 2 decimal places with leading digit but no commas or currency symbols **
	$strPost=$strPost . "&Currency=" . $strCurrency;
	// Up to 100 chars of free format description
	$strPost=$strPost . "&Description=" . urlencode('Tikidrums Order (#'.$db->f("order_id").')');
    $strPost=$strPost . "&CardHolder=" . $strCardHolder;
	$strPost=$strPost . "&CardNumber=" . $strCardNumber;
	if (strlen($strStartDate)>0) 
		$strPost=$strPost . "&StartDate=" . $strStartDate;
	$strPost=$strPost . "&ExpiryDate=" . $strExpiryDate;
	if (strlen($strIssueNumber)>0) 
		$strPost=$strPost . "&IssueNumber=" . $strIssueNumber;
	$strPost=$strPost . "&CV2=" . $strCV2;
	$strPost=$strPost . "&CardType=" . $strCardType;
	$strPost=$strPost . "&CustomerEMail=" . urlencode($_SESSION["strCustomerEMail"]);
	$strPost=$strPost . "&ClientIPAddress=" . $_SERVER['REMOTE_ADDR'];
	$strPost=$strPost . "&AccountType=E";
	
	// SagePay requires a 2-letter country code, not 3 which Joomla is producing
	// Gather the 2-letter version from `jos_vm_country`
	$q  = "SELECT country_2_code FROM #__vm_country WHERE country_3_code='".$dbbt->f("country")."'"; 
	$dbbtt = new ps_DB;
	$dbbtt->setQuery($q);
	$dbbtt->query();
	$dbbtt->next_record();
	
	/* Billing Details 
	** This section is optional in its entirety but if one field of the address is provided then all non-optional fields must be provided 
	** If AVS/CV2 is ON for your account, or, if paypal cardtype is specified and its not via PayPal Express then this section is compulsory */
	$strPost=$strPost . "&BillingFirstnames=" . urlencode($dbbt->f("first_name"));
	$strPost=$strPost . "&BillingSurname=" . urlencode($dbbt->f("last_name"));
	$strPost=$strPost . "&BillingAddress1=" . urlencode($dbbt->f("address_1"));
	if (strlen($dbbt->f("address_2")) > 0) $strPost=$strPost . "&BillingAddress2=" . urlencode($dbbt->f("address_2"));
	$strPost=$strPost . "&BillingCity=" . urlencode($dbbt->f("city"));
	$strPost=$strPost . "&BillingPostCode=" . urlencode($dbbt->f("zip"));
	$strPost=$strPost . "&BillingCountry=" . urlencode($dbbtt->f("country_2_code"));
	// only supply a state for US customers
	if ((strlen($dbbt->f("state")) > 0) && ($dbbtt->f("country_2_code") == "US")) $strPost=$strPost . "&BillingState=" . urlencode($dbbt->f("state"));
	if (strlen($dbbt->f("phone_1")) > 0) $strPost=$strPost . "&BillingPhone=" . urlencode($dbbt->f("phone_1"));
	
	// SagePay requires a 2-letter country code, not 3 which Joomla is producing
	// Search the 2-letter version from `jos_vm_country`
	$q  = "SELECT country_2_code FROM #__vm_country WHERE country_3_code='".$dbst->f("country")."'"; 
	$dbstt = new ps_DB;
	$dbstt->setQuery($q);
	$dbstt->query();
	$dbstt->next_record();
	
	/* Delivery Details
	** This section is optional in its entirety but if one field of the address is provided then all non-optional fields must be provided
	** If paypal cardtype is specified then this section is compulsory */
	$strPost=$strPost . "&DeliveryFirstnames=" . urlencode($dbst->f("first_name"));
	$strPost=$strPost . "&DeliverySurname=" . urlencode($dbst->f("last_name"));
	$strPost=$strPost . "&DeliveryAddress1=" . urlencode($dbst->f("address_1"));
	if (strlen($dbst->f("address_2")) > 0) $strPost=$strPost . "&DeliveryAddress2=" . urlencode($dbst->f("address_2"));
	$strPost=$strPost . "&DeliveryCity=" . urlencode($dbst->f("city"));
	$strPost=$strPost . "&DeliveryPostCode=" . urlencode($dbst->f("zip"));
	$strPost=$strPost . "&DeliveryCountry=" . urlencode($dbstt->f("country_2_code"));
	// only supply a state for US customers
	if ((strlen($dbst->f("state")) > 0) && ($dbstt->f("country_2_code") == "US")) $strPost=$strPost . "&DeliveryState=" . urlencode($dbst->f("state"));	if (strlen($dbst->f("phone_1")) > 0) $strPost=$strPost . "&DeliveryPhone=" . urlencode($dbst->f("phone_1"));
	
	/* The full transaction registration POST has now been built **
	** Send the post to the target URL
	** if anything goes wrong with the connection process:
	** - $arrResponse["Status"] will be 'FAIL';
	** - $arrResponse["StatusDetail"] will be set to describe the problem 
	** Data is posted to strPurchaseURL which is set depending on whether you are using SIMULATOR, TEST or LIVE */
	$arrResponse = requestPost($strPurchaseURL, $strPost);

	/* Analyse the response from Sage Pay Direct to check that everything is okay
	** Registration results come back in the Status and StatusDetail fields */
	$strStatus=$arrResponse["Status"];
	$strStatusDetail=$arrResponse["StatusDetail"];
					
	/* If this isn't 3D-Auth, then this is an authorisation result (either successful or otherwise) **
	** Get the results form the POST if they are there */
	$strVPSTxId=$arrResponse["VPSTxId"];
	$strSecurityKey=$arrResponse["SecurityKey"];
	$strTxAuthNo=$arrResponse["TxAuthNo"];
	$strAVSCV2=$arrResponse["AVSCV2"];
	$strAddressResult=$arrResponse["AddressResult"];
	$strPostCodeResult=$arrResponse["PostCodeResult"];
	$strCV2Result=$arrResponse["CV2Result"];
	$str3DSecureStatus=$arrResponse["3DSecureStatus"];
	$strCAVV=$arrResponse["CAVV"];
			
	// Update the database and redirect the user appropriately
	if ($strStatus=="OK")
		$strDBStatus="AUTHORISED - The transaction was successfully authorised with the bank.";
	elseif ($strStatus=="MALFORMED")
		$strDBStatus="MALFORMED - The StatusDetail was:" . mysql_real_escape_string(substr($strStatusDetail,0,255));
	elseif ($strStatus=="INVALID")
		$strDBStatus="INVALID - The StatusDetail was:" . mysql_real_escape_string(substr($strStatusDetail,0,255));
	elseif ($strStatus=="NOTAUTHED")
		$strDBStatus="DECLINED - The transaction was not authorised by the bank.";
	elseif ($strStatus=="REJECTED")
		$strDBStatus="REJECTED - The transaction was failed by your 3D-Secure or AVS/CV2 rule-bases.";
	elseif ($strStatus=="AUTHENTICATED")
		$strDBStatus="AUTHENTICATED - The transaction was successfully 3D-Secure Authenticated and can now be Authorised.";
	elseif ($strStatus=="REGISTERED")
		$strDBStatus="REGISTERED - The transaction was could not be 3D-Secure Authenticated, but has been registered to be Authorised.";
	elseif ($strStatus=="ERROR")
		$strDBStatus="ERROR - There was an error during the payment process.  The error details are: " . mysql_real_escape_string($strStatusDetail);
	else
		$strDBStatus="UNKNOWN - An unknown status was returned from Sage Pay.  The Status was: " . mysql_real_escape_string($strStatus) . ", with StatusDetail:" . mysql_real_escape_string($strStatusDetail);

    
    // UPDATE THE ORDER STATUS to 'CONFIRMED'
   if (($strStatus=="OK")||($strStatus=="AUTHENTICATED")||($strStatus=="REGISTERED")){
   
        // SUCCESS: UPDATE THE ORDER STATUS to 'CONFIRMED'
		$d['order_status'] = 'C';
        require_once ( CLASSPATH . 'ps_order.php' );
        $ps_order= new ps_order;
        $ps_order->order_status_update($d);
        
        $d["order_payment_log"] = $VM_LANG->_('PHPSHOP_PAYMENT_TRANSACTION_SUCCESS').": ".$strDBStatus;
		$d["order_payment_trans_id"] = $strVendorTxCode;
		
		// Right-o we have a paid for order, lets e-mail the customer
		require_once( CLASSPATH. 'ps_checkout.php' );
		$ps_checkout = new ps_checkout;
		$ps_checkout->email_receipt($d['order_id']);
		
    } else {

        // FAILED: UPDATE THE ORDER STATUS to 'PENDING'
        $d['order_status'] = 'P';
        require_once ( CLASSPATH . 'ps_order.php' );
        $ps_order= new ps_order;
        $ps_order->order_status_update($d);
        
        $d["order_payment_log"] = $VM_LANG->_('PHPSHOP_PAYMENT_TRANSACTION_FAILURE').": ".$strDBStatus;
		$d["order_payment_trans_id"] = $strVendorTxCode;
    }
    
    // record additional transaction details
	$q = "UPDATE #__{vm}_order_payment SET ";
	$q .="order_payment_log='".$d["order_payment_log"]."',";
	$q .="order_payment_trans_id='".$d["order_payment_trans_id"]."' ";
	$q .="WHERE order_id='".$d['order_id']."' ";
	$db->query( $q );

	// Having updated our order transfer user to the order details page
	vmRedirect(SECUREURL."index.php?option=com_virtuemart&page=account.order_details&order_id=".$d['order_id']);
}

?>

