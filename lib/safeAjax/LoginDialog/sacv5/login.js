/*
*

LoginDialog, by allen joslin

	Decoupling the login dialog support 
	from the login actions as provided by SafeAjax

11/11/10 - vers 1.0: using jQueryUI

*
*/


// -----------------------------------------------------------------------------------------------------------------------------------
/*** our sacv5 accessor must already be defined for these funtions -- they need a backend to talk to ***/

// load the css resources we need 
$('<link rel="stylesheet" type="text/css" href="./lib/safeAjax/LoginDialog/sacv5/login.css" >').appendTo('head');

// append our dialog markup
$('<div id="sa_LoginDialogs">').css({display:'none'}).appendTo('body');
$.ajax({ async: false, url: './lib/safeAjax/LoginDialog/sacv5/login.html', success: function(data){ $('#sa_LoginDialogs').html(data); } });

// -------------------------------------------------------------------------------------------------------
var sa_userLogin = function() { // userLogin dialog ------ userLogin dialog ------ userLogin dialog ------ 

	// create the dialog
	$('#sa_userLogin').dialog({ title: "User Login", 
		autoOpen:false,  modal:true, resizeable: false, closeOnEscape: false,
		dialogClass: 'sa_userLoginDialog' });

	var buttonList = 	{ 
		Login: function(){
			if (sa_attemptLogin()) { 
				$(this).dialog('close'); 
			}
		}, 
		Cancel:function() { 
			$(this).dialog('close');	
			// window.back();
		} 
	};
	
	// remove the Cancel button
	function revokeCancel () {
		delete buttonList['Cancel'];
	}

	// fix the field alignments
	function alignForSafeAjax () {
		$('#sa_loginFields').find('div:first').removeClass('sa_css-right');
	}

	// open the userLogin dialog
	function openDialog () { 
		$('.sa_needsClear').val('');
		$('#sa_userLogin').dialog('option', { width: 400, buttons: buttonList }).dialog('open');

		// add the "forgotton" links to the buttonPane
		var theForgotten = $('<div class="sa_forgotLinks-div">');
		$(theForgotten).append($('<div class="sa_forgotLinks"><a id="sa_forgotUser-link" href="">forgot login/username</a></div>'));
		$(theForgotten).append($('<div class="sa_forgotLinks"><a id="sa_forgotPass-link" href="">forgot password</a></div>'));
		$('.sa_userLoginDialog').find('.ui-dialog-buttonpane').prepend(theForgotten);

		// handle clicks
		$('#sa_forgotPass-link').click(function(ig){ sa_forgotPassword.openDialog(); $(this).dialog('close'); });
		$('#sa_forgotUser-link').click(function(ig){ sa_forgotLogin.openDialog(); $(this).dialog('close'); });
	};

	// exposure
	return { openDialog: openDialog, revokeCancel: revokeCancel, alignForSafeAjax: alignForSafeAjax };
}();

function sa_attemptLogin (self) {
	$('#sa_userLogin-result').empty();
	// login is done via saConnector, parm names are by sacv5
	var dataItems = {'sacv_cookieTest':1};
	dataItems['sa_UName'] = $('#sa_userLogin-Username').val();
	dataItems['sa_PWord'] = $('#sa_userLogin-Password').val();
	var data = sacv5.syncRequest(dataItems); 
	// console.log('sa_attemptLogin: '+JSON.stringify(data));
	if (! data.authOK) {
		$('#sa_userLogin-result').text('either the username or the password was incorrect.');
		return false;
	}
	return true;
};

// ---------------------------------------------------------------------------------------------------------------
var sa_forgotLogin = function() { // forgotLogin dialog ------ forgotLogin dialog ------ forgotLogin dialog ------ 

	// create the dialog
	$('#sa_forgotLogin').dialog({ title: "Forgot Login", 
		autoOpen:false,  modal:true, resizeable: false, 
		dialogClass: 'sa_forgotLoginDialog' });
		
	// open the forgotLogin dialog
	function openDialog () { 
		$('.sa_needsClear').val('');
		$('#sa_forgotLogin').dialog('option', { width: 400, buttons: { 
			Request: function(){
				if (sa_attemptForgotLogin()) { 
					$(this).dialog('close'); 
				} 
			}, 
			Cancel:function() { 
				$(this).dialog('close');	
				// window.back();
			} 
		}
		}).dialog('open');
	};

	// exposure
	return { openDialog: openDialog };
}();

function sa_attemptForgotLogin (self) {
	$('#sa_forgotLogin-result').empty();
	var dataItems = {'forgotUser':1};
	dataItems['newEMail'] = $('#sa_forgotLogin-EMail').val();
	var data = sacv5.syncRequest(dataItems);
	if (data.authErr) {
		// console.log('data.authErr: '+data.authErr);
		$('#sa_forgotLogin-result').text(data.authErr);
		return false;
	}
	return true;
};

// ---------------------------------------------------------------------------------------------------------------------------
var sa_forgotPassword = function() { // forgotPassword dialog ------ forgotPassword dialog ------ forgotPassword dialog ------ 

	// create the dialog
	$('#sa_forgotPassword').dialog({ title: "First Password", 
		autoOpen:false,  modal:true, resizeable: false, 
		dialogClass: 'sa_forgotPasswordDialog' });
		
	// open the forgotPassword dialog
	function openDialog () { 
		$('.sa_needsClear').val('');
		$('#sa_forgotPassword').dialog('option', { width: 400, buttons: { 
			Request: function(){
				if (sa_attemptForgotPassword()) { 
					$(this).dialog('close'); 
				}
			}, 
			Cancel:function() { 
				$(this).dialog('close');	
				// window.back();
			} 
		}
		}).dialog('open');
	};

	// exposure
	return { openDialog: openDialog };
}();

function sa_attemptForgotPassword (self) {
	$('#sa_forgotPassword-result').empty();
	var dataItems = {'forgotPass':1};
	dataItems['newEMail'] = $('#sa_forgotPassword-EMail').val();
	dataItems['newUsername'] = $('#sa_forgotPassword-Username').val();
	var data = sacv5.syncRequest(dataItems);
	if (data.authErr) {
		$('#sa_forgotPassword-result').text(data.authErr);
		return false;
	}
	return true;
};

// -----------------------------------------------------------------------------------------------------------------------------------
var sa_userRegistration = function() { // userRegistration dialog ------ userRegistration dialog ------ userRegistration dialog ------ 

	// create the dialog
	$('#sa_registerNewUser').dialog({ title: "Register New User",
		autoOpen:false,  modal:true, resizeable: false, 
		dialogClass: 'sa_registerNewUserDialog' });

	// open the userRegistration dialog
	function openDialog () {
		$('.sa_needsClear').val('');
		$('#sa_registerNewUser').dialog('option', { width: 400, buttons: { 
			Register: function(){
				if (sa_attemptUserRegistration()) { 
					$(this).dialog('close'); 
				}
			}, 
			Cancel:function() { 
				 $(this).dialog('close');	
			} 
		}
		}).dialog('open');
	};

	// exposure
	return { openDialog: openDialog };
}();

function sa_attemptUserRegistration (self) {
	$('#sa_registerNewUser-result').empty();
	var dataItems = {'regUser':1};
	dataItems['newEMail'] = $('#sa_register-EMail').val();
	dataItems['newUsername'] = $('#sa_register-Username').val();
	var data = sacv5.syncRequest(dataItems);
	if (data.authErr) {
		$('#sa_registerNewUser-result').text(data.authErr);
		return false;
	}
	return true;
};

// -----------------------------------------------------------------------------------------------------------------------
var sa_passwordReset = function() { // passwordReset dialog ------ passwordReset dialog ------ passwordReset dialog ------ 

	// create the dialog
	$('#sa_resetPassword').dialog({ title: "Set New Password", 
		autoOpen:false,  modal:true, resizeable: false, 
		dialogClass: 'sa_resetPasswordDialog' });
		
	// handle keyUps to ensure that the two inputs are equal
	$('#sa_resetPassword-Password,#resetPassword-Confirm').keyup(function(evt){
		$('.sa_resetPasswordDialog button:contains("Submit")').button('disable');
		var pwd = $('#sa_resetPassword-Password').val();
		if ((pwd.length) && (pwd == $('#sa_resetPassword-Confirm').val())) {
			$('.sa_resetPasswordDialog button:contains("Submit")').button('enable');
		}
	});

	// open the passwordReset dialog
	function openDialog () { 
		$('.sa_needsClear').val('');
		$('#sa_resetPassword-Hash').val($.url.param("pwdResetHash"));
		// console.log($('#sa_resetPassword').length);
		$('#sa_resetPassword').dialog('option', { width: 400, buttons: { 
			Submit: function(){
				if (sa_attemptPasswordReset()) { 
					$(this).dialog('close'); 
					window.location = "http://" + window.location.hostname + window.location.pathname;
				}
			}, 
			Cancel:function() { 
				 $(this).dialog('close');	
				window.location = "http://" + window.location.hostname + window.location.pathname;
			} 
		}
		}).dialog('open');
		$('.sa_resetPasswordDialog button:contains("Reset")').button('disable');
	};

	// exposure
	return { openDialog: openDialog };
}();

function sa_attemptPasswordReset (self) {
	$('#sa_resetPassword-result').empty();
	var dataItems = {'passwordResetByHash':1};
	dataItems['passwordHash'] = $('#sa_resetPassword-Hash').val();
	dataItems['newPassword'] = $('#sa_resetPassword-Password').val();
	var data = sacv5.syncRequest(dataItems);
	if (data.authErr) {
		$('#sa_resetPassword-result').text(data.authErr);
		return false;
	}
	return true;
};

// ---------------------------------------------------------------------------------------------------------------------------
var sa_Message = function() { // Message dialog ------ Message dialog ------ Message dialog ------ 
	
	// create the dialog
	$('#sa_Message').dialog({ 
		autoOpen:false,  modal:true, resizeable: false, title: "Alert", 
		dialogClass: 'sa_MessageDialog' });
		
	// set a title
	function setTitle ( _title ) {
		$('#sa_Message').dialog('option', { title: _title });
	}

	// set the message
	function setMessage ( _msg ) {
		$('.sa_Message-explain').text(_msg);
	}
		
	// open the Message dialog
	function openDialog () { 
		$('#sa_Message').dialog('option', { width: 300, 
			buttons: { OK:function() { $(this).dialog('close');	}  }
		}).dialog('open');
	};

	// exposure
	return { openDialog: openDialog, setTitle: setTitle, setMessage: setMessage };
	
}();

// done

