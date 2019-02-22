<?php
defined('_JEXEC') or die();


JFormHelper::loadFieldClass('filelist');
class JFormFieldGeturlcallback extends JFormFieldFileList {

	/**
	 * Element name
	 *
	 * @access    protected
	 * @var        string
	 */
	var $type = 'Geturlcallback';

	protected function getInput() {

		$url = '<span style="font-weight: bolder;">'.JURI::root().'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&plg=ifthenpay&chave=[CHAVE_ANTI_PHISHING]&entidade=[ENTIDADE]&referencia=[REFERENCIA]&valor=[VALOR]'.'</span>';

		$html = $url . '
		<br/>
		<br/>
		<span style="font-weight: bolder; text-decoration: underline;">Enviar esta informação</span> para a Ifthenpay para o email <span style="font-weight: bolder; text-decoration: underline;">callback@ifthenpay.com</span> com os seguintes elementos:
		<ul>
			<li>Entidade</li>
			<li>Sub-entidade</li>
			<li>Url de Callback</li>
			<li>Chave Anti-Phishing</li>
			<li>Últimos 4 dígitos da chave de backoffice</li>
		</ul>
		
		';

		return $html;

	}


}
