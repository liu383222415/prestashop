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

class MyAccountControllerCore extends FrontController
{
    public $auth = true;
    public $php_self = 'my-account';
    public $authRedirection = 'my-account';
    public $ssl = true;

    public function setMedia()
    {
        parent::setMedia();
        $this->addCSS(_THEME_CSS_DIR_.'my-account.css');
    }

    /**
     * Assign template vars related to page content
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $has_address = $this->context->customer->getAddresses($this->context->language->id);
				//获取用户分组   
		$customergroup= $this->context->customer->id_default_group;
		$customerid= $this->context->customer->id;

        //推送当前顾客的积分到前台去
        if($this->context->customer->id){
        $points =$this->getCustomerPoint($this->context->customer->id);
        }else{
            
        $points = 0;    
        }
		
        $this->context->smarty->assign(array(
            'has_customer_an_address' => empty($has_address),
            'voucherAllowed' => (int)CartRule::isFeatureActive(),
            'returnAllowed' => (int)Configuration::get('PS_ORDER_RETURN'),
            'points'=>$points,
            'customerid'=>$customerid
        ));
		
        $this->context->smarty->assign('HOOK_CUSTOMER_ACCOUNT', Hook::exec('displayCustomerAccount'));
		//如果为设计师用户 则增加判断
		if($customergroup==4){
		$this->context->smarty->assign('mydesigner','xxxxxx');
		}
        if($this->context->shop->theme_name=='uniwigs2016-m'){
             $this->removeJS(array(
                '/js/jquery/plugins/fancybox/jquery.fancybox.js',
                '/js/jquery/plugins/jquery.scrollTo.js',
                '/themes/uniwigs2016-m/js/autoload/uikit.min.js',
                '/js/jquery/plugins/bxslider/jquery.bxslider.js',
                '/themes/uniwigs2016-m/js/autoload/15-jquery.uniform-modified.js',
                '/themes/uniwigs2016-m/js/modules/blockwishlist/js/ajax-wishlist.js',
                '/themes/uniwigs2016-m/js/modules/blockcart/ajax-cart.js',
                '/js/jquery/plugins/autocomplete/jquery.autocomplete.js',
                '/themes/uniwigs2016-m/js/modules/blocksearch/blocksearch.js',
            ));
             $this->removeCSS(array(
                '/js/jquery/plugins/fancybox/jquery.fancybox.css',
                '/themes/uniwigs2016-m/css/modules/blockcart/blockcart.css',
                '/themes/uniwigs2016-m/css/modules/blockwishlist/blockwishlist.css',
                '/js/jquery/plugins/bxslider/jquery.bxslider.css',
                '/themes/uniwigs2016-m/css/modules/blocksearch/blocksearch.css',
                '/js/jquery/plugins/autocomplete/jquery.autocomplete.css',
                '/themes/uniwigs2016-m/css/modules/blockcontact/blockcontact.css',
                '/themes/uniwigs2016-m/css/autoload/uniform.default.css'
            ));
        }
		
        $this->setTemplate(_PS_THEME_DIR_.'my-account.tpl');
    }

    //获取客户当前积分 
    
    
    public function  getCustomerPoint($id_customer){
        
        $result = Db::getInstance()-> getValue("SELECT points from  px_customer_point 
    WHERE   id_customer = $id_customer   ");
        
        if($result){
            return $result;
            
        }else{
            
            return 0;
        }
        
        
    }
}
