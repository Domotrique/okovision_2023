<?php
 $page = basename($_SERVER['SCRIPT_NAME']);
 $pageNotLogged = ['setup.php', 'index.php', 'histo.php', 'adminMatrix.php'];

 if (!in_array($page, $pageNotLogged) && !session::getInstance()->getVar('logged')) {
     header('Location: /errors/401.php');
     exit();
 }
?>
<!DOCTYPE html> 
<html lang="fr">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>OkoVision 2023</title>
    <script type="text/javascript">
            var sessionToken = "<?php echo session::getInstance()->getVar('sid'); ?>";		
   </script>
   <script src="js/jquery/jquery.min.js"></script>
   <script src="js/bootstrap/bootstrap.min.js"></script>
	<!-- Bootstrap core CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap theme -->
    <link href="css/bootstrap-theme.min.css" rel="stylesheet">
    <link href="css/jquery-ui.min.css" rel="stylesheet">
    <link href="css/custom.css" rel="stylesheet">
    <link href="css/animate.css" rel="stylesheet">
    <link href="css/jquery-ui-timepicker-addon.css" rel="stylesheet">
	
	</head>

  <body role="document">
  