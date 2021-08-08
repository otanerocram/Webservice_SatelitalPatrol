<?php
	/** WEBSERVICE PARA Satellital Patrol. */
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	header('Content-Type: text/html; charset=UTF-8');
  	header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
  	header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
  	header("Cache-Control: no-store, no-cache, must-revalidate");
  	header("Cache-Control: post-check=0, pre-check=0", false);
  	header("Pragma: no-cache");
  	error_reporting(E_ALL);
	date_default_timezone_set('America/Lima');

	// Parametros de Configuracion BD
	$db_server	= 'localhost';
	$db_user	= 'gts';
	$db_pass	= 'gts_pass';
	$db_name	= 'gts';
	$db_port	= 3306;

	$conexion 		= @new mysqli($db_server, $db_user, $db_pass, $db_name, $db_port);

	if ($conexion->connect_error){
		die('Error de conectando a la base de datos: ' . $conexion->connect_error);
	}

	$sqlQuery 	= "SELECT `id`, `placa`, `fecha`, `latitud`, `longitud`, `velocidad`, `rumbo`, `evento`, `odometer`, `estado`, `empresa` FROM WS_Satelital WHERE`estado`='Nuevo' ORDER BY id DESC LIMIT 20;";
	
	$resultado 	= $conexion->query($sqlQuery);

	$server_ip	= "13.85.20.253";
	$server_udp	= 9003;
	
	$reponseData  	= array();
	$htmlTracks		= "";
	$devicesCount	= 0;

	$firstRowID	= 0;
	$lastRowID	= 0;
	$company	= "";
	$tramaUDP	= "";

	if ($resultado->num_rows > 0){

		while($row = $resultado->fetch_array(MYSQLI_ASSOC)){
			
			if ($firstRowID == 0){ $firstRowID = $row['id'];}

			$devicesCount++;

			$tramaEval	= "";

			/*
			]BBT822  270721172531-0542230-0808463303934400100000000000000000[
			]BBT916  270721172510-0458065-0812553905333000100000000000000000[
			*/

			$latitud	= utf8_encode($row['latitud']);
			$longitud	= utf8_encode($row['longitud']);
			$latDec		= explode(".",$latitud);
			$lonDec		= explode(".",$longitud);
			$latInt 	= (int)$latDec[0];
			$lonInt 	= (int)$lonDec[0];

			$tramaEval	.= "]";
			$tramaEval	.= utf8_encode($row['placa'])."  ";
			$tramaEval	.= utf8_encode($row['fecha']);

			if ($latInt >= 0 ){	// entre -9 y +9
				// agregar letra + cuando es mayor o igual a 0
				$tramaEval	.= '+0';
				$tramaEval	.= $latDec[0];
			}

			if ($latInt > -10 && $latInt < 0){	// entre -9 y +9
				// cuando es menor a 0, reemplazar el - por -0
				$tramaEval	.= str_replace("-", "-0", $latInt);
			}else{
				$tramaEval	.= $latDec[0];
			}

			$tramaEval	.= $latDec[1];
			$difNumb	= 5 - strlen($latDec[1]);

			for ($i = 1; $i <= $difNumb; $i++) {
				$tramaEval	.= "0";
			}
			
			if ($lonInt >= 0 && $lonInt < 10){	// entre -9 y +9
				// agregar letra + cuando es mayor o igual a 0
				$tramaEval	.= '+00';
				$tramaEval	.= $lonDec[0];
				
			} else if ($lonInt >= 10 ){	// entre -9 y +9
				// agregar letra + cuando es mayor o igual a 0
				$tramaEval	.= '+0';
				$tramaEval	.= $lonDec[0];
			}
			
			if ($lonInt > -10 && $lonInt < 0){
				// cuando es menor a 0, reemplazar el - por -0
				$tramaEval	.= str_replace("-", "-00", $lonInt);
			} else if ($lonInt < -10){	// entre -9 y +9
				// cuando es menor a 0, reemplazar el - por -0
				$tramaEval	.= str_replace("-", "-0", $lonInt);
			}

			$difNumb	= 5 - strlen($lonDec[1]);

			$tramaEval	.= $lonDec[1];
			
			for ($i = 1; $i <= $difNumb; $i++) {
				$tramaEval	.= "0";
			}

			$velInt	= (int)utf8_encode($row['velocidad']);
			
			if ($velInt > 9 && $velInt < 100){	// 10 - 99
				$tramaEval	.= "0".$velInt;
			} else if ($velInt >= 0 && $velInt < 10){ //0-9
				$tramaEval	.= "00".$velInt;
			} else{
				$tramaEval	.= $velInt;
			}

			$rumboInt		= (int)utf8_encode($row['rumbo']);
			
			if ($rumboInt > 9 && $rumboInt < 100){	// 10 - 99
				$tramaEval	.= "0".$rumboInt;
			} else if ($rumboInt >= 0 && $rumboInt < 10){ //0-9
				$tramaEval	.= "00".$rumboInt;
			} else{
				$tramaEval	.= $rumboInt;
			}
			
			$tramaEval	.= "0";
			$tramaEval	.= "01";

			$tramaEval	.= "000000000000";
			$tramaEval	.= "00000";
			$tramaEval	.= "[";

			$tramaLen	= strlen($tramaEval);

			if ($tramaLen == 65){
				$tramaUDP.=$tramaEval;
			}

			$lastRowID = $row['id'];

    	}

	}else{
		die("Todos los registros han sido enviados! No hay data nueva que enviar...");
	}

	$mensajeUpdate	= "";
	
	$sqlUpdate 		= "UPDATE WS_Satelital SET estado='Sent' WHERE estado='Nuevo' AND id BETWEEN ".$lastRowID." AND ".$firstRowID.";";
	
	if ($conexion->query($sqlUpdate) === TRUE) {
		$mensajeUpdate	= "Tablas actualizadas!  ";
	} else {
		$mensajeUpdate	= "Error actualizando la tabla ".$conexion->error;
	}
	
	mysqli_close($conexion);
	
	$sock 	= socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    $len 	= strlen($tramaUDP);

    socket_sendto($sock, $tramaUDP, $len, 0, $server_ip, $server_udp);
    socket_close($sock);

	
	print_r("  <!DOCTYPE html>\n");
	print_r("  <html lang=\"en\">\n");
	print_r("    <head>\n");
	print_r("      <meta charset=\"utf-8\">\n");
	print_r("      <meta name=\"viewport\" content=\"width=device-width, initial-scale=1, shrink-to-fit=no\">\n");
  	print_r("      <link rel=\"stylesheet\" href=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css\" integrity=\"sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u\" crossorigin=\"anonymous\">");
	print_r("      <title>Rest Client WebService</title>\n");
	print_r("    </head>\n");
	print_r("    <body>\n");
	print_r("      <div class=\"container\">\n");
	print_r("         <nav class=\"navbar navbar-default\">");
	print_r("           <div class=\"container-fluid\">");
	print_r("             <div class=\"navbar-header\">");
	print_r("               <a class=\"navbar-brand\" href=\"#\">");
	print_r("                 WebService ".$server_ip." -> ".$server_udp.".\n");
	print_r("               </a>");
	print_r("             </div>");
	print_r("           </div>");
	print_r("         </nav>");
	print_r("         <div class=\"panel panel-default\">");
	print_r("           <div class=\"panel-body\">");
	print_r("             <hr>");
	print_r("							<pre><code>".$tramaUDP."</code></pre>");
	print_r("           </div>");
	print_r("         </div>");
	print_r("         <div class='mastfoot' align='center'>");
	print_r("           <div class='inner'>");
	print_r("             <p>Sistema desarrollado por  <a href='http://aguilacontrol.com'>AguilaControl</a>, by <a target='_blank' href='https://twitter.com/renato_beltran'>@renato_beltran</a>.</p>");
	print_r("			<p>ID Inicio: ".$lastRowID.", Final: ".$firstRowID." -> ".$mensajeUpdate."</p>");
	print_r("           </div>");
	print_r("         </div>");
	print_r("      </div>\n");
	print_r("    </body>\n");
	print_r("  </html>\n");

?>
