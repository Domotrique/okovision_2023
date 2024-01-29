/*****************************************************
 * Projet : Okovision - Supervision chaudiere OeKofen
 * Auteur : Stawen Dronek
 * Utilisation commerciale interdite sans mon accord
 ******************************************************/
/* global lang,$ */

$(document).ready(function() {


	/*
	 * Espace saison
	 */

	function refreshDumps() {
		$.api('GET', 'admin.getDumps').done(function(json) {


			if (json.response) {
				$("#availableDumps > tbody").html("");

				$.each(json.data, function(key, val) {
					//console.log(val);
					//$('#select_graphe').append('<option value="' + val.id + '">' + val.name + '</option>');
					$('#availableDumps > tbody:last').append('<tr id=' + val.dumpname + '> \
				   											<td>' + val.dumpname + '</td>\
				                                        	<td>' + val.date + '</td>\
				                                        	<td> \
																<a class="btn btn-default btn-sm" href="/dumps/' + val.dumpname + '" title="' + lang.text.downloadDump + '"> \
																	<span class="glyphicon glyphicon-download-alt" aria-hidden="true"></span> \
																</a> \
				                                        		<button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#modal_sqldump" title="' + lang.text.renameDump + '"> \
                                                                	<span class="glyphicon glyphicon-edit" aria-hidden="true"></span> \
                                                                </button> \
                                                                <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#confirm-delete" title="' + lang.text.deleteDumpTitle + '"> \
                                                                	<span class="glyphicon glyphicon-trash" aria-hidden="true"></span> \
                                                                </button> \
																<button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#confirm-delete" title="' + lang.text.importDumpTitle + '"> \
																	<span class="glyphicon glyphicon-log-in" aria-hidden="true"></span> \
																</button> \
				                                        	</td>\
				                                        </tr>');
				});


			}
			else {
				$.growlWarning(lang.error.getSeasons);
			}
		});
	}

	function initModalSqldump() {
		$('#modal_sqldump').on('show.bs.modal', function() {
			$(this).find('#typeModal').val("add");
			$(this).find('.modal-title').html(lang.text.newDump);
		});
	}

	function updateDump() {
		$strRegex = new RegExp(/^[a-z0-9\_\-]+$/i);
		$dumpName = $('#modal_sqldump').find('#dumpName').val();
		$oldDumpName = $('#modal_sqldump').find('#dumpId').val();

		if (($dumpName + ".sql") != $oldDumpName) {
			if ($strRegex.test($dumpName)) {
				var tab = {
					idDump: $oldDumpName,
					newDumpName: $dumpName
				};
				
				$.api('GET', 'admin.dumpExist', {
					name: tab.newDumpName
				}).done(function(json) {

					if (!json.response) {
					
						$.api('POST', 'admin.updateDump', tab, false).done(function(json) {

							$('#modal_sqldump').modal('hide');

							if (json.response) {
								$.growlValidate(lang.valid.update);
								setTimeout(refreshDumps(), 1000);
							}
							else {
								$.growlErreur(lang.error.update);
							}
						});
					}
					else {
						$.growlWarning(lang.error.dumpExists);
					}
				});
			}
			else {
				$.growlWarning(lang.error.dump);
			}
		}
	}

	function deleteDump() {

		var tab = {
			idDump: $('#confirm-delete').find('#dumpIdDelete').val()
		};
		
		$.api('POST', 'admin.deleteDump', tab, false).done(function(json) {

			$('#confirm-delete').modal('hide');
			if (json.response) {
				$.growlValidate(lang.valid.delete);
				setTimeout(refreshDumps(), 1000);
			}
			else {
				$.growlErreur(lang.error.deleteDump);
			}
		});
	}

	function importDump() {

		var tab = {
			idDump: $('#confirm-delete').find('#dumpIdDelete').val()
		};
		
		$.api('POST', 'admin.importDump', tab, false).done(function(json) {

			$('#confirm-delete').modal('hide');
			if (json.response) {
				$.growlValidate(lang.valid.delete);
				setTimeout(refreshDumps(), 1000);
			}
			else {
				$.growlErreur(lang.error.deleteDump);
			}
		});
	}

	function newDump() {
		$strRegex = new RegExp(/^[a-z0-9\_\-]+$/i);
		$dumpName = $('#modal_sqldump').find('#dumpName').val();

		$('#modal_sqldump').modal('hide');

		if ($strRegex.test($dumpName)) {
			var tab = {
				idDump: $dumpName
			};
			
			$.api('GET', 'admin.newDump', {
				name: tab.idDump
			}).done(function(json) {

				if (json.response) {
					$.growlValidate(lang.valid.save);
					setTimeout(refreshDumps(), 1000);
				}
				else {
					$.growlErreur(lang.error.createDump);
				}
			});
		} else {
			$.growlWarning(lang.error.dump);
		}
	}

	function initModalEditDump(row) {
		var dumpName = row.attr("id");
		var dumpNameOnly = dumpName.split('.');
		dumpNameOnly.pop();
		
		$('#modal_sqldump').on('show.bs.modal', function() {
			$(this).find('#typeModal').val("edit");
			$(this).find('#dumpName').val(dumpNameOnly);
			$(this).find('.modal-title').html(lang.text.updateDump + " : " + dumpName);
			$(this).find('#dumpId').val(dumpName);
		});
	}

	function confirmDeleteDump(row) {
		var dumpName = row.attr("id");

		$('#confirm-delete').on('show.bs.modal', function() {
			$(this).find('#typeModalValid').val("delete");
			$(this).find('.modal-title').html(lang.text.deleteDump + " : " + dumpName + " ?");
			$(this).find('#dumpIdDelete').val(dumpName);
		});
	}

	function confirmImportDump(row) {
		var dumpName = row.attr("id");
		var warningMsg = "";

		var tab = {
			idDump: dumpName
		};

		$.api('POST', 'admin.checkSqlFile', tab, false).done(function(json) {
			if (json.response) {
				if (json.drop) {
					warningMsg += '<div class="alert alert-danger" role="alert"><i class="glyphicon glyphicon-warning-sign text-danger" aria-hidden="true"></i> ' + lang.warning.drop + "</div>";
				}
				if (json.insert) {
					warningMsg += '<div class="alert alert-danger" role="alert"><i class="glyphicon glyphicon-warning-sign text-danger" aria-hidden="true"></i> ' + lang.warning.insert + "</div>";
				}
			}
		});

		$('#confirm-delete').on('show.bs.modal', function() {
			$(this).find('#typeModalValid').val("import");
			$(this).find('.modal-title').html(lang.text.importDump + " : " + dumpName + " ?<br/><br/>" + warningMsg);
			$(this).find('#dumpIdDelete').val(dumpName);
		});
	}

	$('#fileupload').fileupload({

		url: 'ajax.php?sid=' + sessionToken + '&type=admin&action=uploadDump',
		dataType: 'json',
		autoUpload: true,
		acceptFileTypes: /(\.|\/)(sql)$/i,
		maxFileSize: 50000000,
		done: function(e, data) {
			setTimeout(function() {
				refreshDumps();
			}, 1000);
		},
		fail: function(e, data) {
			data.errorThrown
    		data.textStatus;
    		data.jqXHR;
		},
	});

	$("body").on("click", ".btn", function() {

		if ($(this).children().is(".glyphicon-edit")) {
			initModalEditDump($(this).closest("tr"));
		}
		if ($(this).children().is(".glyphicon-trash")) {
			confirmDeleteDump($(this).closest("tr"));
		}
		if ($(this).children().is(".glyphicon-log-in")) {
			confirmImportDump($(this).closest("tr"));
		}
		if ($(this).children().is(".glyphicon-plus")) {
			initModalSqldump();
		}
		
		if ($(this).is('#deleteConfirm')) {
			if ($("#confirm-delete").find('#typeModalValid').val() === "delete") {
				deleteDump();
			}
			if ($("#confirm-delete").find('#typeModalValid').val() === "import") {
				importDump();
			}
		}

		if ($(this).is('#dumpConfirm')) {			
			if ($("#modal_sqldump").find('#typeModal').val() === "add") {
				newDump();
			}
			if ($("#modal_sqldump").find('#typeModal').val() === "edit") {
				updateDump();
			}
		}

	});

	refreshDumps();
});