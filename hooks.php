<?php
function getToken()
{
	$application_key = mysql_fetch_array( select_query('tbladdonmodules', 'value', array('module' => 'WHMCS2Pushover', 'setting' => 'key') ), MYSQL_ASSOC );
	return $application_key['value'];
}

function getUsersToPermission($permission)
{
	return full_query("SELECT `access_token`, `supportdepts` FROM `tblpushover_whmcs` as p, `tbladmins` as a WHERE `permissions` LIKE '%". $permission ."%' AND a.id = p.adminid");
}

function push_ClientAdd($vars) {
	global $customadminpath, $CONFIG;

	$administrators  = getUsersToPermission('new_client');

	while($u = mysql_fetch_array( $administrators, MYSQL_ASSOC )){
		sendPush($u['access_token'],
				 'New WHMCS Client',
				 'A new client has signed up!',
				 $CONFIG['SystemURL'].'/'.$customadminpath.'/clientssummary.php?userid='.$vars['userid']);
	}

}

function push_InvoicePaid($vars) {
	global $customadminpath, $CONFIG;

	$administrators  = getUsersToPermission('new_invoice');
    while($u = mysql_fetch_array( $administrators, MYSQL_ASSOC )){
		sendPush($u['access_token'],
				 'An invoice has just been paid',
				 'Invoice #'.$vars['invoiceid'].' has been paid.',
				  $CONFIG['SystemURL'].'/'.$customadminpath.'/invoices.php?action=edit&id='.$vars['invoiceid']);
	}
}

function push_TicketOpen($vars) {
	global $customadminpath, $CONFIG;

	$administrators  = getUsersToPermission('new_ticket');
    while($u = mysql_fetch_array( $administrators, MYSQL_ASSOC )){
    		$arr_dept = explode(',', $u['supportdepts']);
		if(!in_array($vars['deptid'], $arr_dept)) continue;
		sendPush($u['access_token'],
				 'A new ticket has arrived',
				substr($vars['subject'].' (in '.$vars['deptname'].")\n" . $vars['message'], 0, 480)  . '...',
				 $CONFIG['SystemURL'].'/'.$customadminpath.'/supporttickets.php?action=viewticket&id='.$vars['ticketid']);
	}
}

function push_TicketUserReply($vars) {
	global $customadminpath, $CONFIG;
	$administrators  = getUsersToPermission('new_update');
    while($u = mysql_fetch_array( $administrators, MYSQL_ASSOC )){
    		$arr_dept = explode(',', $u['supportdepts']);
		if(!in_array($vars['deptid'], $arr_dept)) continue;
		sendPush($u['access_token'],
				 'A ticket has been updated',
				 substr($vars['subject'].' (in '.$vars['deptname'].")\n" . $vars['message'], 0, 480) . '...',
				 $CONFIG['SystemURL'].'/'.$customadminpath.'/supporttickets.php?action=viewticket&id='.$vars['ticketid']);
	}
}

function push_CancellationRequest($vars) {
	global $customadminpath, $CONFIG;

	$administrators  = getUsersToPermission('new_cancellation');
    while($u = mysql_fetch_array( $administrators, MYSQL_ASSOC )){
		sendPush($u['access_token'],
				 ''.$vars['type'].' Cancellation Request ',
				 substr($vars['relid'].' (by '.$vars['userid'].")\n" . $vars['reason'], 0, 480) . '...',
				  $CONFIG['SystemURL'].'/'.$customadminpath.'/cancelrequests.php');
	}
}


function push_AdminLogin($vars) {
	global $customadminpath, $CONFIG;

	$administrators  = getUsersToPermission('new_adminlogin');
    while($u = mysql_fetch_array( $administrators, MYSQL_ASSOC )){
			sendPush($u['access_token'],
					 ''.$vars['username'].' just logged in..',
					 $CONFIG['SystemURL'].'/'.$customadminpath.'systemadminlog.php');
	}
}


function sendPush( $user, $title = '', $message = '', $url = '')
{
	$token = getToken();
	curlCall("https://api.pushover.net/1/messages.json", array('token' => $token,
					  'user' => $user,
					  'title' => $title,
					  'message' => $message,
					  'url' => $url
					  ));
}

add_hook("ClientAdd",1,"push_ClientAdd");
add_hook("InvoicePaid",1,"push_InvoicePaid");
add_hook("TicketOpen",1,"push_TicketOpen");
add_hook("TicketUserReply",1,"push_TicketUserReply");
add_hook("CancellationRequest",1,"push_CancellationRequest");
add_hook("AdminLogin",1,"push_AdminLogin");


function widget_push_whmcs($vars) {

	if(isset($_POST['action']) && $_POST['action'] == 'sendpush')
	{
		$u = mysql_fetch_array( select_query('tbladmins', 'username', array('id' => $vars['adminid']) ), MYSQL_ASSOC );
		sendPush($_POST['user'], "Message from ". $u['username'], $_POST['message']);
	}

    $title = "Send a Push";

    $rs = full_query("SELECT `tbladmins`.`username` as `user`, `tblpushover_whmcs`.`access_token` as `token`  FROM `tblpushover_whmcs`, `tbladmins` WHERE `tbladmins`.`id` = `tblpushover_whmcs`.`adminid`");
    $content = '
    <script>
    function widgetsendpush()
    {
		$.post("index.php", { action: "sendpush", user: $("#id_send_push_user").val(), message: $("#id_message_push").val() });
		$("#send_push_confirm").slideDown().delay(2000).slideUp();
		$("#id_message_push").val("");
	}
    </script>
    <div id="send_push_confirm" style="display:none;margin:0 0 5px 0;padding:5px 20px;background-color:#DBF3BA;font-weight:bold;color:#6A942C;">Push Sent Successfully!</div>
    ';
    $options = "User: <select id='id_send_push_user'>";
    while($u = mysql_fetch_array( $rs, MYSQL_ASSOC ))
	{
		$options .= "<option value='". $u['token']. "'>". $u['user']. "</option>";
	}
	$options .= "</select>";

	$content .= $options . "<br/><br/><textarea style='width:95%;height:100px;' id='id_message_push'></textarea><br/>";
	$content .= '<input type="button" value="Send push" onclick="widgetsendpush()" />';
    return array('title'=>$title,'content'=> $content);

}

add_hook("AdminHomeWidgets",1,"widget_push_whmcs");
