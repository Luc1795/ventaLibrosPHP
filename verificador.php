<?php
include 'global/config.php';
include 'global/conexion.php';
include 'carrito.php';
include 'templates/cabecera.php';
?>
<?php
//print_r($_GET);
//$ClientID = "AWurg7pKaL6Oyb8wtDZr92ReVDrSGOOp0o1fMytnhjTBp8SGnCJtwS3EYrBTpCDmfgjHNzuyq1LhjcLx";
//$Secret = "EJCuGJ8-eUUwmZYPcUw2j62MC6XgLNSc2iCp7UmJA0liwq-d36L9Q3k7SbllJxGsqFYipkzHb-po3j4Y";


$Login = curl_init(LINKAPI."/v1/oauth2/token");
curl_setopt($Login, CURLOPT_SSL_VERIFYPEER, false);//Linea necesaria para desbloquear y quitar la verificacion de seguridad
curl_setopt($Login, CURLOPT_RETURNTRANSFER, true);
curl_setopt($Login, CURLOPT_USERPWD, $CLIENTID . ":" . $SECRET);
curl_setopt($Login, CURLOPT_POSTFIELDS, "grant_type=client_credentials");//Credenciales
$Respuesta = curl_exec($Login);
$objRespuesta = json_decode($Respuesta);
$AccesToken = $objRespuesta->access_token;
//echo "<br>";
//print_r($AccesToken);
//echo "<br><h1>-----------</h1>";
$venta = curl_init(LINKAPI."/v1/payments/payment/" . $_GET['paymentID']);
curl_setopt($venta, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($venta, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Authorization: Bearer " . $AccesToken));

curl_setopt($venta, CURLOPT_RETURNTRANSFER, true);
$RespuestaVenta = curl_exec($venta);
//print_r($RespuestaVenta);
$objDatosTransaccion = json_decode($RespuestaVenta);
//print_r($objDatosTransaccion->payer->payer_info->email);
$state = $objDatosTransaccion->state;
$email = $objDatosTransaccion->payer->payer_info->email;

$total = $objDatosTransaccion->transactions[0]->amount->total;
$currency = $objDatosTransaccion->transactions[0]->amount->currency;
$custom = $objDatosTransaccion->transactions[0]->custom;
//echo $total;

//print_r($custom);
$clave = explode("#", $custom);
$SID = $clave[0];
$claveVenta = openssl_decrypt($clave[1], COD, KEY);
//print_r($claveVenta);
curl_close($venta);
curl_close($Login);
//echo $state;
//echo $claveVenta;
if ($state == "approved") {
    $mensajePaypal = "<h3>Pago aprobado</h3>";
    $sentencia = $pdo->prepare("UPDATE `tblventas` 
SET `PaypalDatos` = :PaypalDatos,
    `status` = 'aprobado' 
WHERE `tblventas`.`ID` = :ID;");
    $sentencia->bindParam(":ID", $claveVenta);
    $sentencia->bindParam(":PaypalDatos", $RespuestaVenta);
    $sentencia->execute();

    $sentencia = $pdo->prepare("UPDATE tblventas SET status='completo'
WHERE ClaveTransaccion=:ClaveTransaccion
AND Total=:TOTAL
AND ID=:ID");
    $sentencia->bindParam(':ClaveTransaccion', $SID);
    $sentencia->bindParam(':TOTAL', $total);
    $sentencia->bindParam(':ID', $claveVenta);
    $sentencia->execute();

    $completado = $sentencia->rowCount();

    session_destroy();//destruye variables de la sesion 

} else {
    $mensajePaypal = "<h3>Hay un problema con el pago de paypal</h3>";
}
//echo $mensajePaypal;

/*$Login = curl_init("https://api.sandbox.paypal.com/v1/oauth2/token");
//$login = curl_init("https://api-m.sandbox.paypal.com/v1/payments");
//curl_setopt($Login, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($Login, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt($Login, CURLOPT_USERPWD, $ClientID . ":" . $Secret);
curl_setopt($Login, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
$Respuesta = curl_exec($Login);

$objRespuesta = json_decode($Respuesta);
$AccessToken = $objRespuesta->access_token;
print_r($AccessToken);

$venta = curl_init("https://api.sandbox.paypal.com/v1/payments/payment/" . $_GET['paymentID']);
curl_setopt($venta, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Authorization: Bearer " . $AccessToken));
//curl_setopt($venta, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Authorization: Bearer " . $AccessToken));
//curl_setopt($venta, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Authorization:Bearer" . $AccessToken));
$RespuestaVenta = curl_exec($venta);
print_r($RespuestaVenta);*/
?>
<div class="jumbotron">
    <h1 class="display-4">¡Listo!</h1>
    <hr class="my-4">
    <p class="lead"><?php echo $mensajePaypal; ?></p>
    <p>
        <?php
        if ($completado >= 1) {

            $sentencia = $pdo->prepare("SELECT * FROM tbldetalleventa,tblproducto 
WHERE tbldetalleventa.IDPRODUCTO=tblproducto.ID 
  AND tbldetalleventa.IDVENTA=:ID");

            $sentencia->bindParam(':ID', $claveVenta);
            $sentencia->execute();

            $listaProductos = $sentencia->fetchAll(PDO::FETCH_ASSOC);
            //print_r($listaProductos);
        }
        ?>

        <div class="row">
            <?php foreach($listaProductos as $producto) { ?>
            <div class="col-2">
            <div class="card">
                <img class="card-img-top" src="<?php echo $producto['Imagen']; ?>">
               
                <div class="card-body">
                <p class="card-text"><?php echo $producto['Nombre']; ?></p>

                <?php if($producto['DESCARGADO']<DESCARGASPERMITIDAS) { ?> <!--SI PRODUCTO DE DESCARGA ES MENOR QUE 1, TIENE MAS POSIBILIDADES DE DESCARGAR SU PRODUCTO-->
                    <form method="post" action="descargas.php">
                        <input type="hidden" name="IDVENTA" id="" value="<?php echo openssl_encrypt($claveVenta, COD, KEY); ?>">
                        <input type="hidden" name="IDPRODUCTO" id="" value="<?php echo openssl_encrypt($producto['IDPRODUCTO'], COD, KEY); ?>">
                    <button class="btn btn-success" type="submit">Descargar</button>
                    </form>
                    <?php }else{?>
                        <button class="btn btn-success" type="button" disabled>Descargar</button>
                        <?php } ?>
    </div>
    </div>
    </div>
    <?php } ?>
    </div>
    </p>
</div>
<?php
include 'templates/pie.php';
?>
