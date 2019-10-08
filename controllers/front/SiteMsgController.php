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

class SiteMsgControllerCore extends FrontController
{
  
	public $auth = true; //增加登陆验证
    public $php_self = 'sitemsg';
    public $authRedirection = 'sitemsg';//增加登陆验证
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
		$id_customer = $this->context->customer->id;
		//echo $email;
		$sql =  "select * from  px_msg  where 
 id_customer = $id_customer  or  type= 'all' order by  id_msg desc ";
		$res = Db::getInstance()->ExecuteS($sql);
		
		if(isset($_GET['mode']) &&  isset($_GET['id'])){
			if($_GET['id']>0){
				$sql =  "select * from  px_msg  where 
 id_customer = $id_customer  and id_msg= ".pSql($_GET['id']) ." order by  id_msg desc ";
 
				$res = Db::getInstance()->ExecuteS($sql);
				if($res){
					$this->context->smarty->assign('detail',$res[0]);
				}else{
					
					$this->context->smarty->assign('detail',false);
				}
			}else{
				$this->context->smarty->assign('detail',false);
			}
		}
		$this->context->smarty->assign('msg',$res);
        $this->setTemplate(_PS_THEME_DIR_.'sitemsg.tpl');

    }

}
