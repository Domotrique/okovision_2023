<?php
/*
* Projet : Okovision - Supervision chaudiere OeKofen
* Auteur : Stawen Dronek
* Utilisation commerciale interdite sans mon accord
*/

if (!file_exists('config.php')) {
    header('Location: setup.php');
} else {
    include_once 'config.php';
    include_once '_templates/header.php';
    include_once '_templates/menu.php';
}

?>   

<div class="se-pre-con"></div>

    <div class="container theme-showcase" role="main">
		
		<div class="page-header" align="center">
			<div class="col-md-12" >
		 		<div id="stock_alert" class="alert alert-danger" role="danger" style="display: none;"> <strong><?php echo session::getInstance()->getLabel('lang.text.page.label.alertStock'); ?></strong></div>
		 	</div>
		 	<div class="col-md-12" >
		 		<div id="ashtray_alert" class="alert alert-warning" role="warning" style="display: none;"> <strong><?php echo session::getInstance()->getLabel('lang.text.page.label.alertAshtray'); ?></strong></div>
		 		<div id="ashtray_noInfo" class="alert alert-warning" role="warning" style="display: none;"> <strong><?php echo session::getInstance()->getLabel('lang.text.page.label.alertAshtrayNoInfo'); ?></strong></div>
		 		<div id="ashtray_noDate" class="alert alert-warning" role="warning" style="display: none;"> <strong><?php echo session::getInstance()->getLabel('lang.text.page.label.alertAshtrayNoDate'); ?></strong></div>
		 	</div>
			<span class="glyphicon glyphicon-hand-right"></span> <?php echo session::getInstance()->getLabel('lang.text.page.label.conso'); ?> <span id="consoPellet" class="label label-primary">00,00 Kg</span> &nbsp;&nbsp;
																 <?php echo session::getInstance()->getLabel('lang.text.page.label.conso.ecs'); ?> <span id="consoPelletHotwater" class="label label-primary">00,00 Kg</span> &nbsp;&nbsp;
			<span class="glyphicon glyphicon-arrow-up"></span> <?php echo session::getInstance()->getLabel('lang.text.page.label.tcmax'); ?> <span id="tcmax" class="label label-success">00,0 °C</span> &nbsp;&nbsp;
			<span class="glyphicon glyphicon-arrow-down"></span> <?php echo session::getInstance()->getLabel('lang.text.page.label.tcmin'); ?> <span id="tcmin" class="label label-warning">00,0 °C</span>
		</div>
		<div class="container-graphe" id="container-graphs">
		</div>
		<!-- Analytics Info Modal -->
		<div class="modal fade" id="analyticsInfoModal" tabindex="-1" role="dialog" aria-labelledby="analyticsInfoLabel" aria-hidden="true">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title" id="analyticsInfoLabel">Information</h4>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<p>Des statistiques anonymes d'utilisation sont envoyées pour améliorer Okovision. Vous pouvez désactiver cette option à tout moment dans les paramètres.</p>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-primary" data-dismiss="modal">J'ai compris</button>
					</div>
				</div>
			</div>
		</div>

<?php
include __DIR__.'/_templates/footer.php';
?>

		<script>
		(function() {
			// Ne rien faire si l'utilisateur a déjà vu la popup
			if (localStorage.getItem('okv_analytics_info_shown') === '1') {
				return;
			}

			// Check if analytics are enabled via AJAX
			console.log('Checking analytics info');

			var analyticsEnabled = <?php echo (defined('OKV_ANALYTICS_ENABLED') && OKV_ANALYTICS_ENABLED) ? 'true' : 'false'; ?>;

			if (analyticsEnabled) {
				// Affiche la popup (nécessite Bootstrap ou équivalent)
				$('#analyticsInfoModal').modal('show');
				// Mémorise que la popup a été affichée
				localStorage.setItem('okv_analytics_info_shown', '1');
			}
		})();
		</script>
	<!--appel des scripts personnels de la page -->
		<script src="_langs/<?php echo session::getInstance()->getLang(); ?>.datepicker.js"></script>
		<script src="js/index.js"></script>
	</body>
</html>
