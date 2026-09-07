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
				lang: $('input[name=oko_language]:checked').val(),
				analytics_enabled: $('#analytics_enabled').is(':checked') ? 1 : 0
			};
			$.ajax({
				url: 'setup.php?type=install',
				type: 'POST',
				data: $.param(tab),
				async: false,
				success: function (a) {
					window.location.replace("index.php?setup=1");
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
		var $bt = $(this);
		var tab = {
			ip: $('#oko_ip').val()
		};

		$bt.prop('disabled', true).text('Testing…');

		$.ajax({
			url: 'setup.php?type=ip',
			type: 'POST',
			data: $.param(tab),
			async: false,
			success: function(a) {
				if (a.status === 'ok') {
					$('#ip_ok').val("true");
					$('#ip_validation').show();
				}
				else {
					$('#ip_ok').val("false");
					$('#ip_validation').hide();

					if (a.status === 'no_csv') {
						$.growlErreur('Boiler page /logfiles/pelletronic responds but contains no CSV file');
					} else if (a.status === 'no_logfiles') {
						$.growlErreur('This address responds but /logfiles/pelletronic was not found: this is probably not the boiler');
					} else if (a.status === 'empty_ip') {
						$.growlErreur('Please enter the boiler IP address');
					} else {
						$.growlErreur('Boiler is not responding at this address');
					}
				}
			},
			complete: function() {
				$bt.prop('disabled', false).text('Validate Boiler Connection');
			}
		});
	});

});