<?php

if (!defined('_VALID_MOS') && !defined('_JEXEC')) die('Direct Access to '.basename(__FILE__).' is not allowed.');

if (!class_exists('vmPSPlugin')) require(JPATH_VM_PLUGINS.DS.'vmpsplugin.php');

class plgVmPaymentMultibanco extends vmPSPlugin {

    public static $_this = false;

    function __construct( & $subject, $config) {

        parent::__construct($subject, $config);

        $this->_loggable = true;
        $this->tableFields = array_keys($this->getTableSQLFields());

        $varsToPush = array('entidade' => array(0000, 'char'), 'subentidade' => array(000, 'char'), 'chaveantiphishing' => array('', 'char'), 'payment_currency' => array(0, 'char'), 'status_pending' => array(0, 'char'), 'payment_logos' => array('', 'char'), 'countries' => array('', 'char')

        );

        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    protected function getVmPluginCreateTableSQL() {
        return $this->createTableSQL('Payment Multibanco Table');
    }

    function getTableSQLFields() {
        $SQLfields = array('id' => 'int(1) unsigned NOT NULL AUTO_INCREMENT', 'virtuemart_order_id' => 'int(11) UNSIGNED DEFAULT NULL', 'order_number' => 'char(32) DEFAULT NULL', 'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED DEFAULT NULL', 'payment_name' => 'char(255) NOT NULL DEFAULT \'\' ', 'payment_currency' => 'char(3) ', 'entity' => ' char(10)  DEFAULT NULL', 'subentity' => ' char(10)  DEFAULT NULL', 'referencia' => ' char(10)  DEFAULT NULL', 'value' => ' decimal(15,5) NOT NULL DEFAULT \'0.00\' ', 'tax_id' => 'smallint(11) DEFAULT NULL', 'estado' => 'smallint(1) DEFAULT 0');

        return $SQLfields;
    }

    function plgVmConfirmedOrder($cart, $order) {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $lang = JFactory::getLanguage();
        $filename = 'com_virtuemart';
        $lang->load($filename, JPATH_ADMINISTRATOR);
        $vendorId = 0;

        $html = "";

        if (!class_exists('VirtueMartModelOrders')) require(JPATH_VM_ADMINISTRATOR.DS.'models'.DS.'orders.php');
        $this->getPaymentCurrency($method);
        // END printing out HTML Form code (Payment Extra Info)
        $q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="'.$method->payment_currency.'" ';
        $db = & JFactory::getDBO();
        $db->setQuery($q);
        $currency_code_3 = $db->loadResult();
        $paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
        $totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);
        $cd = CurrencyDisplay::getInstance($cart->pricesCurrency);

        $subent = $method->subentidade;

        $this->_virtuemart_paymentmethod_id = $order['details']['BT']->virtuemart_paymentmethod_id;
        $dbValues['payment_name'] = $this->renderPluginName($method);
        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['virtuemart_paymentmethod_id'] = $this->_virtuemart_paymentmethod_id;
        $dbValues['entity'] = $method->entidade;
        $dbValues['payment_currency'] = $currency_code_3;
        $dbValues['value'] = $totalInPaymentCurrency;
        $dbValues['subentity'] = $method->subentidade;
        $dbValues['referencia'] = GenerateMbRef($dbValues['entity'], $subent, $order['details']['BT']->virtuemart_order_id, $dbValues['value'], 'refonly');
        $dbValues['tax_id'] = 0;
        $this->storePSPluginInternalData($dbValues);



        $html = GenerateMbRef($dbValues['entity'], $subent, $order['details']['BT']->virtuemart_order_id, $dbValues['value'], 'email');
        $html2 = GenerateMbRef($dbValues['entity'], $subent, $order['details']['BT']->virtuemart_order_id, $dbValues['value'], 'front');


        $modelOrder = VmModel::getModel('orders');
        $order['order_status'] = ((isset($method->status_pending) and $method->status_pending != ""
        and $method->status_pending != "C") ? $method->status_pending : 'U');
        $order['customer_notified'] = 1;

        $order['comments'] = $html;
        $modelOrder->updateStatusForOneOrder($order['details']['BT']->virtuemart_order_id, $order, true);

		
        //We delete the old stuff
        $cart->emptyCart();
        JRequest::setVar('html', $html2);
        return true;


    }

    /**
     * Display stored payment data for an order
     *
     */
    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id) {
        if (!$this->selectedThisByMethodId($virtuemart_payment_id)) {
            return null; // Another method was selected, do nothing
        }

        $db = JFactory::getDBO();

        $q = 'SELECT * FROM `'.$this->_tablename.'` '.'WHERE `virtuemart_order_id` = '.$virtuemart_order_id;
        $db->setQuery($q);
        if (!($paymentTable = $db->loadObject())) {
            vmWarn(500, $q." ".$db->getErrorMsg());
            //return '';
        }
        
        $this->getPaymentCurrency($paymentTable);

        $html = '<table class="adminlist">'."\n";
        $html .= $this->getHtmlHeaderBE();
        $html .= $this->getHtmlRowBE('STANDARD_PAYMENT_NAME', $paymentTable->payment_name);
        $html .= '</table>'."\n";
        /*$html .= '<div style="font-family:Arial;font-size:10pt;width: 200px;border: 1px solid #2e99d4;">
					<img src="https://ifthenpay.com/mb.png" style="height:80px;width:80px;display: block;margin-left: auto;margin-right: auto;">
					<p style="line-height: 1.8;padding: 2px 20px;">Entidade:&nbsp;&nbsp;<span style="float: right"><b>'.$paymentTable->entity.'</b></span><br>
					Referência:&nbsp;&nbsp;<span style="float: right"><b>'.$paymentTable->referencia.'</b></span><br>
					Valor:&nbsp;&nbsp;<span style="float: right"><b>'.number_format($paymentTable->value, 2).'</b></span></p>
				</div>';*/
				
        return $html;
    }



    function getCosts(VirtueMartCart $cart, $method, $cart_prices) {
        return (0 + ($cart_prices['salesPrice'] * 0));
    }


    protected function checkConditions($cart, $method, $cart_prices) {
        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

        $countries = array();
        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }

        // probably did not gave his BT:ST address
        if (!is_array($address)) {
            $address = array();
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id'])) {
            $address['virtuemart_country_id'] = 0;
        }
        if (count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries)) {
            return TRUE;
        }

        return FALSE;
    }


    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart) {
        return $this->OnSelectCheck($cart);
    }

    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, & $htmlIn) {
        return $this->displayListFE($cart, $selected, $htmlIn);
    }


    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array & $cart_prices, & $cart_prices_name) {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, & $paymentCurrencyId) {

        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        $this->getPaymentCurrency($method);

        $paymentCurrencyId = $method->payment_currency;
    }

    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array()) {
        return $this->onCheckAutomaticSelected($cart, $cart_prices);
    }

    protected function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, & $payment_name) {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }


    function plgVmonShowOrderPrintPayment($order_number, $method_id) {
        return 'abab';
    }

    function plgVmDeclarePluginParamsPayment($name, $id, & $data) {
        return $this->declarePluginParams('payment', $name, $id, $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, & $table) {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    function plgVmDeclarePluginParamsPaymentVM3( & $data) {
        return $this->declarePluginParams('payment', $data);
    }

    function plgVmOnPaymentNotification() {
        require_once(JPATH_VM_ADMINISTRATOR.DS.'models'.DS.'orders.php');

        $parameter = $_GET;

        if(empty($parameter['plg']))
            return false;

        if($parameter['plg']!='ifthenpay')
            return false;

        if(empty($parameter['chave']) || empty($parameter['entidade']) || empty($parameter['referencia']) || empty($parameter['valor']))
            return false;

        $chave = $parameter['chave'];
        $entidade = $parameter['entidade'];
        $referencia = $parameter['referencia'];
        $valor = $parameter['valor'];
        // $orderID = substr($referencia, 3, 4);

        $db = &JFactory::getDBO();

        $q = 'SELECT id, virtuemart_order_id FROM `' . $this->_tablename . '` WHERE entity = ' . $db->quote($entidade,true) . ' AND referencia = ' . $db->quote($referencia,true) . ' AND value like \'%' . $db->escape($valor) . '%\' AND estado = 0 ORDER BY modified_on DESC';
        $db->setQuery($q, 0, 1);
        $callback_fetch = $db->loadRow();

        if(empty($callback_fetch))
            return false;

        $order = VirtueMartModelOrders::getOrder($callback_fetch[1]);

        if (!$order) {
            return false;
        }

        $q = 'SELECT `payment_params` FROM `#__virtuemart_paymentmethods` WHERE `payment_params` LIKE "%entidade%" ';
        $db->setQuery($q);

        $fetch = $db->loadResult();
        $details = explode("|", $fetch);

        $chaveFetched = preg_replace('/^(\w)+=|"/', "", $details[2]);

        if($chaveFetched == $chave)
        {
            $modelOrder = VmModel::getModel ('orders');

            $orderArr = array();
            $orderArr["order_status"] = 'C';

            try {
                $modelOrder->updateStatusForOneOrder($callback_fetch[1], $orderArr);

                $db->setQuery('UPDATE `' . $this->_tablename . '` SET estado = 1 WHERE id = ' .$callback_fetch[0].' AND virtuemart_order_id = ' .$callback_fetch[1].' AND estado = 0');
                $db->execute();

            } catch(Exception $ex) {}
            return true;
        }

        return false;
    }

}


function GenerateMbRef($ent_id, $subent_id, $order_id, $order_value, $tipo) {
    if (strlen($ent_id) < 5) {
        echo "Lamentamos mas tem de indicar uma entidade válida";
        return;
    } else if (strlen($ent_id) > 5) {
        echo "Lamentamos mas tem de indicar uma entidade válida";
        return;
    }
    if (strlen($subent_id) == 0) {
        echo "Lamentamos mas tem de indicar uma subentidade válida";
        return;
    } else if (strlen($subent_id) == 1) {
        $subent_id = '00'.$subent_id;
    } else if (strlen($subent_id) == 2) {
        $subent_id = '0'.$subent_id;
    } else if (strlen($subent_id) > 3) {
        echo "Lamentamos mas tem de indicar uma entidade válida";
        return;
    }

    $chk_val = 0;

    $order_id = "0000".$order_id;

    $order_value = sprintf("%01.2f", $order_value);

    $order_value = format_number($order_value);

    //Apenas sao considerados os 4 caracteres mais a direita do order_id
    $order_id = substr($order_id, (strlen($order_id) - 4), strlen($order_id));

    //cálculo dos check digits
    $chk_str = sprintf('%05u%03u%04u%08u', $ent_id, $subent_id, $order_id, round($order_value * 100));

    $chk_array = array(3, 30, 9, 90, 27, 76, 81, 34, 49, 5, 50, 15, 53, 45, 62, 38, 89, 17, 73, 51);

    for ($i = 0; $i < 20; $i++) {
        $chk_int = substr($chk_str, 19 - $i, 1);
        $chk_val += ($chk_int % 10) * $chk_array[$i];
    }

    $chk_val %= 97;

    $chk_digits = sprintf('%02u', 98 - $chk_val);

    if ($tipo == 'email') {
        return '<div style="font-family:Arial;font-size:10pt;width: 200px;">
					<img src="https://ifthenpay.com/mb.png" style="height:80px;width:80px;display: block;margin-left: auto;margin-right: auto;">
					<p style="line-height: 1;padding: 2px 20px;">&nbsp;&nbsp;Entidade:&nbsp;&nbsp;<span style="float: right"><b>'.$ent_id.'</b></span><br>
					&nbsp;&nbsp;Referência:&nbsp;&nbsp;<span style="float: right"><b>'.$subent_id." ".substr($chk_str, 8, 3)." ".substr($chk_str, 11, 1).$chk_digits.'</b></span><br>
					&nbsp;&nbsp;Valor:&nbsp;&nbsp;<span style="float: right"><b>'.number_format($order_value, 2, ',', ' ').'</b></span></p>
				</div>';
				
    } else if ($tipo == 'front') {
        return '<p>Pagamento por Multibanco ou Homebanking</p>
				<div style="font-family:Arial;font-size:10pt;width: 200px;border: 0px solid #2e99d4;">
					<img src="https://ifthenpay.com/mb.png" style="height:80px;width:80px;display: block;margin-left: auto;margin-right: auto;">
					<p style="line-height: 1.5;padding: 2px 20px;">Entidade:&nbsp;&nbsp;<span style="float: right"><b>'.$ent_id.'</b></span><br>
					Referência:&nbsp;&nbsp;<span style="float: right"><b>'.$subent_id." ".substr($chk_str, 8, 3)." ".substr($chk_str, 11, 1).$chk_digits.'</b></span><br>
					Valor:&nbsp;&nbsp;<span style="float: right"><b>'.number_format($order_value, 2, ',', ' ').'</b></span></p>
				</div>';
    } else if ($tipo == 'refonly'){
        return $subent_id . substr($chk_str, 8, 3) . substr($chk_str, 11, 1).$chk_digits;
    }

    return "";
}

function format_number($number) {
    $verifySepDecimal = number_format(99, 2);

    $valorTmp = $number;

    $sepDecimal = substr($verifySepDecimal, 2, 1);

    $hasSepDecimal = True;

    $i = (strlen($valorTmp) - 1);

    for ($i; $i != 0; $i -= 1) {
        if (substr($valorTmp, $i, 1) == "." || substr($valorTmp, $i, 1) == ",") {
            $hasSepDecimal = True;
            $valorTmp = trim(substr($valorTmp, 0, $i))."@".trim(substr($valorTmp, 1 + $i));
            break;
        }
    }

    if ($hasSepDecimal != True) {
        $valorTmp = number_format($valorTmp, 2);

        $i = (strlen($valorTmp) - 1);

        for ($i; $i != 1; $i--) {
            if (substr($valorTmp, $i, 1) == "." || substr($valorTmp, $i, 1) == ",") {
                $hasSepDecimal = True;
                $valorTmp = trim(substr($valorTmp, 0, $i))."@".trim(substr($valorTmp, 1 + $i));
                break;
            }
        }
    }

    for ($i = 1; $i != (strlen($valorTmp) - 1); $i++) {
        if (substr($valorTmp, $i, 1) == "." || substr($valorTmp, $i, 1) == "," || substr($valorTmp, $i, 1) == " ") {
            $valorTmp = trim(substr($valorTmp, 0, $i)).trim(substr($valorTmp, 1 + $i));
            break;
        }
    }

    if (strlen(strstr($valorTmp, '@')) > 0) {
        $valorTmp = trim(substr($valorTmp, 0, strpos($valorTmp, '@'))).trim($sepDecimal).trim(substr($valorTmp, strpos($valorTmp, '@') + 1));
    }

    return $valorTmp;
}

// No closing tag
