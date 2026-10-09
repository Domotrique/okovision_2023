/*****************************************************
 * Projet : Okovision - Supervision chaudiere OeKofen
 * Auteur : Stawen Dronek
 * Utilisation commerciale interdite sans mon accord
 ******************************************************/
/* global lang */
$(document).ready(function() {

    var selectedId = null; //graphique affiché dans le panneau de droite
    var capteurs = []; //tous les capteurs de la matrice
    var editId = null; //graphique en cours de renommage, null pour une création
    var pendingDelete = null; //suppression en attente de confirmation

    var handle = '<td class="drag-handle"><span class="glyphicon glyphicon-resize-vertical" aria-hidden="true"></span></td>';

    function button(cls, icon) {
        return '<button type="button" class="btn btn-default btn-xs ' + cls + '"><span class="glyphicon glyphicon-' + icon + '" aria-hidden="true"></span></button>';
    }

    function emptyRow(colspan, text) {
        return $('<tr class="gst-empty"></tr>').append($('<td class="text-muted"></td>').attr('colspan', colspan).text(text));
    }

    //position (base 1) d'une ligne parmi les lignes de données de son tableau
    function positionOf(tr) {
        return tr.parent().children('tr[id]').index(tr) + 1;
    }

    function parseCoeff(value) {
        var coeff = $.trim(String(value)).replace(',', '.');

        return $.isNumeric(coeff) ? coeff : null;
    }

    /************************************************
     * ************ Graphiques **********************
     * *********************************************/
    function refreshTableGraphe(selectName) {
        $.api('GET', 'graphique.getGraphe').done(function(json) {
                var tbody = $("#listeGraphique > tbody").empty();
                var found = false;
                var data = json.data || [];

                $.each(data, function(key, val) {
                    if (selectName !== undefined && String(val.name) === selectName) {
                        selectedId = val.id;
                    }
                });

                $.each(data, function(key, val) {
                    found = found || String(val.id) === String(selectedId);

                    var tr = $('<tr></tr>').attr('id', val.id).append(handle);
                    tr.append($('<td class="gst-name"></td>').text(val.name));
                    tr.append('<td class="text-right text-nowrap">' + button('editGraphe', 'edit') + ' ' + button('deleteGraphe', 'trash') + '</td>');
                    tbody.append(tr);
                });

                if (!found) {
                    selectedId = data.length ? data[0].id : null;
                }
                if (!data.length) {
                    tbody.append(emptyRow(3, lang.text.noGraphe));
                }

                refreshTableAsso();
            })
            .fail(function() {
                $.growlErreur(lang.error.getGraphe);
            });
    }

    function openModalGraphe(row) {
        var name = row ? row.find('.gst-name').text() : '';

        editId = row ? row.attr('id') : null;
        $('#graphiqueTitre').text(row ? lang.text.updateGraphe + ' ' + name : lang.text.addGraphe);
        $('#name').val(name);
        $('#modal_graphique').modal('show');
    }

    function addGraphe(name) {
        $.api('GET', 'graphique.grapheNameExist', {
            name: name
        }).done(function(json) {

            if (json.exist) {
                $.growlWarning(lang.error.grapehAlreadyExist);
                return;
            }

            $.api('GET', 'graphique.getLastGraphePosition').done(function(json) {
                var tab = {
                    name: name,
                    position: (json.data.lastPosition === null) ? 1 : parseInt(json.data.lastPosition) + 1
                };

                $.api('POST', 'graphique.addGraphe', tab).done(function(json) {

                    $('#modal_graphique').modal('hide');
                    if (json.response) {
                        $.growlValidate(lang.valid.save);
                        //le nouveau graphique est sélectionné, prêt à recevoir ses capteurs
                        refreshTableGraphe(name);
                    }
                    else {
                        $.growlErreur(lang.error.save);
                    }
                });
            }).fail(function() {
                $.growlErreur(lang.error.position);
            });
        });
    }

    function updateGraphe(name) {
        $.api('POST', 'graphique.updateGraphe', {
            id: editId,
            name: name
        }).done(function(json) {

            $('#modal_graphique').modal('hide');
            if (json.response) {
                $.growlValidate(lang.valid.update);
                refreshTableGraphe();
            }
            else {
                $.growlErreur(lang.error.save);
            }
        });
    }

    function deleteGraphe(id) {
        $.api('POST', 'graphique.deleteGraphe', {
            id: id
        }).done(function(json) {

            $('#confirm-delete').modal('hide');
            if (json.response === true) {
                $.growlValidate(lang.valid.delete);
                refreshTableGraphe();
            }
            else {
                $.growlErreur(lang.error.deleteGraphe);
            }
        });
    }

    /************************************************
     * ************ Capteurs du graphique ***********
     * *********************************************/
    function refreshSelectCapteur(used) {
        var select = $('#select_capteur').empty();

        $.each(capteurs, function(key, val) {
            //on ne propose que les capteurs absents du graphique
            if ($.inArray(String(val.id), used) === -1) {
                select.append($('<option></option>').val(val.id).text(val.name));
            }
        });

        var disabled = selectedId === null || select.children().length === 0;
        $('#formAddAsso').find('select, input, button').prop('disabled', disabled);
    }

    function refreshTableAsso() {
        var tbody = $("#listeAsso > tbody").empty();
        var selectedRow = $('#listeGraphique tr[id="' + selectedId + '"]');

        $('#listeGraphique tr').removeClass('info');
        selectedRow.addClass('info');
        $('#grapheSelectedName').text(selectedId === null ? '' : ': ' + selectedRow.find('.gst-name').text());

        if (selectedId === null) {
            tbody.append(emptyRow(4, lang.text.noGraphe));
            refreshSelectCapteur([]);
            return;
        }

        $.api('GET', 'graphique.getGrapheAsso', {
                graphe: selectedId
            }).done(function(json) {
                var used = [];

                $.each(json.data || [], function(key, val) {
                    //capteur supprimé de la matrice
                    if (val.id === null) {
                        return;
                    }
                    used.push(String(val.id));

                    var tr = $('<tr></tr>').attr('id', val.id).append(handle);
                    tr.append($('<td class="gst-name"></td>').text(val.name));
                    tr.append($('<td></td>').append($('<input type="text" class="form-control input-sm gst-coeff">').val(val.coeff).data('saved', val.coeff)));
                    tr.append('<td class="text-right">' + button('deleteAsso', 'trash') + '</td>');
                    tbody.append(tr);
                });

                if (!used.length) {
                    tbody.append(emptyRow(4, lang.text.noAsso));
                }

                refreshSelectCapteur(used);
            })
            .fail(function() {
                $.growlErreur(lang.error.getAsso);
            });
    }

    function addAsso() {
        var tab = {
            id_graphe: selectedId,
            id_capteur: $('#select_capteur').val(),
            position: $('#listeAsso > tbody > tr[id]').length + 1,
            coeff: parseCoeff($('#coeff').val())
        };

        if (tab.id_graphe === null || !tab.id_capteur) {
            return;
        }
        if (tab.coeff === null) {
            $.growlErreur(lang.error.coeffMustBeNumber);
            return;
        }

        $.api('POST', 'graphique.addGrapheAsso', tab).done(function(json) {

            if (json.response) {
                $.growlValidate(lang.valid.save);
                $('#coeff').val("1");
                refreshTableAsso();
            }
            else {
                $.growlErreur(lang.error.save);
            }
        });
    }

    function updateAsso(input) {
        var coeff = parseCoeff(input.val());

        if (coeff === null) {
            $.growlErreur(lang.error.coeffMustBeNumber);
            input.val(input.data('saved'));
            return;
        }

        $.api('POST', 'graphique.updateGrapheAsso', {
            id_graphe: selectedId,
            id_capteur: input.closest('tr').attr('id'),
            coeff: coeff
        }).done(function(json) {

            if (json.response) {
                $.growlValidate(lang.valid.update);
                input.val(coeff).data('saved', coeff);
            }
            else {
                $.growlErreur(lang.error.update);
                input.val(input.data('saved'));
            }
        });
    }

    function deleteAssoGraphe(idCapteur) {
        $.api('POST', 'graphique.deleteAssoGraphe', {
            id_capteur: idCapteur,
            id_graphe: selectedId
        }).done(function(json) {

            $('#confirm-delete').modal('hide');
            if (json.response) {
                $.growlValidate(lang.valid.delete);
                refreshTableAsso();
            }
            else {
                $.growlErreur(lang.error.deleteAsso);
            }
        });
    }

    function confirmDelete(title, action) {
        pendingDelete = action;
        $('#deleteTitre').text(title);
        $('#confirm-delete').modal('show');
    }

    /************************************************
     * ************ Evenements **********************
     * *********************************************/
    $('#openModalAddGraphique').click(function() {
        openModalGraphe(null);
    });

    $('#modal_graphique').on('shown.bs.modal', function() {
        $('#name').focus();
    });

    $('#formGraphique').submit(function(e) {
        e.preventDefault();

        var name = $.trim($('#name').val());
        if (name === '') {
            return;
        }

        if (editId === null) {
            addGraphe(name);
        }
        else {
            updateGraphe(name);
        }
    });

    //obligé d'utiliser "on()" car les lignes sont ajoutées apres le chargement de la page
    $('#listeGraphique').on('click', 'tr[id]', function(e) {
        var row = $(this);

        if ($(e.target).closest('.editGraphe').length) {
            openModalGraphe(row);
        }
        else if ($(e.target).closest('.deleteGraphe').length) {
            confirmDelete(lang.text.deleteGraphe + ' ' + row.find('.gst-name').text() + ' ?', function() {
                deleteGraphe(row.attr('id'));
            });
        }
        else if (String(selectedId) !== row.attr('id')) {
            selectedId = row.attr('id');
            refreshTableAsso();
        }
    });

    $('#listeAsso').on('click', '.deleteAsso', function() {
        var row = $(this).closest('tr');
        var name = $.trim($('#grapheSelectedName').text().replace(/^:/, '')) + ' - ' + row.find('.gst-name').text();

        confirmDelete(lang.text.deleteAsso + ' ' + name + ' ?', function() {
            deleteAssoGraphe(row.attr('id'));
        });
    });

    $('#listeAsso').on('change', '.gst-coeff', function() {
        updateAsso($(this));
    });

    $('#listeAsso').on('keydown', '.gst-coeff', function(e) {
        if (e.which === 13) {
            $(this).blur();
        }
    });

    $('#formAddAsso').submit(function(e) {
        e.preventDefault();
        addAsso();
    });

    $('#deleteConfirm').click(function() {
        if (pendingDelete) {
            pendingDelete();
            pendingDelete = null;
        }
    });

    var currentPosition;
    $('#listeGraphique > tbody, #listeAsso > tbody').sortable({
        items: 'tr[id]',
        handle: '.drag-handle',
        opacity: 0.75,
        helper: fixWidthHelper,
        start: function(event, ui) {
            currentPosition = positionOf(ui.item);
        },
        update: function(event, ui) {
            var isGraphe = $(this).closest('table').is("#listeGraphique");
            var tab = {
                id_graphe: isGraphe ? ui.item.attr('id') : selectedId,
                current: currentPosition,
                position: positionOf(ui.item)
            };

            if (!isGraphe) {
                tab.id_capteur = ui.item.attr('id');
            }

            $.api('POST', isGraphe ? 'graphique.updateGraphePosition' : 'graphique.updateGrapheAssoPosition', tab).done(function(json) {

                if (json.response) {
                    $.growlValidate(lang.valid.update);
                }
                else {
                    $.growlErreur(lang.error.update);
                    refreshTableGraphe();
                }
            });
        }
    });

    function fixWidthHelper(e, ui) {
        ui.children().each(function() {
            $(this).width($(this).width());
        });
        return ui;
    }

    //les capteurs sont chargés avant les graphiques pour alimenter la liste d'ajout
    $.api('GET', 'graphique.getCapteurs').done(function(json) {

        if (json.response) {
            capteurs = json.data;
        }
        else {
            $.growlErreur(lang.error.getSensor);
        }
    }).always(function() {
        refreshTableGraphe();
    });

});
