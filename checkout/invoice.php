<?php
require '../web.php';
require '../lib/is_login.php';
require '../lib/result.php';
require '../lib/csrf_token.php';

$ipaymu = $model->db_query($db, "*", "payment_setting", "id = '2'");
$cp = $model->db_query($db, "*", "payment_setting", "id = '4'");
if (!isset($_SESSION['login'])) {
	$_SESSION['result'] = array('alert' => 'danger', 'title' => 'Otentikasi dibutuhkan!', 'msg' => 'Silahkan masuk ke akun Anda.');
	exit(header("Location: ".$config['web']['base_url']."signin/"));
}
if ($model->db_query($db, "*", "user", "id = '".$_SESSION['login']."'")['count'] == 0) {
	exit("No direct script access allowed!!");
}
if (!isset($_GET['query_invoice'])) {
	exit("No direct script access allowed!!");
}
$id_invoice = mysqli_real_escape_string($db, $_GET['query_invoice']);
$service_information = $model->db_query($db, "*", "cart", "kode_invoice = '".mysqli_real_escape_string($db, $_GET['query_invoice'])."' ");
$layanan_ = $service_information['rows']['service_id'];
$detail_layanan = $model->db_query($db, "*", "services", "id = '$layanan_' "); 
$allow_buyer = $detail_layanan['rows']['allow_buyer_information'];


$id_bank = $db->real_escape_string(trim(htmlspecialchars(htmlentities($_POST['filter_opt']))));
$buyer_information = $db->real_escape_string(trim(htmlspecialchars(htmlentities($_POST['buyer_information']))));

$orderan_masuk = $model->db_query($db, "*", "orders", "kode_unik = '".$service_information['rows']['kode_unik']."' ");
if($buyer_information != null){
$data_for_buyer_information = $model->db_query($db, "*", "cart", "kode_invoice = '".mysqli_real_escape_string($db, $_GET['query_invoice'])."' AND buyer_id = '".$login['id']."' ");
$input_post_orders = array(
'instruction' => $buyer_information,
); 
$update_orders_buyer_information = $model->db_update($db, "orders", $input_post_orders, "kode_unik = '".$data_for_buyer_information['rows']['kode_unik']."' ");
} elseif($orderan_masuk['rows']['instruction'] == null && $allow_buyer == 'yes'){
    $_SESSION['result'] = array('alert' => 'danger', 'title' => 'Gagal!', 'msg' => 'Isi Informasi Dari Penjual');
   exit(header("Location: ".$config['web']['base_url']."checkout/".$id_invoice)); 
}



if(isset($_POST['filter_opt'])){

if($id_bank == "saldo_tersedia"){
    $website = $model->db_query($db, "*", "website", "id = '1'");
    $data_targetss = $model->db_query($db, "*", "cart", "kode_invoice = '".mysqli_real_escape_string($db, $_GET['query_invoice'])."' AND buyer_id = '".$login['id']."' ");
   $data_services = $model->db_query($db, "*", "services", "id = '".$data_targetss['rows']['service_id']."' ");
   $jangka_waktu = $data_services['rows']['jangka_waktu'];
   $now = date("Y-m-d H:i:s");
   $send_before = date('Y-m-d H:i:s',strtotime('+'.$jangka_waktu.' Day',strtotime($now)));
   $total_price_admin = $data_targetss['rows']['total_price_admin'];
   $user_buyer = $model->db_query($db, "*", "user", "id = '".$login['id']."' ");
    if($user_buyer['rows']['saldo_tersedia'] < $total_price_admin){
        $_SESSION['result'] = array('alert' => 'danger', 'title' => 'Gagal!', 'msg' => 'Saldo Anda Tidak Cukup! ');
        exit(header("Location: ".$config['web']['base_url']."checkout/".$id_invoice));
    } else {
        $update_saldo_user = $db->query("UPDATE user set saldo_tersedia = saldo_tersedia-$total_price_admin WHERE id = '".$login['id']."' ");
        $input_post_orders_active = array(
        'status' => 'active',
        'created_at' => $now,
        'send_before' => $send_before
        );
        $update_orders = $model->db_update($db, "orders", $input_post_orders_active, "kode_unik = '".$data_targetss['rows']['kode_unik']."' ");
        $input_post_update_active = array(
        'pembayaran_id_bank' => $id_bank,
        'status' => 'success'
        );
        $update_cart = $model->db_update($db, "cart", $input_post_update_active, "kode_unik = '".$data_targetss['rows']['kode_unik']."' "); 
        
        $order_detail = $model->db_query($db, "*", "orders", "kode_unik = '".$data_targetss['rows']['kode_unik']."' AND buyer_id = '".$login['id']."' ");
        $input_post_penghasilan_admin = array(
        'admin_fee' => $website['rows']['admin_fee'],
        'order_id' => $order_detail['rows']['id'],
        'created_at' => $now
        );
        $insert = $model->db_insert($db, "penghasilan_admin", $input_post_penghasilan_admin);
        
        if($update_orders == true && $update_cart == true && $update_saldo_user == true && $insert == true){
            $update_history_pembayaran = array(
            'user_id' => $login['id'],
            'amount' => $total_price_admin,
            'message' => 'Pembelian Produk #'.$order_detail['rows']['id']." - ".$data_services['rows']['nama_layanan'],
            'created_at' => $now
            );
            $model->db_insert($db, "history_pembayaran", $update_history_pembayaran);
            $update_notifikasi = array(
            'buyer_id' => $login['id'],
            'seller_id' => $data_services['rows']['author'],
            'service_id' => $data_targetss['rows']['service_id'],
            'type' => 'pembelian',
            'go' => "show-sales/".$order_detail['rows']['id'],
            'created_at' => $now
            );
            
            $model->db_insert($db, "notifikasi", $update_notifikasi);
            
            
            
             $email_orderan = $model->db_query($db, "*", "email", "id = '3'");
            $user_penjual = $model->db_query($db, "*", "user", "id = '".$data_services['rows']['author']."' ");			    
            $ke = decrypt($user_penjual['rows']['email']);
            $nohp = decrypt($user_penjual['rows']['no_hp']);
            $message_sm = $config['web']['base_url']."show-sales/".$order_detail['rows']['id'];
            $username = $user_penjual['rows']['username'];
            $message_sms = 'Halo '.$username.' ada pesanan baru nih, silahkan di cek pada '.$message_sm;
            $nama = $user_penjual['rows']['nama'];
            $format = $email_orderan['rows']['email'];
            $pisah = explode("{{link_penjualan}}", $format);
            $orderan_link = $pisah[0].$config['web']['base_url']."show-sales/".$order_detail['rows']['id'].$pisah[1];
            
            $email_invoice = $model->db_query($db, "*", "email", "id = '7'");
            $formats = $email_invoice['rows']['email'];
            $user_pembeli = $model->db_query($db, "*", "user", "id = '".$login['id']."' ");	
            $ke_pembeli = decrypt($user_pembeli['rows']['email']);
            $nama_pembeli  = $user_pembeli['rows']['nama'];
            $subject_pembeli = "Pembayaran Berhasil Untuk Invoice #".$data_targetss['rows']['kode_invoice'];
            $subject = "Pesanan Baru";
            $satu = str_replace("{{logo_website}}",$config['web']['base_url']."file-photo/website/".$website['rows']['logo_web'],$formats);
            $dua = str_replace("{{id_invoice}}",$data_targetss['rows']['kode_invoice'],$satu);
            $tiga = str_replace("{{gif}}",$config['web']['base_url']."img/image-2.gif",$dua);
            $empat = str_replace("{{layanan}}",$data_services['rows']['nama_layanan'],$tiga);
            $lima = str_replace("{{harga_layanan}}",number_format($data_services['rows']['price'],0,',','.'),$empat);
            $enam = str_replace("{{admin_fee}}",number_format($website['rows']['admin_fee'],0,',','.'),$lima);
            $invoice_fix = str_replace("{{amount}}",number_format($data_targetss['rows']['total_price_admin'],0,',','.'),$enam);
            kirim_email($ke_pembeli, $nama_pembeli, $invoice_fix, $subject_pembeli); 
		    kirim_email($ke, $nama, $orderan_link, $subject); 
// 		    if($user_penjual['rows']['terima_wa_orderan'] == '1'){
// $pesan_wa = 'Hallo '.$nama.' ada rejeki baru nih...
// Kamu mendapatkan penjualan dengan ID #'.$order_detail['rows']['id'].'

// Silahkan Login Pada Gubukdigital.net untuk memproses penjualanmu
            
// Pesan Ini Dibuat Secara Otomatis,
// Jika ingin mematikan pemberitahuan via Whatsapp, Silahkan Ketik:
// *matikan notifikasi orderan*

// Atau pada pengaturan akunnmu

// Regards
// GubukDigital.Net';
// $no_hp = decrypt($user_penjual['rows']['no_hp']); 
// kirim_wa_pesan($no_hp, $pesan_wa);
//                                     }
        if($user_penjual['rows']['terima_tele_orderan'] == '1'){
                                           $text = 'Hallo '.$user_penjual['rows']['username'].' ada rejeki baru nih...
Kamu mendapatkan penjualan dengan ID #'.$order_detail['rows']['id'].'

Silahkan Login Pada Gubukdigital.net untuk memproses penjualanmu
            
Pesan Ini Dibuat Secara Otomatis

Regards
Gubuk Digital';
                                        $teks = urlencode($text);
                                        
                                        $chat_id = decrypt($user_penjual['rows']['telegram_id']);
                                        kirim_tele($teks, $chat_id); 
                                        }
		  
		    
		    
            $_SESSION['result'] = array('alert' => 'success', 'title' => 'Berhasil!', 'msg' => 'Pembelian Anda Sudah Berhasil Di Proses ke Penjual ^.^');
            exit(header("Location: ".$config['web']['base_url']."my-orders/"));
            
        } else {
            echo "Something wrong!";
        }
    }
    
} elseif($id_bank == "5"){ // perfectmoney
    
    $website = $model->db_query($db, "*", "website", "id = '1'");
    $data_targetss = $model->db_query($db, "*", "cart", "kode_invoice = '".mysqli_real_escape_string($db, $_GET['query_invoice'])."' AND buyer_id = '".$login['id']."' ");
    $total_price_admin = $data_targetss['rows']['total_price_admin'];
    exit(header("Location: ".$config['web']['base_url']."checkout/perfectmoney/".$id_invoice));
            
    
} elseif($id_bank == "6"){ //midtrans

$website = $model->db_query($db, "*", "website", "id = '1'");
$data_targetss = $model->db_query($db, "*", "cart", "kode_invoice = '".mysqli_real_escape_string($db, $_GET['query_invoice'])."' AND buyer_id = '".$login['id']."' ");
$total_price_admin = $data_targetss['rows']['total_price_admin'];
exit(header("Location: ".$config['web']['base_url']."midtrans/proses/snap-redirect/proses.php?oid=".$id_invoice."&amount=".$total_price_admin));
        

} elseif($id_bank == "17"){ //midtrans BCA VA core API 

$website = $model->db_query($db, "*", "website", "id = '1'");
$data_targetss = $model->db_query($db, "*", "cart", "kode_invoice = '".mysqli_real_escape_string($db, $_GET['query_invoice'])."' AND buyer_id = '".$login['id']."' ");
$total_price_admin = $data_targetss['rows']['total_price_admin'];
exit(header("Location: ".$config['web']['base_url']."midtrans/proses/core-api/checkout-process.php?oid=".$id_invoice."&amount=".$total_price_admin."&action=gopay"));
        

} elseif($id_bank == "16"){ //midtrans BCA VA core API 

$website = $model->db_query($db, "*", "website", "id = '1'");
$data_targetss = $model->db_query($db, "*", "cart", "kode_invoice = '".mysqli_real_escape_string($db, $_GET['query_invoice'])."' AND buyer_id = '".$login['id']."' ");
$total_price_admin = $data_targetss['rows']['total_price_admin'];
exit(header("Location: ".$config['web']['base_url']."midtrans/proses/core-api/checkout-process.php?oid=".$id_invoice."&amount=".$total_price_admin."&action=bni"));
        

} elseif($id_bank == "7"){ // paypal
    
    $website = $model->db_query($db, "*", "website", "id = '1'");
    $data_targetss = $model->db_query($db, "*", "cart", "kode_invoice = '".mysqli_real_escape_string($db, $_GET['query_invoice'])."' AND buyer_id = '".$login['id']."' ");
    $total_price_admin = $data_targetss['rows']['total_price_admin'];
    exit(header("Location: ".$config['web']['base_url']."checkout/paypal/".$id_invoice));
            
    
} elseif($id_bank == "8"){ // BTC
    
    $website = $model->db_query($db, "*", "website", "id = '1'");
    
    $btc = $model->db_query($db, "*", "bank_information", "id = '$id_bank'");
    $data_targetss = $model->db_query($db, "*", "cart", "kode_invoice = '".mysqli_real_escape_string($db, $_GET['query_invoice'])."' AND buyer_id = '".$login['id']."' ");
    $buyer_btc =  $model->db_query($db, "*", "user", "id = '".$data_targetss['rows']['buyer_id']."'");
    $data_services = $model->db_query($db, "*", "services", "id = '".$data_targetss['rows']['service_id']."' ");
    $total_price_admin = $data_targetss['rows']['total_price_admin'];
    $total_price_dalam_dollar = $total_price_admin / $btc['rows']['rate_dollar'];
    // exit(header("Location: ".$config['web']['base_url']."checkout/paypal/".$id_invoice));

/*
	CoinPayments.net API Example
	Copyright 2014-2018 CoinPayments.net. All rights reserved.	
	License: GPLv2 - http://www.gnu.org/licenses/gpl-2.0.txt
*/
	require('../coinpayments/coinpayments.inc.php');
	$cps = new CoinPaymentsAPI();
	$cps->Setup(decrypt($cp['rows']['value_2']), decrypt($cp['rows']['value_1']));

	$req = array(
		'amount' => round($total_price_dalam_dollar, 2),
		'currency1' => 'USD',
		'currency2' => 'BTC',
		'buyer_email' => $buyer_btc['rows']['email'],
		'item_name' => $data_services['rows']['nama_layanan'],
		'item_number' => mysqli_real_escape_string($db, $_GET['query_invoice']),
		'address' => '', // leave blank send to follow your settings on the Coin Settings page
		'ipn_url' => $config['web']['base_url'].'coinpayments/ipnhandler.php',
		'success_url' => $config['web']['base_url'].'payment-status/success.php',
		'cancel_url' => $config['web']['base_url'],
	);
	// See https://www.coinpayments.net/apidoc-create-transaction for all of the available fields
			
	$result = $cps->CreateTransaction($req);
	if ($result['error'] == 'ok') {
		$le = php_sapi_name() == 'cli' ? "\n" : '<br />';
		$input_id_bank = array(
        'pembayaran_id_bank' => $id_bank,
        'tx_id_paypal' => $result['result']['txn_id'],
        'url_coinpayment' => $result['result']['status_url'],
        );
        $update_cart = $model->db_update($db, "cart", $input_id_bank, "kode_unik = '".$data_targetss['rows']['kode_unik']."' ");
		exit(header("Location: ".$result['result']['checkout_url']));
	} else {
		$_SESSION['result'] = array('alert' => 'danger', 'title' => 'Gagal!', 'msg' => 'Terjadi Kesalahan, Silahkan Hubungi Admin Website! Code (01)');
            exit(header("Location: ".$config['web']['base_url']."checkout/".mysqli_real_escape_string($db, $_GET['query_invoice'])));
	}
     
    
} elseif($id_bank == "11"){ // IPAYMU QRIS 

         $website = $model->db_query($db, "*", "website", "id = '1'");
        $data_targetss = $model->db_query($db, "*", "cart", "kode_invoice = '".mysqli_real_escape_string($db, $_GET['query_invoice'])."' AND buyer_id = '".$login['id']."' ");
       $data_services = $model->db_query($db, "*", "services", "id = '".$data_targetss['rows']['service_id']."' ");
       $jangka_waktu = $data_services['rows']['jangka_waktu'];
       $now = date("Y-m-d H:i:s");
       $send_before = date('Y-m-d H:i:s',strtotime('+'.$jangka_waktu.' Day',strtotime($now)));
       $total_price_admin = $data_targetss['rows']['total_price_admin'];
       $user_buyer = $model->db_query($db, "*", "user", "id = '".$login['id']."' ");
        // SAMPLE HIT API iPaymu v2 PHP //

        $va           = decrypt($ipaymu['rows']['value_1']); //get on iPaymu dashboard
        $secret       = decrypt($ipaymu['rows']['value_2']); //get on iPaymu dashboard
    
        $url          = 'https://my.ipaymu.com/api/v2/payment'; //url
        $method       = 'POST'; //method
    
        //Request Body//
        $body['product']    = array($data_services['rows']['nama_layanan']);
        $body['qty']        = array('1');
        $body['price']      = array($total_price_admin);
        $body['returnUrl']  = $config['web']['base_url'].'payment-status/success';
        $body['cancelUrl']  = $config['web']['base_url'].'payment-status/cancel';
        $body['notifyUrl']  = $config['web']['base_url'].'payment-status/ipaymu.php';
        $body['buyerName']  = $user_buyer['rows']['nama'];
        $body['buyerEmail']  = decrypt($user_buyer['rows']['email']);
        $body['buyerPhone']  = decrypt($user_buyer['rows']['no_hp']);
        $body['paymentMethod']  = 'qris';
        //End Request Body//
    
        //Generate Signature
        // *Don't change this
        $jsonBody     = json_encode($body, JSON_UNESCAPED_SLASHES);
        $requestBody  = strtolower(hash('sha256', $jsonBody));
        $stringToSign = strtoupper($method) . ':' . $va . ':' . $requestBody . ':' . $secret;
        $signature    = hash_hmac('sha256', $stringToSign, $secret);
        $timestamp    = Date('YmdHis');
        //End Generate Signature
    
    
        $ch = curl_init($url);
    
        $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
            'va: ' . $va,
            'signature: ' . $signature,
            'timestamp: ' . $timestamp
        );
    
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
        curl_setopt($ch, CURLOPT_POST, count($body));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
    
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $err = curl_error($ch);
        $ret = curl_exec($ch);
        curl_close($ch);
        if($err) {
            echo $err;
        } else {
    
            //Response
            $ret = json_decode($ret);
            if($ret->Status == 200) {
                $sessionId  = $ret->Data->SessionID;
                $url        =  $ret->Data->Url;
                $input_id_bank = array(
                'pembayaran_id_bank' => $id_bank,
                'tx_id_paypal' => $sessionId,
                'url_coinpayment' => $url,
                );
                $update_cart = $model->db_update($db, "cart", $input_id_bank, "kode_unik = '".$data_targetss['rows']['kode_unik']."' ");
                
                header('Location:' . $url);
            } else {
                $_SESSION['result'] = array('alert' => 'danger', 'title' => 'Gagal!', 'msg' => 'Terjadi Kesalahan, Silahkan Hubungi Admin Website! Code (03)');
            exit(header("Location: ".$config['web']['base_url']."checkout/".mysqli_real_escape_string($db, $_GET['query_invoice'])));
            }
            //End Response
        }


    
} elseif($id_bank == "12"){ // IPAYMU VA

         $website = $model->db_query($db, "*", "website", "id = '1'");
        $data_targetss = $model->db_query($db, "*", "cart", "kode_invoice = '".mysqli_real_escape_string($db, $_GET['query_invoice'])."' AND buyer_id = '".$login['id']."' ");
       $data_services = $model->db_query($db, "*", "services", "id = '".$data_targetss['rows']['service_id']."' ");
       $jangka_waktu = $data_services['rows']['jangka_waktu'];
       $now = date("Y-m-d H:i:s");
       $send_before = date('Y-m-d H:i:s',strtotime('+'.$jangka_waktu.' Day',strtotime($now)));
       $total_price_admin = $data_targetss['rows']['total_price_admin'];
       $user_buyer = $model->db_query($db, "*", "user", "id = '".$login['id']."' ");
        // SAMPLE HIT API iPaymu v2 PHP //

        $va           = decrypt($ipaymu['rows']['value_1']); //get on iPaymu dashboard
        $secret       = decrypt($ipaymu['rows']['value_2']); //get on iPaymu dashboard
    
        $url          = 'https://my.ipaymu.com/api/v2/payment'; //url
        $method       = 'POST'; //method
    
        //Request Body//
        $body['product']    = array($data_services['rows']['nama_layanan']);
        $body['qty']        = array('1');
        $body['price']      = array($total_price_admin);
        $body['returnUrl']  = $config['web']['base_url'].'payment-status/success';
        $body['cancelUrl']  = $config['web']['base_url'].'payment-status/cancel';
        $body['notifyUrl']  = $config['web']['base_url'].'payment-status/ipaymu.php';
        $body['buyerName']  = $user_buyer['rows']['nama'];
        $body['buyerEmail']  = decrypt($user_buyer['rows']['email']);
        $body['buyerPhone']  = decrypt($user_buyer['rows']['no_hp']);
        $body['paymentMethod']  = 'va';
        //End Request Body//
    
        //Generate Signature
        // *Don't change this
        $jsonBody     = json_encode($body, JSON_UNESCAPED_SLASHES);
        $requestBody  = strtolower(hash('sha256', $jsonBody));
        $stringToSign = strtoupper($method) . ':' . $va . ':' . $requestBody . ':' . $secret;
        $signature    = hash_hmac('sha256', $stringToSign, $secret);
        $timestamp    = Date('YmdHis');
        //End Generate Signature
    
    
        $ch = curl_init($url);
    
        $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
            'va: ' . $va,
            'signature: ' . $signature,
            'timestamp: ' . $timestamp
        );
    
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
        curl_setopt($ch, CURLOPT_POST, count($body));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
    
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $err = curl_error($ch);
        $ret = curl_exec($ch);
        curl_close($ch);
        if($err) {
            echo $err;
        } else {
    
            //Response
            $ret = json_decode($ret);
            if($ret->Status == 200) {
                $sessionId  = $ret->Data->SessionID;
                $url        =  $ret->Data->Url;
                $input_id_bank = array(
                'pembayaran_id_bank' => $id_bank,
                'tx_id_paypal' => $sessionId,
                'url_coinpayment' => $url,
                );
                $update_cart = $model->db_update($db, "cart", $input_id_bank, "kode_unik = '".$data_targetss['rows']['kode_unik']."' ");
                
                header('Location:' . $url);
            } else {
                $_SESSION['result'] = array('alert' => 'danger', 'title' => 'Gagal!', 'msg' => 'Terjadi Kesalahan, Silahkan Hubungi Admin Website! Code (03)');
            exit(header("Location: ".$config['web']['base_url']."checkout/".mysqli_real_escape_string($db, $_GET['query_invoice'])));
            }
            //End Response
        }


    
} elseif($id_bank == "15"){ // LTC
    
    $website = $model->db_query($db, "*", "website", "id = '1'");
    
    $btc = $model->db_query($db, "*", "bank_information", "id = '$id_bank'");
    $data_targetss = $model->db_query($db, "*", "cart", "kode_invoice = '".mysqli_real_escape_string($db, $_GET['query_invoice'])."' AND buyer_id = '".$login['id']."' ");
    $buyer_btc =  $model->db_query($db, "*", "user", "id = '".$data_targetss['rows']['buyer_id']."'");
    $data_services = $model->db_query($db, "*", "services", "id = '".$data_targetss['rows']['service_id']."' ");
    $total_price_admin = $data_targetss['rows']['total_price_admin'];
    $total_price_dalam_dollar = $total_price_admin / $btc['rows']['rate_dollar'];
    // exit(header("Location: ".$config['web']['base_url']."checkout/paypal/".$id_invoice));

/*
	CoinPayments.net API Example
	Copyright 2014-2018 CoinPayments.net. All rights reserved.	
	License: GPLv2 - http://www.gnu.org/licenses/gpl-2.0.txt
*/
	require('../coinpayments/coinpayments.inc.php');
	$cps = new CoinPaymentsAPI();
	$cps->Setup(decrypt($cp['rows']['value_2']), decrypt($cp['rows']['value_1']));

	$req = array(
		'amount' => round($total_price_dalam_dollar, 2),
		'currency1' => 'USD',
		'currency2' => 'LTC',
		'buyer_email' => $buyer_btc['rows']['email'],
		'item_name' => $data_services['rows']['nama_layanan'],
		'item_number' => mysqli_real_escape_string($db, $_GET['query_invoice']),
		'address' => '', // leave blank send to follow your settings on the Coin Settings page
		'ipn_url' => $config['web']['base_url'].'coinpayments/ipnhandler.php',
		'success_url' => $config['web']['base_url'].'payment-status/success.php',
		'cancel_url' => $config['web']['base_url'],
	);
	// See https://www.coinpayments.net/apidoc-create-transaction for all of the available fields
			
	$result = $cps->CreateTransaction($req);
	if ($result['error'] == 'ok') {
		$le = php_sapi_name() == 'cli' ? "\n" : '<br />';
		$input_id_bank = array(
        'pembayaran_id_bank' => $id_bank,
        'tx_id_paypal' => $result['result']['txn_id'],
        'url_coinpayment' => $result['result']['status_url'],
        );
        $update_cart = $model->db_update($db, "cart", $input_id_bank, "kode_unik = '".$data_targetss['rows']['kode_unik']."' ");
		exit(header("Location: ".$result['result']['checkout_url']));
	} else {
		$_SESSION['result'] = array('alert' => 'danger', 'title' => 'Gagal!', 'msg' => 'Terjadi Kesalahan, Silahkan Hubungi Admin Website! Code (01)');
            exit(header("Location: ".$config['web']['base_url']."checkout/".mysqli_real_escape_string($db, $_GET['query_invoice'])));
	}
     
    
} elseif($id_bank == "13"){ // IPAYMU ALFA/INDOMART

         $website = $model->db_query($db, "*", "website", "id = '1'");
        $data_targetss = $model->db_query($db, "*", "cart", "kode_invoice = '".mysqli_real_escape_string($db, $_GET['query_invoice'])."' AND buyer_id = '".$login['id']."' ");
       $data_services = $model->db_query($db, "*", "services", "id = '".$data_targetss['rows']['service_id']."' ");
       $jangka_waktu = $data_services['rows']['jangka_waktu'];
       $now = date("Y-m-d H:i:s");
       $send_before = date('Y-m-d H:i:s',strtotime('+'.$jangka_waktu.' Day',strtotime($now)));
       $total_price_admin = $data_targetss['rows']['total_price_admin'];
       $user_buyer = $model->db_query($db, "*", "user", "id = '".$login['id']."' ");
        // SAMPLE HIT API iPaymu v2 PHP //

        $va           = decrypt($ipaymu['rows']['value_1']); //get on iPaymu dashboard
        $secret       = decrypt($ipaymu['rows']['value_2']); //get on iPaymu dashboard
    
        $url          = 'https://my.ipaymu.com/api/v2/payment'; //url
        $method       = 'POST'; //method
    
        //Request Body//
        $body['product']    = array($data_services['rows']['nama_layanan']);
        $body['qty']        = array('1');
        $body['price']      = array($total_price_admin);
        $body['returnUrl']  = $config['web']['base_url'].'payment-status/success';
        $body['cancelUrl']  = $config['web']['base_url'].'payment-status/cancel';
        $body['notifyUrl']  = $config['web']['base_url'].'payment-status/ipaymu.php';
        $body['buyerName']  = $user_buyer['rows']['nama'];
        $body['buyerEmail']  = decrypt($user_buyer['rows']['email']);
        $body['buyerPhone']  = decrypt($user_buyer['rows']['no_hp']);
        $body['paymentMethod']  = 'cstore';
        //End Request Body//
    
        //Generate Signature
        // *Don't change this
        $jsonBody     = json_encode($body, JSON_UNESCAPED_SLASHES);
        $requestBody  = strtolower(hash('sha256', $jsonBody));
        $stringToSign = strtoupper($method) . ':' . $va . ':' . $requestBody . ':' . $secret;
        $signature    = hash_hmac('sha256', $stringToSign, $secret);
        $timestamp    = Date('YmdHis');
        //End Generate Signature
    
    
        $ch = curl_init($url);
    
        $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
            'va: ' . $va,
            'signature: ' . $signature,
            'timestamp: ' . $timestamp
        );
    
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
        curl_setopt($ch, CURLOPT_POST, count($body));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
    
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $err = curl_error($ch);
        $ret = curl_exec($ch);
        curl_close($ch);
        if($err) {
            echo $err;
        } else {
    
            //Response
            $ret = json_decode($ret);
            if($ret->Status == 200) {
                $sessionId  = $ret->Data->SessionID;
                $url        =  $ret->Data->Url;
                $input_id_bank = array(
                'pembayaran_id_bank' => $id_bank,
                'tx_id_paypal' => $sessionId,
                'url_coinpayment' => $url,
                );
                $update_cart = $model->db_update($db, "cart", $input_id_bank, "kode_unik = '".$data_targetss['rows']['kode_unik']."' ");
                
                header('Location:' . $url);
            } else {
                $_SESSION['result'] = array('alert' => 'danger', 'title' => 'Gagal!', 'msg' => 'Terjadi Kesalahan, Silahkan Hubungi Admin Website! Code (03)');
            exit(header("Location: ".$config['web']['base_url']."checkout/".mysqli_real_escape_string($db, $_GET['query_invoice'])));
            }
            //End Response
        }


    
} elseif($id_bank == "14"){ // IPAYMU BCA

         $website = $model->db_query($db, "*", "website", "id = '1'");
        $data_targetss = $model->db_query($db, "*", "cart", "kode_invoice = '".mysqli_real_escape_string($db, $_GET['query_invoice'])."' AND buyer_id = '".$login['id']."' ");
       $data_services = $model->db_query($db, "*", "services", "id = '".$data_targetss['rows']['service_id']."' ");
       $jangka_waktu = $data_services['rows']['jangka_waktu'];
       $now = date("Y-m-d H:i:s");
       $send_before = date('Y-m-d H:i:s',strtotime('+'.$jangka_waktu.' Day',strtotime($now)));
       $total_price_admin = $data_targetss['rows']['total_price_admin'];
       $user_buyer = $model->db_query($db, "*", "user", "id = '".$login['id']."' ");
        // SAMPLE HIT API iPaymu v2 PHP //

        $va           = decrypt($ipaymu['rows']['value_1']); //get on iPaymu dashboard
        $secret       = decrypt($ipaymu['rows']['value_2']); //get on iPaymu dashboard
    
        $url          = 'https://my.ipaymu.com/api/v2/payment'; //url
        $method       = 'POST'; //method
    
        //Request Body//
        $body['product']    = array($data_services['rows']['nama_layanan']);
        $body['qty']        = array('1');
        $body['price']      = array($total_price_admin);
        $body['returnUrl']  = $config['web']['base_url'].'payment-status/success';
        $body['cancelUrl']  = $config['web']['base_url'].'payment-status/cancel';
        $body['notifyUrl']  = $config['web']['base_url'].'payment-status/ipaymu.php';
        $body['buyerName']  = $user_buyer['rows']['nama'];
        $body['buyerEmail']  = decrypt($user_buyer['rows']['email']);
        $body['buyerPhone']  = decrypt($user_buyer['rows']['no_hp']);
        $body['paymentMethod']  = 'banktransfer';
        //End Request Body//
    
        //Generate Signature
        // *Don't change this
        $jsonBody     = json_encode($body, JSON_UNESCAPED_SLASHES);
        $requestBody  = strtolower(hash('sha256', $jsonBody));
        $stringToSign = strtoupper($method) . ':' . $va . ':' . $requestBody . ':' . $secret;
        $signature    = hash_hmac('sha256', $stringToSign, $secret);
        $timestamp    = Date('YmdHis');
        //End Generate Signature
    
    
        $ch = curl_init($url);
    
        $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
            'va: ' . $va,
            'signature: ' . $signature,
            'timestamp: ' . $timestamp
        );
    
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
        curl_setopt($ch, CURLOPT_POST, count($body));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
    
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $err = curl_error($ch);
        $ret = curl_exec($ch);
        curl_close($ch);
        if($err) {
            echo $err;
        } else {
    
            //Response
            $ret = json_decode($ret);
            if($ret->Status == 200) {
                $sessionId  = $ret->Data->SessionID;
                $url        =  $ret->Data->Url;
                $input_id_bank = array(
                'pembayaran_id_bank' => $id_bank,
                'tx_id_paypal' => $sessionId,
                'url_coinpayment' => $url,
                );
                $update_cart = $model->db_update($db, "cart", $input_id_bank, "kode_unik = '".$data_targetss['rows']['kode_unik']."' ");
                
                exit(header("Location: ".$url));
            } else {
                $_SESSION['result'] = array('alert' => 'danger', 'title' => 'Gagal!', 'msg' => 'Terjadi Kesalahan, Silahkan Hubungi Admin Website! Code (03)');
            exit(header("Location: ".$config['web']['base_url']."checkout/".mysqli_real_escape_string($db, $_GET['query_invoice'])));
            }
            //End Response
        }


    
} else {
       $data_targetsa = $model->db_query($db, "*", "cart", "kode_invoice = '".mysqli_real_escape_string($db, $_GET['query_invoice'])."' AND buyer_id = '".$login['id']."' ");
        $input_post_update = array(
        'pembayaran_id_bank' => $id_bank,
        );
        $update = $model->db_update($db, "cart", $input_post_update, "kode_unik = '".$data_targetsa['rows']['kode_unik']."' "); 
    }
    
}


$invoice_cart = $model->db_query($db, "*", "cart", "kode_invoice = '".mysqli_real_escape_string($db, $_GET['query_invoice'])."' AND buyer_id = '".$login['id']."' ");

if($invoice_cart['rows']['pembayaran_id_bank'] == null){
    $_SESSION['result'] = array('alert' => 'danger', 'title' => 'Gagal!', 'msg' => 'Anda Belum memilih Metode Pembayaran! ');
    exit(header("Location: ".$config['web']['base_url']."checkout/".$id_invoice));
}




$website = $model->db_query($db, "*", "website", "id = '1'");
$data_targets = $model->db_query($db, "*", "cart", "kode_invoice = '".mysqli_real_escape_string($db, $_GET['query_invoice'])."' AND buyer_id = '".$login['id']."' ");
if($data_targets['count'] == 0){
    exit(header("Location: ".$config['web']['base_url']));
}
if($data_targets['rows']['status'] == 'pending'){
    $status_invoice = 'Menunggu Pembayaran';
    $warna = "orange";
} elseif($data_targets['rows']['status'] == 'success'){
    $status_invoice = 'Pembayaran Diterima';
    $warna = "green";
} else {
    $status_invoice = 'Gagal Melakukan Pembayaran';
    $warna = "red";
}


$data_service = $model->db_query($db, "*", "services", "id = '".$data_targets['rows']['service_id']."' ");

$data_user = $model->db_query($db, "*", "user", "id = '".$login['id']."' ");


if($data_targets['rows']['pembayaran_id_bank'] == '1'){
    $bank_infor = $model->db_query($db, "*", "bank_information", "id = '".$data_targets['rows']['pembayaran_id_bank']."' ");
    $pembayaran = $bank_infor['rows']['bank'];
    $nama_admin = $bank_infor['rows']['nama_pemilik_bank'];
    $norek_admin = $bank_infor['rows']['no_rek'];
     $message_invoice = "Detail Pembayaran <br>".$pembayaran." ".$norek_admin." A.n ".$nama_admin."<br>Bayar Sebelum : ".format_date(substr($data_targets['rows']['expired_date'], 0, -9)).", ".substr($data_targets['rows']['expired_date'], 11, -3)." UTC+7";
   $total_bayar = $data_targets['rows']['price_kode_unik'];
} elseif($data_targets['rows']['pembayaran_id_bank'] == '16'){
    if($data_targets['rows']['tx_id_paypal'] == 'bni'){
        $virtual_number = 'BNI Virtual Accoount';
        $norek_admin = $data_targets['rows']['url_coinpayment'];
    } 
    $message_invoice = "Detail Pembayaran <br>".$virtual_number."<br> Virtual Number: <strong><font color='blue'>".$norek_admin."</font></strong> <br>Bayar Sebelum : ".format_date(substr($data_targets['rows']['expired_date'], 0, -9)).", ".substr($data_targets['rows']['expired_date'], 11, -3)." UTC+7";
    $total_bayar = $data_targets['rows']['total_price_admin'];
} elseif($data_targets['rows']['pembayaran_id_bank'] == '17'){
    
        $virtual_number = 'QRIS/E-Money';
        $norek_admin = $data_targets['rows']['url_coinpayment'];
 
    $message_invoice = "Detail Pembayaran <br>".$virtual_number."<br> Scan QR Code Dibawah menggunakan QRIS/E-Money <br><img src='$norek_admin' width='250px' height='250px'> <br>Bayar Sebelum : ".format_date(substr($data_targets['rows']['expired_date'], 0, -9)).", ".substr($data_targets['rows']['expired_date'], 11, -3)." UTC+7";
    $total_bayar = $data_targets['rows']['total_price_admin'];
} else {
    exit(header("Location: ".$config['web']['base_url'])); 
}

$title = "Invoice #".$id_invoice;

require '../template/header.php';
require '../template/header-dashboard.php';
?>

<section class="dashboard-area">
        <div class="dashboard_contents section--padding">
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        <div class="dashboard_title_area">
                            <div class="">
                                <div class="dashboard__title">
                                    <h3>Invoice</h3>
                                </div>
                            </div>
                            <div class="ml-auto">
                                <a href="#" class="btn btn-sm btn-secondary print_btn">
                                    <span class="icon-printer"></span>Print</a>
                                <a href="#" class="btn btn-sm btn-primary">Download</a>
                            </div>
                        </div>
                    </div><!-- ends: .col-md-12 -->
                    <div class="col-md-12">
                        <div class="invoice">
                            <div class="invoice__head">
                                <div class="invoice_logo">
                                    <img src="../img/logo.png" alt="">
                                </div>
                                <div class="info">
                                    <h4>Invoice</h4>
                                    <p>#<?=$data_targets['rows']['kode_invoice']?></p>
                                </div>
                            </div><!-- ends: .invoice__head -->
                            <div class="invoice__meta">
                                <div class="address">
                                    <h5 class="bold"><?=$website['rows']['title']?></h5>
                                    <p><?=$message_invoice?></p>
                                </div>
                                <div class="date_info">
                                    <p>
                                        <span>Tanggal Invoice</span><?= format_date(substr($data_targets['rows']['created_at'], 0, -9)).", ".substr($data_targets['rows']['created_at'], 11, -3); ?></p>
                                    <p>
                                        <span>Jatuh Tempo</span><?= format_date(substr($data_targets['rows']['expired_date'], 0, -9)).", ".substr($data_targets['rows']['expired_date'], 11, -3); ?></p>
                                    <p>
                                        <span>Status</span><font color="<?=$warna?>"><?=$status_invoice?></font></p>
                                </div>
                            </div><!-- ends: .invoice__meta -->
                            <div class="table-responsive invoice__detail">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Pembeli</th>
                                            <th>Produk</th>
                                            <th>Harga</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><?= format_date(substr($data_targets['rows']['created_at'], 0, -9)); ?></td>
                                            <td class="author"><?=$data_user['rows']['nama']?></td>
                                            <td class="detail">
                                                <a href="<?=$config['web']['base_url']?>product/<?=$data_service['rows']['id']?>/<?=$data_service['rows']['url']?>"><?=$data_service['rows']['nama_layanan']?></a>
                                            </td>
                                            <td>Rp <?= number_format($data_targets['rows']['price'],0,',','.') ?></td>
                                        </tr>
                                        <?
                                        if($data_targets['rows']['extra_product'] != null){
                                        ?>
                                        <tr>
                                            <td><?= format_date(substr($data_targets['rows']['created_at'], 0, -9)); ?></td>
                                            <td class="author"><?=$data_user['rows']['nama']?></td>
                                            <td class="detail">
                                                Extra : <a><?=$data_targets['rows']['extra_product']?></a>
                                            </td>
                                            <td>Rp <?= number_format($data_targets['rows']['price_extra_product'],0,',','.') ?></td>
                                        </tr>
                                        <?    
                                        } if($data_targets['rows']['extra_product1'] != null){
                                        ?>    
                                        <tr>
                                            <td><?= format_date(substr($data_targets['rows']['created_at'], 0, -9)); ?></td>
                                            <td class="author"><?=$data_user['rows']['nama']?></td>
                                            <td class="detail">
                                                Extra : <a><?=$data_targets['rows']['extra_product1']?></a>
                                            </td>
                                            <td>Rp <?= number_format($data_targets['rows']['price_extra_product1'],0,',','.') ?></td>
                                        </tr>
                                        <?    
                                        } if($data_targets['rows']['extra_product2'] != null){
                                        ?>    
                                        <tr>
                                            <td><?= format_date(substr($data_targets['rows']['created_at'], 0, -9)); ?></td>
                                            <td class="author"><?=$data_user['rows']['nama']?></td>
                                            <td class="detail">
                                                Extra : <a><?=$data_targets['rows']['extra_product2']?></a>
                                            </td>
                                            <td>Rp <?= number_format($data_targets['rows']['price_extra_product2'],0,',','.') ?></td>
                                        </tr>
                                        <?    
                                        }
                                        ?>
                                    </tbody>
                                </table>
                                <div class="pricing_info">
                                    <span>Biaya Admin : Rp <?= number_format($data_targets['rows']['biaya_admin'],0,',','.') ?></span><br><br>
                                    <p class="bold">Total Pembayaran : Rp <?= number_format($total_bayar,0,',','.') ?></p>
                                    
                                    <?
                                    if($data_targets['rows']['pembayaran_id_bank'] == '1'){
                                    ?>
                                    <span>Note : Harap Transfer Sesuai Kode Unik</span>
                                    <?
                                    }
                                    ?>
                                </div>
                            </div><!-- ends: .invoice_detail -->
                        </div><!-- ends: .invoice -->
                    </div><!-- ends: .col-md-12 -->
                </div><!-- ends: .row -->
            </div><!-- ends: .container --> 
        </div><!-- ends: .dashboard_menu_area -->
    </section><!-- ends: .dashboard-area -->
    
<?php
require '../template/footer.php';

?>