<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class ReportControllerCore extends FrontController
{
  
	public $auth = true; //增加登陆验证
    public $php_self = 'report';
    public $authRedirection = 'report';//增加登陆验证
    public $ssl = true;
    /** @var Customer */
    protected $customer;

    public function init()
    {
        parent::init();
        $this->customer = $this->context->customer;
    }

    /**
     * Assign template vars related to page content
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();
		$email  =  $this->context->customer->email;
		//echo $email;
		$res= Db::getInstance()->ExecuteS("select *  from  px_complain  a

LEFT JOIN  ps_customer  b  on a.email=b.email 

where  a.email  =  '$email'  group  by  a.id_complain");

		$this->context->smarty->assign('report',$res);
        $this->setTemplate(_PS_THEME_DIR_.'myreport.tpl');
    }


}
