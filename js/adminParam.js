/*****************************************************
 * Projet : Okovision - Supervision chaudiere OeKofen
 * Auteur : Stawen Dronek
 * Utilisation commerciale interdite sans mon accord
 ******************************************************/
/* global lang, $ */

$(document).ready(function() {
	var $bt = $(this);
	var ip = $('#oko_ip').val();

	$bt.prop('disabled', true);
	$('#url_csv').html('');

	$.api('GET', 'admin.testIp', {
		ip: ip
	}).done(function(json) {

		if (json.status === 'ok') {
			$.growlValidate(lang.valid.communication);
		} else if (json.status === 'no_csv') {
			$.growlWarning(lang.error.boilerNoCsv);
		} else if (json.status === 'no_logfiles') {
			$.growlWarning(lang.error.boilerLogfilesNotFound);
		} else if (json.status === 'empty_ip') {
			$.growlWarning(lang.error.ipEmpty);
		} else {
			$.growlWarning(lang.error.ipNotPing);
		}

		// le lien reste utile pour diagnostiquer quand la page repond sans csv
		if (json.url && (json.status === 'ok' || json.status === 'no_csv')) {
			$('#url_csv').append('<a target="_blank" href="' + json.url + '">' + lang.text.seeFileOnboiler + '</a>');
		}
	}).always(function() {
		$bt.prop('disabled', false);
	});

	function syncDeleteBtn() {
		const disabled = $('#analytics_enabled').is(':checked');
		$('#bt_delete_install_id').prop('disabled', disabled);
	}

	syncDeleteBtn();
	$('#analytics_enabled').on('change', function () {
		syncDeleteBtn();
	});

	$('#bt_delete_install_id').on('click', function () {
		if (!confirm("Supprimer l'install_id local et les éventuels tokens ?")) return;
		
		$.api('GET', 'admin.analyticsDeleteLocalId').done(function (json) {
			if (json && json.response) {
			$.growlValidate('Install ID supprimé localement.');
			// Si tu stockes quelque chose côté navigateur (rare), nettoie-le ici :
			try { localStorage.removeItem('okv_install_id'); } catch(e) {}
			} else {
			$.growlErreur(json && json.message ? json.message : 'Échec de la suppression.');
			}
		})
		.error(function () {
			$.growlErreur('Erreur réseau.');
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
