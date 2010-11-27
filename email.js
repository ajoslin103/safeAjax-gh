
$(document).ready(function() {

	// this call sets up the connection requesting debug to the console, 
	//	identify the username and password field names
	//	setup a 5minute access allowance
	// use internal login support
	var sacv5 = SafeAjax.safeAjax_v5.init({ // use post to increase request size 
		ajaxConnector: './lib/safeAjax/saConnector.php'
		,loginFirst: false ,handleLogin: false, sha1Logins: false
		,debug: true ,notify: true ,duration: '5m'
	});
	
	function assembleEMailParams ( dataItems ) {
		dataItems['email_name'] = $('#email_name').val();
		dataItems['email_domain'] = $('#email_domain').val();
		dataItems['email_addr'] = $('#email_addr').val();
		dataItems['email_to'] = $('#email_to').val();
		dataItems['email_cc'] = $('#email_cc').val();
		dataItems['email_bcc'] = $('#email_bcc').val();
		dataItems['email_subject'] = $('#email_subject').val();
		dataItems['email_body'] = $('#email_body').val();
		try {
			dataItems['email_substitutions'] = JSON.parse($('#email_substitutions').val());
		}
		catch(ex) {
			alert("email_substitutions text was not a valid JSON string");
		}
		return dataItems;
	}
	
	function textualizeHTML ( htmlText ) {
		if (! htmlText) { return " [empty result] "; }
		htmlText = htmlText.split("<").join("&lt;")
		htmlText = htmlText.split(">").join("&gt;")
		return "<pre>"+htmlText+"</pre>";
	}

	$('#getEMailHeader').click(function(){
		$('#getEMailHandler-result').empty();
		var dataItems = assembleEMailParams({ 'getEMailHeader': 1 });
		sacv5.asyncRequest(dataItems,function(data){
			if (data['EMailHandler-error']) {
				$('#getEMailHandler-result').html(data['EMailHandler-error']);
			} else {
				$('#getEMailHandler-result').html(textualizeHTML(data['EMailHandler-result']));
			}
		});
	});

	$('#getEMail').click(function(){
		$('#getEMailHandler-result').empty();
		var dataItems = assembleEMailParams({ 'getEMail': 1 });
		sacv5.asyncRequest(dataItems,function(data){
			if (data['EMailHandler-error']) {
				$('#getEMailHandler-result').html(data['EMailHandler-error']);
			} else {
				$('#getEMailHandler-result').html(textualizeHTML(data['EMailHandler-result']));
			}
		});
	});

	$('#sendEMail').click(function(){
		$('#getEMailHandler-result').empty();
		var dataItems = assembleEMailParams({ 'sendEMail': 1 });
		sacv5.asyncRequest(dataItems,function(data){
			if (data['EMailHandler-error']) {
				$('#getEMailHandler-result').html(data['EMailHandler-error']);
			} else {
				$('#getEMailHandler-result').html(textualizeHTML("sendEMail result: "+data['EMailHandler-result']));
			}
		});
	});

});
