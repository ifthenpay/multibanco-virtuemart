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
        $SQLfields = array('id' => 'int(1) unsigned NOT NULL AUTO_INCREMENT', 'virtuemart_order_id' => 'int(11) UNSIGNED DEFAULT NULL', 'order_number' => 'char(32) DEFAULT NULL', 'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED DEFAULT NULL', 'payment_name' => 'char(255) NOT NULL DEFAULT \'\' ', 'payment_currency' => 'char(3) ', 'entity' => ' char(10)  DEFAULT NULL', 'subentity' => ' char(10)  DEFAULT NULL', 'value' => ' decimal(15,5) NOT NULL DEFAULT \'0.00\' ', 'tax_id' => 'smallint(11) DEFAULT NULL');

        return $SQLfields;
    }

    function plgVmConfirmedOrder($cart, $order) {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        // 		$params = new JParameter($payment->payment_params);
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


        $this->_virtuemart_paymentmethod_id = $order['details']['BT']->virtuemart_paymentmethod_id;
        $dbValues['payment_name'] = $this->renderPluginName($method);
        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['virtuemart_paymentmethod_id'] = $this->_virtuemart_paymentmethod_id;
        //$dbValues['order_number'] = $order['details']['BT']->virtuemart_order_id;
        //$dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
        $dbValues['entity'] = $method->entidade;
        $dbValues['payment_currency'] = $currency_code_3;
        $dbValues['value'] = $totalInPaymentCurrency;
        $dbValues['subentity'] = $method->subentidade;
        $dbValues['tax_id'] = 0;
        $this->storePSPluginInternalData($dbValues);

        $subent = $method->subentidade;

        $html = GenerateMbRef($dbValues['entity'], $subent, $order['details']['BT']->virtuemart_order_id, $dbValues['value'], 'sim');
        $html2 = GenerateMbRef($dbValues['entity'], $subent, $order['details']['BT']->virtuemart_order_id, $dbValues['value'], 'nao');


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
        $html .= $this->getHtmlRowBE('STANDARD_PAYMENT_TOTAL_CURRENCY', $paymentTable->value.' €');
        $html .= '</table>'."\n";
        $html .= GenerateMbRef($paymentTable->entity, $paymentTable->subentity, $paymentTable->virtuemart_order_id, $paymentTable->value, 'nao');
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
        return $this->onShowOrderPrint($order_number, $method_id);
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
        
        $chave = JRequest::getVar('chave');
        $entidade = JRequest::getVar('entidade');
        $referencia = JRequest::getVar('referencia');
        $orderID = substr($referencia, 3, 4);
        $order = VirtueMartModelOrders::getOrder($orderID);
        
        if (!$order) {
            return false;
        }
        
        $q = 'SELECT `payment_params` FROM `#__virtuemart_paymentmethods` WHERE `payment_params` LIKE "%entidade%" ';
        $db = &JFactory::getDBO();
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
                $modelOrder->updateStatusForOneOrder($orderID, $orderArr);                
            } catch(Exception $ex) {}
            return true;
        }
        
        return false;
    }

}


function GenerateMbRef($ent_id, $subent_id, $order_id, $order_value, $email) {
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

    if ($email == 'sim') {
        return '<table cellpadding="3" width="300px" cellspacing="0" style="margin-top: 10px;border: 1px solid #45829F"><tr><td style="font-size: x-small; border-bottom: 1px solid #45829F; background-color: #45829F; color: White" colspan="3">Pagamento por Multibanco ou Homebanking</td></tr><tr><td rowspan="3"><img src="http://dl.dropbox.com/u/14494130/ifmb/imagensmodulos/mb.jpg" alt="" width="52" height="60"/></td><td style="font-size: x-small; font-weight:bold; text-align:left">Entidade:</td><td style="font-size: x-small; text-align:left">'.$ent_id.'</td></tr><tr><td style="font-size: x-small; font-weight:bold; text-align:left">Referência:</td><td style="font-size: x-small; text-align:left">'.$subent_id." ".substr($chk_str, 8, 3)." ".substr($chk_str, 11, 1).$chk_digits.'</td></tr><tr><td style="font-size: x-small; font-weight:bold; text-align:left">Valor:</td><td style="font-size: x-small; text-align:left">&euro;&nbsp; '.number_format($order_value, 2, ',', ' ').'</td></tr><tr><td style="font-size: xx-small;border-top: 1px solid #45829F; background-color: #45829F; color: White" colspan="3">O talão emitido pela caixa automática faz prova de pagamento. Conserve-o.</td></tr></table>';
    } else {
        return '
           <div style="
    border: 1px solid #539FD1;
    width: 300px;
">
  <div style="
    text-align: center;
    border-bottom: 1px solid #539FD1;
    margin-left: 7px;
    margin-right: 7px;
">Pagamento por Referência Multibanco</div>
  <div style="
    margin-left: 35px;
">
  <div style="
    float: left;
"><img src="http://dl.dropbox.com/u/14494130/ifmb/imagensmodulos/mb.jpg" alt="" width="52" height="60" style="
    padding-top: 2px;
"></div>
  <div style="
    margin-left: 74px;
">
    <strong style="
    margin-right: 10px;
">Entidade: </strong>'.$ent_id.'<br>
  <strong>Referência: </strong> '.$subent_id." ".substr($chk_str, 8, 3)." ".substr($chk_str, 11, 1).$chk_digits.'<br>
<strong style="
    margin-right: 37px;
">Valor: </strong>&euro;&nbsp; '.number_format($order_value, 2, ',', ' ').'
  </div>
  </div>
  
  <div style="
    text-align: center;
    border-top: 1px solid #539FD1;
    margin-left: 7px;
    margin-right: 7px;
    font-size: xx-small;
">O talão emitido pela caixa automática faz prova de pagamento. Conserve-o.</div>
</div>';
    }




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
