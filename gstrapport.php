<?php
/* * Projet : Okovision - Supervision chaudiere OeKofen
* Auteur : Stawen Dronek
* Utilisation commerciale interdite sans mon accord
* */
include_once 'config.php';
include_once '_templates/header.php';
include_once '_templates/menu.php';
?>


	<div class="container theme-showcase" role="main">
		<div class="page-header">
			<h3> <small><?php echo session::getInstance()->getLabel('lang.text.page.repport.title'); ?></small></h3>
		</div>

		<p class="text-muted"><?php echo session::getInstance()->getLabel('lang.text.page.repport.hint'); ?></p>

		<div class="row">
			<div class="col-md-4">
				<div class="panel panel-default">
					<div class="panel-heading clearfix">
						<button type="button" class="btn btn-xs btn-default pull-right" id="openModalAddGraphique">
							<span class="glyphicon glyphicon-plus" aria-hidden="true"></span> <?php echo session::getInstance()->getLabel('lang.text.page.repport.add'); ?>
						</button>
						<strong><?php echo session::getInstance()->getLabel('lang.text.page.repport.list.title'); ?></strong>
					</div>

					<table id="listeGraphique" class="table table-hover gst-rapport">
						<tbody>
						</tbody>
					</table>
				</div>
			</div>

			<div class="col-md-8">
				<div class="panel panel-default">
					<div class="panel-heading">
						<strong><?php echo session::getInstance()->getLabel('lang.text.page.repport.asso.panel'); ?></strong>
						<span id="grapheSelectedName"></span>
					</div>

					<table id="listeAsso" class="table table-hover gst-rapport">
						<thead>
							<tr>
								<th class="col-md-1"></th>
								<th class="col-md-7"><?php echo session::getInstance()->getLabel('lang.text.page.repport.asso.table.name'); ?></th>
								<th class="col-md-3"><?php echo session::getInstance()->getLabel('lang.text.page.repport.asso.table.coef'); ?></th>
								<th class="col-md-1"></th>
							</tr>
						</thead>

						<tbody>
						</tbody>
					</table>

					<div class="panel-footer">
						<form class="form-inline" id="formAddAsso">
							<div class="form-group">
								<label for="select_capteur" class="sr-only"><?php echo session::getInstance()->getLabel('lang.text.page.repport.asso.modale.capteur'); ?></label>
								<select class="form-control input-sm" id="select_capteur">
								</select>
							</div>
							<div class="form-group">
								<label for="coeff" class="control-label"><?php echo session::getInstance()->getLabel('lang.text.page.repport.asso.modale.coef'); ?></label>
								<input type="text" class="form-control input-sm gst-coeff" id="coeff" placeholder="ex : 0,25" value="1">
							</div>
							<button type="submit" class="btn btn-default btn-sm" id="addAsso">
								<span class="glyphicon glyphicon-plus" aria-hidden="true"></span> <?php echo session::getInstance()->getLabel('lang.text.page.repport.asso.add'); ?>
							</button>
						</form>
					</div>
				</div>
			</div>
		</div>

		<div class="modal fade" id="modal_graphique" tabindex="-1" role="dialog" aria-labelledby="graphiqueTitre" aria-hidden="true">
			<div class="modal-dialog">
				<div class="modal-content">
					<form id="formGraphique">
						<div class="modal-header">
							<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span>
							</button>
							<h4 class="modal-title" id="graphiqueTitre"></h4>
						</div>
						<div class="modal-body">
							<div class="form-group">
								<label for="name" class="control-label"><?php echo session::getInstance()->getLabel('lang.text.page.repport.repport.modale.title'); ?></label>
								<input type="text" class="form-control" id="name">
							</div>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-default btn-sm" data-dismiss="modal">
								<span class="glyphicon glyphicon-remove" aria-hidden="true"></span>
							</button>
							<button type="submit" id="addGraphique" class="btn btn-default btn-sm">
								<span class="glyphicon glyphicon-ok" aria-hidden="true"></span>
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>

		<div class="modal fade" id="confirm-delete" tabindex="-1" role="dialog" aria-labelledby="deleteTitre" aria-hidden="true">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span>
						</button>
						<h4 class="modal-title" id="deleteTitre"></h4>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal"><?php echo session::getInstance()->getLabel('lang.text.modal.cancel'); ?></button>
						<button type="button" class="btn btn-danger btn-ok" id="deleteConfirm"><?php echo session::getInstance()->getLabel('lang.text.modal.confirm'); ?></button>
					</div>
				</div>
			</div>
		</div>
	</div>


	<?php include __DIR__.'/_templates/footer.php'; ?>
	<!--appel des scripts personnels de la page -->
	<script src="js/gstrapport.js?v=<?php echo filemtime(__DIR__.'/js/gstrapport.js'); ?>"></script>
	</body>

</html>
