<?php
if( !defined( '_VALID_MOS' ) && !defined( '_JEXEC' ) ) die( 'Direct Access to '.basename(__FILE__).' is not allowed.' );
/**
*
* @version $Id: ps_multibancos.php 1095 2007-12-19 20:19:16Z soeren_nb $
* @package VirtueMart
* @subpackage multibancos
* @copyright Copyright (C) 2004-2007 soeren - All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
* VirtueMart is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
*
* http://virtuemart.net
*/


/**
*
* The ps_payment class, containing the default payment processing code
* for payment methods that have no own class
*
*/
class ps_multibancos {
	var $payment_code = "MULTI";
    var $classname = "ps_multibancos";
  
    /**
    * Show all configuration parameters for this payment method
    * @returns boolean False when the Payment method has no configration
    */
    function show_configuration() {
	
	

      /** Read current Configuration ***/
      require_once(CLASSPATH ."payment/".$this->classname.".cfg.php");
    ?>
      <table>
        <tr>
            <td><strong>Entidade: </strong></td>
            <td>
                <input type="text" name="MULTI_ENT" class="inputbox" value="<?php echo MULTI_ENT ?>" />
            </td>
            <td>Entidade multibanco fornecida pela IFTHEN no acto do contracto</td>
        </tr>
        <tr>
            <td><strong>Sub-Entidade</strong></td>
            <td>
                <input type="text" name="MULTI_SUB_ENT" class="inputbox" value="<?php echo MULTI_SUB_ENT ?>" />
            </td>
            <td>Sub-Entidade fornecida pela IFTHEN no acto do contracto</td>
        </tr>
      </table>
   <?php
      // return false if there's no configuration
      return true;
    }	
    
    function has_configuration() {
      // return false if there's no configuration
      return false;
   }
   
  /**
	* Returns the "is_writeable" status of the configuration file
	* @param void
	* @returns boolean True when the configuration file is writeable, false when not
	*/
   function configfile_writeable() {
      return is_writeable( CLASSPATH."payment/".$this->classname.".cfg.php" );
   }
   
  /**
	* Returns the "is_readable" status of the configuration file
	* @param void
	* @returns boolean True when the configuration file is writeable, false when not
	*/
   function configfile_readable() {
      return is_readable( CLASSPATH."payment/".$this->classname.".cfg.php" );
   }
   
  /**
	* Writes the configuration file for this payment method
	* @param array An array of objects
	* @returns boolean True when writing was successful
	*/
   function write_configuration( &$d ) {
      $my_config_array = array("MULTI_ENT" => $d['MULTI_ENT'],
                                "MULTI_SUB_ENT" => $d['MULTI_SUB_ENT']
                          );
      $config = "<?php\n";
      $config .= "if( !defined( '_VALID_MOS' ) && !defined( '_JEXEC' ) ) die( 'Direct Access to '.basename(__FILE__).' is not allowed.' ); \n\n";
      foreach( $my_config_array as $key => $value ) {
        $config .= "define ('$key', '$value');\n";
      }
      
      $config .= "?>";
  
      if ($fp = fopen(CLASSPATH ."payment/".$this->classname.".cfg.php", "w")) {
          fputs($fp, $config, strlen($config));
          fclose ($fp);
          return true;
     }
     else
        return false;
   }
   
  /**************************************************************************
  ** name: process_payment()
  ** returns: 
  ***************************************************************************/
   function process_payment($order_number, $order_total, &$d) {
   
   global $VM_LANG;
   
   require_once(CLASSPATH ."payment/".$this->classname.".cfg.php");

  $config = new JConfig();
  
  $esquema_tabela = $config -> db;
  
	//$userid=explode('_',$order_number);
	$db = new ps_DB;
	$q1 = "SELECT table_name FROM information_schema.tables WHERE table_name LIKE '%vm_orders' AND TABLE_SCHEMA = '".$esquema_tabela."'";
	$db->query($q1);
	$tablename = $db->f("table_name");
	//$q2  = 'SELECT order_id FROM '.$tablename.' WHERE order_number =  \''.$order_number.'\'';
	$q2  = 'SELECT AUTO_INCREMENT FROM information_schema.tables WHERE table_name =  \''.$tablename.'\' AND  TABLE_SCHEMA =  \''.$esquema_tabela.'\'';
	$db->query($q2);
	$order_id = $db->f("AUTO_INCREMENT");
	
	//echo $q2 . ' - ' . $db->f("order_id") . ' - ' . $order_id;
	
	$ent_id = MULTI_ENT;
	$subent_id = MULTI_SUB_ENT;
	$order_value = $order_total;
    $order_id = "0000".$order_id;
   //     Apenas são considerados os 4 caracteres mais à direita do order_id
	$order_id = substr($order_id, (strlen($order_id) - 4), strlen($order_id));

//     Podemos definir ou não um valor mínimo para pagamentos no multibanco apesar
//     não existir um limite mínimo
	if ($order_value < 1)
	{
		echo "Lamentamos mas é impossível gerar uma referência MB para valores inferiores a 1 Euro";
		return;
	}

//     O valor máximo que se pode pagar no multibanco é 999999.99 Euros pelo que
//     teremos que repartir o valor por mais do que uma referencia se o valor for superior
	if ($order_value >= 1000000)
	{
		echo "<b>AVISO:</b> Pagamento fraccionado por exceder o valor limite para pagamentos no sistema Multibanco<br>";
	}
	
	while ($order_value >= 1000000)
	{
		GenerateMbRef($order_id, 999999.99);
		$order_value -= 999999.99;
	}
					  
//     Cálculo dos check-digits
	
	$chk_str = sprintf('%05u%03u%04u%08u', $ent_id, $subent_id, $order_id, round($order_value*100));
		   
           $chk_array = array(3, 30, 9, 90, 27, 76, 81, 34, 49, 5, 50, 15, 53, 45, 62, 38, 89, 17, 73, 51);
           
           for ($i = 0; $i < 20; $i++)
           {
                 $chk_int = substr($chk_str, 19-$i, 1);
                 $chk_val += ($chk_int%10)*$chk_array[$i];
           }
   
	$chk_val %= 97;
   
	$chk_digits = sprintf('%02u', 98-$chk_val);
	
	$tabelaMulti = '<table cellpadding="3" width="330px" cellspacing="0" style="margin-top: 10px;border: 1px solid #45829F" align="center">';
	$tabelaMulti .= "	<tr>";
	$tabelaMulti .=	'		<td style="font-size: x-small; border-top: 0px; border-left: 0px; border-right: 0px; border-bottom: 1px solid #45829F; background-color: #45829F; color: White" colspan="3"><center>Pagamento por Multibanco ou Homebanking</center></td>';
	$tabelaMulti .= "	</tr>";
	$tabelaMulti .= "	<tr>";
	$tabelaMulti .=	'		<td rowspan="3"><center><img src="http://img412.imageshack.us/img412/9672/30239592.jpg" alt="" width="52" height="60"/></center></td>';
	$tabelaMulti .=	'		<td style="font-size: x-small; font-weight:bold; text-align:left">Entidade:</td>';
	$tabelaMulti .=	'		<td style="font-size: x-small; text-align:left">'.$ent_id.'</td>';
	$tabelaMulti .= "	</tr>";
	$tabelaMulti .= "	<tr>";
	$tabelaMulti .=	'		<td style="font-size: x-small; font-weight:bold; text-align:left">Refer&ecirc;ncia:</td>';
	$tabelaMulti .=	'		<td style="font-size: x-small; text-align:left">'.$subent_id." ".substr($chk_str, 8, 3)." ".substr($chk_str, 11, 1).$chk_digits.'</td>';
	$tabelaMulti .= "	</tr>";
	$tabelaMulti .= "	<tr>";
	$tabelaMulti .=	'		<td style="font-size: x-small; font-weight:bold; text-align:left">Valor:</td>';
	$tabelaMulti .=	'		<td style="font-size: x-small; text-align:left">'.number_format($order_value, 2,',', ' ').'</td>';
	$tabelaMulti .= "	</tr>";
	$tabelaMulti .= "	<tr>";
	$tabelaMulti .=	'		<td style="font-size: xx-small;border-top: 1px solid #45829F; border-left: 0px; border-right: 0px; border-bottom: 0px; background-color: #45829F; color: White" colspan="3"><center>O tal&atilde;o emitido pela caixa autom&aacute;tica faz prova de pagamento. Conserve-o.</center></td>';
	$tabelaMulti .= "	</tr>";
	$tabelaMulti .= "</table>";
	$tabelaMulti .= '<table cellpadding="3" width="330px" cellspacing="0" border="0" align="center">';
	$tabelaMulti .= "	<tr>";
	$tabelaMulti .=	'		<td>&nbsp;</td>';
	$tabelaMulti .= "	</tr>";
	$tabelaMulti .= "</table>";
	$tabelaMulti .= "</ br>";
	$tabelaMulti .= "</ br>";
	
	
		echo $tabelaMulti;
		
        return true;
    }
   
}