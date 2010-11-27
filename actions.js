
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

	// finding users

	$("#findByEMail").click(function(){
		$(".aResult").empty();
		sacv5.asyncRequest({'findViaEmail':$("#findBy").val()},function(data){
			$("#auth-result").html(JSON.stringify(data.auth));
			$("#auth-err").html(data.authErr);
		});
	});

	$("#findByUsername").click(function(){
		$(".aResult").empty();
		sacv5.asyncRequest({'findViaUsername':$("#findBy").val()},function(data){
			$("#auth-result").html(JSON.stringify(data.auth));
			$("#auth-err").html(data.authErr);
		});
	});

	$("#findByUserId").click(function(){
		$(".aResult").empty();
		sacv5.asyncRequest({'findViaUserId':$("#findBy").val()},function(data){
			$("#auth-result").html(JSON.stringify(data.auth));
			$("#auth-err").html(data.authErr);
		});
	});

	$("#findByPwdResetHash").click(function(){
		$(".aResult").empty();
		sacv5.asyncRequest({'findViaPwdResetHash':$("#findBy").val()},function(data){
			$("#auth-result").html(JSON.stringify(data.auth));
			$("#auth-err").html(data.authErr);
		});
	});

	$("#findByPersistence").click(function(){
		$(".aResult").empty();
		sacv5.asyncRequest({'findViaPersistence':$("#findBy").val()},function(data){
			$("#auth-result").html(JSON.stringify(data.auth));
			$("#auth-err").html(data.authErr);
		});
	});

	// updating users

	$("#updateIndexed").click(function(){
		$(".aResult").empty();
		sacv5.asyncRequest({'updateValue':$("#update").val(), 'updateIndexed':$("#updateKey").val(), 'userId':$("#update-UserId").val(), 'updatorId':$("#updatorId").val()},function(data){
			$("#auth-result").html(JSON.stringify(data.auth));
			$("#auth-err").html(data.authErr);
		});
	});

	$("#updateArbitrary").click(function(){
		$(".aResult").empty();
		sacv5.asyncRequest({'updateValue':$("#update").val(), 'updateArbitrary':$("#updateKey").val(), 'userId':$("#update-UserId").val(), 'updatorId':$("#updatorId").val()},function(data){
			$("#auth-result").html(JSON.stringify(data.auth));
			$("#auth-err").html(data.authErr);
		});
	});

	// get password reset hash

	$("#getPasswordResetHash").click(function(){
		$(".aResult").empty();
		sacv5.asyncRequest({'getPasswordResetHash':$("#get-UserId").val()},function(data){
			$("#auth-result").html(JSON.stringify(data.auth));
			$("#auth-err").html(data.authErr);
		});
	});

	// delete user

	$("#deleteUser").click(function(){
		$(".aResult").empty();
		sacv5.asyncRequest({'deleteUser':1,'deletorId':$("#deletorId").val(), 'deleteUserId':$("#delete-UserId").val()},function(data){
			$("#auth-result").html(JSON.stringify(data.auth));
			$("#auth-err").html(data.authErr);
		});
	});

	// create user

	$("#createUser").click(function(){
		$(".aResult").empty();
		sacv5.asyncRequest({'createUser':1,
		'newEMail':$("#newEMail").val(),
		'newUsername':$("#newUsername").val(),
		'newPassword':$("#newPassword").val(),
		'passwordSha1':$("#sha1:checked").size(),
		'newAccess':$("#newAccess").val(),
		'creatorId':$("#creatorId").val()},function(data){
			$("#auth-result").html(JSON.stringify(data.auth));
			$("#auth-err").html(data.authErr);
		});
	});

});
