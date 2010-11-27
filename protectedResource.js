
$(document).ready(function() {

	// this call sets up the connection requesting debug to the console, 
	//	identify the username and password field names
	//	setup a 5minute access allowance
	// use internal login support
	
	var sacv5 = SafeAjax.safeAjax_v5.init({ // use post to increase request size 
		ajaxConnector: "./lib/safeAjax/saConnector.php"
		,loginFirst: false ,handleLogin: true, sha1Logins: false 
		,debug: true ,notify: true ,duration: "5m"
	});
	
	$('#getResource').click(function(){
		
		sacv5.asyncRequest({ protect: 'sampleRequest' },function(data){
			// $("#debugDiv").html(JSON.stringify(data));
			$("#protectedResource").html(data.returnedResource); 
		})
		
		
	});

});


