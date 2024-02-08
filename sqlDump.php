<?php
/*****************************************************
* Projet : Okovision - Supervision chaudiere OeKofen
* Auteur : Stawen Dronek
* Utilisation commerciale interdite sans mon accord
******************************************************/

	include_once 'config.php';
	include_once '_templates/header.php';
	include_once '_templates/menu.php';
?>

<div class="container theme-showcase" role="main">
<br/>
    <div class="page-header" >
         <h2><?php echo session::getInstance()->getLabel( 'lang.text.menu.admin.sqldump') ?></h2>
    </div>
                   
	<?php echo session::getInstance()->getLabel('lang.text.page.sqldump') ?>
    
	<br/><br/>
	<button type="button" class="btn btn-xs btn-success" id="openModalSqldump" data-toggle="modal" data-target="#modal_sqldump">
        <span class="glyphicon glyphicon-plus" aria-hidden="true"></span> <?php echo session::getInstance()->getLabel('lang.text.page.sqldump.generate') ?>
    </button>
    <span class="btn btn-xs btn-default fileinput-button" title="<?php echo session::getInstance()->getLabel('lang.text.page.sqldump.info') ?>" disabled>
        <i class="glyphicon glyphicon-import"></i>
        <span id="btup"><?php 
        $htaccess = file(realpath(dirname(__FILE__)) . '/.htaccess');
        foreach ($htaccess as $line) {
            if (str_contains($line, "upload_max_filesize")) {
                preg_match('/(\d+)(?!.*\d)/',$line, $matches);
                $max_file_size = $matches[1];
            }
        }
        echo (session::getInstance()->getLabel('lang.text.page.sqldump.import') . $max_file_size . "MB max)") ?></span>
        <!-- The file input field used as target for the file upload widget -->
        <input id="fileupload" type="file" name="files[]">
    </span>

    <table id="availableDumps" class="table table-hover">
        <thead>
            <tr >
                <th class="col-md-3"><?php echo session::getInstance()->getLabel('lang.text.page.sqldump.title') ?></th>
                <th class="col-md-3"><?php echo session::getInstance()->getLabel('lang.text.page.sqldump.date') ?></th>
                <th class="col-md-3"></th>
                
            </tr>
        </thead>
    
        <tbody>
        </tbody>

    </table>
    
    <div class="modal fade" id="modal_sqldump" tabindex="-1" role="dialog" aria-labelledby="sqldumpLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="dumpConfirmTitle"></h4>
                </div>               
               <div class="modal-body">
                    <div class="hidden">
                        <input type="text" id="dumpId">
                        <input type="text" id="typeModal">
                    </div>
                    <form>

                        <div class="form-group">
                            <label for="recipient-name" class="control-label"><?php echo session::getInstance()->getLabel('lang.text.page.sqldump.modal.name') ?></label>
                            <input type="text" class="form-control" id="dumpName" value="<?php echo date("Ymd") . '_dump'; ?>">
                        </div>
                        
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo session::getInstance()->getLabel('lang.text.modal.cancel') ?></button>
                    <button type="button" class="btn btn-danger btn-ok" id="dumpConfirm"><?php echo session::getInstance()->getLabel('lang.text.modal.confirm') ?></button>
                </div>
            </div>
        </div>
    </div>
    
    
    <div class="modal fade" id="confirm-delete" tabindex="-1" role="dialog" aria-labelledby="deleteSqlBackup" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="deleteTitre"></h4>
                </div>
                <div class="hidden">
                    <input type="text" id="dumpIdDelete">
                    <input type="text" id="typeModalValid">
               </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo session::getInstance()->getLabel('lang.text.modal.cancel') ?></button>
                    <button type="button" class="btn btn-danger btn-ok" id="deleteConfirm"><?php echo session::getInstance()->getLabel('lang.text.modal.confirm') ?></button>
                </div>
            </div>
        </div>
    </div>

<?php
include(__DIR__ . '/_templates/footer.php');
?>
    <script src="js/jquery/jquery.fileupload.js"></script>
	<script src="js/sqlDump.js"></script>
    </body>
</html>
