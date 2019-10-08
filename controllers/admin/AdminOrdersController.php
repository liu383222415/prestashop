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

class BoOrder extends PaymentModule
{
    public $active = 1;
    public $name = 'bo_order';

    public function __construct()
    {
        $this->displayName = $this->l('Back office order');
    }
}

/**
 * @property Order $object
 */
class AdminOrdersControllerCore extends AdminController
{
    public $toolbar_title;

    protected $statuses_array = array();

    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'order';
        $this->className = 'Order';
        $this->lang = false;
        $this->addRowAction('view');
        $this->explicitSelect = true;
        $this->allow_export = false;//修改为自定义 导出
        $this->deleted = false;
        $this->context = Context::getContext();

        $this->_select = '
		GROUP_CONCAT(distinct '."'".'<img src="../img/tmp/product_mini_\',product_id,\'_1.jpg" alt="" class="imgm img-thumbnail" style="
    width: 48px;
    height: 55px;
">\' SEPARATOR \' \')  AS `image` ,
		GROUP_CONCAT(distinct po.product_reference SEPARATOR\'</br>\' ) as psku,
		GROUP_CONCAT(po.product_name SEPARATOR\'</br>\' ) as pname,
		a.id_currency,
		a.id_order AS id_pdf,
		a.valid,
		if( c.note is not null, \'有备注\' ,\'\') as note,
		crl.`name` as ccode,
        CONCAT(dcl.name,\'--\',\'($\',FORMAT(a.total_shipping,2),\')\') as total_shipping,		
		CONCAT(address.`firstname`, \'. \', address.`lastname`) AS `customer`,
		CONCAT( address.`firstname`,\'.\',address.`lastname`) as customername, 
		country_lang.`name` as pcountry,
		state.`name` as pstate,
		address.`city` as pcity,
		IF(mark is null ,\'\',IF(mark = 0 ,\'否\',\'是\')) as mymark,
		CONCAT(address.`address1`,\'.\',address.`address2`)    AS pstreet,
        address.`postcode` as postcode,
		IF(address.`phone`=\'\',address.`phone_mobile`,address.`phone`) AS pphone,
		 #(SELECT count(*) from ps_orders where id_customer =c.id_customer) as num ,
        IF(	COUNT(DISTINCT dcr.id_order_carrier)=0,
        sum(po.product_quantity),
        CAST(sum(po.product_quantity)/COUNT(DISTINCT dcr.id_order_carrier) as SIGNED  ))  as num,
		c.email,
	    CONCAT(dcr.real_carrier,\'-\',dcr.tracking_number)as shipping_number,
		osl.`name` AS `osname`,
		os.`color`,
		IF((SELECT so.id_order FROM `'._DB_PREFIX_.'orders` so WHERE so.id_customer = a.id_customer AND so.id_order < a.id_order LIMIT 1) > 0, 0, 1) as new,
		country_lang.name as cname,
		IF(a.valid, 1, 0) badge_success';

        $this->_join = '
		LEFT JOIN ps_cart_cart_rule ccr on a.id_cart = ccr.id_cart 
		LEFT JOIN  px_order_mark  om on  a.id_cart=om.id_cart
		LEFT JOIN ps_cart_rule_lang  crl on  ccr.id_cart_rule = crl.id_cart_rule 
		LEFT JOIN `'._DB_PREFIX_.'customer` c ON (c.`id_customer` = a.`id_customer`)
		left JOIN `'._DB_PREFIX_.'order_detail` po  on po.`id_order` = a.`id_order`
        LEFT JOIN  px_differ_carrier dcr on  dcr.id_order=a.id_order
		LEFT JOIN  ps_carrier dcl on  dcl.id_carrier=a.id_carrier 
		INNER JOIN `'._DB_PREFIX_.'address` address ON address.id_address = a.id_address_delivery
		INNER JOIN `'._DB_PREFIX_.'country` country ON address.id_country = country.id_country
		INNER JOIN `'._DB_PREFIX_.'country_lang` country_lang ON (country.`id_country` = country_lang.`id_country` AND country_lang.`id_lang` = '.(int)$this->context->language->id.')
		LEFT JOIN `'._DB_PREFIX_.'state` state ON state.id_state = address.id_state
		LEFT JOIN `'._DB_PREFIX_.'order_state` os ON (os.`id_order_state` = a.`current_state`)
		LEFT JOIN `'._DB_PREFIX_.'order_state_lang` osl 
		ON (os.`id_order_state` = osl.`id_order_state` AND osl.`id_lang` = '.(int)$this->context->language->id.')';
		$this->_group = 'GROUP BY a.`id_order`';
		$this->_orderBy = 'id_order';
        $this->_orderWay = 'DESC';
        $this->_use_found_rows = true;

        $statuses = OrderState::getOrderStates((int)$this->context->language->id);
        foreach ($statuses as $status) {
            $this->statuses_array[$status['id_order_state']] = $status['name'];
        }

        $this->fields_list = array(
            'id_order' => array(
                'title' => $this->l('ID'),
                'align' => 'text-center',
                'class' => 'fixed-width-xs'
            ),
			'new' => array(
                'title' => $this->l('New client'),
                'align' => 'text-center',
                'type' => 'bool',
                'tmpTableFilter' => true,
                'orderby' => false,
                'callback' => 'printNewCustomer'
            ),
            'note' => array(
                'title' => $this->l('Note'),
				'badge_danger' => true,
            ),
			'ccode' => array(
                'title' => $this->l('Code'),
				'badge_danger' => true,
            ),
            'image' => array(
                'title' => $this->l('image'),
				'filter' => false,
				
				
            ),
            'customer' => array(
                'title' => $this->l('Customer'),
                'havingFilter' => true,
            ),
        );

        if (Configuration::get('PS_B2B_ENABLE')) {
            $this->fields_list = array_merge($this->fields_list, array(
                'company' => array(
                    'title' => $this->l('Company'),
                    'filter_key' => 'c!company'
                ),
            ));
        }

        $this->fields_list = array_merge($this->fields_list, array(
			//修改 订单列表 展示金额为 总支付金额 
            //'total_paid_tax_incl' => array(
			'total_paid' => array(
                'title' => $this->l('Total'),
                'align' => 'text-right',
                'type' => 'price',
                'currency' => true,
                'callback' => 'setOrderCurrency',
                'badge_success' => true
            ),
			'total_shipping' => array(
                'title' => $this->l('Shipping')
            ),
			'mymark' => array(
                'title' => $this->l('Pkg Sign')
            ),
            'payment' => array(
                'title' => $this->l('Payment')
            ),
            'osname' => array(
                'title' => $this->l('Status'),
                'type' => 'select',
                'color' => 'color',
                'list' => $this->statuses_array,
                'filter_key' => 'os!id_order_state',
                'filter_type' => 'int',
                'order_key' => 'osname'
            ),
            'date_add' => array(
                'title' => $this->l('Date'),
                'align' => 'text-right',
                'type' => 'datetime',
                'filter_key' => 'a!date_add'
            ),
            'id_pdf' => array(
                'title' => $this->l('PDF'),
                'align' => 'text-center',
                'callback' => 'printPDFIcons',
                'orderby' => false,
                'search' => false,
                'remove_onclick' => true
            )
        ));

        if (Country::isCurrentlyUsed('country', true)) {
            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('
			SELECT DISTINCT c.id_country, cl.`name`
			FROM `'._DB_PREFIX_.'orders` o
			'.Shop::addSqlAssociation('orders', 'o').'
			INNER JOIN `'._DB_PREFIX_.'address` a ON a.id_address = o.id_address_delivery
			INNER JOIN `'._DB_PREFIX_.'country` c ON a.id_country = c.id_country
			INNER JOIN `'._DB_PREFIX_.'country_lang` cl ON (c.`id_country` = cl.`id_country` AND cl.`id_lang` = '.(int)$this->context->language->id.')
			ORDER BY cl.name ASC');

            $country_array = array();
            foreach ($result as $row) {
                $country_array[$row['id_country']] = $row['name'];
            }

            $part1 = array_slice($this->fields_list, 0, 3);
            $part2 = array_slice($this->fields_list, 3);
            $part1['cname'] = array(
                'title' => $this->l('Delivery'),
                'type' => 'select',
                'list' => $country_array,
                'filter_key' => 'country!id_country',
                'filter_type' => 'int',
                'order_key' => 'cname'
            );
            $this->fields_list = array_merge($part1, $part2);
        }

        $this->shopLinkType = 'shop';
        $this->shopShareDatas = Shop::SHARE_ORDER;

        if (Tools::isSubmit('id_order')) {
            // Save context (in order to apply cart rule)
            $order = new Order((int)Tools::getValue('id_order'));
            $this->context->cart = new Cart($order->id_cart);
            $this->context->customer = new Customer($order->id_customer);
        }

        $this->bulk_actions = array(
            'updateOrderStatus' => array('text' => $this->l('Change Order Status'), 'icon' => 'icon-refresh')
        );

        parent::__construct();
    }

    public static function setOrderCurrency($echo, $tr)
    {
        $order = new Order($tr['id_order']);
        return Tools::displayPrice($echo, (int)$order->id_currency);
    }

    public function initPageHeaderToolbar()
    {
        parent::initPageHeaderToolbar();

        if (empty($this->display)) {
            $this->page_header_toolbar_btn['new_order'] = array(
                'href' => self::$currentIndex.'&addorder&token='.$this->token,
                'desc' => $this->l('Add new order', null, null, false),
                'icon' => 'process-icon-new'
            );
        }

        if ($this->display == 'add') {
            unset($this->page_header_toolbar_btn['save']);
        }

        if (Context::getContext()->shop->getContext() != Shop::CONTEXT_SHOP && isset($this->page_header_toolbar_btn['new_order'])
            && Shop::isFeatureActive()) {
            unset($this->page_header_toolbar_btn['new_order']);
        }
    }

    public function renderForm()
    {
        if (Context::getContext()->shop->getContext() != Shop::CONTEXT_SHOP && Shop::isFeatureActive()) {
            $this->errors[] = $this->l('You have to select a shop before creating new orders.');
        }

        $id_cart = (int)Tools::getValue('id_cart');
        $cart = new Cart((int)$id_cart);
        if ($id_cart && !Validate::isLoadedObject($cart)) {
            $this->errors[] = $this->l('This cart does not exists');
        }
        if ($id_cart && Validate::isLoadedObject($cart) && !$cart->id_customer) {
            $this->errors[] = $this->l('The cart must have a customer');
        }
        if (count($this->errors)) {
            return false;
        }

        parent::renderForm();
        unset($this->toolbar_btn['save']);
        $this->addJqueryPlugin(array('autocomplete', 'fancybox', 'typewatch'));

        $defaults_order_state = array('cheque' => (int)Configuration::get('PS_OS_CHEQUE'),
                                                'bankwire' => (int)Configuration::get('PS_OS_BANKWIRE'),
                                                'cashondelivery' => Configuration::get('PS_OS_COD_VALIDATION') ? (int)Configuration::get('PS_OS_COD_VALIDATION') : (int)Configuration::get('PS_OS_PREPARATION'),
                                                'other' => (int)Configuration::get('PS_OS_PAYMENT'));
        $payment_modules = array();
        foreach (PaymentModule::getInstalledPaymentModules() as $p_module) {
            $payment_modules[] = Module::getInstanceById((int)$p_module['id_module']);
        }

        $this->context->smarty->assign(array(
            'recyclable_pack' => (int)Configuration::get('PS_RECYCLABLE_PACK'),
            'gift_wrapping' => (int)Configuration::get('PS_GIFT_WRAPPING'),
            'cart' => $cart,
            'currencies' => Currency::getCurrenciesByIdShop(Context::getContext()->shop->id),
            'langs' => Language::getLanguages(true, Context::getContext()->shop->id),
            'payment_modules' => $payment_modules,
            'order_states' => OrderState::getOrderStates((int)Context::getContext()->language->id),
            'defaults_order_state' => $defaults_order_state,
            'show_toolbar' => $this->show_toolbar,
            'toolbar_btn' => $this->toolbar_btn,
            'toolbar_scroll' => $this->toolbar_scroll,
            'PS_CATALOG_MODE' => Configuration::get('PS_CATALOG_MODE'),
            'title' => array($this->l('Orders'), $this->l('Create order'))

        ));
        $this->content .= $this->createTemplate('form.tpl')->fetch();
    }

    public function initToolbar()
    {
        if ($this->display == 'view') {
            /** @var Order $order */
            $order = $this->loadObject();
            $customer = $this->context->customer;

            if (!Validate::isLoadedObject($order)) {
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders'));
            }
			// 系统原有代码   $this->toolbar_title[] = sprintf($this->l('Order %1$s from %2$s %3$s'), $order->reference, $customer->firstname, $customer->lastname);
            $this->toolbar_title[] = sprintf($this->l('Order %1$s from %2$s %3$s'), '#'.$order->id, $customer->firstname, $customer->lastname);
            
			
			$this->addMetaTitle($this->toolbar_title[count($this->toolbar_title) - 1]);

            if ($order->hasBeenShipped()) {
                $type = $this->l('Return products');
            } elseif ($order->hasBeenPaid()) {
                $type = $this->l('Standard refund');
            } else {
                $type = $this->l('Cancel products');
            }

            if (!$order->hasBeenShipped() && !$this->lite_display) {
                $this->toolbar_btn['new'] = array(
                    'short' => 'Create',
                    'href' => '#',
                    'desc' => $this->l('Add a product'),
                    'class' => 'add_product'
                );
            }

            if (Configuration::get('PS_ORDER_RETURN') && !$this->lite_display) {
                $this->toolbar_btn['standard_refund'] = array(
                    'short' => 'Create',
                    'href' => '',
                    'desc' => $type,
                    'class' => 'process-icon-standardRefund'
                );
            }

            if ($order->hasInvoice() && !$this->lite_display) {
                $this->toolbar_btn['partial_refund'] = array(
                    'short' => 'Create',
                    'href' => '',
                    'desc' => $this->l('Partial refund'),
                    'class' => 'process-icon-partialRefund'
                );
            }
        }
        $res = parent::initToolbar();
		//修改 导出为 自定义导出
		$this->toolbar_btn['export'] = array(
                        // 'href' => self::$currentIndex.'&outexcel=text&token='.$this->token,
						'href' => self::$currentIndex.'&export'.$this->table.'&outexcel=order&token='.$this->token,
                        'desc' => $this->l('Export')
                    );
        if (Context::getContext()->shop->getContext() != Shop::CONTEXT_SHOP && isset($this->toolbar_btn['new']) && Shop::isFeatureActive()) {
            unset($this->toolbar_btn['new']);
        }
        return $res;
    }

    public function setMedia()
    {
        parent::setMedia();

        $this->addJqueryUI('ui.datepicker');
        $this->addJS(_PS_JS_DIR_.'vendor/d3.v3.min.js');
        //$this->addJS('https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false');

        if ($this->tabAccess['edit'] == 1 && $this->display == 'view') {
            $this->addJS(_PS_JS_DIR_.'admin/orders.js');
            $this->addJS(_PS_JS_DIR_.'tools.js');
            $this->addJqueryPlugin('autocomplete');
        }
    }

    public function printPDFIcons($id_order, $tr)
    {
        static $valid_order_state = array();

        $order = new Order($id_order);
        if (!Validate::isLoadedObject($order)) {
            return '';
        }

        if (!isset($valid_order_state[$order->current_state])) {
            $valid_order_state[$order->current_state] = Validate::isLoadedObject($order->getCurrentOrderState());
        }

        if (!$valid_order_state[$order->current_state]) {
            return '';
        }

        $this->context->smarty->assign(array(
            'order' => $order,
            'tr' => $tr
        ));

        return $this->createTemplate('_print_pdf_icon.tpl')->fetch();
    }

    public function printNewCustomer($id_order, $tr)
    {
        return ($tr['new'] ? $this->l('Yes') : $this->l('No'));
    }

    public function processBulkUpdateOrderStatus()
    {
        if (Tools::isSubmit('submitUpdateOrderStatus')
            && ($id_order_state = (int)Tools::getValue('id_order_state'))) {
            if ($this->tabAccess['edit'] !== '1') {
                $this->errors[] = Tools::displayError('You do not have permission to edit this.');
            } else {
                $order_state = new OrderState($id_order_state);

                if (!Validate::isLoadedObject($order_state)) {
                    $this->errors[] = sprintf(Tools::displayError('Order status #%d cannot be loaded'), $id_order_state);
                } else {
                    foreach (Tools::getValue('orderBox') as $id_order) {
                        $order = new Order((int)$id_order);
                        if (!Validate::isLoadedObject($order)) {
                            $this->errors[] = sprintf(Tools::displayError('Order #%d cannot be loaded'), $id_order);
                        } else {
                            $current_order_state = $order->getCurrentOrderState();
                            if ($current_order_state->id == $order_state->id) {
                                $this->errors[] = $this->displayWarning(sprintf('Order #%d has already been assigned this status.', $id_order));
                            } else {
                                $history = new OrderHistory();
                                $history->id_order = $order->id;
                                $history->id_employee = (int)$this->context->employee->id;

                                $use_existings_payment = !$order->hasInvoice();
                                $history->changeIdOrderState((int)$order_state->id, $order, $use_existings_payment);

                                $carrier = new Carrier($order->id_carrier, $order->id_lang);
                                $templateVars = array();
                                if ($history->id_order_state == Configuration::get('PS_OS_SHIPPING') && $order->shipping_number) {
                                    $templateVars = array('{followup}' => str_replace('@', $order->shipping_number, $carrier->url));
                                }

                                if ($history->addWithemail(true, $templateVars)) {
                                    if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')) {
                                        foreach ($order->getProducts() as $product) {
                                            if (StockAvailable::dependsOnStock($product['product_id'])) {
                                                StockAvailable::synchronize($product['product_id'], (int)$product['id_shop']);
                                            }
                                        }
                                    }
                                } else {
                                    $this->errors[] = sprintf(Tools::displayError('Cannot change status for order #%d.'), $id_order);
                                }
                            }
                        }
                    }
                }
            }
            if (!count($this->errors)) {
                Tools::redirectAdmin(self::$currentIndex.'&conf=4&token='.$this->token);
            }
        }
    }

    public function renderList()
    {
        if (Tools::isSubmit('submitBulkupdateOrderStatus'.$this->table)) {
            if (Tools::getIsset('cancel')) {
                Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token);
            }
            $this->tpl_list_vars['updateOrderStatus_mode'] = true;
            $this->tpl_list_vars['order_statuses'] = $this->statuses_array;
            $this->tpl_list_vars['REQUEST_URI'] = $_SERVER['REQUEST_URI'];
            $this->tpl_list_vars['POST'] = $_POST;
        }

        return parent::renderList();
    }

    public function postProcess()
    {
        // If id_order is sent, we instanciate a new Order object
        if (Tools::isSubmit('id_order') && Tools::getValue('id_order') > 0) {
            $order = new Order(Tools::getValue('id_order'));
            if (!Validate::isLoadedObject($order)) {
                $this->errors[] = Tools::displayError('The order cannot be found within your database.');
            }
            ShopUrl::cacheMainDomainForShop((int)$order->id_shop);
        }
		
		//退换货 操作
		if (Tools::isSubmit('submitmyReturn') && isset($order)) {
			
			$employee_name = $this->context->employee->firstname.'.'.$this->context->employee->lastname;
			
			$id_return_order = (int)$order->id;
            $employee = (int)$this->context->employee->id;
	
            $comreturn_reason = intval(Tools::getValue("comreturn_reason"));
            $comreturn_comment =  pSQL(Tools::getValue('comreturn_comment',''));
            $comreturn_refund = Tools::getValue('comreturn_refund');
            $comreturn_sum = Tools::getValue('comreturn_sum');
            $comreturn_return = Tools::getValue('comreturn_return');
            $comreturnresult=Db::getInstance()->getRow("select comreturn_reason,comreturn_comment,comreturn_refund,comreturn_sum,comreturn_return from px_order_comreturn where id_order='".$id_return_order."' ");
			
			
			  if($comreturnresult){
                    if ($comreturn_reason!=$comreturnresult['comreturn_reason']
                    		OR $comreturn_comment!=$comreturnresult['comreturn_comment']
                    		OR $employee!=$comreturnresult['id_employee']
                    		OR $comreturn_sum!=$comreturnresult['comreturn_sum']
                    		OR $comreturn_refund!=$comreturnresult['comreturn_refund']
                    		OR $comreturn_return!=$comreturnresult['comreturn_return']) {
				
                    	Db::getInstance()->Execute("
                    		update px_order_comreturn set
                    			comreturn_reason='".$comreturn_reason."'
                    			,comreturn_comment='".$comreturn_comment."'
                    			,comreturn_refund='".$comreturn_refund."'
                    			,comreturn_sum='".$comreturn_sum."'
                    			,comreturn_return='".$comreturn_return."'
                    			,id_employee='".$employee."'
                    			,operate_time='".date('Y-m-d H:m:s')."'
                    		where id_order='".$id_return_order."'"
                    	);
						
					
						$search  = array("100","12","11","10","9","8",'7','6',"5","4","3","2","1");
						$replace = array("其它问题","长度问题","产品有残次","发质不适合自己，不融合",
										"曲度问题","款式不喜欢，不合适",'网底大小不合适',
										'自动退回件','密度问题',
										'不喜欢，不适合，不是自己期望的','物流问题','质量问题','颜色问题');
						
						$comreturn_reason = str_replace($search ,$replace,$comreturn_reason);
						
						$action  = "<span style=\'color:red\'>$employee_name</span> 更新信息-退换原因<span style=\'color:red\'>($comreturn_reason)</span>--退换备注<span style=\'color:red\'>($comreturn_comment)</span>--
						退换方式<span style=\'color:red\'>($comreturn_refund)</span>--金额<span style=\'color:red\'>($comreturn_sum)</span>货品是否退回<span style=\'color:red\'>($comreturn_return)</span>";
						$employee_name = $this->context->employee->firstname.'.'.$this->context->employee->lastname;
						Db::getInstance()->Execute("
                    		insert into  px_order_comreturn_history 
							(id_order,action,actor,operate_time) values (".(int)$order->id.",'".$action."',
							'".$this->context->employee->email."',date_sub(now(), interval 12 hour))"
                    	);
						
                    }
                }else{
                    Db::getInstance()->Execute("insert into px_order_comreturn
                    	(id_order,id_employee,comreturn_reason,comreturn_comment,comreturn_refund,comreturn_sum,comreturn_return,operate_time)
                    	values('".$id_return_order."','".$employee."','".$comreturn_reason."','".$comreturn_comment."','".$comreturn_refund."','".$comreturn_sum."','".$comreturn_return."','".date('Y-m-d H:m:s')."') "
                    );
					$search  = array("100","12","11","10","9","8",'7','6',"5","4","3","2","1");
					$replace = array("其它问题","长度问题","产品有残次","发质不适合自己，不融合",
										"曲度问题","款式不喜欢，不合适",'网底大小不合适',
										'自动退回件','密度问题',
										'不喜欢，不适合，不是自己期望的','物流问题','质量问题','颜色问题');
						
					$comreturn_reason = str_replace($search ,$replace,$comreturn_reason);

					
					$action  = "<span style=\'color:red\'>$employee_name</span> 更新信息-退换原因<span style=\'color:red\'>($comreturn_reason)</span>--退换备注<span style=\'color:red\'>($comreturn_comment)</span>--
						退换方式<span style=\'color:red\'>($comreturn_refund)</span>--金额<span style=\'color:red\'>($comreturn_sum)</span>货品是否退回<span style=\'color:red\'>($comreturn_return)</span>";
					$employee_name = $this->context->employee->firstname.'.'.$this->context->employee->lastname;
					Db::getInstance()->Execute("
                    		insert into  px_order_comreturn_history 
							(id_order,action,actor,operate_time) values (".(int)$order->id.",'".$action."',
							'".$employee_name."',date_sub(now(), interval 12 hour))"
                    	);
					
					
                }
			Tools::redirectAdmin(self::$currentIndex.'&id_order='.(int)$order->id.'&vieworder&token='.$this->token);
			//exit;
		}
		
		
		//订单产品 成本价/批发价设置
		if (Tools::isSubmit('submitOrderWholesale') && isset($order)) {
			$mid_order = (int)$order->id;
			$whole_price= $_POST['whole_price'];
			$whole_product = $_POST['whole_product'];
			$this->errors[] = Tools::displayError('The order cannot be found within your database.');
			//更新产品成本价
			Db::getInstance()->execute("UPDATE ps_order_detail SET original_wholesale_price = '$whole_price'
WHERE  id_order = $mid_order
AND product_reference = '$whole_product'");
			
			
			Tools::redirectAdmin(self::$currentIndex.'&id_order='.(int)$order->id.'&vieworder&token='.$this->token);
		}
		
		
		
		
		
		if (Tools::isSubmit('submitOrderRemind') && isset($order)) {
			//获取 当前登录人员的姓名 
			$employee_name = $this->context->employee->firstname.'.'.$this->context->employee->lastname;
			
			if(Tools::getValue('id_remind')){
				
				$nowdate=date("y-m-d");
				$adddate=Tools::getValue('remind_date');
				if($adddate=='' or strtotime($adddate)<strtotime($nowdate)){	
				 $this->errors[] = Tools::displayError("请填写正确的备货时间(大于当前时间)");	
				}else{
					
				//更新已经存在的 id_remind 
				Db::getInstance()->execute("UPDATE 
				px_order_remind SET date = '".Tools::getValue('remind_date')."', 
				actor ='$employee_name',date_upd =date_sub(now(), interval 1 day),
				manufacture='".Tools::getValue('remind_manufacture')."',status='".Tools::getValue('remind_status')."'
				WHERE id_remind=".Tools::getValue('id_remind'));
				
				$action = '<span style="color:red">'.$employee_name."</span>"."更新订单(".Tools::getValue('id_order').")的产品(".'<span style="color:red">'.Tools::getValue('skus')."</span>)备货截止日期为(".'<span style="color:red">'.Tools::getValue('remind_date')."</span>)";
				//增加生产编号  和 生产状态  两个字段
				$action.="生产单号为(".'<span style="color:red">'.Tools::getValue('remind_manufacture')."</span>)";
				$action.="生产状态为(".'<span style="color:red">'.Tools::getValue('remind_status')."</span>)";
				
				Db::getInstance()->execute("insert into px_order_remind_history 
				(id_remind,action,date_add,actor)VALUES (".Tools::getValue('id_remind').",'$action',date_sub(now(), interval 1 day),'$employee_name')");
				//执行成功后 跳转回原有order页面
				Tools::redirectAdmin(self::$currentIndex.'&id_order='.(int)$order->id.'&vieworder&token='.$this->token);
					
				}
				
				
			
			
			}else
			{
				$nowdate=date("y-m-d");
				$adddate=Tools::getValue('remind_date');
				if($adddate=='' or strtotime($adddate)<strtotime($nowdate)){	
				 $this->errors[] = Tools::displayError("请填写正确的备货时间(大于当前时间)");	
				}else{
					$remind_exits= Db::getInstance()->getValue("select id_remind from px_order_remind where id_order ='".Tools::getValue('id_order')."' and product_name ='".Tools::getValue('product_name')."'"."
					");	
					if(!$remind_exits){
						Db::getInstance()->execute("insert into px_order_remind 
						(id_order,skus,product_name,date,status,manufacture,date_upd,actor)
						VALUES 
						(".Tools::getValue('id_order').",'".Tools::getValue('skus')."','".Tools::getValue('product_name')."','".Tools::getValue('remind_date')."','".Tools::getValue('remind_status')."','".Tools::getValue('remind_manufacture')."',date_sub(now(), interval 1 day),'$employee_name')  
						");
						
						//产品名称 存在特殊字符 
						$product_name=	str_replace('"','\"',Tools::getValue('product_name'));
						$sqlre="select id_remind from px_order_remind where id_order ='".(int)$order->id."'and product_name ='$product_name'";
						$id_reming= Db::getInstance()->getValue($sqlre);

						//备货信息更新 发送站内信
						if(Tools::getValue('remind_status') == '生产'){
								$cname = Db::getInstance()->getValue('select CONCAT(firstname,\'.\',lastname) as cname from  ps_customer where  id_customer =' . $order->id_customer);
			
								$params = [];
								$params['name'] =  $cname;//用户姓名
								$params['id_order'] =  (int)$order->id;//订单号
								$params['id_status'] =  3;//订单状态id
								$params['datetime'] =  $adddate;//客服填写的备货时间
								
								$subject = '订单备货通知'; 
								$title =  'Your Order Is Under Preparation' ;
								$params['status'] =  'Preparation in progress';//订单状态
								
								if(_MSG_OPEN){
									Tools::sendSiteMsg('person',$order->id_customer,$subject,$title,json_encode($params));
									
								}
							
						}
					
						
						$action = '<span style="color:red">'.$employee_name."</span>"."更新订单(".Tools::getValue('id_order').")的产品(".'<span style="color:red">'.Tools::getValue('skus')."</span>)备货截止日期为(".'<span style="color:red">'.Tools::getValue('remind_date')."</span>)";
						if(Tools::getValue('remind_manufacture')!=''){
						$action.="生产单号为(".'<span style="color:red">'.Tools::getValue('remind_manufacture')."</span>)";
						}
						if(Tools::getValue('remind_status')!=''){
						$action.="生产状态为(".'<span style="color:red">'.Tools::getValue('remind_status')."</span>)";
						}
						Db::getInstance()->execute("insert into px_order_remind_history 
						(id_remind,action,date_add,actor)VALUES ($id_reming,'$action',date_sub(now(), interval 1 day),'$employee_name')");
						 //执行成功后 跳转回原有order页面
						 Tools::redirectAdmin(self::$currentIndex.'&id_order='.(int)$order->id.'&vieworder&token='.$this->token);
					}	
					
				}
			
				
				
			}
		
		
		}
		
		
        /* Update shipping number  更新物流信息 */
        if (Tools::isSubmit('submitShippingNumber') && isset($order)) {
            if ($this->tabAccess['edit'] === '1') {
			
                $order_carrier = new OrderCarrier(Tools::getValue('id_order_carrier'));
					
                if (!Validate::isLoadedObject($order_carrier)) {
                    $this->errors[] = Tools::displayError('The order carrier ID is invalid.');
                } elseif (!Validate::isTrackingNumber(Tools::getValue('tracking_number'))) {
                    $this->errors[] = Tools::displayError('The tracking number is incorrect.');
                } else {
                    // update shipping number
                    // Keep these two following lines for backward compatibility, remove on 1.6 version
					
				
                    $order->shipping_number = Tools::getValue('tracking_number');
                    $order->update();

                    // Update order_carrier
                    $order_carrier->tracking_number = pSQL(Tools::getValue('tracking_number'));
					//获取真实的快递名称
					
					$order->real_carrier    =Tools::getValue('rcarrier');	
					if(Tools::getValue('rcarrier')=='0'){
						
						$order->real_carrier ='USPS';
					}
						//echo $order->real_carrier;
				
						
					//更新快递名称
					if(!$this->updatecarrier(Tools::getValue('id_order'),$order->real_carrier))
					{
					echo '快递名称更改失败';
						
					}
			
                    if ($order_carrier->update()) {
                        // Send mail to customer
                        $customer = new Customer((int)$order->id_customer);
                        $carrier = new Carrier((int)$order->id_carrier, $order->id_lang);
                        if (!Validate::isLoadedObject($customer)) {
                            throw new PrestaShopException('Can\'t load Customer object');
                        }
                        if (!Validate::isLoadedObject($carrier)) {
                            throw new PrestaShopException('Can\'t load Carrier object');
                        }
                        $templateVars = array(
                            '{followup}' => str_replace('@', $order->shipping_number, $carrier->url),
                            '{firstname}' => $customer->firstname,
                            '{lastname}' => $customer->lastname,
                            '{id_order}' => $order->id,
                            '{shipping_number}' => $order->shipping_number,
                            '{order_name}' => $order->getUniqReference()
                        );
						/*
						 * mail 函数自定义参数 
						 *Mail::Send('language','template_name','mail_subject','template_var','email','customer');
						 * $customer->email
						 */
                        if (@Mail::Send((int)$order->id_lang, 'in_transit', Mail::l('Package in transit', (int)$order->id_lang), $templateVars,
                            $customer->email, $customer->firstname.' '.$customer->lastname, null, null, null, null,
                            _PS_MAIL_DIR_, true, (int)$order->id_shop)) {
                            Hook::exec('actionAdminOrdersTrackingNumberUpdate', array('order' => $order, 'customer' => $customer, 'carrier' => $carrier), null, false, true, false, $order->id_shop);
                            Tools::redirectAdmin(self::$currentIndex.'&id_order='.$order->id.'&vieworder&conf=4&token='.$this->token);
                        } else {
                            $this->errors[] = Tools::displayError('An error occurred while sending an email to the customer.');
                        }
                    } else {
                        $this->errors[] = Tools::displayError('The order carrier cannot be updated.');
                    }
                }
            } else {
                $this->errors[] = Tools::displayError('You do not have permission to edit this.');
            }
        }
		//统一物流操作
		elseif(Tools::isSubmit('submitSameShippingNumber') && isset($order)){
		
			//删除 可能存在的记录 
			$res =Db::getInstance()->execute("
			delete from  px_differ_carrier where id_order = ".$order->id);
			
			if(Tools::getValue('rcarrier')=='0'){
				
				$rcarrier='USPS';
			}else{
				$rcarrier=Tools::getValue('rcarrier');
				
			}
			
			//插入最新的数据 
			$product_name  =Db::getInstance()->executeS("
			select * from  ps_order_detail where id_order = ".$order->id);
			
			foreach($product_name as $a ){
					Db::getInstance()->execute("
			insert  into  px_differ_carrier (`id_order`,`sku`,`product_name`,
			`real_carrier`,`tracking_number`
			,`date_add`)value 
			('".$order->id."','".$a['product_reference']."','" . pSQL($a['product_name']) . "',
			'$rcarrier','".Tools::getValue('tracking_number')."',now())");	
			}
		    $customer = new Customer((int)$order->id_customer);
			switch ($rcarrier)
			{
			case 'USPS':
			  $curl='https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1=';
			  break;  
			case 'UPS':
			  $curl='https://www.ups.com/';
			  break;
			case 'FEDEX':
			  $curl='http://www.fedex.com/us/';
			  break;
			case 'DHL':
			  $curl='http://www.dhl.com/en.html';
			  break;
			case 'EMS':
			  $curl='http://www.ems.com/';
			  break;
			default:
			  ;
			}
			
			//批量推送 订单物流信息 
			
			//7.发货后 推送物流单号  --存在拆单的 则要发送多个物流信息

			$params = [];
			$params['name'] =  $customer->firstname . '.' . $customer->lastname;//用户姓名
			$params['id_order'] =  $order->id;//订单号
			$params['track_type'] =$rcarrier ;//物流平台
			$params['track_num'] = Tools::getValue('tracking_number') ;//物流单号
			if(_MSG_OPEN){
			Tools::sendSiteMsg('person',$customer->id,'订单物流通知','Your Order Is Shipped',json_encode($params));
			}
			
			
			
			$templateVars = array(
                            '{followup}' => $rcarrier.'--'.Tools::getValue('tracking_number'),
							'{curl}'=> $curl,
                            '{firstname}' => $customer->firstname,
                            '{lastname}' => $customer->lastname,
                            '{id_order}' => $order->id,
                            '{shipping_number}' =>$rcarrier.'--'.Tools::getValue('tracking_number'),
                            '{order_name}' => $order->id
                        );
		
			 if (@Mail::Send((int)$order->id_lang, 'in_transit', Mail::l('Package in transit', (int)$order->id_lang), $templateVars,
                           $customer->email, $customer->firstname.' '.$customer->lastname, null, null, null, null,
                            _PS_MAIL_DIR_, true, (int)$order->id_shop)) {
                            Hook::exec('actionAdminOrdersTrackingNumberUpdate', array('order' => $order, 'customer' => $customer, 'carrier' => $carrier), null, false, true, false, $order->id_shop);
                            Tools::redirectAdmin(self::$currentIndex.'&id_order='.$order->id.'&vieworder&conf=4&token='.$this->token);
                        } else {
                            $this->errors[] = Tools::displayError('An error occurred while sending an email to the customer.');
            }
		
		
		}
		
		
		//分单物流操作
		elseif(Tools::isSubmit('submitDifferShippingNumber') && isset($order)){

			
			$res =Db::getInstance()->executeS( "
			select  *  from  px_differ_carrier where id_order = ".$order->id." and  sku= '".Tools::getValue('skus')."'");
			
			if(Tools::getValue('rcarrier')=='0'){
				
				$rcarrier='USPS';
			}else{
				$rcarrier=Tools::getValue('rcarrier');
				
			}
		
			
			if($res){
		
			Db::getInstance()->execute("update  px_differ_carrier  
			set  real_carrier='$rcarrier'   ,
			tracking_number='".Tools::getValue('tracking_number')."',date_add=now() 
			 where id_order = ".$order->id." 
			and  sku= '".Tools::getValue('skus')."'");
			
		
			$customer = new Customer((int)$order->id_customer);
			
			switch ($rcarrier)
			{
			case 'USPS':
			  $curl='https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1=';
			  break;  
			case 'UPS':
			  $curl='https://www.ups.com/';
			  break;
			case 'FEDEX':
			  $curl='http://www.fedex.com/us/';
			  break;
			case 'DHL':
			  $curl='http://www.dhl.com/en.html';
			  break;
			case 'EMS':
			  $curl='http://www.ems.com/';
			  break;
			case 'TNT':
			  $curl='http://www.tnt.com/express/en_us/site/home.html';
			  break;
			default:
			  ;
			}
			
			//7.发货后 推送物流单号  --存在拆单的 则要发送多个物流信息
		
			$params = [];
			$params['name'] =  $customer->firstname . '.' . $customer->lastname;//用户姓名
			$params['id_order'] =  $order->id;//订单号
			$params['pname'] =  pSQL(Tools::getValue('carrier_product'));
			$params['track_type'] =$rcarrier ;//物流平台
			$params['track_num'] = Tools::getValue('tracking_number') ;//物流单号
			if(_MSG_OPEN){
			Tools::sendSiteMsg('person',$customer->id,'订单物流通知','Your Order Is Shipped',json_encode($params));
			}
			$templateVars = array(
                            '{followup}' => $rcarrier.'--'.Tools::getValue('tracking_number'),
							'{curl}'=> $curl.Tools::getValue('tracking_number'),
                            '{firstname}' => $customer->firstname,
                            '{lastname}' => $customer->lastname,
                            '{id_order}' => $order->id,
							'{skus}' => Tools::getValue('skus'),
                            '{shipping_number}' =>$rcarrier.'--'.Tools::getValue('tracking_number'),
                            '{product_name}' => Tools::getValue('carrier_product')
                        );
			
			$check_ship = "select  IF( COUNT(DISTINCT a.product_name) = COUNT(DISTINCT b.product_name),'yes','no')    as  ship


			 from  ps_order_detail  a 
			LEFT JOIN  px_differ_carrier  b  on a.id_order = b.id_order  and  a.product_name = b.product_name 

			where a.id_order = ".$order->id."

			GROUP BY  a.id_order    
			";
			$res = Db::getInstance()->getValue($check_ship);
		
			
			if(  $res == 'yes'  ){
				
				
				$tpl_name =  'in_transit_differ';
					
				
			}else{
				
				$tpl_name =  'in_transit_differ_noship';
			}
			
			
			 if (@Mail::Send((int)$order->id_lang, $tpl_name, Mail::l('Package in transit', (int)$order->id_lang), $templateVars,
                         $customer->email, $customer->firstname.' '.$customer->lastname, null, null, null, null,
                            _PS_MAIL_DIR_, true, (int)$order->id_shop)) {
                            Hook::exec('actionAdminOrdersTrackingNumberUpdate', array('order' => $order, 'customer' => $customer, 'carrier' => $carrier), null, false, true, false, $order->id_shop);
                            Tools::redirectAdmin(self::$currentIndex.'&id_order='.$order->id.'&vieworder&conf=4&token='.$this->token);
                        } else {
                            $this->errors[] = Tools::displayError('An error occurred while sending an email to the customer.');
            }	
			


			//echo  '更新成功';
				
			}else{
				$id_order= $order->id;
			Db::getInstance()->execute("
			insert  into  px_differ_carrier (`id_order_carrier`,`id_order`,`sku`,`product_name`,
			`real_carrier`,`tracking_number`
			,`date_add`)value 
			('".Tools::getValue('id_order_carrier')."','$id_order','".Tools::getValue('skus')."','". pSQL(Tools::getValue('carrier_product')) ."',
			'$rcarrier','".Tools::getValue('tracking_number')."',now())");
			$customer = new Customer((int)$order->id_customer);
			switch ($rcarrier)
			{
			case 'USPS':
			  $curl='https://www.usps.com/';
			  break;  
			case 'UPS':
			  $curl='https://www.ups.com/';
			  break;
			case 'FEDEX':
			  $curl='http://www.fedex.com/us/';
			  break;
			case 'DHL':
			  $curl='http://www.dhl.com/en.html';
			  break;
			case 'EMS':
			  $curl='http://www.ems.com/';
			  break;
			case 'TNT':
			  $curl='http://www.tnt.com/express/en_us/site/home.html';
			  break;
			default:
			  ;
			}
			
			//7.发货后 推送物流单号  --存在拆单的 则要发送多个物流信息

			$params = [];
			$params['name'] =  $customer->firstname . '.' . $customer->lastname;//用户姓名
			$params['id_order'] =  $order->id;//订单号
			$params['pname'] =  pSQL(Tools::getValue('carrier_product'));
			$params['track_type'] =$rcarrier ;//物流平台
			$params['track_num'] = Tools::getValue('tracking_number') ;//物流单号
			if(_MSG_OPEN){
			Tools::sendSiteMsg('person',$customer->id,'订单物流通知','Your Order Is Shipped',json_encode($params));
			}
			
			
			$templateVars = array(
                            '{followup}' => $rcarrier.'--'.Tools::getValue('tracking_number'),
                            '{firstname}' => $customer->firstname,
							'{curl}'=> $curl,
                            '{lastname}' => $customer->lastname,
                            '{id_order}' => $order->id,
							'{skus}' => Tools::getValue('skus'),
                            '{shipping_number}' =>$rcarrier.'--'.Tools::getValue('tracking_number'),
                            '{product_name}' => Tools::getValue('carrier_product')
                        );
						
			
			$check_ship = "select  IF( COUNT(DISTINCT a.product_name) = COUNT(DISTINCT b.product_name),'yes','no')    as  ship


 from  ps_order_detail  a 
LEFT JOIN  px_differ_carrier  b  on a.id_order = b.id_order  and  a.product_name = b.product_name 

where a.id_order = ".$order->id."

GROUP BY  a.id_order    
";
			$res = Db::getInstance()->getValue($check_ship);
			
			
			
			if(  $res == 'yes' ){
				$tpl_name =  'in_transit_differ';
			}else{
				
				$tpl_name =  'in_transit_differ_noship';
			}
		
			 if (@Mail::Send((int)$order->id_lang, $tpl_name, Mail::l('Package in transit', (int)$order->id_lang), $templateVars,
                          $customer->email, $customer->firstname.' '.$customer->lastname, null, null, null, null,
                            _PS_MAIL_DIR_, true, (int)$order->id_shop)) {
                            Hook::exec('actionAdminOrdersTrackingNumberUpdate', array('order' => $order, 'customer' => $customer, 'carrier' => $carrier), null, false, true, false, $order->id_shop);
                            Tools::redirectAdmin(self::$currentIndex.'&id_order='.$order->id.'&vieworder&conf=4&token='.$this->token);
                        } else {
                            $this->errors[] = Tools::displayError('An error occurred while sending an email to the customer.');
				}		
				//echo  '插入成功';	
			
			
			}
			
			
			
			
		}
			
        /* Change order status, add a new entry in order history and send an e-mail to the customer if needed */
        elseif (Tools::isSubmit('submitState') && isset($order)) {
            if ($this->tabAccess['edit'] === '1') {
                $order_state = new OrderState(Tools::getValue('id_order_state'));
			
                if (!Validate::isLoadedObject($order_state)) {
                    $this->errors[] = Tools::displayError('The new order status is invalid.');
				
                } else {
                    $current_order_state = $order->getCurrentOrderState();
                    if ($current_order_state->id != $order_state->id) {
                        // Create new OrderHistory
                        $history = new OrderHistory();
                        $history->id_order = $order->id;
                        $history->id_employee = (int)$this->context->employee->id;

                        $use_existings_payment = false;
                        if (!$order->hasInvoice()) {
                            $use_existings_payment = true;
                        }

						
                        $history->changeIdOrderState((int)$order_state->id, $order, $use_existings_payment);
						
						$checkFirstSql =  "select id_order  from  ps_orders  where  id_customer  =  ".$order->id_customer  ." limit 2 " ;
							
						$checkFirst = Db::getInstance()->executeS($checkFirstSql);
						
						if(count($checkFirst)  <2){
							
							$checkFirst = true ;
							
							
						}else{
							
							$checkFirst = false; 
						}
						if($order_state->id =='4' or  $checkFirst){
							//判断客户等级 是否发生变化  
							
							$oldLevel = $this->queryOrderOldCustomerLevel($order->id);
							$nowLevel = $this->queryOrderCustomerLevel($order->id);
							$checkFirstSql =  "select id_order  from  ps_orders  where  id_customer  =  ".$order->id_customer  ." limit 2 " ;
							
							
							if($nowLevel >$oldLevel ){
								
							     $changeLevel = true ;
								 
								 $this->changeCustomerLevelGroup($order->id_customer,$nowLevel);
								
							}else{
								
								$changeLevel = false  ;
							}
							
							
							//发生变化 则发送等级提醒邮件  并更改分组至正确的分组 
							//查询客户新等级 应属分组 
							
							if( (file_exists(_PS_ROOT_DIR_ . '/mails/en/customer_level_test.html')  && $changeLevel )   or  $checkFirst  ){
								//当前订单状态 ;(int)$order_state->id
								
								$customer =new Customer($order->id_customer);
								   $varsTpl = array(
									'{lastname}' => $customer->lastname,
									'{firstname}' => $customer->firstname,
									'{id_order}' => $order->id,
									'{order_name}' => $order->getUniqReference(),
									'{level}' => $this->queryOrderCustomerLevel($order->id)
									);

								if(@Mail::Send((int)$order->id_lang, 'customer_level_test',
									Mail::l('YOUR CUSTOMER GRADE LEVEL UP', (int)$order->id_lang), $varsTpl,$customer->email,
									$customer->firstname.' '.$customer->lastname, null, null, null, null, _PS_MAIL_DIR_, true, (int)$order->id_shop)){
										echo  '发送成功';
										
									}else{
										
										$this->errors[] = Tools::displayError('无法发送 客户等级提醒邮件.');
									}
								/* if(@Mail::Send((int)$order->id_lang, 'customer_level_test',
									Mail::l('New message regarding your order', (int)$order->id_lang), $varsTpl,'alex@uniwigs.com',
									$customer->firstname.' '.$customer->lastname, null, null, null, null, _PS_MAIL_DIR_, true, (int)$order->id_shop)){
										
										
									}else{
										
										$this->errors[] = Tools::displayError('无法发送客户 等级提醒邮件.');
									} */	
									
								
							}else{
								if( (file_exists(_PS_ROOT_DIR_ . '/mails/en/customer_level.html')   && $changeLevel )  or  $checkFirst ){
									$customer =new Customer($order->id_customer);
									 $varsTpl = array(
									'{lastname}' => $customer->lastname,
									'{firstname}' => $customer->firstname,
									'{id_order}' => $order->id,
									'{order_name}' => $order->getUniqReference(),
									'{level}' => $this->queryOrderCustomerLevel($order->id)
									);
										
									if(@Mail::Send((int)$order->id_lang, 'customer_level',
										Mail::l('New message regarding your order', (int)$order->id_lang), $varsTpl,$customer->email,
										$customer->firstname.' '.$customer->lastname, null, null, null, null, _PS_MAIL_DIR_, true, (int)$order->id_shop)){
											
											
										}else{
											
											$this->errors[] = Tools::displayError('无法发送客户 等级提醒邮件.');
										}
								}								
							}		
						}
                        $carrier = new Carrier($order->id_carrier, $order->id_lang);
                        $templateVars = array();
                        if ($history->id_order_state == Configuration::get('PS_OS_SHIPPING') && $order->shipping_number) {
                            $templateVars = array('{followup}' => str_replace('@', $order->shipping_number, $carrier->url));
                        }
						
						
                        // Save all changes
                        if ($history->addWithemail(true, $templateVars)) {
                            // synchronizes quantities if needed..
                            if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')) {
                                foreach ($order->getProducts() as $product) {
                                    if (StockAvailable::dependsOnStock($product['product_id'])) {
                                        StockAvailable::synchronize($product['product_id'], (int)$product['id_shop']);
                                    }
                                }
                            }													
							
                            Tools::redirectAdmin(self::$currentIndex.'&id_order='.(int)$order->id.'&vieworder&token='.$this->token);
                        }
                        $this->errors[] = Tools::displayError('An error occurred while changing order status, or we were unable to send an email to the customer.');
                    } else {
                        $this->errors[] = Tools::displayError('The order has already been assigned this status.');
                    }
                }
            } else {
                $this->errors[] = Tools::displayError('You do not have permission to edit this.');
            }
        }


        /* Add a new message for the current order and send an e-mail to the customer if needed */
        elseif (Tools::isSubmit('submitMessage') && isset($order)) {
            if ($this->tabAccess['edit'] === '1') {
                $customer = new Customer(Tools::getValue('id_customer'));
                if (!Validate::isLoadedObject($customer)) {
                    $this->errors[] = Tools::displayError('The customer is invalid.');
                } elseif (!Tools::getValue('message')) {
                    $this->errors[] = Tools::displayError('The message cannot be blank.');
                } else {
                    /* Get message rules and and check fields validity */
                    $rules = call_user_func(array('Message', 'getValidationRules'), 'Message');
                    foreach ($rules['required'] as $field) {
                        if (($value = Tools::getValue($field)) == false && (string)$value != '0') {
                            if (!Tools::getValue('id_'.$this->table) || $field != 'passwd') {
                                $this->errors[] = sprintf(Tools::displayError('field %s is required.'), $field);
                            }
                        }
                    }
                    foreach ($rules['size'] as $field => $maxLength) {
                        if (Tools::getValue($field) && Tools::strlen(Tools::getValue($field)) > $maxLength) {
                            $this->errors[] = sprintf(Tools::displayError('field %1$s is too long (%2$d chars max).'), $field, $maxLength);
                        }
                    }
                    foreach ($rules['validate'] as $field => $function) {
                        if (Tools::getValue($field)) {
                            if (!Validate::$function(htmlentities(Tools::getValue($field), ENT_COMPAT, 'UTF-8'))) {
                                $this->errors[] = sprintf(Tools::displayError('field %s is invalid.'), $field);
                            }
                        }
                    }

                    if (!count($this->errors)) {
                        //check if a thread already exist
                        $id_customer_thread = CustomerThread::getIdCustomerThreadByEmailAndIdOrder($customer->email, $order->id);
                        if (!$id_customer_thread) {
                            $customer_thread = new CustomerThread();
                            $customer_thread->id_contact = 0;
                            $customer_thread->id_customer = (int)$order->id_customer;
                            $customer_thread->id_shop = (int)$this->context->shop->id;
                            $customer_thread->id_order = (int)$order->id;
                            $customer_thread->id_lang = (int)$this->context->language->id;
                            $customer_thread->email = $customer->email;
                            $customer_thread->status = 'open';
                            $customer_thread->token = Tools::passwdGen(12);
                            $customer_thread->add();
                        } else {
                            $customer_thread = new CustomerThread((int)$id_customer_thread);
                        }

                        $customer_message = new CustomerMessage();
                        $customer_message->id_customer_thread = $customer_thread->id;
                        $customer_message->id_employee = (int)$this->context->employee->id;
                        $customer_message->message = Tools::getValue('message');
                        $customer_message->private = Tools::getValue('visibility');

                        if (!$customer_message->add()) {
                            $this->errors[] = Tools::displayError('An error occurred while saving the message.');
                        } elseif ($customer_message->private) {
                            Tools::redirectAdmin(self::$currentIndex.'&id_order='.(int)$order->id.'&vieworder&conf=11&token='.$this->token);
                        } else {
                            $message = $customer_message->message;
                            if (Configuration::get('PS_MAIL_TYPE', null, null, $order->id_shop) != Mail::TYPE_TEXT) {
                                $message = Tools::nl2br($customer_message->message);
                            }

                            $varsTpl = array(
                                '{lastname}' => $customer->lastname,
                                '{firstname}' => $customer->firstname,
                                '{id_order}' => $order->id,
                                '{order_name}' => $order->getUniqReference(),
                                '{message}' => $message
                            );
                            if (@Mail::Send((int)$order->id_lang, 'order_merchant_comment',
                                Mail::l('New message regarding your order', (int)$order->id_lang), $varsTpl, $customer->email,
                                $customer->firstname.' '.$customer->lastname, null, null, null, null, _PS_MAIL_DIR_, true, (int)$order->id_shop)) {
                                Tools::redirectAdmin(self::$currentIndex.'&id_order='.$order->id.'&vieworder&conf=11'.'&token='.$this->token);
                            }
                        }
                        $this->errors[] = Tools::displayError('An error occurred while sending an email to the customer.');
                    }
                }
            } else {
                $this->errors[] = Tools::displayError('You do not have permission to delete this.');
            }
        }

        /* Partial refund from order */
        elseif (Tools::isSubmit('partialRefund') && isset($order)) {
            if ($this->tabAccess['edit'] == '1') {
                if (Tools::isSubmit('partialRefundProduct') && ($refunds = Tools::getValue('partialRefundProduct')) && is_array($refunds)) {
                    $amount = 0;
                    $order_detail_list = array();
                    $full_quantity_list = array();
                    foreach ($refunds as $id_order_detail => $amount_detail) {
                        $quantity = Tools::getValue('partialRefundProductQuantity');
                        if (!$quantity[$id_order_detail]) {
                            continue;
                        }

                        $full_quantity_list[$id_order_detail] = (int)$quantity[$id_order_detail];

                        $order_detail_list[$id_order_detail] = array(
                            'quantity' => (int)$quantity[$id_order_detail],
                            'id_order_detail' => (int)$id_order_detail
                        );

                        $order_detail = new OrderDetail((int)$id_order_detail);
                        if (empty($amount_detail)) {
                            $order_detail_list[$id_order_detail]['unit_price'] = (!Tools::getValue('TaxMethod') ? $order_detail->unit_price_tax_excl : $order_detail->unit_price_tax_incl);
                            $order_detail_list[$id_order_detail]['amount'] = $order_detail->unit_price_tax_incl * $order_detail_list[$id_order_detail]['quantity'];
                        } else {
                            $order_detail_list[$id_order_detail]['amount'] = (float)str_replace(',', '.', $amount_detail);
                            $order_detail_list[$id_order_detail]['unit_price'] = $order_detail_list[$id_order_detail]['amount'] / $order_detail_list[$id_order_detail]['quantity'];
                        }
                        $amount += $order_detail_list[$id_order_detail]['amount'];
                        if (!$order->hasBeenDelivered() || ($order->hasBeenDelivered() && Tools::isSubmit('reinjectQuantities')) && $order_detail_list[$id_order_detail]['quantity'] > 0) {
                            $this->reinjectQuantity($order_detail, $order_detail_list[$id_order_detail]['quantity']);
                        }
                    }

                    $shipping_cost_amount = (float)str_replace(',', '.', Tools::getValue('partialRefundShippingCost')) ? (float)str_replace(',', '.', Tools::getValue('partialRefundShippingCost')) : false;

                    if ($amount == 0 && $shipping_cost_amount == 0) {
                        if (!empty($refunds)) {
                            $this->errors[] = Tools::displayError('Please enter a quantity to proceed with your refund.');
                        } else {
                            $this->errors[] = Tools::displayError('Please enter an amount to proceed with your refund.');
                        }
                        return false;
                    }

                    $choosen = false;
                    $voucher = 0;

                    if ((int)Tools::getValue('refund_voucher_off') == 1) {
                        $amount -= $voucher = (float)Tools::getValue('order_discount_price');
                    } elseif ((int)Tools::getValue('refund_voucher_off') == 2) {
                        $choosen = true;
                        $amount = $voucher = (float)Tools::getValue('refund_voucher_choose');
                    }

                    if ($shipping_cost_amount > 0) {
                        if (!Tools::getValue('TaxMethod')) {
                            $tax = new Tax();
                            $tax->rate = $order->carrier_tax_rate;
                            $tax_calculator = new TaxCalculator(array($tax));
                            $amount += $tax_calculator->addTaxes($shipping_cost_amount);
                        } else {
                            $amount += $shipping_cost_amount;
                        }
                    }

                    $order_carrier = new OrderCarrier((int)$order->getIdOrderCarrier());
                    if (Validate::isLoadedObject($order_carrier)) {
                        $order_carrier->weight = (float)$order->getTotalWeight();
                        if ($order_carrier->update()) {
                            $order->weight = sprintf("%.3f ".Configuration::get('PS_WEIGHT_UNIT'), $order_carrier->weight);
                        }
                    }

                    if ($amount >= 0) {
                        if (!OrderSlip::create($order, $order_detail_list, $shipping_cost_amount, $voucher, $choosen,
                            (Tools::getValue('TaxMethod') ? false : true))) {
                            $this->errors[] = Tools::displayError('You cannot generate a partial credit slip.');
                        } else {
                            Hook::exec('actionOrderSlipAdd', array('order' => $order, 'productList' => $order_detail_list, 'qtyList' => $full_quantity_list), null, false, true, false, $order->id_shop);
                            $customer = new Customer((int)($order->id_customer));
                            $params['{lastname}'] = $customer->lastname;
                            $params['{firstname}'] = $customer->firstname;
                            $params['{id_order}'] = $order->id;
                            $params['{order_name}'] = $order->getUniqReference();
                            @Mail::Send(
                                (int)$order->id_lang,
                                'credit_slip',
                                Mail::l('New credit slip regarding your order', (int)$order->id_lang),
                                $params,
                                $customer->email,
                                $customer->firstname.' '.$customer->lastname,
                                null,
                                null,
                                null,
                                null,
                                _PS_MAIL_DIR_,
                                true,
                                (int)$order->id_shop
                            );
                        }

                        foreach ($order_detail_list as &$product) {
                            $order_detail = new OrderDetail((int)$product['id_order_detail']);
                            if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')) {
                                StockAvailable::synchronize($order_detail->product_id);
                            }
                        }

                        // Generate voucher
                        if (Tools::isSubmit('generateDiscountRefund') && !count($this->errors) && $amount > 0) {
                            $cart_rule = new CartRule();
                            $cart_rule->description = sprintf($this->l('Credit slip for order #%d'), $order->id);
                            $language_ids = Language::getIDs(false);
                            foreach ($language_ids as $id_lang) {
                                // Define a temporary name
                                $cart_rule->name[$id_lang] = sprintf('V0C%1$dO%2$d', $order->id_customer, $order->id);
                            }

                            // Define a temporary code
                            $cart_rule->code = sprintf('V0C%1$dO%2$d', $order->id_customer, $order->id);
                            $cart_rule->quantity = 1;
                            $cart_rule->quantity_per_user = 1;

                            // Specific to the customer
                            $cart_rule->id_customer = $order->id_customer;
                            $now = time();
                            $cart_rule->date_from = date('Y-m-d H:i:s', $now);
                            $cart_rule->date_to = date('Y-m-d H:i:s', strtotime('+1 year'));
                            $cart_rule->partial_use = 1;
                            $cart_rule->active = 1;

                            $cart_rule->reduction_amount = $amount;
                            $cart_rule->reduction_tax = true;
                            $cart_rule->minimum_amount_currency = $order->id_currency;
                            $cart_rule->reduction_currency = $order->id_currency;

                            if (!$cart_rule->add()) {
                                $this->errors[] = Tools::displayError('You cannot generate a voucher.');
                            } else {
                                // Update the voucher code and name
                                foreach ($language_ids as $id_lang) {
                                    $cart_rule->name[$id_lang] = sprintf('V%1$dC%2$dO%3$d', $cart_rule->id, $order->id_customer, $order->id);
                                }
                                $cart_rule->code = sprintf('V%1$dC%2$dO%3$d', $cart_rule->id, $order->id_customer, $order->id);

                                if (!$cart_rule->update()) {
                                    $this->errors[] = Tools::displayError('You cannot generate a voucher.');
                                } else {
                                    $currency = $this->context->currency;
                                    $customer = new Customer((int)($order->id_customer));
                                    $params['{lastname}'] = $customer->lastname;
                                    $params['{firstname}'] = $customer->firstname;
                                    $params['{id_order}'] = $order->id;
                                    $params['{order_name}'] = $order->getUniqReference();
                                    $params['{voucher_amount}'] = Tools::displayPrice($cart_rule->reduction_amount, $currency, false);
                                    $params['{voucher_num}'] = $cart_rule->code;
                                    @Mail::Send((int)$order->id_lang, 'voucher', sprintf(Mail::l('New voucher for your order #%s', (int)$order->id_lang), $order->reference),
                                        $params, $customer->email, $customer->firstname.' '.$customer->lastname, null, null, null,
                                        null, _PS_MAIL_DIR_, true, (int)$order->id_shop);
                                }
                            }
                        }
                    } else {
                        if (!empty($refunds)) {
                            $this->errors[] = Tools::displayError('Please enter a quantity to proceed with your refund.');
                        } else {
                            $this->errors[] = Tools::displayError('Please enter an amount to proceed with your refund.');
                        }
                    }

                    // Redirect if no errors
                    if (!count($this->errors)) {
                        Tools::redirectAdmin(self::$currentIndex.'&id_order='.$order->id.'&vieworder&conf=30&token='.$this->token);
                    }
                } else {
                    $this->errors[] = Tools::displayError('The partial refund data is incorrect.');
                }
            } else {
                $this->errors[] = Tools::displayError('You do not have permission to delete this.');
            }
        }

        /* Cancel product from order */
        elseif (Tools::isSubmit('cancelProduct') && isset($order)) {
            if ($this->tabAccess['delete'] === '1') {
                if (!Tools::isSubmit('id_order_detail') && !Tools::isSubmit('id_customization')) {
                    $this->errors[] = Tools::displayError('You must select a product.');
                } elseif (!Tools::isSubmit('cancelQuantity') && !Tools::isSubmit('cancelCustomizationQuantity')) {
                    $this->errors[] = Tools::displayError('You must enter a quantity.');
                } else {
                    $productList = Tools::getValue('id_order_detail');
                    if ($productList) {
                        $productList = array_map('intval', $productList);
                    }

                    $customizationList = Tools::getValue('id_customization');
                    if ($customizationList) {
                        $customizationList = array_map('intval', $customizationList);
                    }

                    $qtyList = Tools::getValue('cancelQuantity');
                    if ($qtyList) {
                        $qtyList = array_map('intval', $qtyList);
                    }

                    $customizationQtyList = Tools::getValue('cancelCustomizationQuantity');
                    if ($customizationQtyList) {
                        $customizationQtyList = array_map('intval', $customizationQtyList);
                    }

                    $full_product_list = $productList;
                    $full_quantity_list = $qtyList;

                    if ($customizationList) {
                        foreach ($customizationList as $key => $id_order_detail) {
                            $full_product_list[(int)$id_order_detail] = $id_order_detail;
                            if (isset($customizationQtyList[$key])) {
                                $full_quantity_list[(int)$id_order_detail] += $customizationQtyList[$key];
                            }
                        }
                    }

                    if ($productList || $customizationList) {
                        if ($productList) {
                            $id_cart = Cart::getCartIdByOrderId($order->id);
                            $customization_quantities = Customization::countQuantityByCart($id_cart);

                            foreach ($productList as $key => $id_order_detail) {
                                $qtyCancelProduct = abs($qtyList[$key]);
                                if (!$qtyCancelProduct) {
                                    $this->errors[] = Tools::displayError('No quantity has been selected for this product.');
                                }

                                $order_detail = new OrderDetail($id_order_detail);
                                $customization_quantity = 0;
                                if (array_key_exists($order_detail->product_id, $customization_quantities) && array_key_exists($order_detail->product_attribute_id, $customization_quantities[$order_detail->product_id])) {
                                    $customization_quantity = (int)$customization_quantities[$order_detail->product_id][$order_detail->product_attribute_id];
                                }

                                if (($order_detail->product_quantity - $customization_quantity - $order_detail->product_quantity_refunded - $order_detail->product_quantity_return) < $qtyCancelProduct) {
                                    $this->errors[] = Tools::displayError('An invalid quantity was selected for this product.');
                                }
                            }
                        }
                        if ($customizationList) {
                            $customization_quantities = Customization::retrieveQuantitiesFromIds(array_keys($customizationList));

                            foreach ($customizationList as $id_customization => $id_order_detail) {
                                $qtyCancelProduct = abs($customizationQtyList[$id_customization]);
                                $customization_quantity = $customization_quantities[$id_customization];

                                if (!$qtyCancelProduct) {
                                    $this->errors[] = Tools::displayError('No quantity has been selected for this product.');
                                }

                                if ($qtyCancelProduct > ($customization_quantity['quantity'] - ($customization_quantity['quantity_refunded'] + $customization_quantity['quantity_returned']))) {
                                    $this->errors[] = Tools::displayError('An invalid quantity was selected for this product.');
                                }
                            }
                        }

                        if (!count($this->errors) && $productList) {
                            foreach ($productList as $key => $id_order_detail) {
                                $qty_cancel_product = abs($qtyList[$key]);
                                $order_detail = new OrderDetail((int)($id_order_detail));

                                if (!$order->hasBeenDelivered() || ($order->hasBeenDelivered() && Tools::isSubmit('reinjectQuantities')) && $qty_cancel_product > 0) {
                                    $this->reinjectQuantity($order_detail, $qty_cancel_product);
                                }

                                // Delete product
                                $order_detail = new OrderDetail((int)$id_order_detail);
                                if (!$order->deleteProduct($order, $order_detail, $qty_cancel_product)) {
                                    $this->errors[] = Tools::displayError('An error occurred while attempting to delete the product.').' <span class="bold">'.$order_detail->product_name.'</span>';
                                }
                                // Update weight SUM
                                $order_carrier = new OrderCarrier((int)$order->getIdOrderCarrier());
                                if (Validate::isLoadedObject($order_carrier)) {
                                    $order_carrier->weight = (float)$order->getTotalWeight();
                                    if ($order_carrier->update()) {
                                        $order->weight = sprintf("%.3f ".Configuration::get('PS_WEIGHT_UNIT'), $order_carrier->weight);
                                    }
                                }

                                if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT') && StockAvailable::dependsOnStock($order_detail->product_id)) {
                                    StockAvailable::synchronize($order_detail->product_id);
                                }
                                Hook::exec('actionProductCancel', array('order' => $order, 'id_order_detail' => (int)$id_order_detail), null, false, true, false, $order->id_shop);
                            }
                        }
                        if (!count($this->errors) && $customizationList) {
                            foreach ($customizationList as $id_customization => $id_order_detail) {
                                $order_detail = new OrderDetail((int)($id_order_detail));
                                $qtyCancelProduct = abs($customizationQtyList[$id_customization]);
                                if (!$order->deleteCustomization($id_customization, $qtyCancelProduct, $order_detail)) {
                                    $this->errors[] = Tools::displayError('An error occurred while attempting to delete product customization.').' '.$id_customization;
                                }
                            }
                        }
                        // E-mail params
                        if ((Tools::isSubmit('generateCreditSlip') || Tools::isSubmit('generateDiscount')) && !count($this->errors)) {
                            $customer = new Customer((int)($order->id_customer));
                            $params['{lastname}'] = $customer->lastname;
                            $params['{firstname}'] = $customer->firstname;
                            $params['{id_order}'] = $order->id;
                            $params['{order_name}'] = $order->getUniqReference();
                        }

                        // Generate credit slip
                        if (Tools::isSubmit('generateCreditSlip') && !count($this->errors)) {
                            $product_list = array();
                            $amount = $order_detail->unit_price_tax_incl * $full_quantity_list[$id_order_detail];

                            $choosen = false;
                            if ((int)Tools::getValue('refund_total_voucher_off') == 1) {
                                $amount -= $voucher = (float)Tools::getValue('order_discount_price');
                            } elseif ((int)Tools::getValue('refund_total_voucher_off') == 2) {
                                $choosen = true;
                                $amount = $voucher = (float)Tools::getValue('refund_total_voucher_choose');
                            }
                            foreach ($full_product_list as $id_order_detail) {
                                $order_detail = new OrderDetail((int)$id_order_detail);
                                $product_list[$id_order_detail] = array(
                                    'id_order_detail' => $id_order_detail,
                                    'quantity' => $full_quantity_list[$id_order_detail],
                                    'unit_price' => $order_detail->unit_price_tax_excl,
                                    'amount' => isset($amount) ? $amount : $order_detail->unit_price_tax_incl * $full_quantity_list[$id_order_detail],
                                );
                            }

                            $shipping = Tools::isSubmit('shippingBack') ? null : false;

                            if (!OrderSlip::create($order, $product_list, $shipping, $voucher, $choosen)) {
                                $this->errors[] = Tools::displayError('A credit slip cannot be generated. ');
                            } else {
                                Hook::exec('actionOrderSlipAdd', array('order' => $order, 'productList' => $full_product_list, 'qtyList' => $full_quantity_list), null, false, true, false, $order->id_shop);
                                @Mail::Send(
                                    (int)$order->id_lang,
                                    'credit_slip',
                                    Mail::l('New credit slip regarding your order', (int)$order->id_lang),
                                    $params,
                                    $customer->email,
                                    $customer->firstname.' '.$customer->lastname,
                                    null,
                                    null,
                                    null,
                                    null,
                                    _PS_MAIL_DIR_,
                                    true,
                                    (int)$order->id_shop
                                );
                            }
                        }

                        // Generate voucher
                        if (Tools::isSubmit('generateDiscount') && !count($this->errors)) {
                            $cartrule = new CartRule();
                            $language_ids = Language::getIDs((bool)$order);
                            $cartrule->description = sprintf($this->l('Credit card slip for order #%d'), $order->id);
                            foreach ($language_ids as $id_lang) {
                                // Define a temporary name
                                $cartrule->name[$id_lang] = 'V0C'.(int)($order->id_customer).'O'.(int)($order->id);
                            }
                            // Define a temporary code
                            $cartrule->code = 'V0C'.(int)($order->id_customer).'O'.(int)($order->id);

                            $cartrule->quantity = 1;
                            $cartrule->quantity_per_user = 1;
                            // Specific to the customer
                            $cartrule->id_customer = $order->id_customer;
                            $now = time();
                            $cartrule->date_from = date('Y-m-d H:i:s', $now);
                            $cartrule->date_to = date('Y-m-d H:i:s', $now + (3600 * 24 * 365.25)); /* 1 year */
                            $cartrule->active = 1;

                            $products = $order->getProducts(false, $full_product_list, $full_quantity_list);

                            $total = 0;
                            foreach ($products as $product) {
                                $total += $product['unit_price_tax_incl'] * $product['product_quantity'];
                            }

                            if (Tools::isSubmit('shippingBack')) {
                                $total += $order->total_shipping;
                            }

                            if ((int)Tools::getValue('refund_total_voucher_off') == 1) {
                                $total -= (float)Tools::getValue('order_discount_price');
                            } elseif ((int)Tools::getValue('refund_total_voucher_off') == 2) {
                                $total = (float)Tools::getValue('refund_total_voucher_choose');
                            }

                            $cartrule->reduction_amount = $total;
                            $cartrule->reduction_tax = true;
                            $cartrule->minimum_amount_currency = $order->id_currency;
                            $cartrule->reduction_currency = $order->id_currency;

                            if (!$cartrule->add()) {
                                $this->errors[] = Tools::displayError('You cannot generate a voucher.');
                            } else {
                                // Update the voucher code and name
                                foreach ($language_ids as $id_lang) {
                                    $cartrule->name[$id_lang] = 'V'.(int)($cartrule->id).'C'.(int)($order->id_customer).'O'.$order->id;
                                }
                                $cartrule->code = 'V'.(int)($cartrule->id).'C'.(int)($order->id_customer).'O'.$order->id;
                                if (!$cartrule->update()) {
                                    $this->errors[] = Tools::displayError('You cannot generate a voucher.');
                                } else {
                                    $currency = $this->context->currency;
                                    $params['{voucher_amount}'] = Tools::displayPrice($cartrule->reduction_amount, $currency, false);
                                    $params['{voucher_num}'] = $cartrule->code;
                                    @Mail::Send((int)$order->id_lang, 'voucher', sprintf(Mail::l('New voucher for your order #%s', (int)$order->id_lang), $order->reference),
                                    $params, $customer->email, $customer->firstname.' '.$customer->lastname, null, null, null,
                                    null, _PS_MAIL_DIR_, true, (int)$order->id_shop);
                                }
                            }
                        }
                    } else {
                        $this->errors[] = Tools::displayError('No product or quantity has been selected.');
                    }

                    // Redirect if no errors
                    if (!count($this->errors)) {
                        Tools::redirectAdmin(self::$currentIndex.'&id_order='.$order->id.'&vieworder&conf=31&token='.$this->token);
                    }
                }
            } else {
                $this->errors[] = Tools::displayError('You do not have permission to delete this.');
            }
        } elseif (Tools::isSubmit('messageReaded')) {
            Message::markAsReaded(Tools::getValue('messageReaded'), $this->context->employee->id);
        } elseif (Tools::isSubmit('submitAddPayment') && isset($order)) {
            if ($this->tabAccess['edit'] === '1') {
                $amount = str_replace(',', '.', Tools::getValue('payment_amount'));
                $currency = new Currency(Tools::getValue('payment_currency'));
                $order_has_invoice = $order->hasInvoice();
                if ($order_has_invoice) {
                    $order_invoice = new OrderInvoice(Tools::getValue('payment_invoice'));
                } else {
                    $order_invoice = null;
                }

                if (!Validate::isLoadedObject($order)) {
                    $this->errors[] = Tools::displayError('The order cannot be found');
                } elseif (!Validate::isNegativePrice($amount) || !(float)$amount) {
                    $this->errors[] = Tools::displayError('The amount is invalid.');
                } elseif (!Validate::isGenericName(Tools::getValue('payment_method'))) {
                    $this->errors[] = Tools::displayError('The selected payment method is invalid.');
                } elseif (!Validate::isString(Tools::getValue('payment_transaction_id'))) {
                    $this->errors[] = Tools::displayError('The transaction ID is invalid.');
                } elseif (!Validate::isLoadedObject($currency)) {
                    $this->errors[] = Tools::displayError('The selected currency is invalid.');
                } elseif ($order_has_invoice && !Validate::isLoadedObject($order_invoice)) {
                    $this->errors[] = Tools::displayError('The invoice is invalid.');
                } elseif (!Validate::isDate(Tools::getValue('payment_date'))) {
                    $this->errors[] = Tools::displayError('The date is invalid');
                } else {
                    if (!$order->addOrderPayment($amount, Tools::getValue('payment_method'), Tools::getValue('payment_transaction_id'), $currency, Tools::getValue('payment_date'), $order_invoice)) {
                        $this->errors[] = Tools::displayError('An error occurred during payment.');
                    } else {
                        Tools::redirectAdmin(self::$currentIndex.'&id_order='.$order->id.'&vieworder&conf=4&token='.$this->token);
                    }
                }
            } else {
                $this->errors[] = Tools::displayError('You do not have permission to edit this.');
            }
        } elseif (Tools::isSubmit('submitEditNote')) {
            $note = Tools::getValue('note');
            $order_invoice = new OrderInvoice((int)Tools::getValue('id_order_invoice'));
            if (Validate::isLoadedObject($order_invoice) && Validate::isCleanHtml($note)) {
                if ($this->tabAccess['edit'] === '1') {
                    $order_invoice->note = $note;
                    if ($order_invoice->save()) {
                        Tools::redirectAdmin(self::$currentIndex.'&id_order='.$order_invoice->id_order.'&vieworder&conf=4&token='.$this->token);
                    } else {
                        $this->errors[] = Tools::displayError('The invoice note was not saved.');
                    }
                } else {
                    $this->errors[] = Tools::displayError('You do not have permission to edit this.');
                }
            } else {
                $this->errors[] = Tools::displayError('The invoice for edit note was unable to load. ');
            }
        } elseif (Tools::isSubmit('submitAddOrder') && ($id_cart = Tools::getValue('id_cart')) &&
            ($module_name = Tools::getValue('payment_module_name')) &&
            ($id_order_state = Tools::getValue('id_order_state')) && Validate::isModuleName($module_name)) 
			{
            if ($this->tabAccess['edit'] === '1') {
                if (!Configuration::get('PS_CATALOG_MODE')) {
                    $payment_module = Module::getInstanceByName($module_name);
                } else {
                    $payment_module = new BoOrder();
                }

                $cart = new Cart((int)$id_cart);
                Context::getContext()->currency = new Currency((int)$cart->id_currency);
                Context::getContext()->customer = new Customer((int)$cart->id_customer);

                $bad_delivery = false;
                if (($bad_delivery = (bool)!Address::isCountryActiveById((int)$cart->id_address_delivery))
                    || !Address::isCountryActiveById((int)$cart->id_address_invoice)) {
                    if ($bad_delivery) {
                        $this->errors[] = Tools::displayError('This delivery address country is not active.');
                    } else {
                        $this->errors[] = Tools::displayError('This invoice address country is not active.');
                    }
                } else {
                    $employee = new Employee((int)Context::getContext()->cookie->id_employee);
                    $payment_module->validateOrder(
                        (int)$cart->id, (int)$id_order_state,
                        $cart->getOrderTotal(true, Cart::BOTH), $payment_module->displayName, $this->l('Manual order -- Employee:').' '.
                        substr($employee->firstname, 0, 1).'. '.$employee->lastname, array(), null, false, $cart->secure_key
                    );
                    if ($payment_module->currentOrder) {
                        Tools::redirectAdmin(self::$currentIndex.'&id_order='.$payment_module->currentOrder.'&vieworder'.'&token='.$this->token);
                    }
                }
            } else {
                $this->errors[] = Tools::displayError('You do not have permission to add this.');
            }
        } elseif ((Tools::isSubmit('submitAddressShipping') || Tools::isSubmit('submitAddressInvoice')) && isset($order)) {
            if ($this->tabAccess['edit'] === '1') {
                $address = new Address(Tools::getValue('id_address'));
                if (Validate::isLoadedObject($address)) {
                    // Update the address on order
                    if (Tools::isSubmit('submitAddressShipping')) {
                        $order->id_address_delivery = $address->id;
                    } elseif (Tools::isSubmit('submitAddressInvoice')) {
                        $order->id_address_invoice = $address->id;
                    }
                    $order->update();
                    Tools::redirectAdmin(self::$currentIndex.'&id_order='.$order->id.'&vieworder&conf=4&token='.$this->token);
                } else {
                    $this->errors[] = Tools::displayError('This address can\'t be loaded');
                }
            } else {
                $this->errors[] = Tools::displayError('You do not have permission to edit this.');
            }
        } elseif (Tools::isSubmit('submitChangeCurrency') && isset($order)) {
            if ($this->tabAccess['edit'] === '1') {
                if (Tools::getValue('new_currency') != $order->id_currency && !$order->valid) {
                    $old_currency = new Currency($order->id_currency);
                    $currency = new Currency(Tools::getValue('new_currency'));
                    if (!Validate::isLoadedObject($currency)) {
                        throw new PrestaShopException('Can\'t load Currency object');
                    }

                    // Update order detail amount
                    foreach ($order->getOrderDetailList() as $row) {
                        $order_detail = new OrderDetail($row['id_order_detail']);
                        $fields = array(
                            'ecotax',
                            'product_price',
                            'reduction_amount',
                            'total_shipping_price_tax_excl',
                            'total_shipping_price_tax_incl',
                            'total_price_tax_incl',
                            'total_price_tax_excl',
                            'product_quantity_discount',
                            'purchase_supplier_price',
                            'reduction_amount',
                            'reduction_amount_tax_incl',
                            'reduction_amount_tax_excl',
                            'unit_price_tax_incl',
                            'unit_price_tax_excl',
                            'original_product_price'

                        );
                        foreach ($fields as $field) {
                            $order_detail->{$field} = Tools::convertPriceFull($order_detail->{$field}, $old_currency, $currency);
                        }

                        $order_detail->update();
                        $order_detail->updateTaxAmount($order);
                    }

                    $id_order_carrier = (int)$order->getIdOrderCarrier();
                    if ($id_order_carrier) {
                        $order_carrier = $order_carrier = new OrderCarrier((int)$order->getIdOrderCarrier());
                        $order_carrier->shipping_cost_tax_excl = (float)Tools::convertPriceFull($order_carrier->shipping_cost_tax_excl, $old_currency, $currency);
                        $order_carrier->shipping_cost_tax_incl = (float)Tools::convertPriceFull($order_carrier->shipping_cost_tax_incl, $old_currency, $currency);
                        $order_carrier->update();
                    }

                    // Update order && order_invoice amount
                    $fields = array(
                        'total_discounts',
                        'total_discounts_tax_incl',
                        'total_discounts_tax_excl',
                        'total_discount_tax_excl',
                        'total_discount_tax_incl',
                        'total_paid',
                        'total_paid_tax_incl',
                        'total_paid_tax_excl',
                        'total_paid_real',
                        'total_products',
                        'total_products_wt',
                        'total_shipping',
                        'total_shipping_tax_incl',
                        'total_shipping_tax_excl',
                        'total_wrapping',
                        'total_wrapping_tax_incl',
                        'total_wrapping_tax_excl',
                    );

                    $invoices = $order->getInvoicesCollection();
                    if ($invoices) {
                        foreach ($invoices as $invoice) {
                            foreach ($fields as $field) {
                                if (isset($invoice->$field)) {
                                    $invoice->{$field} = Tools::convertPriceFull($invoice->{$field}, $old_currency, $currency);
                                }
                            }
                            $invoice->save();
                        }
                    }

                    foreach ($fields as $field) {
                        if (isset($order->$field)) {
                            $order->{$field} = Tools::convertPriceFull($order->{$field}, $old_currency, $currency);
                        }
                    }

                    // Update currency in order
                    $order->id_currency = $currency->id;
                    // Update exchange rate
                    $order->conversion_rate = (float)$currency->conversion_rate;
                    $order->update();
                } else {
                    $this->errors[] = Tools::displayError('You cannot change the currency.');
                }
            } else {
                $this->errors[] = Tools::displayError('You do not have permission to edit this.');
            }
        } elseif (Tools::isSubmit('submitGenerateInvoice') && isset($order)) {
            if (!Configuration::get('PS_INVOICE', null, null, $order->id_shop)) {
                $this->errors[] = Tools::displayError('Invoice management has been disabled.');
            } elseif ($order->hasInvoice()) {
                $this->errors[] = Tools::displayError('This order already has an invoice.');
            } else {
                $order->setInvoice(true);
                Tools::redirectAdmin(self::$currentIndex.'&id_order='.$order->id.'&vieworder&conf=4&token='.$this->token);
            }
        } elseif (Tools::isSubmit('submitDeleteVoucher') && isset($order)) {
            if ($this->tabAccess['edit'] === '1') {
                $order_cart_rule = new OrderCartRule(Tools::getValue('id_order_cart_rule'));
                if (Validate::isLoadedObject($order_cart_rule) && $order_cart_rule->id_order == $order->id) {
                    if ($order_cart_rule->id_order_invoice) {
                        $order_invoice = new OrderInvoice($order_cart_rule->id_order_invoice);
                        if (!Validate::isLoadedObject($order_invoice)) {
                            throw new PrestaShopException('Can\'t load Order Invoice object');
                        }

                        // Update amounts of Order Invoice
                        $order_invoice->total_discount_tax_excl -= $order_cart_rule->value_tax_excl;
                        $order_invoice->total_discount_tax_incl -= $order_cart_rule->value;

                        $order_invoice->total_paid_tax_excl += $order_cart_rule->value_tax_excl;
                        $order_invoice->total_paid_tax_incl += $order_cart_rule->value;

                        // Update Order Invoice
                        $order_invoice->update();
                    }

                    // Update amounts of order
                    $order->total_discounts -= $order_cart_rule->value;
                    $order->total_discounts_tax_incl -= $order_cart_rule->value;
                    $order->total_discounts_tax_excl -= $order_cart_rule->value_tax_excl;

                    $order->total_paid += $order_cart_rule->value;
                    $order->total_paid_tax_incl += $order_cart_rule->value;
                    $order->total_paid_tax_excl += $order_cart_rule->value_tax_excl;

                    // Delete Order Cart Rule and update Order
                    $order_cart_rule->delete();
                    $order->update();
                    Tools::redirectAdmin(self::$currentIndex.'&id_order='.$order->id.'&vieworder&conf=4&token='.$this->token);
                } else {
                    $this->errors[] = Tools::displayError('You cannot edit this cart rule.');
                }
            } else {
                $this->errors[] = Tools::displayError('You do not have permission to edit this.');
            }
        } elseif (Tools::isSubmit('submitNewVoucher') && isset($order)) {
            if ($this->tabAccess['edit'] === '1') {
                if (!Tools::getValue('discount_name')) {
                    $this->errors[] = Tools::displayError('You must specify a name in order to create a new discount.');
                } else {
                    if ($order->hasInvoice()) {
                        // If the discount is for only one invoice
                        if (!Tools::isSubmit('discount_all_invoices')) {
                            $order_invoice = new OrderInvoice(Tools::getValue('discount_invoice'));
                            if (!Validate::isLoadedObject($order_invoice)) {
                                throw new PrestaShopException('Can\'t load Order Invoice object');
                            }
                        }
                    }

                    $cart_rules = array();
                    $discount_value = (float)str_replace(',', '.', Tools::getValue('discount_value'));
                    switch (Tools::getValue('discount_type')) {
                        // Percent type
                        case 1:
                            if ($discount_value < 100) {
                                if (isset($order_invoice)) {
                                    $cart_rules[$order_invoice->id]['value_tax_incl'] = Tools::ps_round($order_invoice->total_paid_tax_incl * $discount_value / 100, 2);
                                    $cart_rules[$order_invoice->id]['value_tax_excl'] = Tools::ps_round($order_invoice->total_paid_tax_excl * $discount_value / 100, 2);

                                    // Update OrderInvoice
                                    $this->applyDiscountOnInvoice($order_invoice, $cart_rules[$order_invoice->id]['value_tax_incl'], $cart_rules[$order_invoice->id]['value_tax_excl']);
                                } elseif ($order->hasInvoice()) {
                                    $order_invoices_collection = $order->getInvoicesCollection();
                                    foreach ($order_invoices_collection as $order_invoice) {
                                        /** @var OrderInvoice $order_invoice */
                                        $cart_rules[$order_invoice->id]['value_tax_incl'] = Tools::ps_round($order_invoice->total_paid_tax_incl * $discount_value / 100, 2);
                                        $cart_rules[$order_invoice->id]['value_tax_excl'] = Tools::ps_round($order_invoice->total_paid_tax_excl * $discount_value / 100, 2);

                                        // Update OrderInvoice
                                        $this->applyDiscountOnInvoice($order_invoice, $cart_rules[$order_invoice->id]['value_tax_incl'], $cart_rules[$order_invoice->id]['value_tax_excl']);
                                    }
                                } else {
                                    $cart_rules[0]['value_tax_incl'] = Tools::ps_round($order->total_paid_tax_incl * $discount_value / 100, 2);
                                    $cart_rules[0]['value_tax_excl'] = Tools::ps_round($order->total_paid_tax_excl * $discount_value / 100, 2);
                                }
                            } else {
                                $this->errors[] = Tools::displayError('The discount value is invalid.');
                            }
                            break;
                        // Amount type
                        case 2:
                            if (isset($order_invoice)) {
                                if ($discount_value > $order_invoice->total_paid_tax_incl) {
                                    $this->errors[] = Tools::displayError('The discount value is greater than the order invoice total.');
                                } else {
                                    $cart_rules[$order_invoice->id]['value_tax_incl'] = Tools::ps_round($discount_value, 2);
                                    $cart_rules[$order_invoice->id]['value_tax_excl'] = Tools::ps_round($discount_value / (1 + ($order->getTaxesAverageUsed() / 100)), 2);

                                    // Update OrderInvoice
                                    $this->applyDiscountOnInvoice($order_invoice, $cart_rules[$order_invoice->id]['value_tax_incl'], $cart_rules[$order_invoice->id]['value_tax_excl']);
                                }
                            } elseif ($order->hasInvoice()) {
                                $order_invoices_collection = $order->getInvoicesCollection();
                                foreach ($order_invoices_collection as $order_invoice) {
                                    /** @var OrderInvoice $order_invoice */
                                    if ($discount_value > $order_invoice->total_paid_tax_incl) {
                                        $this->errors[] = Tools::displayError('The discount value is greater than the order invoice total.').$order_invoice->getInvoiceNumberFormatted(Context::getContext()->language->id, (int)$order->id_shop).')';
                                    } else {
                                        $cart_rules[$order_invoice->id]['value_tax_incl'] = Tools::ps_round($discount_value, 2);
                                        $cart_rules[$order_invoice->id]['value_tax_excl'] = Tools::ps_round($discount_value / (1 + ($order->getTaxesAverageUsed() / 100)), 2);

                                        // Update OrderInvoice
                                        $this->applyDiscountOnInvoice($order_invoice, $cart_rules[$order_invoice->id]['value_tax_incl'], $cart_rules[$order_invoice->id]['value_tax_excl']);
                                    }
                                }
                            } else {
                                if ($discount_value > $order->total_paid_tax_incl) {
                                    $this->errors[] = Tools::displayError('The discount value is greater than the order total.');
                                } else {
                                    $cart_rules[0]['value_tax_incl'] = Tools::ps_round($discount_value, 2);
                                    $cart_rules[0]['value_tax_excl'] = Tools::ps_round($discount_value / (1 + ($order->getTaxesAverageUsed() / 100)), 2);
                                }
                            }
                            break;
                        // Free shipping type
                        case 3:
                            if (isset($order_invoice)) {
                                if ($order_invoice->total_shipping_tax_incl > 0) {
                                    $cart_rules[$order_invoice->id]['value_tax_incl'] = $order_invoice->total_shipping_tax_incl;
                                    $cart_rules[$order_invoice->id]['value_tax_excl'] = $order_invoice->total_shipping_tax_excl;

                                    // Update OrderInvoice
                                    $this->applyDiscountOnInvoice($order_invoice, $cart_rules[$order_invoice->id]['value_tax_incl'], $cart_rules[$order_invoice->id]['value_tax_excl']);
                                }
                            } elseif ($order->hasInvoice()) {
                                $order_invoices_collection = $order->getInvoicesCollection();
                                foreach ($order_invoices_collection as $order_invoice) {
                                    /** @var OrderInvoice $order_invoice */
                                    if ($order_invoice->total_shipping_tax_incl <= 0) {
                                        continue;
                                    }
                                    $cart_rules[$order_invoice->id]['value_tax_incl'] = $order_invoice->total_shipping_tax_incl;
                                    $cart_rules[$order_invoice->id]['value_tax_excl'] = $order_invoice->total_shipping_tax_excl;

                                    // Update OrderInvoice
                                    $this->applyDiscountOnInvoice($order_invoice, $cart_rules[$order_invoice->id]['value_tax_incl'], $cart_rules[$order_invoice->id]['value_tax_excl']);
                                }
                            } else {
                                $cart_rules[0]['value_tax_incl'] = $order->total_shipping_tax_incl;
                                $cart_rules[0]['value_tax_excl'] = $order->total_shipping_tax_excl;
                            }
                            break;
                        default:
                            $this->errors[] = Tools::displayError('The discount type is invalid.');
                    }

                    $res = true;
                    foreach ($cart_rules as &$cart_rule) {
                        $cartRuleObj = new CartRule();
                        $cartRuleObj->date_from = date('Y-m-d H:i:s', strtotime('-1 hour', strtotime($order->date_add)));
                        $cartRuleObj->date_to = date('Y-m-d H:i:s', strtotime('+1 hour'));
                        $cartRuleObj->name[Configuration::get('PS_LANG_DEFAULT')] = Tools::getValue('discount_name');
                        $cartRuleObj->quantity = 0;
                        $cartRuleObj->quantity_per_user = 1;
                        if (Tools::getValue('discount_type') == 1) {
                            $cartRuleObj->reduction_percent = $discount_value;
                        } elseif (Tools::getValue('discount_type') == 2) {
                            $cartRuleObj->reduction_amount = $cart_rule['value_tax_excl'];
                        } elseif (Tools::getValue('discount_type') == 3) {
                            $cartRuleObj->free_shipping = 1;
                        }
                        $cartRuleObj->active = 0;
                        if ($res = $cartRuleObj->add()) {
                            $cart_rule['id'] = $cartRuleObj->id;
                        } else {
                            break;
                        }
                    }

                    if ($res) {
                        foreach ($cart_rules as $id_order_invoice => $cart_rule) {
                            // Create OrderCartRule
                            $order_cart_rule = new OrderCartRule();
                            $order_cart_rule->id_order = $order->id;
                            $order_cart_rule->id_cart_rule = $cart_rule['id'];
                            $order_cart_rule->id_order_invoice = $id_order_invoice;
                            $order_cart_rule->name = Tools::getValue('discount_name');
                            $order_cart_rule->value = $cart_rule['value_tax_incl'];
                            $order_cart_rule->value_tax_excl = $cart_rule['value_tax_excl'];
                            $res &= $order_cart_rule->add();

                            $order->total_discounts += $order_cart_rule->value;
                            $order->total_discounts_tax_incl += $order_cart_rule->value;
                            $order->total_discounts_tax_excl += $order_cart_rule->value_tax_excl;
                            $order->total_paid -= $order_cart_rule->value;
                            $order->total_paid_tax_incl -= $order_cart_rule->value;
                            $order->total_paid_tax_excl -= $order_cart_rule->value_tax_excl;
                        }

                        // Update Order
                        $res &= $order->update();
                    }

                    if ($res) {
                        Tools::redirectAdmin(self::$currentIndex.'&id_order='.$order->id.'&vieworder&conf=4&token='.$this->token);
                    } else {
                        $this->errors[] = Tools::displayError('An error occurred during the OrderCartRule creation');
                    }
                }
            } else {
                $this->errors[] = Tools::displayError('You do not have permission to edit this.');
            }
        } elseif (Tools::isSubmit('sendStateEmail') && Tools::getValue('sendStateEmail') > 0 && Tools::getValue('id_order') > 0) {
            if ($this->tabAccess['edit'] === '1') {
                $order_state = new OrderState((int)Tools::getValue('sendStateEmail'));

                if (!Validate::isLoadedObject($order_state)) {
                    $this->errors[] = Tools::displayError('An error occurred while loading order status.');
                } else {
                    $history = new OrderHistory((int)Tools::getValue('id_order_history'));

                    $carrier = new Carrier($order->id_carrier, $order->id_lang);
                    $templateVars = array();
                    if ($order_state->id == Configuration::get('PS_OS_SHIPPING') && $order->shipping_number) {
                        $templateVars = array('{followup}' => str_replace('@', $order->shipping_number, $carrier->url));
                    }

                    if ($history->sendEmail($order, $templateVars)) {
                        Tools::redirectAdmin(self::$currentIndex.'&id_order='.$order->id.'&vieworder&conf=10&token='.$this->token);
                    } else {
                        $this->errors[] = Tools::displayError('An error occurred while sending the e-mail to the customer.');
                    }
                }
            } else {
                $this->errors[] = Tools::displayError('You do not have permission to edit this.');
            }
        }
		

        parent::postProcess();
    }

    public function renderKpis()
    {
        $time = time();
        $kpis = array();

        /* The data generation is located in AdminStatsControllerCore */

        $helper = new HelperKpi();
        $helper->id = 'box-conversion-rate';
        $helper->icon = 'icon-sort-by-attributes-alt';
        //$helper->chart = true;
        $helper->color = 'color1';
        $helper->title = $this->l('Conversion Rate', null, null, false);
        $helper->subtitle = $this->l('30 days', null, null, false);
        if (ConfigurationKPI::get('CONVERSION_RATE') !== false) {
            $helper->value = ConfigurationKPI::get('CONVERSION_RATE');
        }
        if (ConfigurationKPI::get('CONVERSION_RATE_CHART') !== false) {
            $helper->data = ConfigurationKPI::get('CONVERSION_RATE_CHART');
        }
        $helper->source = $this->context->link->getAdminLink('AdminStats').'&ajax=1&action=getKpi&kpi=conversion_rate';
        $helper->refresh = (bool)(ConfigurationKPI::get('CONVERSION_RATE_EXPIRE') < $time);
        $kpis[] = $helper->generate();

        $helper = new HelperKpi();
        $helper->id = 'box-carts';
        $helper->icon = 'icon-shopping-cart';
        $helper->color = 'color2';
        $helper->title = $this->l('Abandoned Carts', null, null, false);
        $helper->subtitle = $this->l('Today', null, null, false);
        $helper->href = $this->context->link->getAdminLink('AdminCarts').'&action=filterOnlyAbandonedCarts';
        if (ConfigurationKPI::get('ABANDONED_CARTS') !== false) {
            $helper->value = ConfigurationKPI::get('ABANDONED_CARTS');
        }
        $helper->source = $this->context->link->getAdminLink('AdminStats').'&ajax=1&action=getKpi&kpi=abandoned_cart';
        $helper->refresh = (bool)(ConfigurationKPI::get('ABANDONED_CARTS_EXPIRE') < $time);
        $kpis[] = $helper->generate();

        $helper = new HelperKpi();
        $helper->id = 'box-average-order';
        $helper->icon = 'icon-money';
        $helper->color = 'color3';
        $helper->title = $this->l('Average Order Value', null, null, false);
        $helper->subtitle = $this->l('30 days', null, null, false);
        if (ConfigurationKPI::get('AVG_ORDER_VALUE') !== false) {
            $helper->value = sprintf($this->l('%s tax excl.'), ConfigurationKPI::get('AVG_ORDER_VALUE'));
        }
        $helper->source = $this->context->link->getAdminLink('AdminStats').'&ajax=1&action=getKpi&kpi=average_order_value';
        $helper->refresh = (bool)(ConfigurationKPI::get('AVG_ORDER_VALUE_EXPIRE') < $time);
        $kpis[] = $helper->generate();

        $helper = new HelperKpi();
        $helper->id = 'box-net-profit-visit';
        $helper->icon = 'icon-user';
        $helper->color = 'color4';
        $helper->title = $this->l('Net Profit per Visit', null, null, false);
        $helper->subtitle = $this->l('30 days', null, null, false);
        if (ConfigurationKPI::get('NETPROFIT_VISIT') !== false) {
            $helper->value = ConfigurationKPI::get('NETPROFIT_VISIT');
        }
        $helper->source = $this->context->link->getAdminLink('AdminStats').'&ajax=1&action=getKpi&kpi=netprofit_visit';
        $helper->refresh = (bool)(ConfigurationKPI::get('NETPROFIT_VISIT_EXPIRE') < $time);
        $kpis[] = $helper->generate();

        $helper = new HelperKpiRow();
        $helper->kpis = $kpis;
        return $helper->generate();
    }
	
	
	
	//异常 comming soon
	function cleanstatus()
	{
		//先筛选 符合条件的 comming soon 订单
		$sql = "SELECT mb.id_order from 
					(
					SELECT ma.*,COUNT(ma.id_order) as num  from 
					(SELECT mya.* from 

					(select a.id_order  from  ps_orders  a 
					LEFT JOIN  ps_order_history  b on  a.id_order=b.id_order 

					where  b.id_employee = 0  and b.id_order_state=13 ) mya 

					LEFT JOIN  ps_order_history c on mya.id_order = c.id_order 

					where c.id_employee=0 and c.id_order_state=1 ) ma 

					LEFT JOIN ps_order_history d on  ma.id_order =d.id_order 

					GROUP BY ma.id_order 
					) mb 
					LEFT JOIN ps_orders dd on mb.id_order = dd.id_order
					where mb.num=2 and dd.current_state =13 ";
		
		$res = Db::getInstance()->executeS($sql);

		if($res){
		
			foreach ($res as $a ){
			//修复正确的订单状态
			//1. 修正当前状态 
			$repair1 = "update ps_orders set current_state = 1 ,date_upd=date_add where id_order = ".$a['id_order'];
			//2. 删除coomming soon 
			$repair2 = "delete from  ps_order_history  where id_order =".$a['id_order']." and id_employee=0 and id_order_state=13 ";
			
			Db::getInstance()->Execute($repair1);
			Db::getInstance()->Execute($repair2);
			file_put_contents('Fixstatus.txt',$a['id_order']."\n\r",FILE_APPEND);	
			}
		}


	}
	
    public function renderView()
    {		
		//打开订单详情页面之前 做一次异常comming soon 状态订单修复
		$this->cleanstatus();
		
        $order = new Order(Tools::getValue('id_order'));
        if (!Validate::isLoadedObject($order)) {
            $this->errors[] = Tools::displayError('The order cannot be found within your database.');
        }

        $customer = new Customer($order->id_customer);
        $carrier = new Carrier($order->id_carrier);
        $products = $this->getProducts($order);
        $currency = new Currency((int)$order->id_currency);
        // Carrier module call
        $carrier_module_call = null;
        if ($carrier->is_module) {
            $module = Module::getInstanceByName($carrier->external_module_name);
            if (method_exists($module, 'displayInfoByCart')) {
                $carrier_module_call = call_user_func(array($module, 'displayInfoByCart'), $order->id_cart);
            }
        }

        // Retrieve addresses information
        $addressInvoice = new Address($order->id_address_invoice, $this->context->language->id);
        if (Validate::isLoadedObject($addressInvoice) && $addressInvoice->id_state) {
            $invoiceState = new State((int)$addressInvoice->id_state);
        }

        if ($order->id_address_invoice == $order->id_address_delivery) {
            $addressDelivery = $addressInvoice;
            if (isset($invoiceState)) {
                $deliveryState = $invoiceState;
            }
        } else {
            $addressDelivery = new Address($order->id_address_delivery, $this->context->language->id);
            if (Validate::isLoadedObject($addressDelivery) && $addressDelivery->id_state) {
                $deliveryState = new State((int)($addressDelivery->id_state));
            }
        }

        $this->toolbar_title = sprintf($this->l('Order #%1$d (%2$s) - %3$s %4$s'), $order->id, $order->reference, $customer->firstname, $customer->lastname);
        if (Shop::isFeatureActive()) {
            $shop = new Shop((int)$order->id_shop);
            $this->toolbar_title .= ' - '.sprintf($this->l('Shop: %s'), $shop->name);
        }

        // gets warehouses to ship products, if and only if advanced stock management is activated
        $warehouse_list = null;

        $order_details = $order->getOrderDetailList();
        foreach ($order_details as $order_detail) {
            $product = new Product($order_detail['product_id']);

            if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')
                && $product->advanced_stock_management) {
                $warehouses = Warehouse::getWarehousesByProductId($order_detail['product_id'], $order_detail['product_attribute_id']);
                foreach ($warehouses as $warehouse) {
                    if (!isset($warehouse_list[$warehouse['id_warehouse']])) {
                        $warehouse_list[$warehouse['id_warehouse']] = $warehouse;
                    }
                }
            }
        }

        $payment_methods = array();
        foreach (PaymentModule::getInstalledPaymentModules() as $payment) {
            $module = Module::getInstanceByName($payment['name']);
            if (Validate::isLoadedObject($module) && $module->active) {
                $payment_methods[] = $module->displayName;
            }
        }

        // display warning if there are products out of stock
        $display_out_of_stock_warning = false;
        $current_order_state = $order->getCurrentOrderState();
        if (Configuration::get('PS_STOCK_MANAGEMENT') && (!Validate::isLoadedObject($current_order_state) || ($current_order_state->delivery != 1 && $current_order_state->shipped != 1))) {
            $display_out_of_stock_warning = true;
        }

        // products current stock (from stock_available)
        foreach ($products as &$product) {
            // Get total customized quantity for current product
            $customized_product_quantity = 0;

            if (is_array($product['customizedDatas'])) {
                foreach ($product['customizedDatas'] as $customizationPerAddress) {
                    foreach ($customizationPerAddress as $customizationId => $customization) {
                        $customized_product_quantity += (int)$customization['quantity'];
                    }
                }
            }

            $product['customized_product_quantity'] = $customized_product_quantity;
            $product['current_stock'] = StockAvailable::getQuantityAvailableByProduct($product['product_id'], $product['product_attribute_id'], $product['id_shop']);
            $resume = OrderSlip::getProductSlipResume($product['id_order_detail']);
            $product['quantity_refundable'] = $product['product_quantity'] - $resume['product_quantity'];
            $product['amount_refundable'] = $product['total_price_tax_excl'] - $resume['amount_tax_excl'];
            $product['amount_refundable_tax_incl'] = $product['total_price_tax_incl'] - $resume['amount_tax_incl'];
            $product['amount_refund'] = Tools::displayPrice($resume['amount_tax_incl'], $currency);
            $product['refund_history'] = OrderSlip::getProductSlipDetail($product['id_order_detail']);
            $product['return_history'] = OrderReturn::getProductReturnDetail($product['id_order_detail']);

            // if the current stock requires a warning
            if ($product['current_stock'] <= 0 && $display_out_of_stock_warning) {
                $this->displayWarning($this->l('This product is out of stock: ').' '.$product['product_name']);
            }
            if ($product['id_warehouse'] != 0) {
                $warehouse = new Warehouse((int)$product['id_warehouse']);
                $product['warehouse_name'] = $warehouse->name;
                $warehouse_location = WarehouseProductLocation::getProductLocation($product['product_id'], $product['product_attribute_id'], $product['id_warehouse']);
                if (!empty($warehouse_location)) {
                    $product['warehouse_location'] = $warehouse_location;
                } else {
                    $product['warehouse_location'] = false;
                }
            } else {
                $product['warehouse_name'] = '--';
                $product['warehouse_location'] = false;
            }
        }

        $gender = new Gender((int)$customer->id_gender, $this->context->language->id);

        $history = $order->getHistory($this->context->language->id);
		

		//var_dump(CustomerThread::getCustomerMessages($order->id_customer, null, $order->id));
	//exit;
        foreach ($history as &$order_state) {
            $order_state['text-color'] = Tools::getBrightness($order_state['color']) < 128 ? 'white' : 'black';
        }
		
		//获取订单退货历史信息
		$comreturnhistory=Db::getInstance()->getRow("select comreturn_reason,comreturn_comment,comreturn_refund,comreturn_return,comreturn_sum from px_order_comreturn where id_order='".$order->id."' ");
		//获取当前订单退货操作信息 
		$comreturnactor = Db::getInstance()->executeS("select * from px_order_comreturn_history where id_order=".$order->id." order by operate_time desc ");
	
		//获取客户历史订单信息
		$corder = $this->getCustomerOrders($order->id_customer);
		
		
		//获取当前订单 定制信息  
		$orderremind = $this->getOrderRemind($order->id); 
	

		
		$orderremindhistory = $this->getOrderRemindHistroy($order->id); 
		
		//增加订单金额不相符 提醒
		$payment_warning = '';
		$cart_total_paid = (float)Tools::ps_round((float)$this->context->cart->getOrderTotal(true, Cart::BOTH), 2);
		$cart_total_paid =  number_format($cart_total_paid, _PS_PRICE_COMPUTE_PRECISION_)-number_format($order->getOrderPoints(), _PS_PRICE_COMPUTE_PRECISION_);
		$cart_total_paid = number_format($order->total_paid, _PS_PRICE_COMPUTE_PRECISION_);
		$total_real = number_format($order->total_paid_real, _PS_PRICE_COMPUTE_PRECISION_);
		if($cart_total_paid !=$total_real){
			
			$payment_warning = "<span style='color:red'>".$total_real."</span> instead of <span style='color:red'>".$cart_total_paid."</span>" ;
		}
		
		
		//增加签收服务 
		//echo $this->context->cart->id;
		if($this->context->cart->id!=0 ||$this->context->cart->id!='' ){
		$orderservice = $this->getOrderService($this->context->cart->id);
		
		}else{
			
			$orderservice='';
		}
		
		
		
		if($orderservice===false){
			
			$orderservice='';
		}
		$mid_order=$order->id;
		$exchangeres = Db::getInstance()->executeS("select a.* from px_order_exchange a 

where a.id_order = $mid_order");
		
		//推送成本价 操作管理员标记
		$superArr = array("1", "2", "7", "17");
		if(in_array($this->context->employee->id, $superArr)){
			$superadmin=true;
		
		}else{
			
			$superadmin=false;
		}
		
			
		//订单产品详情
		foreach($products as $a){
			
			$res =  Db::getInstance()->getValue("SELECT
	id_cart
FROM
	ps_cart_product
WHERE
	id_cart =".$order->id_cart."
AND id_product =".$a['product_id']."
AND id_product_attribute = ".$a['product_attribute_id']);

			
			if($res){
				$products[$a['id_order_detail']]['is_exchange'] = false;
				
			}else{
				
				
				$products[$a['id_order_detail']]['is_exchange'] = true;
			}
	
		}
		
		
        // Smarty assign
        $this->tpl_view_vars = array(
            'order' => $order,
			'superadmin'=>$superadmin,
			'exchangeres'=>$exchangeres,
			'orderservice'=>$orderservice,
			'customer_order'=>$corder,
			'comreturnhistory'=>$comreturnhistory,
			'comreturnactor'=>$comreturnactor,
			'payment_warning'=>$payment_warning,
            'cart' => new Cart($order->id_cart),
            'customer' => $customer,
			'orderremind' => $orderremind,
			'orderremindhistory' =>$orderremindhistory,
            'gender' => $gender,
            'customer_addresses' => $customer->getAddresses($this->context->language->id),
            'addresses' => array(
                'delivery' => $addressDelivery,
                'deliveryState' => isset($deliveryState) ? $deliveryState : null,
                'invoice' => $addressInvoice,
                'invoiceState' => isset($invoiceState) ? $invoiceState : null
            ),
            'customerStats' => $customer->getStats(),
            'products' => $products,
            'discounts' => $order->getCartRules(),
            'orders_total_paid_tax_incl' => $order->getOrdersTotalPaid(), // Get the sum of total_paid_tax_incl of the order with similar reference
            'total_paid' => $order->getTotalPaid(),
			'total_points'=>$order->getOrderPoints(),
            'returns' => OrderReturn::getOrdersReturn($order->id_customer, $order->id),
            'customer_thread_message' => CustomerThread::getCustomerMessages($order->id_customer, null, $order->id),
            'orderMessages' => OrderMessage::getOrderMessages($order->id_lang),
            'messages' => Message::getMessagesByOrderId($order->id, true),
            'carrier' => new Carrier($order->id_carrier),
            'history' => $history,
            'states' => OrderState::getOrderStates($this->context->language->id),
            'warehouse_list' => $warehouse_list,
            'sources' => ConnectionsSource::getOrderSources($order->id),
            'currentState' => $order->getCurrentOrderState(),
            'currency' => new Currency($order->id_currency),
            'currencies' => Currency::getCurrenciesByIdShop($order->id_shop),
            'previousOrder' => $order->getPreviousOrderId(),
            'nextOrder' => $order->getNextOrderId(),
            'current_index' => self::$currentIndex,
            'carrierModuleCall' => $carrier_module_call,
            'iso_code_lang' => $this->context->language->iso_code,
            'id_lang' => $this->context->language->id,
            'can_edit' => ($this->tabAccess['edit'] == 1),
            'current_id_lang' => $this->context->language->id,
            'invoices_collection' => $order->getInvoicesCollection(),
            'not_paid_invoices_collection' => $order->getNotPaidInvoicesCollection(),
            'payment_methods' => $payment_methods,
            'invoice_management_active' => Configuration::get('PS_INVOICE', null, null, $order->id_shop),
            'display_warehouse' => (int)Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT'),
            'HOOK_CONTENT_ORDER' => Hook::exec('displayAdminOrderContentOrder', array(
                'order' => $order,
                'products' => $products,
                'customer' => $customer)
            ),
            'HOOK_CONTENT_SHIP' => Hook::exec('displayAdminOrderContentShip', array(
                'order' => $order,
                'products' => $products,
                'customer' => $customer)
            ),
            'HOOK_TAB_ORDER' => Hook::exec('displayAdminOrderTabOrder', array(
                'order' => $order,
                'products' => $products,
                'customer' => $customer)
            ),
            'HOOK_TAB_SHIP' => Hook::exec('displayAdminOrderTabShip', array(
                'order' => $order,
                'products' => $products,
                'customer' => $customer)
            ),
        );

        return parent::renderView();
    }

    public function ajaxProcessSearchProducts()
    {
        Context::getContext()->customer = new Customer((int)Tools::getValue('id_customer'));
        $currency = new Currency((int)Tools::getValue('id_currency'));
        if ($products = Product::searchByName((int)$this->context->language->id, pSQL(Tools::getValue('product_search')))) {
            foreach ($products as &$product) {
                // Formatted price
                $product['formatted_price'] = Tools::displayPrice(Tools::convertPrice($product['price_tax_incl'], $currency), $currency);
                // Concret price
                $product['price_tax_incl'] = Tools::ps_round(Tools::convertPrice($product['price_tax_incl'], $currency), 2);
                $product['price_tax_excl'] = Tools::ps_round(Tools::convertPrice($product['price_tax_excl'], $currency), 2);
                $productObj = new Product((int)$product['id_product'], false, (int)$this->context->language->id);
                $combinations = array();
                $attributes = $productObj->getAttributesGroups((int)$this->context->language->id);

                // Tax rate for this customer
                if (Tools::isSubmit('id_address')) {
                    $product['tax_rate'] = $productObj->getTaxesRate(new Address(Tools::getValue('id_address')));
                }

                $product['warehouse_list'] = array();

                foreach ($attributes as $attribute) {
                    if (!isset($combinations[$attribute['id_product_attribute']]['attributes'])) {
                        $combinations[$attribute['id_product_attribute']]['attributes'] = '';
                    }
                    $combinations[$attribute['id_product_attribute']]['attributes'] .= $attribute['attribute_name'].' - ';
                    $combinations[$attribute['id_product_attribute']]['id_product_attribute'] = $attribute['id_product_attribute'];
                    $combinations[$attribute['id_product_attribute']]['default_on'] = $attribute['default_on'];
                    if (!isset($combinations[$attribute['id_product_attribute']]['price'])) {
                        $price_tax_incl = Product::getPriceStatic((int)$product['id_product'], true, $attribute['id_product_attribute']);
                        $price_tax_excl = Product::getPriceStatic((int)$product['id_product'], false, $attribute['id_product_attribute']);
                        $combinations[$attribute['id_product_attribute']]['price_tax_incl'] = Tools::ps_round(Tools::convertPrice($price_tax_incl, $currency), 2);
                        $combinations[$attribute['id_product_attribute']]['price_tax_excl'] = Tools::ps_round(Tools::convertPrice($price_tax_excl, $currency), 2);
                        $combinations[$attribute['id_product_attribute']]['formatted_price'] = Tools::displayPrice(Tools::convertPrice($price_tax_excl, $currency), $currency);
                    }
                    if (!isset($combinations[$attribute['id_product_attribute']]['qty_in_stock'])) {
                        $combinations[$attribute['id_product_attribute']]['qty_in_stock'] = StockAvailable::getQuantityAvailableByProduct((int)$product['id_product'], $attribute['id_product_attribute'], (int)$this->context->shop->id);
                    }

                    if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT') && (int)$product['advanced_stock_management'] == 1) {
                        $product['warehouse_list'][$attribute['id_product_attribute']] = Warehouse::getProductWarehouseList($product['id_product'], $attribute['id_product_attribute']);
                    } else {
                        $product['warehouse_list'][$attribute['id_product_attribute']] = array();
                    }

                    $product['stock'][$attribute['id_product_attribute']] = Product::getRealQuantity($product['id_product'], $attribute['id_product_attribute']);
                }

                if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT') && (int)$product['advanced_stock_management'] == 1) {
                    $product['warehouse_list'][0] = Warehouse::getProductWarehouseList($product['id_product']);
                } else {
                    $product['warehouse_list'][0] = array();
                }

                $product['stock'][0] = StockAvailable::getQuantityAvailableByProduct((int)$product['id_product'], 0, (int)$this->context->shop->id);

                foreach ($combinations as &$combination) {
                    $combination['attributes'] = rtrim($combination['attributes'], ' - ');
                }
                $product['combinations'] = $combinations;

                if ($product['customizable']) {
                    $product_instance = new Product((int)$product['id_product']);
                    $product['customization_fields'] = $product_instance->getCustomizationFields($this->context->language->id);
                }
            }

            $to_return = array(
                'products' => $products,
                'found' => true
            );
        } else {
            $to_return = array('found' => false);
        }

        $this->content = Tools::jsonEncode($to_return);
    }

    public function ajaxProcessSendMailValidateOrder()
    {
        if ($this->tabAccess['edit'] === '1') {
            $cart = new Cart((int)Tools::getValue('id_cart'));
            if (Validate::isLoadedObject($cart)) {
                $customer = new Customer((int)$cart->id_customer);
                if (Validate::isLoadedObject($customer)) {
                    $mailVars = array(
                        '{order_link}' => Context::getContext()->link->getPageLink('order', false, (int)$cart->id_lang, 'step=3&recover_cart='.(int)$cart->id.'&token_cart='.md5(_COOKIE_KEY_.'recover_cart_'.(int)$cart->id)),
                        '{firstname}' => $customer->firstname,
                        '{lastname}' => $customer->lastname
                    );
                    if (Mail::Send((int)$cart->id_lang, 'backoffice_order', Mail::l('Process the payment of your order', (int)$cart->id_lang), $mailVars, $customer->email,
                            $customer->firstname.' '.$customer->lastname, null, null, null, null, _PS_MAIL_DIR_, true, $cart->id_shop)) {
                        die(Tools::jsonEncode(array('errors' => false, 'result' => $this->l('The email was sent to your customer.'))));
                    }
                }
            }
            $this->content = Tools::jsonEncode(array('errors' => true, 'result' => $this->l('Error in sending the email to your customer.')));
        }
    }

    public function ajaxProcessAddProductOnOrder()
    {	//处理订单产品添加 
        // Load object
        $order = new Order((int)Tools::getValue('id_order'));
        if (!Validate::isLoadedObject($order)) {
            die(Tools::jsonEncode(array(
                'result' => false,
                'error' => Tools::displayError('The order object cannot be loaded.')
            )));
        }

        $old_cart_rules = Context::getContext()->cart->getCartRules();

        if ($order->hasBeenShipped()) {
			//换货状态下可以添加删除产品信息
			if( $order->current_state=='14'){

				
			}else{
				 die(Tools::jsonEncode(array(
					'result' => false,
					'error' => Tools::displayError('You cannot add products to delivered orders aaaa. ')
				)));	
				
			}
			
           
        }

        $product_informations = $_POST['add_product'];
        if (isset($_POST['add_invoice'])) {
            $invoice_informations = $_POST['add_invoice'];
        } else {
            $invoice_informations = array();
        }
        $product = new Product($product_informations['product_id'], false, $order->id_lang);
        if (!Validate::isLoadedObject($product)) {
            die(Tools::jsonEncode(array(
                'result' => false,
                'error' => Tools::displayError('The product object cannot be loaded.')
            )));
        }

        if (isset($product_informations['product_attribute_id']) && $product_informations['product_attribute_id']) {
            $combination = new Combination($product_informations['product_attribute_id']);
            if (!Validate::isLoadedObject($combination)) {
                die(Tools::jsonEncode(array(
                'result' => false,
                'error' => Tools::displayError('The combination object cannot be loaded.')
            )));
            }
        }

        // Total method
        $total_method = Cart::BOTH_WITHOUT_SHIPPING;

        // Create new cart
        $cart = new Cart();
        $cart->id_shop_group = $order->id_shop_group;
        $cart->id_shop = $order->id_shop;
        $cart->id_customer = $order->id_customer;
        $cart->id_carrier = $order->id_carrier;
        $cart->id_address_delivery = $order->id_address_delivery;
        $cart->id_address_invoice = $order->id_address_invoice;
        $cart->id_currency = $order->id_currency;
        $cart->id_lang = $order->id_lang;
        $cart->secure_key = $order->secure_key;

        // Save new cart
        $cart->add();

        // Save context (in order to apply cart rule)
        $this->context->cart = $cart;
        $this->context->customer = new Customer($order->id_customer);

        // always add taxes even if there are not displayed to the customer
        $use_taxes = true;

        $initial_product_price_tax_incl = Product::getPriceStatic($product->id, $use_taxes, isset($combination) ? $combination->id : null, 2, null, false, true, 1,
            false, $order->id_customer, $cart->id, $order->{Configuration::get('PS_TAX_ADDRESS_TYPE', null, null, $order->id_shop)});

        // Creating specific price if needed
        if ($product_informations['product_price_tax_incl'] != $initial_product_price_tax_incl) {
            $specific_price = new SpecificPrice();
            $specific_price->id_shop = 0;
            $specific_price->id_shop_group = 0;
            $specific_price->id_currency = 0;
            $specific_price->id_country = 0;
            $specific_price->id_group = 0;
            $specific_price->id_customer = $order->id_customer;
            $specific_price->id_product = $product->id;
            if (isset($combination)) {
                $specific_price->id_product_attribute = $combination->id;
            } else {
                $specific_price->id_product_attribute = 0;
            }
            $specific_price->price = $product_informations['product_price_tax_excl'];
            $specific_price->from_quantity = 1;
            $specific_price->reduction = 0;
            $specific_price->reduction_type = 'amount';
            $specific_price->reduction_tax = 0;
            $specific_price->from = '0000-00-00 00:00:00';
            $specific_price->to = '0000-00-00 00:00:00';
            $specific_price->add();
        }

        // Add product to cart
        $update_quantity = $cart->updateQty($product_informations['product_quantity'], $product->id, isset($product_informations['product_attribute_id']) ? $product_informations['product_attribute_id'] : null,
            isset($combination) ? $combination->id : null, 'up', 0, new Shop($cart->id_shop));

        if ($update_quantity < 0) {
            // If product has attribute, minimal quantity is set with minimal quantity of attribute
            $minimal_quantity = ($product_informations['product_attribute_id']) ? Attribute::getAttributeMinimalQty($product_informations['product_attribute_id']) : $product->minimal_quantity;
            die(Tools::jsonEncode(array('error' => sprintf(Tools::displayError('You must add %d minimum quantity', false), $minimal_quantity))));
        } elseif (!$update_quantity) {
            die(Tools::jsonEncode(array('error' => Tools::displayError('You already have the maximum quantity available for this product.', false))));
        }

        // If order is valid, we can create a new invoice or edit an existing invoice
        if ($order->hasInvoice()) {
            $order_invoice = new OrderInvoice($product_informations['invoice']);
            // Create new invoice
            if ($order_invoice->id == 0) {
                // If we create a new invoice, we calculate shipping cost
                $total_method = Cart::BOTH;
                // Create Cart rule in order to make free shipping
                if (isset($invoice_informations['free_shipping']) && $invoice_informations['free_shipping']) {
                    $cart_rule = new CartRule();
                    $cart_rule->id_customer = $order->id_customer;
                    $cart_rule->name = array(
                        Configuration::get('PS_LANG_DEFAULT') => $this->l('[Generated] CartRule for Free Shipping')
                    );
                    $cart_rule->date_from = date('Y-m-d H:i:s', time());
                    $cart_rule->date_to = date('Y-m-d H:i:s', time() + 24 * 3600);
                    $cart_rule->quantity = 1;
                    $cart_rule->quantity_per_user = 1;
                    $cart_rule->minimum_amount_currency = $order->id_currency;
                    $cart_rule->reduction_currency = $order->id_currency;
                    $cart_rule->free_shipping = true;
                    $cart_rule->active = 1;
                    $cart_rule->add();

                    // Add cart rule to cart and in order
                    $cart->addCartRule($cart_rule->id);
                    $values = array(
                        'tax_incl' => $cart_rule->getContextualValue(true),
                        'tax_excl' => $cart_rule->getContextualValue(false)
                    );
                    $order->addCartRule($cart_rule->id, $cart_rule->name[Configuration::get('PS_LANG_DEFAULT')], $values);
                }

                $order_invoice->id_order = $order->id;
                if ($order_invoice->number) {
                    Configuration::updateValue('PS_INVOICE_START_NUMBER', false, false, null, $order->id_shop);
                } else {
                    $order_invoice->number = Order::getLastInvoiceNumber() + 1;
                }

                $invoice_address = new Address((int)$order->{Configuration::get('PS_TAX_ADDRESS_TYPE', null, null, $order->id_shop)});
                $carrier = new Carrier((int)$order->id_carrier);
                $tax_calculator = $carrier->getTaxCalculator($invoice_address);

                $order_invoice->total_paid_tax_excl = Tools::ps_round((float)$cart->getOrderTotal(false, $total_method), 2);
                $order_invoice->total_paid_tax_incl = Tools::ps_round((float)$cart->getOrderTotal($use_taxes, $total_method), 2);
                $order_invoice->total_products = (float)$cart->getOrderTotal(false, Cart::ONLY_PRODUCTS);
                $order_invoice->total_products_wt = (float)$cart->getOrderTotal($use_taxes, Cart::ONLY_PRODUCTS);
                $order_invoice->total_shipping_tax_excl = (float)$cart->getTotalShippingCost(null, false);
                $order_invoice->total_shipping_tax_incl = (float)$cart->getTotalShippingCost();

                $order_invoice->total_wrapping_tax_excl = abs($cart->getOrderTotal(false, Cart::ONLY_WRAPPING));
                $order_invoice->total_wrapping_tax_incl = abs($cart->getOrderTotal($use_taxes, Cart::ONLY_WRAPPING));
                $order_invoice->shipping_tax_computation_method = (int)$tax_calculator->computation_method;

                // Update current order field, only shipping because other field is updated later
                $order->total_shipping += $order_invoice->total_shipping_tax_incl;
                $order->total_shipping_tax_excl += $order_invoice->total_shipping_tax_excl;
                $order->total_shipping_tax_incl += ($use_taxes) ? $order_invoice->total_shipping_tax_incl : $order_invoice->total_shipping_tax_excl;

                $order->total_wrapping += abs($cart->getOrderTotal($use_taxes, Cart::ONLY_WRAPPING));
                $order->total_wrapping_tax_excl += abs($cart->getOrderTotal(false, Cart::ONLY_WRAPPING));
                $order->total_wrapping_tax_incl += abs($cart->getOrderTotal($use_taxes, Cart::ONLY_WRAPPING));
                $order_invoice->add();

                $order_invoice->saveCarrierTaxCalculator($tax_calculator->getTaxesAmount($order_invoice->total_shipping_tax_excl));

                $order_carrier = new OrderCarrier();
                $order_carrier->id_order = (int)$order->id;
                $order_carrier->id_carrier = (int)$order->id_carrier;
                $order_carrier->id_order_invoice = (int)$order_invoice->id;
                $order_carrier->weight = (float)$cart->getTotalWeight();
                $order_carrier->shipping_cost_tax_excl = (float)$order_invoice->total_shipping_tax_excl;
                $order_carrier->shipping_cost_tax_incl = ($use_taxes) ? (float)$order_invoice->total_shipping_tax_incl : (float)$order_invoice->total_shipping_tax_excl;
                $order_carrier->add();
            }
            // Update current invoice
            else {
                $order_invoice->total_paid_tax_excl += Tools::ps_round((float)($cart->getOrderTotal(false, $total_method)), 2);
                $order_invoice->total_paid_tax_incl += Tools::ps_round((float)($cart->getOrderTotal($use_taxes, $total_method)), 2);
                $order_invoice->total_products += (float)$cart->getOrderTotal(false, Cart::ONLY_PRODUCTS);
                $order_invoice->total_products_wt += (float)$cart->getOrderTotal($use_taxes, Cart::ONLY_PRODUCTS);
                $order_invoice->update();
            }
        }

        // Create Order detail information
        $order_detail = new OrderDetail();
        $order_detail->createList($order, $cart, $order->getCurrentOrderState(), $cart->getProducts(), (isset($order_invoice) ? $order_invoice->id : 0), $use_taxes, (int)Tools::getValue('add_product_warehouse'));

        // update totals amount of order
        $order->total_products += (float)$cart->getOrderTotal(false, Cart::ONLY_PRODUCTS);
        $order->total_products_wt += (float)$cart->getOrderTotal($use_taxes, Cart::ONLY_PRODUCTS);

        $order->total_paid += Tools::ps_round((float)($cart->getOrderTotal(true, $total_method)), 2);
        $order->total_paid_tax_excl += Tools::ps_round((float)($cart->getOrderTotal(false, $total_method)), 2);
        $order->total_paid_tax_incl += Tools::ps_round((float)($cart->getOrderTotal($use_taxes, $total_method)), 2);

        if (isset($order_invoice) && Validate::isLoadedObject($order_invoice)) {
            $order->total_shipping = $order_invoice->total_shipping_tax_incl;
            $order->total_shipping_tax_incl = $order_invoice->total_shipping_tax_incl;
            $order->total_shipping_tax_excl = $order_invoice->total_shipping_tax_excl;
        }
        // discount
        $order->total_discounts += (float)abs($cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS));
        $order->total_discounts_tax_excl += (float)abs($cart->getOrderTotal(false, Cart::ONLY_DISCOUNTS));
        $order->total_discounts_tax_incl += (float)abs($cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS));

        // Save changes of order
        $order->update();

        // Update weight SUM
        $order_carrier = new OrderCarrier((int)$order->getIdOrderCarrier());
        if (Validate::isLoadedObject($order_carrier)) {
            $order_carrier->weight = (float)$order->getTotalWeight();
            if ($order_carrier->update()) {
                $order->weight = sprintf("%.3f ".Configuration::get('PS_WEIGHT_UNIT'), $order_carrier->weight);
            }
        }

        // Update Tax lines
        $order_detail->updateTaxAmount($order);

        // Delete specific price if exists
        if (isset($specific_price)) {
            $specific_price->delete();
        }

        $products = $this->getProducts($order);

        // Get the last product
        $product = end($products);
        $resume = OrderSlip::getProductSlipResume((int)$product['id_order_detail']);
        $product['quantity_refundable'] = $product['product_quantity'] - $resume['product_quantity'];
        $product['amount_refundable'] = $product['total_price_tax_excl'] - $resume['amount_tax_excl'];
        $product['amount_refund'] = Tools::displayPrice($resume['amount_tax_incl']);
        $product['return_history'] = OrderReturn::getProductReturnDetail((int)$product['id_order_detail']);
        $product['refund_history'] = OrderSlip::getProductSlipDetail((int)$product['id_order_detail']);
        if ($product['id_warehouse'] != 0) {
            $warehouse = new Warehouse((int)$product['id_warehouse']);
            $product['warehouse_name'] = $warehouse->name;
            $warehouse_location = WarehouseProductLocation::getProductLocation($product['product_id'], $product['product_attribute_id'], $product['id_warehouse']);
            if (!empty($warehouse_location)) {
                $product['warehouse_location'] = $warehouse_location;
            } else {
                $product['warehouse_location'] = false;
            }
        } else {
            $product['warehouse_name'] = '--';
            $product['warehouse_location'] = false;
        }

        // Get invoices collection
        $invoice_collection = $order->getInvoicesCollection();

        $invoice_array = array();
        foreach ($invoice_collection as $invoice) {
            /** @var OrderInvoice $invoice */
            $invoice->name = $invoice->getInvoiceNumberFormatted(Context::getContext()->language->id, (int)$order->id_shop);
            $invoice_array[] = $invoice;
        }

        // Assign to smarty informations in order to show the new product line
        $this->context->smarty->assign(array(
            'product' => $product,
            'order' => $order,
            'currency' => new Currency($order->id_currency),
            'can_edit' => $this->tabAccess['edit'],
            'invoices_collection' => $invoice_collection,
            'current_id_lang' => Context::getContext()->language->id,
            'link' => Context::getContext()->link,
            'current_index' => self::$currentIndex,
            'display_warehouse' => (int)Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')
        ));

        $this->sendChangedNotification($order);
        $new_cart_rules = Context::getContext()->cart->getCartRules();
        sort($old_cart_rules);
        sort($new_cart_rules);
        $result = array_diff($new_cart_rules, $old_cart_rules);
        $refresh = false;

        $res = true;
        foreach ($result as $cart_rule) {
            $refresh = true;
            // Create OrderCartRule
            $rule = new CartRule($cart_rule['id_cart_rule']);
            $values = array(
                    'tax_incl' => $rule->getContextualValue(true),
                    'tax_excl' => $rule->getContextualValue(false)
                    );
            $order_cart_rule = new OrderCartRule();
            $order_cart_rule->id_order = $order->id;
            $order_cart_rule->id_cart_rule = $cart_rule['id_cart_rule'];
            $order_cart_rule->id_order_invoice = $order_invoice->id;
            $order_cart_rule->name = $cart_rule['name'];
            $order_cart_rule->value = $values['tax_incl'];
            $order_cart_rule->value_tax_excl = $values['tax_excl'];
            $res &= $order_cart_rule->add();

            $order->total_discounts += $order_cart_rule->value;
            $order->total_discounts_tax_incl += $order_cart_rule->value;
            $order->total_discounts_tax_excl += $order_cart_rule->value_tax_excl;
            $order->total_paid -= $order_cart_rule->value;
            $order->total_paid_tax_incl -= $order_cart_rule->value;
            $order->total_paid_tax_excl -= $order_cart_rule->value_tax_excl;
        }

        // Update Order
        $res &= $order->update();


        die(Tools::jsonEncode(array(
            'result' => true,
            'view' => $this->createTemplate('_product_line.tpl')->fetch(),
            'can_edit' => $this->tabAccess['add'],
            'order' => $order,
            'invoices' => $invoice_array,
            'documents_html' => $this->createTemplate('_documents.tpl')->fetch(),
            'shipping_html' => $this->createTemplate('_shipping.tpl')->fetch(),
            'discount_form_html' => $this->createTemplate('_discount_form.tpl')->fetch(),
            'refresh' => $refresh
        )));
    }

    public function sendChangedNotification(Order $order = null)
    {
        if (is_null($order)) {
            $order = new Order(Tools::getValue('id_order'));
        }

        Hook::exec('actionOrderEdited', array('order' => $order));
    }

    public function ajaxProcessLoadProductInformation()
    {
        $order_detail = new OrderDetail(Tools::getValue('id_order_detail'));
        if (!Validate::isLoadedObject($order_detail)) {
            die(Tools::jsonEncode(array(
                'result' => false,
                'error' => Tools::displayError('The OrderDetail object cannot be loaded.')
            )));
        }

        $product = new Product($order_detail->product_id);
        if (!Validate::isLoadedObject($product)) {
            die(Tools::jsonEncode(array(
                'result' => false,
                'error' => Tools::displayError('The product object cannot be loaded.')
            )));
        }

        $address = new Address(Tools::getValue('id_address'));
        if (!Validate::isLoadedObject($address)) {
            die(Tools::jsonEncode(array(
                'result' => false,
                'error' => Tools::displayError('The address object cannot be loaded.')
            )));
        }

        die(Tools::jsonEncode(array(
            'result' => true,
            'product' => $product,
            'tax_rate' => $product->getTaxesRate($address),
            'price_tax_incl' => Product::getPriceStatic($product->id, true, $order_detail->product_attribute_id, 2),
            'price_tax_excl' => Product::getPriceStatic($product->id, false, $order_detail->product_attribute_id, 2),
            'reduction_percent' => $order_detail->reduction_percent
        )));
    }

    public function ajaxProcessEditProductOnOrder()
    {
        // Return value
        $res = true;

        $order = new Order((int)Tools::getValue('id_order'));
        $order_detail = new OrderDetail((int)Tools::getValue('product_id_order_detail'));
        if (Tools::isSubmit('product_invoice')) {
            $order_invoice = new OrderInvoice((int)Tools::getValue('product_invoice'));
        }

        // Check fields validity
        $this->doEditProductValidation($order_detail, $order, isset($order_invoice) ? $order_invoice : null);

        // If multiple product_quantity, the order details concern a product customized
        $product_quantity = 0;
        if (is_array(Tools::getValue('product_quantity'))) {
            foreach (Tools::getValue('product_quantity') as $id_customization => $qty) {
                // Update quantity of each customization
                Db::getInstance()->update('customization', array('quantity' => (int)$qty), 'id_customization = '.(int)$id_customization);
                // Calculate the real quantity of the product
                $product_quantity += $qty;
            }
        } else {
            $product_quantity = Tools::getValue('product_quantity');
        }

        $product_price_tax_incl = Tools::ps_round(Tools::getValue('product_price_tax_incl'), 2);
        $product_price_tax_excl = Tools::ps_round(Tools::getValue('product_price_tax_excl'), 2);
        $total_products_tax_incl = $product_price_tax_incl * $product_quantity;
        $total_products_tax_excl = $product_price_tax_excl * $product_quantity;

        // Calculate differences of price (Before / After)
        $diff_price_tax_incl = $total_products_tax_incl - $order_detail->total_price_tax_incl;
        $diff_price_tax_excl = $total_products_tax_excl - $order_detail->total_price_tax_excl;

        // Apply change on OrderInvoice
        if (isset($order_invoice)) {
            // If OrderInvoice to use is different, we update the old invoice and new invoice
            if ($order_detail->id_order_invoice != $order_invoice->id) {
                $old_order_invoice = new OrderInvoice($order_detail->id_order_invoice);
                // We remove cost of products
                $old_order_invoice->total_products -= $order_detail->total_price_tax_excl;
                $old_order_invoice->total_products_wt -= $order_detail->total_price_tax_incl;

                $old_order_invoice->total_paid_tax_excl -= $order_detail->total_price_tax_excl;
                $old_order_invoice->total_paid_tax_incl -= $order_detail->total_price_tax_incl;

                $res &= $old_order_invoice->update();

                $order_invoice->total_products += $order_detail->total_price_tax_excl;
                $order_invoice->total_products_wt += $order_detail->total_price_tax_incl;

                $order_invoice->total_paid_tax_excl += $order_detail->total_price_tax_excl;
                $order_invoice->total_paid_tax_incl += $order_detail->total_price_tax_incl;

                $order_detail->id_order_invoice = $order_invoice->id;
            }
        }

        if ($diff_price_tax_incl != 0 && $diff_price_tax_excl != 0) {
            $order_detail->unit_price_tax_excl = $product_price_tax_excl;
            $order_detail->unit_price_tax_incl = $product_price_tax_incl;

            $order_detail->total_price_tax_incl += $diff_price_tax_incl;
            $order_detail->total_price_tax_excl += $diff_price_tax_excl;

            if (isset($order_invoice)) {
                // Apply changes on OrderInvoice
                $order_invoice->total_products += $diff_price_tax_excl;
                $order_invoice->total_products_wt += $diff_price_tax_incl;

                $order_invoice->total_paid_tax_excl += $diff_price_tax_excl;
                $order_invoice->total_paid_tax_incl += $diff_price_tax_incl;
            }

            // Apply changes on Order
            $order = new Order($order_detail->id_order);
            $order->total_products += $diff_price_tax_excl;
            $order->total_products_wt += $diff_price_tax_incl;

            $order->total_paid += $diff_price_tax_incl;
            $order->total_paid_tax_excl += $diff_price_tax_excl;
            $order->total_paid_tax_incl += $diff_price_tax_incl;

            $res &= $order->update();
        }

        $old_quantity = $order_detail->product_quantity;

        $order_detail->product_quantity = $product_quantity;
        $order_detail->reduction_percent = 0;

        // update taxes
        $res &= $order_detail->updateTaxAmount($order);

        // Save order detail
        $res &= $order_detail->update();

        // Update weight SUM
        $order_carrier = new OrderCarrier((int)$order->getIdOrderCarrier());
        if (Validate::isLoadedObject($order_carrier)) {
            $order_carrier->weight = (float)$order->getTotalWeight();
            $res &= $order_carrier->update();
            if ($res) {
                $order->weight = sprintf("%.3f ".Configuration::get('PS_WEIGHT_UNIT'), $order_carrier->weight);
            }
        }

        // Save order invoice
        if (isset($order_invoice)) {
            $res &= $order_invoice->update();
        }

        // Update product available quantity
        StockAvailable::updateQuantity($order_detail->product_id, $order_detail->product_attribute_id, ($old_quantity - $order_detail->product_quantity), $order->id_shop);

        $products = $this->getProducts($order);
        // Get the last product
        $product = $products[$order_detail->id];
        $resume = OrderSlip::getProductSlipResume($order_detail->id);
        $product['quantity_refundable'] = $product['product_quantity'] - $resume['product_quantity'];
        $product['amount_refundable'] = $product['total_price_tax_excl'] - $resume['amount_tax_excl'];
        $product['amount_refund'] = Tools::displayPrice($resume['amount_tax_incl']);
        $product['refund_history'] = OrderSlip::getProductSlipDetail($order_detail->id);
        if ($product['id_warehouse'] != 0) {
            $warehouse = new Warehouse((int)$product['id_warehouse']);
            $product['warehouse_name'] = $warehouse->name;
            $warehouse_location = WarehouseProductLocation::getProductLocation($product['product_id'], $product['product_attribute_id'], $product['id_warehouse']);
            if (!empty($warehouse_location)) {
                $product['warehouse_location'] = $warehouse_location;
            } else {
                $product['warehouse_location'] = false;
            }
        } else {
            $product['warehouse_name'] = '--';
            $product['warehouse_location'] = false;
        }

        // Get invoices collection
        $invoice_collection = $order->getInvoicesCollection();

        $invoice_array = array();
        foreach ($invoice_collection as $invoice) {
            /** @var OrderInvoice $invoice */
            $invoice->name = $invoice->getInvoiceNumberFormatted(Context::getContext()->language->id, (int)$order->id_shop);
            $invoice_array[] = $invoice;
        }

        // Assign to smarty informations in order to show the new product line
        $this->context->smarty->assign(array(
            'product' => $product,
            'order' => $order,
            'currency' => new Currency($order->id_currency),
            'can_edit' => $this->tabAccess['edit'],
            'invoices_collection' => $invoice_collection,
            'current_id_lang' => Context::getContext()->language->id,
            'link' => Context::getContext()->link,
            'current_index' => self::$currentIndex,
            'display_warehouse' => (int)Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')
        ));

        if (!$res) {
            die(Tools::jsonEncode(array(
                'result' => $res,
                'error' => Tools::displayError('An error occurred while editing the product line.')
            )));
        }


        if (is_array(Tools::getValue('product_quantity'))) {
            $view = $this->createTemplate('_customized_data.tpl')->fetch();
        } else {
            $view = $this->createTemplate('_product_line.tpl')->fetch();
        }

        $this->sendChangedNotification($order);

        die(Tools::jsonEncode(array(
            'result' => $res,
            'view' => $view,
            'can_edit' => $this->tabAccess['add'],
            'invoices_collection' => $invoice_collection,
            'order' => $order,
            'invoices' => $invoice_array,
            'documents_html' => $this->createTemplate('_documents.tpl')->fetch(),
            'shipping_html' => $this->createTemplate('_shipping.tpl')->fetch(),
            'customized_product' => is_array(Tools::getValue('product_quantity'))
        )));
    }

    public function ajaxProcessDeleteProductLine()
    {	
        $res = true;

        $order_detail = new OrderDetail((int)Tools::getValue('id_order_detail'));
        $order = new Order((int)Tools::getValue('id_order'));
	
		
        $this->doDeleteProductLineValidation($order_detail, $order);
		
		//增加退换货信息记录
		if( $order->current_state =='14' or $order->current_state =='2' or true){
			$employee_name = $this->context->employee->firstname.'.'.$this->context->employee->lastname;
			
			$this->AddOrderExchange((int)Tools::getValue('id_order_detail'),$employee_name);
		}
		
        // Update OrderInvoice of this OrderDetail
        if ($order_detail->id_order_invoice != 0) {
            $order_invoice = new OrderInvoice($order_detail->id_order_invoice);
            $order_invoice->total_paid_tax_excl -= $order_detail->total_price_tax_excl;
            $order_invoice->total_paid_tax_incl -= $order_detail->total_price_tax_incl;
            $order_invoice->total_products -= $order_detail->total_price_tax_excl;
            $order_invoice->total_products_wt -= $order_detail->total_price_tax_incl;
            $res &= $order_invoice->update();
        }
		
        // Update Order
        $order->total_paid -= $order_detail->total_price_tax_incl;
		
		
		$points = Db::getInstance()->getValue("select  total_points  from  ps_orders where  id_order = ".(int)Tools::getValue('id_order'));
		
		$order->total_paid  +=  $points;
		
        $order->total_paid_tax_incl -= $order_detail->total_price_tax_incl;
        $order->total_paid_tax_excl -= $order_detail->total_price_tax_excl;
        $order->total_products -= $order_detail->total_price_tax_excl;
        $order->total_products_wt -= $order_detail->total_price_tax_incl;

        $res &= $order->update();




        // Reinject quantity in stock
        $this->reinjectQuantity($order_detail, $order_detail->product_quantity, true);

        // Update weight SUM
        $order_carrier = new OrderCarrier((int)$order->getIdOrderCarrier());
        if (Validate::isLoadedObject($order_carrier)) {
            $order_carrier->weight = (float)$order->getTotalWeight();
            $res &= $order_carrier->update();
            if ($res) {
                $order->weight = sprintf("%.3f ".Configuration::get('PS_WEIGHT_UNIT'), $order_carrier->weight);
            }
        }

        if (!$res) {
            die(Tools::jsonEncode(array(
                'result' => $res,
                'error' => Tools::displayError('An error occurred while attempting to delete the product line.')
            )));
        }

        // Get invoices collection
        $invoice_collection = $order->getInvoicesCollection();

        $invoice_array = array();
        foreach ($invoice_collection as $invoice) {
            /** @var OrderInvoice $invoice */
            $invoice->name = $invoice->getInvoiceNumberFormatted(Context::getContext()->language->id, (int)$order->id_shop);
            $invoice_array[] = $invoice;
        }

        // Assign to smarty informations in order to show the new product line
        $this->context->smarty->assign(array(
            'order' => $order,
            'currency' => new Currency($order->id_currency),
            'invoices_collection' => $invoice_collection,
            'current_id_lang' => Context::getContext()->language->id,
            'link' => Context::getContext()->link,
            'current_index' => self::$currentIndex
        ));

        $this->sendChangedNotification($order);

        die(Tools::jsonEncode(array(
            'result' => $res,
            'order' => $order,
            'invoices' => $invoice_array,
            'documents_html' => $this->createTemplate('_documents.tpl')->fetch(),
            'shipping_html' => $this->createTemplate('_shipping.tpl')->fetch()
        )));
    }
	
	public function  AddOrderExchange($id_order_detail,$actor){
		
		$sql = "insert into  px_order_exchange(
											id_order_detail,
											id_order,
											id_order_invoice,
											id_warehouse,
											id_shop,
											product_id,
											product_attribute_id,
											product_name,
											product_quantity,
											product_quantity_in_stock,
											product_quantity_refunded,
											product_quantity_return,
											product_quantity_reinjected,
											product_price,
											reduction_percent,
											reduction_amount,
											reduction_amount_tax_incl,
											reduction_amount_tax_excl,
											group_reduction,
											product_quantity_discount,
											product_ean13,
											product_upc,
											product_reference,
											product_supplier_reference,
											product_weight,
											id_tax_rules_group,
											tax_computation_method,
											tax_name,
											tax_rate,
											ecotax,
											ecotax_tax_rate,
											discount_quantity_applied,
											download_hash,
											download_nb,
											download_deadline,
											total_price_tax_incl,
											total_price_tax_excl,
											unit_price_tax_incl,
											unit_price_tax_excl,
											total_shipping_price_tax_incl,
											total_shipping_price_tax_excl,
											purchase_supplier_price,
											original_product_price,
											original_wholesale_price,
											actor,
											datetime


											) SELECT id_order_detail,
											id_order,
											id_order_invoice,
											id_warehouse,
											id_shop,
											product_id,
											product_attribute_id,
											product_name,
											product_quantity,
											product_quantity_in_stock,
											product_quantity_refunded,
											product_quantity_return,
											product_quantity_reinjected,
											product_price,
											reduction_percent,
											reduction_amount,
											reduction_amount_tax_incl,
											reduction_amount_tax_excl,
											group_reduction,
											product_quantity_discount,
											product_ean13,
											product_upc,
											product_reference,
											product_supplier_reference,
											product_weight,
											id_tax_rules_group,
											tax_computation_method,
											tax_name,
											tax_rate,
											ecotax,
											ecotax_tax_rate,
											discount_quantity_applied,
											download_hash,
											download_nb,
											download_deadline,
											total_price_tax_incl,
											total_price_tax_excl,
											unit_price_tax_incl,
											unit_price_tax_excl,
											total_shipping_price_tax_incl,
											total_shipping_price_tax_excl,
											purchase_supplier_price,
											original_product_price,
											original_wholesale_price,
											'$actor',
											now()
											 from ps_order_detail a where a.id_order_detail = $id_order_detail";
									
		Db::getInstance()->execute($sql);
		
		
	}
	
	
    protected function doEditProductValidation(OrderDetail $order_detail, Order $order, OrderInvoice $order_invoice = null)
    {
        if (!Validate::isLoadedObject($order_detail)) {
            die(Tools::jsonEncode(array(
                'result' => false,
                'error' => Tools::displayError('The Order Detail object could not be loaded.')
            )));
        }

        if (!empty($order_invoice) && !Validate::isLoadedObject($order_invoice)) {
            die(Tools::jsonEncode(array(
                'result' => false,
                'error' => Tools::displayError('The invoice object cannot be loaded.')
            )));
        }

        if (!Validate::isLoadedObject($order)) {
            die(Tools::jsonEncode(array(
                'result' => false,
                'error' => Tools::displayError('The order object cannot be loaded.')
            )));
        }

        if ($order_detail->id_order != $order->id) {
            die(Tools::jsonEncode(array(
                'result' => false,
                'error' => Tools::displayError('You cannot edit the order detail for this order.')
            )));
        }

        // We can't edit a delivered order
        if ($order->hasBeenDelivered()) {
            die(Tools::jsonEncode(array(
                'result' => false,
                'error' => Tools::displayError('You cannot edit a delivered order.')
            )));
        }

        if (!empty($order_invoice) && $order_invoice->id_order != Tools::getValue('id_order')) {
            die(Tools::jsonEncode(array(
                'result' => false,
                'error' => Tools::displayError('You cannot use this invoice for the order')
            )));
        }

        // Clean price
        $product_price_tax_incl = str_replace(',', '.', Tools::getValue('product_price_tax_incl'));
        $product_price_tax_excl = str_replace(',', '.', Tools::getValue('product_price_tax_excl'));

        if (!Validate::isPrice($product_price_tax_incl) || !Validate::isPrice($product_price_tax_excl)) {
            die(Tools::jsonEncode(array(
                'result' => false,
                'error' => Tools::displayError('Invalid price')
            )));
        }

        if (!is_array(Tools::getValue('product_quantity')) && !Validate::isUnsignedInt(Tools::getValue('product_quantity'))) {
            die(Tools::jsonEncode(array(
                'result' => false,
                'error' => Tools::displayError('Invalid quantity')
            )));
        } elseif (is_array(Tools::getValue('product_quantity'))) {
            foreach (Tools::getValue('product_quantity') as $qty) {
                if (!Validate::isUnsignedInt($qty)) {
                    die(Tools::jsonEncode(array(
                        'result' => false,
                        'error' => Tools::displayError('Invalid quantity')
                    )));
                }
            }
        }
    }
	
	
	

    protected function doDeleteProductLineValidation(OrderDetail $order_detail, Order $order)
    {
        if (!Validate::isLoadedObject($order_detail)) {
            die(Tools::jsonEncode(array(
                'result' => false,
                'error' => Tools::displayError('The Order Detail object could not be loaded.')
            )));
        }

        if (!Validate::isLoadedObject($order)) {
            die(Tools::jsonEncode(array(
                'result' => false,
                'error' => Tools::displayError('The order object cannot be loaded.')
            )));
        }

        if ($order_detail->id_order != $order->id) {
            die(Tools::jsonEncode(array(
                'result' => false,
                'error' => Tools::displayError('You cannot delete the order detail.')
            )));
        }

        // We can't edit a delivered order
        if ($order->hasBeenDelivered() and false) {
            die(Tools::jsonEncode(array(
                'result' => false,
                'error' => Tools::displayError('You cannot edit a delivered order.')
            )));
        }
    }

    /**
     * @param Order $order
     * @return array
     */
    protected function getProducts($order)
    {
        $products = $order->getProducts();

        foreach ($products as &$product) {
            if ($product['image'] != null) {
                $name = 'product_mini_'.(int)$product['product_id'].(isset($product['product_attribute_id']) ? '_'.(int)$product['product_attribute_id'] : '').'.jpg';
                // generate image cache, only for back office
                $product['image_tag'] = ImageManager::thumbnail(_PS_IMG_DIR_.'p/'.$product['image']->getExistingImgPath().'.jpg', $name, 45, 'jpg');
                if (file_exists(_PS_TMP_IMG_DIR_.$name)) {
                    $product['image_size'] = getimagesize(_PS_TMP_IMG_DIR_.$name);
                } else {
                    $product['image_size'] = false;
                }
            }
        }

        ksort($products);

        return $products;
    }

    /**
     * @param OrderDetail $order_detail
     * @param int $qty_cancel_product
     * @param bool $delete
     */
    protected function reinjectQuantity($order_detail, $qty_cancel_product, $delete = false)
    {
        // Reinject product
        $reinjectable_quantity = (int)$order_detail->product_quantity - (int)$order_detail->product_quantity_reinjected;
        $quantity_to_reinject = $qty_cancel_product > $reinjectable_quantity ? $reinjectable_quantity : $qty_cancel_product;
        // @since 1.5.0 : Advanced Stock Management
        $product_to_inject = new Product($order_detail->product_id, false, (int)$this->context->language->id, (int)$order_detail->id_shop);

        $product = new Product($order_detail->product_id, false, (int)$this->context->language->id, (int)$order_detail->id_shop);

        if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT') && $product->advanced_stock_management && $order_detail->id_warehouse != 0) {
            $manager = StockManagerFactory::getManager();
            $movements = StockMvt::getNegativeStockMvts(
                                $order_detail->id_order,
                                $order_detail->product_id,
                                $order_detail->product_attribute_id,
                                $quantity_to_reinject
                            );
            $left_to_reinject = $quantity_to_reinject;
            foreach ($movements as $movement) {
                if ($left_to_reinject > $movement['physical_quantity']) {
                    $quantity_to_reinject = $movement['physical_quantity'];
                }

                $left_to_reinject -= $quantity_to_reinject;
                if (Pack::isPack((int)$product->id)) {
                    // Gets items
                        if ($product->pack_stock_type == 1 || $product->pack_stock_type == 2 || ($product->pack_stock_type == 3 && Configuration::get('PS_PACK_STOCK_TYPE') > 0)) {
                            $products_pack = Pack::getItems((int)$product->id, (int)Configuration::get('PS_LANG_DEFAULT'));
                            // Foreach item
                            foreach ($products_pack as $product_pack) {
                                if ($product_pack->advanced_stock_management == 1) {
                                    $manager->addProduct(
                                        $product_pack->id,
                                        $product_pack->id_pack_product_attribute,
                                        new Warehouse($movement['id_warehouse']),
                                        $product_pack->pack_quantity * $quantity_to_reinject,
                                        null,
                                        $movement['price_te'],
                                        true
                                    );
                                }
                            }
                        }
                    if ($product->pack_stock_type == 0 || $product->pack_stock_type == 2 ||
                            ($product->pack_stock_type == 3 && (Configuration::get('PS_PACK_STOCK_TYPE') == 0 || Configuration::get('PS_PACK_STOCK_TYPE') == 2))) {
                        $manager->addProduct(
                                $order_detail->product_id,
                                $order_detail->product_attribute_id,
                                new Warehouse($movement['id_warehouse']),
                                $quantity_to_reinject,
                                null,
                                $movement['price_te'],
                                true
                            );
                    }
                } else {
                    $manager->addProduct(
                            $order_detail->product_id,
                            $order_detail->product_attribute_id,
                            new Warehouse($movement['id_warehouse']),
                            $quantity_to_reinject,
                            null,
                            $movement['price_te'],
                            true
                        );
                }
            }

            $id_product = $order_detail->product_id;
            if ($delete) {
                $order_detail->delete();
            }
            StockAvailable::synchronize($id_product);
        } elseif ($order_detail->id_warehouse == 0) {
            StockAvailable::updateQuantity(
                    $order_detail->product_id,
                    $order_detail->product_attribute_id,
                    $quantity_to_reinject,
                    $order_detail->id_shop
                );

            if ($delete) {
                $order_detail->delete();
            }
        } else {
            $this->errors[] = Tools::displayError('This product cannot be re-stocked.');
        }
    }

    /**
     * @param OrderInvoice $order_invoice
     * @param float $value_tax_incl
     * @param float $value_tax_excl
     */
    protected function applyDiscountOnInvoice($order_invoice, $value_tax_incl, $value_tax_excl)
    {
        // Update OrderInvoice
        $order_invoice->total_discount_tax_incl += $value_tax_incl;
        $order_invoice->total_discount_tax_excl += $value_tax_excl;
        $order_invoice->total_paid_tax_incl -= $value_tax_incl;
        $order_invoice->total_paid_tax_excl -= $value_tax_excl;
        $order_invoice->update();
    }
	
	//更新自定义的 快递名称 --自定义函数
	public  function updatecarrier($id_order,$real_carrier){
		
		$result = Db::getInstance()->query("update ps_order_carrier set real_carrier ='$real_carrier' where id_order =$id_order");
		//var_dump($result);
		return $result;
	}
	//获取客户历史订单信息 --自定义函数
	
	
	public function  getCustomerOrders($id_customer){
		
		$result = Db::getInstance()->executeS("SELECT
	o.id_order,
	o.date_add,
	o.total_paid,
	os.`name`,
IF (o.valid = 1, '√', 'X') AS STATUS
FROM
	ps_orders o
LEFT JOIN ps_order_state_lang os on o.current_state=os.id_order_state
WHERE
	id_customer = '$id_customer' GROUP BY o.id_order");
	
		return $result;
		
	}
	
	
	//获取订单定制产品 
	
	
	public function  getOrderRemind($id_order){
		
		$result = Db::getInstance()->executeS("SELECT
    ore.*,od.product_id,od.id_order,od.product_name,od.product_reference as skus	
	FROM	ps_order_detail od
	LEFT JOIN px_order_remind  ore  on   od.product_name=ore.product_name and  od.id_order=ore.id_order
	WHERE	od.id_order = $id_order   ");
	
		return $result;
		
	}
	
	//获取备货时间操作记录
	
	
	public function  getOrderRemindHistroy($id_order){
		
		$result = Db::getInstance()->executeS("SELECT
por.id_remind,action,date_add
FROM
	px_order_remind por
LEFT JOIN px_order_remind_history orh on orh.id_remind=por.id_remind 
where por.id_order= $id_order order by date_add desc");
	
		return $result;
		
	}
	
	
	//获取订单服务状态
	
	
	public function  getOrderService($id_cart){
		if($id_cart==''){
		$result = Db::getInstance()->getValue("select mark from  px_order_mark  where id_cart = $id_cart");
	
		return $result;
		}else{
			
			return '';
		}
		
	}
	
	//获取订单定制产品 
	
	
	public function  AddOrderRemind($id_order,$name,$skus,$date,$actor){
		
		/* Db::getInstance()->executeS("insert into px_order_remind (id_order,skus,product_name,date,actor)VALUES 
		($id_order,$skus,$name,'$date',$actor)");
		 */
		Db::getInstance()->executeS("insert into px_order_remind (id_order,skus,product_name,date,actor)VALUES (1,1,1,'2016-04-05',1)");
		
	}
	
	
	
	public function UpdateOrderRemind($id_remind){
		
		$result = Db::getInstance()->executeS("SELECT
													ore.id_remind,	
													od.product_id,
													od.id_order,
													od.product_name,
													od.product_reference as skus,
													'' as actor,
													now() as date
													FROM
														ps_order_detail od
												
			LEFT JOIN px_order_remind  ore  on  ore.id_order=od.id_order

	WHERE
													od.id_order = $id_order");
	
		return $result;
		
	}
	
	
	
    public function ajaxProcessChangePaymentMethod()
    {
        $customer = new Customer(Tools::getValue('id_customer'));
        $modules = Module::getAuthorizedModules($customer->id_default_group);
        $authorized_modules = array();

        if (!Validate::isLoadedObject($customer) || !is_array($modules)) {
            die(Tools::jsonEncode(array('result' => false)));
        }

        foreach ($modules as $module) {
            $authorized_modules[] = (int)$module['id_module'];
        }

        $payment_modules = array();

        foreach (PaymentModule::getInstalledPaymentModules() as $p_module) {
            if (in_array((int)$p_module['id_module'], $authorized_modules)) {
                $payment_modules[] = Module::getInstanceById((int)$p_module['id_module']);
            }
        }

        $this->context->smarty->assign(array(
            'payment_modules' => $payment_modules,
        ));

        die(Tools::jsonEncode(array(
            'result' => true,
            'view' => $this->createTemplate('_select_payment.tpl')->fetch(),
        )));
    }
	
	
	
	
	//获取当前客户等级  根据订单数目  或者订单总金额  进行判断
	 public function queryCustomerLevel($id_customer){

		$sql = "SELECT  CASE 
		 WHEN amount is null THEN '0'
		 WHEN  amount<500  THEN '0'
		 WHEN amount>=500 and amount<800 THEN '1'
		 WHEN amount>=800 and amount<2000   THEN '2'
		 WHEN amount>=2000 and amount<6000  THEN '3' 
		 WHEN amount>=6000 and amount<20000 THEN '4' 
		 WHEN amount>=20000 THEN '5' 
		 END as mlevel ,
		 amount,
		 CASE 
		 WHEN num is null THEN '0'
		 WHEN num<2 THEN '0'
		 WHEN num >=2 and num <5  THEN '1'
		 WHEN num >=5 and num <8   THEN '2'
		 WHEN num >=8 and num <15  THEN '3' 
		 WHEN num >=15 and num <25  THEN '4' 
		 WHEN num>=25 THEN '5' 
		 END as nlevel ,num  from
		(select mya.id_customer,
		mya.id_order,
		SUM(mya.total_paid_real) as amount,
		count(mya.id_order) as num   from  (
		select a.id_customer,b.id_order,
		b.total_paid_real,
		#c.product_name,
		GROUP_CONCAT(c.product_reference)   as asku
		from  ps_customer  a
		LEFT JOIN  ps_orders b on a.id_customer= b.id_customer and b.current_state in  (4)   and b.total_paid_real >0
		LEFT JOIN  ps_order_detail  c  on b.id_order=  c.id_order  
		where  a.id_customer = '".pSQL($id_customer)."'  
		GROUP BY c.id_order)mya 
		where mya.asku  NOT REGEXP 'UBL|UEL|USG|USE|USAir') myb";
	 
	 
		$res = Db::getInstance()->ExecuteS($sql);
		
		foreach($res as $a){
			
			$level = ($a['mlevel']>$a['nlevel']) ? $a['mlevel'] : $a['nlevel']; 
		}
		
		return $level;
		
	}


	 public function  queryOrderCustomerLevel($id_order){
		$sql="select id_customer from  ps_orders  where  id_order =$id_order ";
		$id_customer = Db::getInstance()->getValue($sql);
		
		$res = $this->queryCustomerLevel($id_customer);
		return  $res ;
		
	}
	
	//更新客户等级 分组信息
	 public function  changeCustomerLevelGroup($id_customer,$level){
		//Diamond25   5星   10
		//Platinum20  4星    8
		//Gold20      3星    9
		
		if($level >2){
			switch ($level)
			{
			case '3':
			  $id_group = '9';
			  break;  
			case '4':
			  $id_group = '8';
			  break;
			case '5':
			  $id_group = '10';
			  break;
			default:
	
			}
			

			$delGroup="delete from  ps_customer_group  where id_customer = $id_customer  and  id_group = $id_group";
			Db::getInstance()->Execute($delGroup);
			$addGroup="INSERT INTO `ps_customer_group` (`id_customer`, `id_group`)VALUES('$id_customer', '$id_group');";
			Db::getInstance()->Execute($addGroup);
			$changeDefaultGroup="update  ps_customer  set  id_default_group  =  '$id_group' where  id_customer = '$id_customer' ";
			Db::getInstance()->Execute($changeDefaultGroup);
			
		}
		
		
	}
	
	 public function  queryOrderOldCustomerLevel($id_order){
		$sql="select id_customer from  ps_orders  where  id_order =$id_order ";
		$id_customer = Db::getInstance()->getValue($sql);
		
		$res = $this->queryCustomerOldLevel($id_customer,$id_order);
		return  $res ;
		
	}
	//获取当前客户等级  根据订单数目  或者订单总金额  进行判断
	 public function queryCustomerOldLevel($id_customer,$id_order){
		$sql = "SELECT  CASE 
		 WHEN amount is null THEN '0'
		 WHEN  amount<500  THEN '0'
		 WHEN amount>=500 and amount<800 THEN '1'
		 WHEN amount>=800 and amount<2000   THEN '2'
		 WHEN amount>=2000 and amount<6000  THEN '3' 
		 WHEN amount>=6000 and amount<20000 THEN '4' 
		 WHEN amount>=20000 THEN '5' 
		 END as mlevel ,
		 amount,
		 CASE 
		 WHEN num is null THEN '0'
		 WHEN num<2 THEN '0'
		 WHEN num >=2 and num <5  THEN '1'
		 WHEN num >=5 and num <8   THEN '2'
		 WHEN num >=8 and num <15  THEN '3' 
		 WHEN num >=15 and num <25  THEN '4' 
		 WHEN num>=25 THEN '5' 
		 END as nlevel ,num  from
		(select mya.id_customer,
		mya.id_order,
		SUM(mya.total_paid_real) as amount,
		count(mya.id_order) as num   from  (
		select a.id_customer,b.id_order,
		b.total_paid_real,
		#c.product_name,
		GROUP_CONCAT(c.product_reference)   as asku
		from  ps_customer  a
		LEFT JOIN  ps_orders b on a.id_customer= b.id_customer and b.current_state in  (4)   and b.total_paid_real >0
		LEFT JOIN  ps_order_detail  c  on b.id_order=  c.id_order  
		where  a.id_customer = '".pSQL($id_customer)."'  
		  and b.id_order != '".pSQL($id_order)."' 
		GROUP BY c.id_order)mya 
		where mya.asku  NOT REGEXP 'UBL|UEL|USG|USE|USAir') myb";
	 
	 
		$res = Db::getInstance()->ExecuteS($sql);
		
		foreach($res as $a){
			
			$level = ($a['mlevel']>$a['nlevel']) ? $a['mlevel'] : $a['nlevel']; 
		}
		
		return $level;
		
	}
	
	
}