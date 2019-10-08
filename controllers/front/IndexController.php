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

class IndexControllerCore extends FrontController
{
    public $php_self = 'index';
    /**
     * Assign template vars related to page content
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        Hook::exec('displayFooter');
        parent::initContent();
        $this->addJS(_THEME_JS_DIR_.'index.js');
        $this->addJS(_THEME_JS_DIR_.'ckplayer/ckplayer.min.js');
        if($this->context->shop->theme_name=='uniwigs2016-m'){
             $this->removeJS(array(
                '/js/jquery/plugins/fancybox/jquery.fancybox.js',
                '/js/jquery/plugins/jquery.scrollTo.js',
                '/themes/uniwigs2016-m/js/autoload/15-jquery.uniform-modified.js',
                '/themes/uniwigs2016-m/js/modules/blockwishlist/js/ajax-wishlist.js',
                '/themes/uniwigs2016-m/js/modules/blockcart/ajax-cart.js',
                ));
            $this->addCSS(_THEME_MOBILE_CSS_DIR_.'qietu.css');
            $this->addCSS(_THEME_MOBILE_CSS_DIR_.'style.css');
        }
        if($this->context->shop->theme_name=='uniwigs2016'){
             $this->removeJS(array(
                '/js/jquery/plugins/fancybox/jquery.fancybox.js',
                '/js/jquery/plugins/jquery.scrollTo.js',
                '/themes/uniwigs2016/js/autoload/15-jquery.uniform-modified.js',
                '/themes/uniwigs2016/js/modules/blockwishlist/js/ajax-wishlist.js'
            ));
             $this->removeCSS(array(
                '/js/jquery/plugins/fancybox/jquery.fancybox.css',
                '/themes/uniwigs2016/css/autoload/uniform.default.css',
                '/themes/uniwigs2016/css/modules/blockwishlist/blockwishlist.css',
            ));
             $this->addCSS(_THEME_CSS_DIR_.'index.css');
        }
        $this->context->smarty->assign(array(
            'HOOK_HOME' => Hook::exec('displayHome'),
            'HOOK_HOME_TAB' => Hook::exec('displayTopColumn'),
            'HOOK_HOME_TAB_CONTENT' => Hook::exec('displayTop'),
        ));
        $this->setTemplate(_PS_THEME_DIR_.'index.tpl');
    }
}
