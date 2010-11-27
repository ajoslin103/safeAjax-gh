/*

SafeAjax, by allen joslin

I wrote this [originally as a] plugin to password protect web pages/sites and to safeguard my ajax 
communications and sql access. It was originally based upon chris shifletts article: the truth about 
sessions 

This package provides a server-based connector for the plugin which handles request validation and provides
a drop-in architecture to assist in organizing your server-side code, all requests will be passed to all
modules each of which can add their contributions to the resulting json structure that is returned

changelog, see allen @joslin .net for change requests or support
11/24/10 - vers 5: rewritten to conform to CommonJS module spec
11/19/10 - vers 4.5.1: add support for jQueryUI, in login dialogs and exceptions
09/10/10 - vers 4.5: bugfix, clear login dialog after login attempt
08/22/10 - vers 4: adding Cassandra and MongoDB authentications, large de-confusion effort. NOTE: syncRequest() deprecates syncDBMS() & asyncRequest() deprecates safeDBMS()
02/14/10 - vers 3: nearly done !! syncRequest only supports login on page load so far
01/30/10 - vers 3: rewriting w/resig's help (ninjascript !) as a library rather than a plugin
01/14/10 - vers 3: internal&external login dialog support options
12/14/09 - vers 3: cookie duration as strings, auto cookie domain from php backend
11/01/09 - vers 3: added synchronous ajax support & failure callbacks
10/01/09 - vers 3: rewriting & documenting, WILL NOT be backwards compatible
09/27/09 - vers 2.3: combine reader & writer in connector
07/01/09 - vers 2.2: extract html to dialogs.html
06/14/09 - vers 2.1: modifications to support IE
05/20/09 - vers 2.0: rewrite/refactor, encapsulate connector, return JSON structure
03/08/09 - vers 1.0: code collection from dev/test/debug

LoginDialog, by allen joslin

I'm working on decoupling the login dialog support from the login actions as provided by SafeAjax

11/11/10 - vers 1.0: using jQueryUI

*/

var SafeAjax = SafeAjax || {};
SafeAjax.safeAjax_v5 = (function() {

	var exports = {};

	exports.init = function (options) {

		defaults = {
			ajaxConnector: "./lib/safeAjax/saConnector.php"		  //	backend connector script
			,cookieName: "sacv4"								  //	cookie setting, default
			,duration: "24h"									  //	cookie setting, default
			,debug: false										  //	throw logic points at the console
			,notify: true										  //	throw panic/failure alerts at the user
			,type: "GET"										  //	ajax call settings, default
			,error: ""											  //	our last known error
			,ready: true										  //	our state
			,loginFirst: false									  //	safeAjax option to login on page load
			,handleLogin: false									  //	safeAjax option to use our own login dialog
			,loginReady: false									  //	state of login codebase
			,loginMissing: false								  //	state of login codebase
			,unFieldName: "sa_UName"							  //	login option
			,pwFieldName: "sa_PWord"							  //	login option
			,sha1Logins: true									  //	login option
			,json_js: "./lib/safeAjax/js/json2.js"		 		  //	working resource
			,sha1_js: "./lib/safeAjax/js/jquery.sha1.js"		  //	login resource
			,jqModal_js: "./lib/safeAjax/js/jqModal.js"			  //	login resource
			,jqModal_css: "./lib/safeAjax/js/jqModal.css"		  //	login resource
			,useJQueryUI: true									  //	use jQuery UI dialogs and routines - avoids jqModal
		};

		var sets = $.extend(defaults,options||{});
		exports.settings = sets; // public access
		
		if (sets.useJQueryUI) { // load the login dialog
			$.ajax({async: false, url: './lib/safeAjax/LoginDialog/sacv5/login.js', dataType: 'script'});
		}

		if (! sets.JSON) { // load JSON if not avail already
			$.ajax({async: false, url: sets.json_js, dataType: "script"});
		}

		if ( sets.loginFirst ) { // protecting from the 1st moment
			if ( sets.handleLogin ) { // using our own login dialog
				firstLogin(); // block this page until login is confirmed
			}
		}

		/*********** attribution ***********/
		(function () {
			var attribs = {
				"John Resig": "for jQuery, http://http://jquery.org/history",
				"Douglas Crockford": "for JSON, http://json.org",
				"Muhammad Hussein Fattahizadeh": "for sha1, http://www.semnanweb.com/jquery-plugin/sha1.html",
				"Brice Burgess": "for jqModal, http://dev.iceburg.net/jquery/jqModal/",
				"Klaus Hartl": "for cookie plugin, http://stilbuero.de/jquery/cookie/",
				"the IETF": "for request UUID's, http://www.ietf.org/rfc/rfc4122.txt"
			};
			debug("SafeAjax credits: this code would not have been possible without the hard work of the following developers: " +JSON.stringify(attribs)); 
			})();

			/*********** local debgging/notification support ***********/
			function debug($obj) { if (sets.debug && window.console && window.console.log) { window.console.log($obj); } }
			function log($obj) { if (window.console && window.console.log) { window.console.log($obj); } }
			function dir($obj) { if (window.console && window.console.dir) { window.console.dir($obj); } }
			function notify($obj) { sets.error = $obj; if (sets.notify) { alert($obj); } }

			function fatal($obj) { 
				// something went really wrong
				var json = { safeAjax: false, authRequires: $obj };
				log("FATAL: "+$obj); notify("[in 'safeAjax'] "+$obj); 
				return json;
			}

			/*********** test for our cookie - with blocking ***********/
			function tasteCookie(){ 

				var callOpts = prep4Call({ sacv_cookieTest: true }); 
				callOpts.async = false; // this call blocks

				debug("tasteCookie call:"+JSON.stringify(callOpts));
				var json = $.ajax(callOpts); 
				if (json.status == 404) { 
					fatal("the backend is missing or broken! (in tasteCookie)");
					return false; 
				}
				try { json = JSON.parse(json.responseText); } 
				catch (ignore){} // will expose result here next anyway
				debug("tasteCookie return:"+JSON.stringify(json));

				return json.authOK;
			}

			/*********** run the login with blocking ***********/
			function firstLogin(){ 

				// if we already have our cookie then let's skip the login
				if (tasteCookie()) { 
					debug("firstLogin(tasteCookie()): true");
					return; 
				}

				if (! prep4Logins()) {
					fatal("not ready to handle a login as requested (in firstLogin)");
					return;
				}

				// safety first
				try {

					if (sets.useJQueryUI) {
						sa_userLogin.alignForSafeAjax();
						sa_userLogin.revokeCancel();
						sa_userLogin.openDialog(); 

					} else {

						$("#sa_LoginBtn").live('click',function(){

							var unSha1Tag = sets.unFieldName; var unSha1Val = (sets.sha1Logins)? $.sha1($("#sa_UName").val()): $("#sa_UName").val(); $("#sa_UName").empty();
							var pwSha1Tag = sets.pwFieldName; var pwSha1Val = (sets.sha1Logins)? $.sha1($("#sa_PWord").val()): $("#sa_PWord").val(); $("#sa_PWord").empty();
							var requestObj = { sacv_cookieTest: true }; requestObj[unSha1Tag] = unSha1Val; requestObj[pwSha1Tag] = pwSha1Val;
							var callOpts = prep4Call(requestObj); callOpts.async = false; // this call blocks

							debug("firstLogin(makeCookie) call:"+JSON.stringify(callOpts));
							var json = $.ajax(callOpts); 
							if (json.status == 404) {
								fatal("backend is missing or broken! (in firstLogin)");
								return; 
							}
							try { json = JSON.parse(json.responseText); } catch (ignored) {}
							debug("firstLogin(makeCookie) return:"+JSON.stringify(json));

							if (json.safeAjaxError) { log(json.safeAjaxError); }
							if (json.authOK) { 
								debug("firstLogin(makeCookie) call success");
								$("#sa_Login").jqmHide(); 
							} else {
								debug("firstLogin(makeCookie) call failure:"+JSON.stringify(requestObj));
							}
						});
						$("#sa_Login").jqmShow();
					}
				}
				catch (commError) {
					fatal("commError:"+commError+ " (in firstLogin)");
				}
			}

			/*********** run the login without blocking ***********/
			function loginUser(givenCallOpts,callbackPass,callbackFail){ 

				// if we already have our cookie then let's skip the login
				if (tasteCookie()) { 
					debug("loginUser(tasteCookie()): true");
					return runCallbacks(givenCallOpts.data,callbackPass,callbackFail);
				}

				if (! prep4Logins()) {
					fatal("not ready to handle a login as requested (in loginUser)");
					return;
				}

				// safety first
				try {

					if (sets.useJQueryUI) {
						sa_userLogin.alignForSafeAjax();
						sa_userLogin.revokeCancel();
						sa_userLogin.openDialog(); 

					} else {

						$("#sa_LoginBtn").live('click',function(){

							var unSha1Tag = sets.unFieldName; var unSha1Val = (sets.sha1Logins)? $.sha1($("#sa_UName").val()): $("#sa_UName").val(); $("#sa_UName").empty();
							var pwSha1Tag = sets.pwFieldName; var pwSha1Val = (sets.sha1Logins)? $.sha1($("#sa_PWord").val()): $("#sa_PWord").val(); $("#sa_PWord").empty();
							var requestObj = { sacv_cookieTest: true }; requestObj[unSha1Tag] = unSha1Val; requestObj[pwSha1Tag] = pwSha1Val;
							var loginOpts = prep4Call(requestObj); loginOpts.async = false; // this call blocks

							debug("loginUser(makeCookie) call:"+JSON.stringify(loginOpts));
							var json = $.ajax(loginOpts); 
							if (json.status == 404) { 
								fatal("backend is missing or broken! (in loginUser)");
								return false; 
							}
							try { json = JSON.parse(json.responseText); } catch (ignored) {}
							debug("loginUser(makeCookie) return:"+JSON.stringify(json));

							if (json.safeAjaxError) { log(json.safeAjaxError); }
							if (json.authOK) { 
								debug("loginUser(makeCookie) call success");
								$("#sa_Login").jqmHide(); 

								// re-run the original call with blocking
								givenCallOpts.async = false; $.ajax(givenCallOpts);
								return runCallbacks(givenCallOpts.data,callbackPass,callbackFail);

							} else {
								debug("loginUser(makeCookie) call failure:"+JSON.stringify(requestObj));
							}
						});

						$("#sa_Login").jqmShow();
					}
				}
				catch (commError) {
					fatal("commError:"+commError+ " (in loginUser)");
				}
			}

			/*********** make a [blocking] call for logout to the backend ***********/
			exports.syncLogout = function() {  

				var callOpts = prep4Call({ sacv_cookieVoid: true }); 
				callOpts.async = false; // prep for a sync call

				var json = $.ajax(callOpts); 
				if (json.status == 404) { // call w/check for error
					fatal("the backend is missing or broken! (in syncLogout)");
					return false; 
				}
				try { json = JSON.parse(json.responseText); } 
				catch (ignore){} // attempt a parse
				debug("syncLogout return:"+JSON.stringify(json));

				return json;
			};

			/*********** make a [blocking] call for logout to the backend ***********/
			exports.syncLoggedIn = function() {  

				var callOpts = prep4Call({ sacv_cookieGood: true }); 
				callOpts.async = false; // prep for a sync call

				var json = $.ajax(callOpts); 
				if (json.status == 404) { // call w/check for error
					fatal("the backend is missing or broken! (in syncLogout)");
					return false; 
				}
				try { json = JSON.parse(json.responseText); } 
				catch (ignore){} // attempt a parse
				debug("syncLoggedIn return:"+JSON.stringify(json));

				return json;
			};

			/*********** make a [blocking] call for re-login to the backend ***********/
			exports.syncLogin = function(username,password) {  

				exports.syncLogout(); // ensure we are not us anymore

				var callOpts = prep4Call({ sacv_cookieTest: true }); 
				callOpts.async = false; // prep for a sync call

				// load the un/pw into the call options, sha1 based on settings
				callOpts.data[sets.unFieldName] = (sets.sha1Logins)? $.sha1(username): username;
				callOpts.data[sets.pwFieldName] = (sets.sha1Logins)? $.sha1(password): password;

				var json = $.ajax(callOpts); 
				if (json.status == 404) {  // call w/check for error
					fatal("the backend is missing or broken! (in syncLogin)");
					return false; 
				}
				try { json = JSON.parse(json.responseText); } 
				catch (ignore){} // attempt a parse
				debug("syncLogin return:"+JSON.stringify(json));

				return json;
			};

			/*********** push a call to the backend with full blocking ***********/
			exports.syncRequest = function(requestObj){  

				// safety first
				try {

					var callOpts = prep4Call(requestObj); 
					callOpts.async = false; // this call blocks

					debug("syncRequest call:"+JSON.stringify(callOpts));
					var json = $.ajax(callOpts); 
					if (json.status == 404) {
						// the call failed, construct a return object to our consistancy
						return fatal("backend is missing or broken! (in syncRequest)");
					}
					try { json = JSON.parse(json.responseText); } 
					catch (e1) { 
						// the call failed, construct a return object to our consistancy
						debug("ERROR: bad JSON return from safeAjax.syncRequest: "+e1.name+":"+e1.message +" -- "+json.responseText ); 
						json = { safeAjax: false, authRequires: "The JSON returned from the server was un-parse-able (in syncRequest)" };
						return fatal(json.authRequires);
					}
					debug("syncRequest return:"+JSON.stringify(json));

					if (json.freshCookie) { // if we just passed a security check then we need to re-submit and re-parse
						json = $.ajax(callOpts); 
						try { json = JSON.parse(json.responseText); } catch (ignored) {}
						debug("SafeAjax: syncRequest freshCookie re-return: "+JSON.stringify(json));
					}

					if (json.safeAjaxError) { log(json.safeAjaxError); }
					if (! json.authOK) {
						if ( sets.handleLogin ) { // we would use our own login dialog, but this is a sync request...
							throw("You have insufficient privileges to execute the requested action.");
							// 	return fatal("sorry, safeAjax doesn't support a blocking login dialog after page load yet...");
						}
					}

					// we made it !!
					return json;
				}

				catch (commError) {
					if (! json.sacv_cookieTest) {
						if (sets.useJQueryUI) {
							sa_Message.setTitle("Privileges Error");
							sa_Message.setMessage(commError);
							sa_Message.openDialog();
						}
						throw(commError);
						// debug('commError: '+commError);
					} else {
						return json; // login result needs answer
					}
				}
			};

			// ********** push a call to the backend without blocking ***********
			exports.asyncRequest = function(requestObj,callbackPass,callbackFail){ 

				if ((callbackPass == undefined) && (callbackFail == undefined)) { 
					fatal("the safeAjax plugin can't be used this way, you can't call asyncRequest without callbacks -- use syncRequest");
					return;
				}

				// safety first
				try {

					var callOpts = prep4Call(requestObj); 
					callOpts.complete = function(json, result){

						// safety first
						try {

							if (json.status == 404) {
								// the call failed, construct a return object to our consistancy
								var jsonTmp = fatal("backend is missing or broken! (in asyncRequest)");
								return runCallbacks(jsonTmp,callbackPass,callbackFail);
							}

							// parse the results
							try { json = JSON.parse(json.responseText); } 
							catch (e1) { 
								// the call failed, construct a return object to our consistancy
								debug("ERROR: bad JSON return from safeAjax.asyncRequest: "+e1.name+":"+e1.message +" -- "+json.responseText ); 
								notify("the 'safeAjax' backend is returning a malformed data block (in asyncRequest)");
								json = { safeAjax: false, authRequires: "The JSON returned from the server was un-parse-able." };
								return runCallbacks(json,callbackPass,callbackFail);
							}
							debug("asyncRequest return:"+JSON.stringify(json));

							if (json.freshCookie) { // if we just passed a security check then we should re-submit and re-parse
								// var callOpts = $.data(document.body,json.uuid); // remember us
								callOpts.async = false; // we need to block on this call
								json = $.ajax(callOpts); 
								try { json = JSON.parse(json.responseText); } catch (ignored) {}
								if (sets.debug) { console.log("SafeAjax:asyncRequest freshCookie re-return: "+JSON.stringify(json)); }
							}
							// $.removeData(document.body,json.uuid); // prevent leaks

							if (json.safeAjaxError) { log(json.safeAjaxError); }
							if (! json.authOK) {
								if ( sets.handleLogin ) { // we're using our own login dialog
								return loginUser(callOpts,callbackPass,callbackFail); // run the login and then re-run our call
							}
						}

						runCallbacks(json,callbackPass,callbackFail);
					}

					catch (completionError) {
						return fatal("completionError:"+completionError+ " (in asyncRequest)");
					}

				};

				// $.data(document.body,callOpts.data.uuid,callOpts); // store our calling dataBlock
				debug("asyncRequest call:"+JSON.stringify(callOpts));
				$.ajax(callOpts); 
			}

			catch (commError) {
				return fatal("commError:"+commError+ " (in asyncRequest)");
			}
		};

		/*********** add items that the backend is expecting from our calls, return an ajax(opts) ***********/
		function runCallbacks(json,callbackPass,callbackFail){ 

			try {

				if (callbackFail != undefined) {

					if (json.authOK) {
						debug("safeAjax calling sucess function");
						(callbackPass || $.noop)( json );

					} else {

						debug("safeAjax calling failure function"); 
						(callbackFail || $.noop)( json );
					}

				} else {

					debug("safeAjax calling sucess function"); 
					(callbackPass || $.noop)( json );
				}
			}

			catch (callbackError) {
				return fatal("callbackError:"+callbackError+ " (in runCallbacks)");
			}
		}

		/*********** add items that the backend is expecting from our calls, return an ajax(opts) ***********/
		function prep4Call(clientItems){ 

			var ajaxOpts = {
				async: true,
				dataType: "json",
				data: clientItems ,
				type: sets.type,
				url: sets.ajaxConnector
			};

			// add our own options to the transmitted data items
			ajaxOpts.data.unFieldName = sets.unFieldName; 
			ajaxOpts.data.pwFieldName = sets.pwFieldName; 
			ajaxOpts.data.sha1Logins = sets.sha1Logins; 
			ajaxOpts.data.sa_Duration = sets.duration; 
			ajaxOpts.data.sa_Debug = sets.debug; 
			ajaxOpts.data.uuid = createUUID();

			var now = new Date(); // add local time and offset
			ajaxOpts.data.sa_localOffset = now.getTimezoneOffset();
			ajaxOpts.data.sa_localTime = now.toUTCString();

			return ajaxOpts;
		}

		/*********** build the login dialog, requires external resources -- see the credits for more info ***********/
		function prep4Logins(){

			if (sets.useJQueryUI) {

				sets.loginReady = true;

			} else {

				if (sets.loginReady) { return true; }
				if (sets.loginMissing) { return false; }

				$('<link rel="stylesheet" type="text/css" href="'+sets.jqModal_css+'" >').appendTo("head");

				// load the code and resources that we need to do dialogs -- see credits()
				sets.loginMissing &= ($.ajax({async: false, url: sets.jqModal_js, dataType: "script"}).status != 200);
				if (sets.loginMissing) { sets.error += "could not load: "+sets.jqModal_js; }
				sets.loginMissing &= ($.ajax({async: false, url: sets.sha1_js, dataType: "script"}).status != 200);
				if (sets.loginMissing) { sets.error += "could not load: "+sets.sha1_js; }

				if (! sets.loginMissing) {
					// TODO -- accept this from an html fragment
					// resources are ready -- create the login dialog
					if (! $("#SafeAjaxDiv").length) { $("<div id='SafeAjaxDiv'>").appendTo("body"); }
					$("<div id='SafeAjaxDialogs'>").appendTo($("#SafeAjaxDiv"));
					$("<div id='sa_Login' class='jqmWindow'>").css({ display: "none", padding: "5px", width: "250px", border: "2px solid black" }).html($("<h4 id='sa_LoginMsg'>")).appendTo($("#SafeAjaxDialogs"));
					$("<input class='sa_LoginData required' id='sa_UName'>").appendTo($("<p>").html($("<span class='sa_LoginLabel'>").html("username:")).appendTo($("#sa_Login")));
					$("<input type='password' class='sa_LoginData required' id='sa_PWord'>").appendTo($("<p>").html($("<span class='sa_LoginLabel'>").html("password:")).appendTo($("#sa_Login")));
					$("<p>").html($("<button id='sa_LoginBtn'>").html("Login")).appendTo($("#sa_Login"));
					$("<p>").html($("<span class='sa_Instructions'>").html("Enter your username and password to access the requested resource.")).appendTo($("#sa_Login"));
					$("#sa_Login").hide().jqm({ modal: true }); // $("#sa_PWord").val("safeAjax"); // for a defaulted password
				}

				sets.loginReady = (! sets.loginMissing);

			}

			return sets.loginReady;
		}

		/*********** generate a time-based [local] UUIDs to id our ajax calls ***********/
		function createUUID(){
			// http://www.ietf.org/rfc/rfc4122.txt
			var s = [];
			var hexDigits = "0123456789ABCDEF";
			for (var i = 0; i < 32; i++) {
				s[i] = hexDigits.substr(Math.floor(Math.random() * 0x10), 1);
			}
			s[12] = "4";  // bits 12-15 of the time_hi_and_version field to 0010
			s[16] = hexDigits.substr((s[16] & 0x3) | 0x8, 1);  // bits 6-7 of the clock_seq_hi_and_reserved to 01
			var uuid = s.join("");
			return uuid;
		}

		/*********** the queue for requests pending on a login **********
		function loadQueue(callOpts){
			$([ exports.loadQueue ]).queue("ajax", function(){
				exports.catchSyncCall(jsonReturn);
				runQueue();
			});
		}; not yet in use */

		/*********** run ajax queue after a successful login ***********
		function runQueue(){
			$.dequeue( exports.loadQueue, "ajax" );
		}; not yet in use */
		
		return this;

	};

	return exports;

})();

/* NOTE:

when .ajax() can't find something..
{
	"readyState": 4,
	"responseXML": null,
	"onload": null,
	"onerror": null,
	"onloadstart": null,
	"status": 404,
	"onabort": null,
	"upload": {
		"onabort": null,
		"onload": null,
		"onprogress": null,
		"onerror": null,
		"onloadstart": null
	},
	"onreadystatechange": null,
	"onprogress": null,
	"withCredentials": false,
	"responseText": "<content>",
	"statusText": "OK"
}

*/

