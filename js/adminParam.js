/*****************************************************
 * Projet : Okovision - Supervision chaudiere OeKofen
 * Auteur : Stawen Dronek
 * Utilisation commerciale interdite sans mon accord
 ******************************************************/
/* global lang, $ */

$(document).ready(function() {

	/*
	 * Espace Information general
	 */

	$('#test_oko_ip').click(function() {
		var ip = $('#oko_ip').val();

		$.api('GET', 'admin.testIp', {
			ip: ip
		}).done(function(json) {

			if (json.response) {
				$('#url_csv').html("");
				$.growlValidate(lang.valid.communication);
				$('#url_csv').append('<a target="_blank" href="' + json.url + '">' + lang.text.seeFileOnboiler + '</a>');
			}
			else {
				$.growlWarning(lang.error.ipNotPing);
			}
		});
	});
        
	$('#bt_save_infoge').click(function() {

		var tab = {
			oko_ip: $('#oko_ip').val(),
			param_tcref: $('#param_tcref').val(),
			param_poids_pellet: $('#param_poids_pellet').val(),
			surface_maison: $('#surface_maison').val(),
			oko_typeconnect: $('input[name=oko_typeconnect]:checked').val(),
			timezone: $("#timezone").val(),
			send_to_web: 0,
			has_silo: $('input[name=oko_loadingmode]:checked').val(),
			silo_size: $('#oko_silo_size').val(),
			ashtray : $('#oko_ashtray').val(),
			lang : $('input[name=oko_language]:checked').val(),
			analytics_enabled: $('#analytics_enabled').is(':checked') ? 1 : 0
		};
		
		$.api('POST', 'admin.saveInfoGe', tab, false).done(function(json) {
			//console.log(a);
			if (json.response) {
				$.growlValidate(lang.valid.configSave);
			}
			else {
				$.growlWarning(lang.error.configNotSave);
			}
		});

	});

	


});
