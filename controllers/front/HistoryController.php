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

class HistoryControllerCore extends FrontController
{
    public $auth = true;
    public $php_self = 'history';
    public $authRedirection = 'history';
    public $ssl = true;

    public function setMedia()
    {
        parent::setMedia();
        $this->addCSS(array(
            _THEME_CSS_DIR_.'history.css',
            _THEME_CSS_DIR_.'addresses.css'
        ));
        $this->addJS(array(
            _THEME_JS_DIR_.'history.js',
            _THEME_JS_DIR_.'tools.js' // retro compat themes 1.5
        ));
        $this->addJqueryPlugin(array('scrollTo', 'footable', 'footable-sort'));
    }

    /**
     * Assign template vars related to page content
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        if ($orders = Order::getCustomerOrders($this->context->customer->id)) {
            foreach ($orders as &$order) {
                $myOrder = new Order((int)$order['id_order']);
                if (Validate::isLoadedObject($myOrder)) {
                    $order['virtual'] = $myOrder->isVirtual(false);
                }
                
            }
        }


         foreach ($orders as &$order) {
                $myOrder = new Order((int)$order['id_order']);
                if (Validate::isLoadedObject($myOrder)) {
                    $order['virtual'] = $myOrder->isVirtual(false);
                }
                 $order['mproducts'] =  $myOrder->getProducts();
            }
// echo "<pre>";
// var_dump($orders);
// echo "</pre>";
// exit;

		//检测当前用户 是否存在异常地址订单
		if(count($orders) > 0){
			$check_address = "SELECT
	a.id_order,
	a.id_cart,
	a.id_customer AS o_cid,
	b.id_customer AS delivery_cid,
	c.id_customer AS invoice_cid

FROM
	ps_orders a
LEFT JOIN ps_address b ON a.id_address_delivery = b.id_address
LEFT JOIN ps_address  c  on a.id_address_invoice = c.id_address
WHERE
	a.date_add > '2018-08-30'
AND (a.id_customer != b.id_customer  or a.id_customer != c.id_customer )
AND a.id_customer =  ".$this->context->customer->id;
		$res = 	Db::getInstance()->ExecuteS($check_address);
			
			//存在异常 去查询此购物车支付时候的 正确地址id 更新到订单中 
			if($res){
				
				foreach($res as $a){
						$refix_address= 'UPDATE ps_orders
INNER JOIN (
	SELECT
		id_address_delivery,
		id_address_invoice
	FROM
		px_order_address
	WHERE
		id_cart = '.$a['id_cart'].'
	ORDER BY
		id_log DESC
	LIMIT 0,
	1
) c
SET ps_orders.id_address_delivery = c.id_address_delivery
,
	ps_orders.id_address_invoice = c.id_address_invoice
WHERE
	ps_orders.id_order = '.$a['id_order'];
					
					Db::getInstance()->Execute($refix_address);
				}
				
				
			}
	/* 		echo '<pre>';
			var_dump($res);
			echo '</pre>';
			die('debug!!!'); */
			
			
		}
			
		

        $this->context->smarty->assign(array(
            'orders' => $orders,
            'invoiceAllowed' => (int)Configuration::get('PS_INVOICE'),
            'reorderingAllowed' => !(bool)Configuration::get('PS_DISALLOW_HISTORY_REORDERING'),
            'slowValidation' => Tools::isSubmit('slowvalidation')
        ));
        if($this->context->shop->theme_name=='uniwigs2016-m'){
             $this->removeJS(array(
                '/js/jquery/plugins/fancybox/jquery.fancybox.js',
                '/js/jquery/plugins/jquery.scrollTo.js',
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

        $this->setTemplate(_PS_THEME_DIR_.'history.tpl');
    }
}
