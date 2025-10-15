<?php

    /**
     * Projet : Okovision - Supervision chaudiere OeKofen
     * Auteur : Stawen Dronek
     * Utilisation commerciale interdite sans mon accord.
     */

    function is_ajax()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 'xmlhttprequest' == strtolower($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    function testBddConnection($s)
    {
        mysqli_report(MYSQLI_REPORT_STRICT);

        $r = true;

        try {
            $db = new mysqli($s['db_adress'], $s['db_user'], $s['db_password']);
        } catch (Exception $e) {
            $r = false;
        }
        $t['response'] = $r;
        header('Content-type: text/json');
        echo json_encode($t, JSON_NUMERIC_CHECK);

        exit(23);
    }

	function testPing($address)
    {
        $waitTimeoutInSeconds = 1;
		
        $r = [];
        $tmp = explode(':', $address['ip']);
        $ip = $tmp[0];
        $port = isset($tmp[1]) ? $tmp[1] : 80;

		if (!$ip == "") {
			if ($fp = @fsockopen($ip, $port, $errCode, $errStr, $waitTimeoutInSeconds)) {
				// It worked
				$r['response'] = true;
				@fclose($fp);
			} else {
				$r['response'] = false;
			}
		} else {
			$r['response'] = false;
		}

		header('Content-type: text/json');
        echo json_encode($r, JSON_NUMERIC_CHECK);

        exit(23);
    }

    function makeInstallation($s)
    {
		if ($s['oko_ip_ok'] == "true") {
			// Retrieve CSV file from boiler for Matrix table creation
			$r = [];
			$url = '/logfiles/pelletronic';
			$htmlCode = file_get_contents('http://'.$s['oko_ip'].$url);

			$dom = new DOMDocument();

			$dom->LoadHTML($htmlCode);

			$links = $dom->GetElementsByTagName('a');

			$t_href = [];
			foreach ($links as $a) {
				$href = $a->getAttribute('href');

				if (preg_match('/csv/i', $href)) {
					$csvFile = 'http://'.$s['oko_ip'].$href;
				}
			}
			
			$file_name = '_tmp\matrice.csv';
			file_put_contents($file_name, file_get_contents($csvFile));
			$r['csv'] = 1;

			header('Content-type: text/json');
        	echo json_encode($r, JSON_NUMERIC_CHECK);
		}

        if ($s['createDb']) {
            // create BDD
            $mysqli = new mysqli($s['db_adress'], $s['db_user'], $s['db_password']);

            // check connection
            if ($mysqli->connect_errno) {
                printf("Connect failed: %s\n", $mysqli->connect_error);
                exit(24);
            }

            $q = 'CREATE DATABASE IF NOT EXISTS `'.$s['db_schema'].'` /*!40100 DEFAULT CHARACTER SET utf8 */;';
            if (!$mysqli->query($q)) {
                echo 'Création BDD impossible';
                exit;
            }
            $mysqli->close();
        }

        $mysqli = new mysqli($s['db_adress'], $s['db_user'], $s['db_password'], $s['db_schema']);

        // execute multi query
        $mysqli->multi_query(file_get_contents('install/install.sql'));
        while ($mysqli->next_result()) {
        } // flush multi_queries

        // init de la table des dates de reference
        $start_day = mktime(0, 0, 0, 9, 1, 2023); //1er septembre 2023
        $stop_day = mktime(0, 0, 0, 9, 1, 2037); //justqu'au 1er septembre 2037, on verra en 2037 si j'utilise encore l'app.
        $nb_day = ($stop_day - $start_day) / 86400;
        $query = 'INSERT INTO oko_dateref (jour) VALUES ';
        for ($i = 0; $i <= $nb_day; ++$i) {
            $day = date('Y-m-d', mktime(0, 0, 0, date('m', $start_day), date('d', $start_day) + $i, date('Y', $start_day)));
            $query .= "('".$day."'),";
        }

        $query = substr($query, 0, strlen($query) - 1).';';

        $mysqli->query($query);

        $mysqli->close();

        // Make Config.php
        $configFile = file_get_contents('config_sample.php');

        $configFile = str_replace('###_BDD_IP_###', $s['db_adress'], $configFile);
        $configFile = str_replace('###_BDD_USER_###', $s['db_user'], $configFile);
        $configFile = str_replace('###_BDD_PASS_###', $s['db_password'], $configFile);
        $configFile = str_replace('###_BDD_SCHEMA_###', $s['db_schema'], $configFile);

        $configFile = str_replace('###_CONTEXT_###', getcwd(), $configFile);

        $configFile = str_replace('###_TOKEN_###', sha1(rand()), $configFile);

		//Get latest version number from github
		$ch = curl_init("https://api.github.com/repos/domotrique/okovision_2023/releases/latest");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'OkovisionDownloader');
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        // Vérifie si une release a été trouvée
        if (isset($data['tag_name'])) {
            $version = $data['tag_name'] ?? '0.0.0';
        } else {
            echo "Aucune release trouvée ou erreur d’API." . PHP_EOL;
        }

        $configFile = str_replace('###_OKOVISION_VERSION_###', $version, $configFile);
        $configFile = str_replace('###_ANALYTICS_###', $s['analytics_enabled'], $configFile);

        file_put_contents('config.php', $configFile);
		
        // Make config.json
        $param = [
            'chaudiere' => $s['oko_ip'],
            'tc_ref' => $s['param_tcref'],
            'poids_pellet' => $s['param_poids_pellet'],
            'surface_maison' => $s['surface_maison'],
            'get_data_from_chaudiere' => $s['oko_typeconnect'],
            'send_to_web' => '0',
            'has_silo' => $s['has_silo'],
            'silo_size' => $s['silo_size'],
            'ashtray' => $s['ashtray'],
            'lang' => $s['lang'],
        ];

        file_put_contents('config.json', json_encode($param));

		exit;
    }

    if (is_ajax()) {
        if (isset($_GET['type'])) {
            switch ($_GET['type']) {
                case 'connect':
                    testBddConnection($_POST);

                    break;
				case 'ip':
					testPing($_POST);

					break;
                case 'install':
                    makeInstallation($_POST);

                    break;
            }
        }
    }

?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>OkoVision</title>
    
	<!-- Bootstrap core CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap theme -->
    <link href="css/bootstrap-theme.min.css" rel="stylesheet">
    <link href="css/jquery-ui.min.css" rel="stylesheet">
    <link href="css/custom.css" rel="stylesheet">
    <link href="css/animate.css" rel="stylesheet">
    <!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>+
    <![endif]-->
	<?php //include_once("analyticstracking.php");?>
	
	</head>

  <body role="document">
  	
<div class="container theme-showcase" role="main">
		<div class="page-header" align="center">
			<h2>Okovision installation</h2> <br>
		</div>
		<div>
			<h3><small>You can modify this information, after installation, through the settings screen</small></h3>
		</div>
		
		
			<fieldset>
				<form class="form-horizontal" id="formConnect">
				<!-- Form Name -->
					<legend>Database Connection</legend>
					
					<!-- Text input-->
					<div class="form-group">
						<label class="col-md-4 control-label" for="db_adress">MySQL Server Address (*) :</label>  
						<div class="col-md-3">
							<input id="db_adress" name="db_adress" type="text" value="localhost" class="form-control input-md" required="">
						</div>
					</div>
					
					<!-- Text input-->
					<div class="form-group">
						<label class="col-md-4 control-label" for="db_adress">Name (*) :</label>  
						<div class="col-md-3">
							<input id="db_schema" name="db_schema" type="text" value="okovision" class="form-control input-md" required="">
						</div>
					</div>
					<div class="form-group">
						<label class="col-md-4 control-label" for="createDb">Create database :</label>  
					  	<div class="col-md-3 checkbox">
							<label>
								<input id="createDb" type="checkbox"> (don't check if database already exist)
							</label>
					  	</div>
					</div>
					
					<!-- Text input-->
					<div class="form-group">
					  <label class="col-md-4 control-label" for="db_user">MySQL User (*) :</label>  
					  <div class="col-md-3">
					  <input id="db_user" name="db_user" type="text" value="okouser" class="form-control input-md" required="">
					  </div>
					</div>
					
					<!-- Text input-->
					<div class="form-group">
					  	<label class="col-md-4 control-label" for="db_password">MySQL Password (*) :</label>  
					  	<div class="col-md-3">
					  		<input id="db_password" name="db_password" type="text" value="okopass" class="form-control input-md" required="">
					  	</div>
					</div>
					
					<!-- Button -->
					<div class="form-group">
						<label class="col-md-4 control-label"  for="bt_testConnection">Connection test :</label>
						<div class="col-md-3">
							<button id="bt_testConnection" name="bt_testConnection" class="btn btn-primary" type="button">Validate Database connection</button><br/>
							<span class="label label-success" id="DB_validation" style="display: none;">Database connection OK !</span>
						</div>
					</div>
				</form>
			</fieldset>
			

			<form class="form-horizontal">
				<fieldset>
					<form class="form-horizontal" id="formCSV">
					<!-- Form Name -->
						<legend>Boiler Communication</legend>
						
						<!-- Select Basic -->
						<div class="form-group">
							<label class="col-md-4 control-label" for="oko_typeconnect_ip">CSV file grab mode :</label>
							<div class="col-md-3">
								<label class="radio-inline"><input id="oko_typeconnect_ip" type="radio" value="1" name="oko_typeconnect" checked>
									<img src="css/images/ethernet.svg" width="25" height="25">
									IP
								</label>
								<label class="radio-inline"><input id="oko_typeconnect_usb" type="radio" value="0" name="oko_typeconnect">
									<img src="css/images/usb-plug.svg" width="25" height="25">
									USB
								</label>
							</div>
						</div>
						
						<!-- Text input-->
						<div id="form-ip">
							<div class="form-group">
								<label class="col-md-4 control-label" for="oko_ip">Boiler IP address :</label>  
								<div class="col-md-3">
									<input id="oko_ip" name="oko_ip" type="text" placeholder="ex : 192.168.0.xx" class="form-control input-md">
									<div class="hidden">
										<input type="text" id="ip_ok">
									</div>
								</div>
							</div>
							<div class="form-group">
								<label class="col-md-4 control-label"  for="test_oko_ip">Boiler Connection test :</label>
								<div class="col-md-3">							
									<button id="test_oko_ip" name="bt_testIP" class="btn btn-primary" type="button">Validate Boiler Connection</button><br/>
									<span class="label label-success" id="ip_validation" style="display: none;">Boiler connection OK !</span>
								</div>
							</div>
						</div>
					</form>
				</fieldset>
			</form>

			<form class="form-horizontal">
				<fieldset>
				
				<!-- Form Name -->
					<legend>Application settings</legend>
					
					<!-- Text input-->
					<div class="form-group">
					  <label class="col-md-4 control-label" for="param_tcref">Reference °C :</label>  
					  <div class="col-md-3">
					  <input id="param_tcref" name="param_tcref" type="text" placeholder="ex : 20" class="form-control input-md" required="" value="20">
					  <span class="help-block">If you have 2 setpoints, Reduced at 19°C and Comfort at 21°C, your average is -&gt; 20°C (DJU calculation)</span>  
					  </div>
					</div>
					
					<!-- Text input-->
					<div class="form-group">
					  <label class="col-md-4 control-label" for="param_poids_pellet">Pellet weight for 60 seconds of work : </label>  
					  <div class="col-md-3">
					  <input id="param_poids_pellet" name="param_poids_pellet" type="text" placeholder="ex : 150" class="form-control input-md" required=""  value="150">
					  <span class="help-block">Pellet weight in grams measured by operating the furnace feed screw for 60 seconds</span>  
					  </div>
					</div>
					
					<!-- Text input-->
					<div class="form-group">
					  <label class="col-md-4 control-label" for="surface_maison">House surface : </label>  
					  <div class="col-md-3">
					  <input id="surface_maison" name="param_surface" type="text" placeholder="ex : 180" class="form-control input-md" required=""  value="180">
					  <span class="help-block">in m²</span>  
					  </div>
					</div>
				
				</fieldset>
			</form>

			<form class="form-horizontal">
				<fieldset>
    				<!-- Form Name -->
    					<legend>Storage tank and ashtray management</legend>
    					
    					<!-- Select Basic -->
    					<div class="form-group">
							<label class="col-md-4 control-label" for="oko_loadingmode_silo">Pellet loading mode :</label>
						  	<div class="col-md-3">
								<label class="radio-inline"><input id="oko_loadingmode_silo" type="radio" value="1" name="oko_loadingmode" checked>
									<img src="css/images/silo.png" width="25" height="25">
									SILO
								</label>
								<label class="radio-inline"><input id="oko_loadingmode_bags" type="radio" value="0" name="oko_loadingmode">
									<img src="css/images/bag-plus.svg" width="25" height="25">
									BAGS
								</label>
							</div>
    					</div>
    					
                        <!-- Text input-->
                        <div class="form-group" id="form-silo-details">
                            <label class="col-md-4 control-label" for="oko_silo_size">Storage tank size (kg) :</label>  
                            <div class="col-md-3">
                                <input id="oko_silo_size" name="oko_silo_size" type="text" class="form-control input-md" value="3500">
                            </div>
    					</div>
    				
    				    <!-- Text input-->
    					<div class="form-group">
							<label class="col-md-4 control-label" for="oko_ashtray">Pellets burned to fill the ashtray (kg) :</label>  
							<div class="col-md-3">
								<input id="oko_ashtray" name="oko_ashtray" type="text" class="form-control input-md" required=""  value="1000">
								<span class="help-block">For example: 1000kg of pellets -> full ashtray</span>  
							</div>
    					</div>
					</fieldset> 

					<legend>Analytics</legend>
					<div class="form-group">
						<label class="col-md-4 control-label" for="analytics_enabled">Enable anonymous usage analytics?</label>
						<div class="col-md-3">
							<input id="analytics_enabled" name="analytics_enabled" type="checkbox" checked>
							<span class="help-block">Help improve Okovision by sending anonymous usage statistics. No personal data is sent.</span>
						</div>
					</div>
					
					<fieldset>
    				
    				<!-- Form Name -->
    					<legend>Language</legend>
    					
    					<!-- Select Basic -->
    					<div class="form-group">
    					  <label class="col-md-4 control-label" for="lang_en">Choice :</label>
    					  <div class="col-md-3">
							<label class="radio-inline"><input id="lang_en" type="radio" value="en" name="oko_language" checked><img src="css/images/en-flag.png"></label>
							<label class="radio-inline"><input id="lang_fr" type="radio" value="fr" name="oko_language"><img src="css/images/fr-flag.png"></label>  
    					  </div>
    					 
    					</div>
    					
    				</fieldset> 
			</form>
            
            	<!-- Button -->
					
			<div class="col-md-12" align="center">
				<button id="bt_install" name="bt_install" class="btn btn-primary" type="button">Install</button>
			</div>
			<div class="col-md-12" align="center"></br></div>
			


	 </div> <!-- /container -->
	
	 <!-- Bootstrap core JavaScript
    ================================================== -->
    <!-- Placed at the end of the document so the pages load faster -->
  <script src="js/jquery/jquery.min.js"></script>
	<script src="js/jquery/jquery-ui.min.js"></script>
	<script src="js/bootstrap/bootstrap.min.js"></script>
	<script src="js/bootstrap/bootstrap-notify.min.js"></script>
	<script src="js/highstock/highstock.js"></script>
	
	<script src="_langs/fr.text.js"></script>
	<script src="js/custom.js"></script>
<!--appel des scripts personnels de la page -->
	<script src="js/setup.js"></script>
	<script src="js/listeners.js"></script>
    </body>
</html>