# SagePay Payment Method for VirtueMart #

Back in the day I put together a few scripts as a SagePay payment module for Virtuemart on the Joomla v1.5 series. Check... http://forum.virtuemart.net/index.php?topic=55753 for the original post.  I've had a few requests in for a copy of the files whiich originally were included with that thread but no longer seem to be there. I've set it up as a repo so it may help others still wrestling with the Joomla gods.

## Installation ##

1. upload "ps_sagepay.cfg.php" to /administrator/components/com_virtuemart/classes/payment/
2. upload "ps_sagepay.php" to /administrator/components/com_virtuemart/classes/payment/
3. upload "checkout.sagepay_result.php" to /administrator/components/com_virtuemart/html/
4. In VM Administration / Store create a new payment method. Call it what ever you want to show on screen when a customer chooses it. Give it a code of 'SAGE' and choose 'ps_sagepay' from the drop down list of "Payment class name". For "Payment method type" choose 'HTML-Form based (e.g. PayPal)'.
5. Save it and then come back into it. In the Configuration tab add in your Vendor Name (provided by Sage Pay) and copy in the HTML included in the file `VM-SagePay-Form.php`