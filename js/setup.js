/*****************************************************
 * Projet : Okovision - Supervision chaudiere OeKofen
 * Auteur : Stawen Dronek
 * Utilisation commerciale interdite sans mon accord
 ******************************************************/
/* global lang, $ */
$(document).ready(function() {

	$("#bt_testConnection").click(function() {
		
		var tab = {
			db_adress: $('#db_adress').val(),
			db_user: $('#db_user').val(),
			db_password: $('#db_password').val(),
			db_schema: $('#db_schema').val()
		};

		$.ajax({
			url: 'setup.php?type=connect',
			type: 'POST',
			data: $.param(tab),
			async: false,
			success: function(a) {
				if (a.response) {
					$('#DB_validation').show();
				}
				else {
					$('#DB_validation').hide();
					$.growlErreur(lang.error.bddFail);
				}

			}
		});

		//	}		

	});
	
	document.body.addEventListener('change', function (e) {
		let target = e.target;
		switch (target.id) {
			case 'oko_typeconnect_ip':
				$("#form-ip").show();
				break;
			case 'oko_typeconnect_usb':
				$("#form-ip").hide();
				break;
			case 'oko_loadingmode_silo':
				$("#form-silo-details").show();
				break;
			case 'oko_loadingmode_bags':
				$("#form-silo-details").hide();
				break;
		}
	});

	$("#bt_install").click(function() {

		var typeConnect = $('input[name=oko_typeconnect]:checked').val();
		var ipOK = $('#ip_ok').val();

		if ( (typeConnect == 0 || ipOK == "true") && $('#DB_validation').is(":visible") ) {
			var tab = {
				db_adress: $('#db_adress').val(),
				db_user: $('#db_user').val(),
				db_password: $('#db_password').val(),
				db_schema: $('#db_schema').val(),
				createDb: $('#createDb').val(),
				oko_ip: $('#oko_ip').val(),
				oko_ip_ok: ipOK,
				param_tcref: $('#param_tcref').val(),
				param_poids_pellet: $('#param_poids_pellet').val(),
				surface_maison: $('#surface_maison').val(),
				oko_typeconnect: typeConnect,
				has_silo: $('input[name=oko_loadingmode]:checked').val(),
				silo_size: $('#oko_silo_size').val(),
				ashtray: $('#oko_ashtray').val(),
				send_to_web: $('#send_to_web').val(),
				lang: $('input[name=oko_language]:checked').val()
			};
			$.ajax({
				url: 'setup.php?type=install',
				type: 'POST',
				data: $.param(tab),
				async: false,
				success: function (a) {
					if(a.csv) {
						window.location.replace("adminMatrix.php?csv=" + a.csv);
					} else {
					//window.location.replace("index.php");
						window.location.replace("index.php?setup=1");
					}
				}
			});
		} else {
			if (typeConnect != 0 && ipOK != "true") {
				$.growlErreur('Please validate Boiler connection');
			}
			if (!$('#DB_validation').is(":visible")) {
				$.growlErreur('Please validate Database connection');
			}
		}
		


	});

	$('#test_oko_ip').click(function() {

		var ip = $('#oko_ip').val();

		var tab = {
			ip: $('#oko_ip').val()
		};

		$.ajax({
			url: 'setup.php?type=ip',
			type: 'POST',
			data: $.param(tab),
			async: false,
			success: function(a) {
				if (a.response) {
					$('#ip_ok').val("true");
					$('#ip_validation').show();
				}
				else {
					$.growlErreur('Communication Error');
					$('#ip_ok').val("false");
					$('#ip_validation').hide();
				}

			}
		});
	});

});