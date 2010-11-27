
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

	// new user registration
	$('#registerUser').click(function(){
		$('#registerUser-result').empty(); $('#auth-result,#auth-err').empty();
		var dataItems = {'registerUser':1};
		dataItems['newEMail'] = $('#register-EMail').val();
		dataItems['newUsername'] = $('#register-Username').val();
		dataItems['newPassword'] = $('#register-Password').val();
		// dataItems['passwordSha1'] = $('#register-sha1:checked').size();
		sacv5.asyncRequest(dataItems,function(data){
			$('#auth-result').html(JSON.stringify(data.auth));
			$('#auth-err').html(data.authErr);
			if (data.authErr) {
				$('#registerUser-result').html(data.authErr);
			} else {
				$('#registerUser-result').html("new user registered");
			}
		});
	});

	// user login
	$('#loginUser').click(function(){
		$('#loginUser-result').empty(); $('#auth-result,#auth-err').empty();
		var data = sacv5.syncLogin($('#loginUser-username').val(),$('#loginUser-password').val());
		if (! data.authOK) {
			$('#loginUser-result').html(data.authRequires);
		} else {
			$('#auth-result').html(JSON.stringify(data.auth));
			$('#auth-err').html(data.authErr);
			if (data.authErr) {
				$('#loginUser-result').html(data.authErr);
			} else {
				$('#loginUser-result').html("login sucessful");
			}
		}
	});

	// user logged in test
	$('#loggedIn').click(function(){
		$('#loggedIn-result').empty(); $('#auth-result,#auth-err').empty();
		data = sacv5.syncLoggedIn();
		$('#loginUser-result').html(data.loggedIn);
	});

	// user logout
	$('#logoutUser').click(function(){
		$('#loginUser-result').empty(); $('#auth-result,#auth-err').empty();
		var data = sacv5.syncLogout();
		$('#loginUser-result').html(data.logout);
	});

	// delegate creation
	$('#delegateCreate').click(function(){
		$('#delegateCreate-result').empty(); $('#auth-result,#auth-err').empty();
		var dataItems = {'delegateCreate':1};
		dataItems['newEMail'] = $('#delegateCreate-EMail').val();
		dataItems['newUsername'] = $('#delegateCreate-Username').val();
		dataItems['newPassword'] = $('#delegateCreate-Password').val();
		// dataItems['passwordSha1'] = $('#delegateCreate-sha1:checked').size();
		sacv5.asyncRequest(dataItems,function(data){
			if (! data.authOK) {
				$('#delegateCreate-result').html(data.authRequires);
			} else {
				$('#auth-result').html(JSON.stringify(data.auth));
				$('#auth-err').html(data.authErr);
				if (data.authErr) {
					$('#delegateCreate-result').html(data.authErr);
				} else {
					$('#delegateCreate-result').html("delegate created");
				}
			}
		});
	});

	// delegate destruction
	$('#delegateDelete').click(function(){
		$('#delegateDelete-result').empty(); $('#auth-result,#auth-err').empty();
		var dataItems = {'delegateDelete':1};
		dataItems['newEMail'] = $('#delegateDelete-EMail').val();
		dataItems['newUsername'] = $('#delegateDelete-Username').val();
		sacv5.asyncRequest(dataItems,function(data){
			if (! data.authOK) {
				$('#delegateDelete-result').html(data.authRequires);
			} else {
				$('#auth-result').html(JSON.stringify(data.auth));
				$('#auth-err').html(data.authErr);
				if (data.auth) {
					$('#delegateDelete-result').html("delegate deleted");
				} else {
					$('#delegateDelete-result').html(data.authErr);
				}
			}
		});
	});

	// username forgotten, supply email to get username
	$('#userForgotten').click(function(){
		$('#userForgotten-result').empty(); $('#auth-result,#auth-err').empty();
		var dataItems = {'userForgotten':1};
		dataItems['newEMail'] = $('#userForgotten-email').val();
		sacv5.asyncRequest(dataItems,function(data){
			if (data.authOK && (data.username != undefined)) {
				$('#userForgotten-result').html(" username emailed would be: "+data.username);
			} else {
				$('#auth-result').html(JSON.stringify(data.auth));
				$('#auth-err').html(data.authErr);
			}
		});
	});

	// password forgotten, supply username & email to get reset hash & URL
	$('#forgotPassword').click(function(){
		$('#forgotPassword-result').empty(); $('#auth-result,#auth-err').empty();
		var dataItems = {'forgotPassword':1};
		dataItems['newUsername'] = $('#forgotPassword-username').val();
		dataItems['newEMail'] = $('#forgotPassword-email').val();
		sacv5.asyncRequest(dataItems,function(data){
			if (data.authOK) {
				if (data.resetHash != undefined) {
					$('#forgotPassword-result').html(" reset hash and URL would have been emailed");
					$('#passwordForgotten-hash').val(data.resetHash);
					$('#passwordForgotten-password').val('');
				} else 	{
					$('#auth-result').html(JSON.stringify(data.auth));
					$('#auth-err').html(data.authErr);
				}
			}
		});
	});

	// // username forgotten, supply email to get username
	$('#passwordForgotten').click(function(){
		$('#passwordForgotten-result').empty(); $('#auth-result,#auth-err').empty();
		var dataItems = {'passwordResetByHash':1};
		dataItems['newPassword'] = $('#passwordForgotten-password').val();
		dataItems['passwordHash'] = $('#passwordForgotten-hash').val();
		sacv5.asyncRequest(dataItems,function(data){
			if (data.passwordReset) {
				$('#passwordForgotten-result').html(data.passwordReset);
			} else {
				$('#auth-result').html(JSON.stringify(data.auth));
				$('#auth-err').html(data.authErr);
			}
		});
	});
	
	$('#sa_fieldList').change(function(){
		$('#updateKey').val($(this).val());
	});
	
	$("#updateIndexed").click(function(){
		$('#userDataRec').empty(); $('#auth-result,#auth-err').empty();
		var dataItems = { 'updateViaIndex': $("#updateKey").val() };
		dataItems['newValue'] = $('#updateValue').val();
		sacv5.asyncRequest(dataItems,function(data){
			if (data.authOK) {
				$("#userDataRec").text(JSON.stringify(data.auth));
				$("#auth-err").html(data.authErr);
			} else {
				$('#auth-result').html(JSON.stringify(data.auth));
				$('#auth-err').html(data.authErr);
			}
		});
	});

	$("#updateArbitrary").click(function(){
		$('#userDataRec').empty(); $('#auth-result,#auth-err').empty();
		var dataItems = { 'updateWithoutIndex': $("#updateKey").val() };
		dataItems['newValue'] = $('#updateValue').val();
		sacv5.asyncRequest(dataItems,function(data){
			$('#updateValue-result').html(data.authRequires);
			if (data.authOK) {
				$("#userDataRec").text(JSON.stringify(data.auth));
				$("#auth-err").html(data.authErr);
			} else {
				$('#auth-result').html(JSON.stringify(data.auth));
				$('#auth-err').html(data.authErr);
			}
		});
	});

});
