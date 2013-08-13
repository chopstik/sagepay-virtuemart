<script type="text/javascript">
<!--

var ClickedTwice = false

function formValidator(theForm)
{
    if (theForm.CardHolder.value == "")
    {
        alert("Please supply the Name that apears on your Credit Card.");
        theForm.CardHolder.focus();
        return (false);
    }
    
    if (theForm.CardNumber.value == "")
    {
        alert("Please enter a value for the "Card Number" field.");
        theForm.CardNumber.focus();
        return (false);
    }
    
    if (theForm.ExpiryDate.value == "")
    {
        alert("Please enter a value for the "Expiry Date" field.");
        theForm.ExpiryMonth.focus();
        return (false);
    }
    
    // Simple check to avoid multiple sends if the user get impatient
    if (ClickedTwice)
        return (false);
    ClickedTwice = true;        

    return (true);
}
//-->
</script>

<h3 style="text-align: left;">To complete your order, provide your bank card details and click 'Pay'</h3>

<form action="<? echo SECUREURL ."index.php?option=com_virtuemart&page=checkout.sagepay_result"; ?>" method="post" name="storeform" onsubmit="return formValidator(this);">
<input type="hidden" name="order_id" value="<? echo $db->f("order_id"); ?>">
<table class="formTable">
	<tbody>
		<tr> 
			<td class="fieldLabel">Card Type:</td>
			<td class="fieldData">
				<select name="CardType">
				<option value="VISA" selected>VISA Credit</option>
				<option value="DELTA">VISA Debit</option>
				<option value="UKE">VISA Electron</option>
				<option value="MC">MasterCard</option>
				<option value="MAESTRO">Maestro</option>
				<option value="SOLO">Solo</option>
				</select>
			</td>
		</tr>
		<tr> 
			<td class="fieldLabel">Card Holder Name:</td>
			<td class="fieldData"><input type="text" maxlength="50" size="25" value="" name="CardHolder"/></td>
		</tr>
		<tr> 
			<td class="fieldLabel">Card Number:</td>
	  		<td class="fieldData"><input type="text" maxlength="24" size="25" value="" name="CardNumber"/>
			 <font size="1">(With no spaces or separators)</font></td>
		</tr>
		<tr> 
			<td class="fieldLabel">Start Date:</td>
	  		<td class="fieldData"><input type="text" maxlength="4" size="5" value="" name="StartDate"/>
		   	 <font size="1">(Where available. Use MMYY format  e.g. 0207)</font></td>
		</tr>
		<tr> 
			<td class="fieldLabel">Expiry Date:</td>
	  		<td class="fieldData"><input type="text" maxlength="4" size="5" value="" name="ExpiryDate"/>
		   	 <font size="1">(Use MMYY format with no / or - separators e.g. 1109)</font></td>
		</tr>
		<tr> 
			<td class="fieldLabel">Issue Number:</td>
	  		<td class="fieldData"><input type="text" maxlength="2" size="5" value="" name="IssueNumber"/>
		   	 <font size="1">(Older Switch cards only. 1 or 2 digits 
		  	as printed on the card)</font></td>
		</tr>
		<tr> 
			<td class="fieldLabel">Card Verification Value:</td>
	  		<td class="fieldData"><input type="text" maxlength="4" size="5" value="" name="CV2"/>
		   	 <font size="1">(Additional 3 digits on card signature strip, 4 on Amex cards)</font></td>
		</tr>
		<tr> 
			<td class="fieldLabel"></td>
	  		<td class="fieldData"><input type="submit" value="Pay" name="Pay" class="button"/></td>
		</tr>
	</tbody>
</table>
</form>
