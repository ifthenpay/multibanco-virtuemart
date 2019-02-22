<?php
defined('_JEXEC') or die();


JFormHelper::loadFieldClass('filelist');
class JFormFieldGetap extends JFormFieldFileList {

	/**
	 * Element name
	 *
	 * @access    protected
	 * @var        string
	 */
	var $type = 'Getap';

	protected function getInput() {

		$valor = (empty($this->value)?substr(hash('sha512', "ifthenpayvirtuamart" . date("D M d, Y G:i")), -50):$this->value) ;

		return "<input type=\"hidden\" name=\"chaveantiphishing\" id=\"chaveantiphishing\" value=\"$valor\" \><span id='txtchaveantiphishing'  style=\"font-weight: bolder;\">$valor</span>";

	}


}
