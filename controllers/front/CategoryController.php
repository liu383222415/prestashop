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

class CategoryControllerCore extends FrontController
{
    /** string Internal controller name */
    public $php_self = 'category';

    /** @var Category Current category object */
    protected $category;

    /** @var bool If set to false, customer cannot view the current category. */
    public $customer_access = true;

    /** @var int Number of products in the current page. */
    protected $nbProducts;

    /** @var array Products to be displayed in the current page . */
    protected $cat_products;

    /**
     * Sets default medias for this controller
     */
    public function setMedia()
    {
        parent::setMedia();

        if (!$this->useMobileTheme()) {
            //TODO : check why cluetip css is include without js file
            $this->addCSS(array(
                _THEME_CSS_DIR_.'scenes.css'       => 'all',
                _THEME_CSS_DIR_.'category.css'     => 'all',
                _THEME_CSS_DIR_.'product_list.css' => 'all',
            ));
        }else{
            $this->addCSS(_THEME_MOBILE_CSS_DIR_.'qietu.css');
            $this->addCSS(_THEME_MOBILE_CSS_DIR_.'style.css');
        }

        $scenes = Scene::getScenes($this->category->id, $this->context->language->id, true, false);
        if ($scenes && count($scenes)) {
            $this->addJS(_THEME_JS_DIR_.'scenes.js');
            $this->addJqueryPlugin(array('scrollTo', 'serialScroll'));
        }

        $this->addJS(_THEME_JS_DIR_.'category.js');


    }

    /**
     * Redirects to canonical or "Not Found" URL
     *
     * @param string $canonical_url
     */
    public function canonicalRedirection($canonical_url = '')
    {
        if (Tools::getValue('live_edit')) {
            return;
        }

        if (!Validate::isLoadedObject($this->category) || !$this->category->inShop() || !$this->category->isAssociatedToShop() || in_array($this->category->id, array(Configuration::get('PS_HOME_CATEGORY'), Configuration::get('PS_ROOT_CATEGORY')))) {
            $this->redirect_after = '404';
            $this->redirect();
        }

        if (!Tools::getValue('noredirect') && Validate::isLoadedObject($this->category)) {
            //parent::canonicalRedirection($this->context->link->getCategoryLink($this->category));
        }
    }

    /**
     * Initializes controller
     *
     * @see FrontController::init()
     * @throws PrestaShopException
     */
    public function init()
    {
        // Get category ID
        $id_category = (int)Tools::getValue('id_category');
	   echo "<!--base:$id_category-->";
        if (!$id_category || !Validate::isUnsignedId($id_category)) {
            $this->errors[] = Tools::displayError('Missing category ID');
        }

        // Instantiate category
        $this->category = new Category($id_category, $this->context->language->id);

        parent::init();

        // Check if the category is active and return 404 error if is disable.
        if (!$this->category->active) {
            header('HTTP/1.1 404 Not Found');
            header('Status: 404 Not Found');
        }

        // Check if category can be accessible by current customer and return 403 if not
        if (!$this->category->checkAccess($this->context->customer->id)) {
            header('HTTP/1.1 403 Forbidden');
            header('Status: 403 Forbidden');
            $this->errors[] = Tools::displayError('You do not have access to this category.');
            $this->customer_access = false;
        }
    }

    /**
     * Initializes page content variables
     */
    public function initContent()
    {
        parent::initContent();

        $this->setTemplate(_PS_THEME_DIR_.'category.tpl');

        if (!$this->customer_access) {
            return;
        }

        if (isset($this->context->cookie->id_compare)) {
            $this->context->smarty->assign('compareProducts', CompareProduct::getCompareProducts((int)$this->context->cookie->id_compare));
        }

        // Product sort must be called before assignProductList()
        $this->productSort();

        $this->assignScenes();
        $this->assignSubcategories();
        $this->assignProductList();



		if($this->context->shop->theme_name=='uniwigs2016-m'){
             $this->removeJS(array(
                '/js/jquery/plugins/fancybox/jquery.fancybox.js',
                '/js/jquery/plugins/jquery.scrollTo.js',
                '/themes/uniwigs2016-m/js/autoload/15-jquery.uniform-modified.js',
                '/themes/uniwigs2016-m/js/modules/blockwishlist/js/ajax-wishlist.js',
                '/themes/uniwigs2016-m/js/modules/blockcart/ajax-cart.js',
            ));
             $this->removeCSS(array(
                '/js/jquery/plugins/fancybox/jquery.fancybox.css',
                '/themes/uniwigs2016-m/css/modules/blockcart/blockcart.css',
                '/themes/uniwigs2016-m/css/autoload/uniform.default.css',
                '/themes/uniwigs2016-m/css/modules/blockwishlist/blockwishlist.css',
                '/js/jquery/plugins/bxslider/jquery.bxslider.css',
            ));
        }



		// echo '<pre>';
		// var_dump($this->cat_products);
		// echo '</pre>';
		// exit;
        $this->context->smarty->assign(array(
            'category'             => $this->category,
            'description_short'    => Tools::truncateString($this->category->description, 350),
            'products'             => (isset($this->cat_products) && $this->cat_products) ? $this->cat_products : null,
            'id_category'          => (int)$this->category->id,
            'id_category_parent'   => (int)$this->category->id_parent,
            'return_category_name' => Tools::safeOutput($this->category->name),
            'path'                 => Tools::getPath($this->category->id),
            'add_prod_display'     => Configuration::get('PS_ATTRIBUTE_CATEGORY_DISPLAY'),
            'categorySize'         => Image::getSize(ImageType::getFormatedName('category')),
            'mediumSize'           => Image::getSize(ImageType::getFormatedName('medium')),
            'thumbSceneSize'       => Image::getSize(ImageType::getFormatedName('m_scene')),
            'homeSize'             => Image::getSize(ImageType::getFormatedName('home')),
            'allow_oosp'           => (int)Configuration::get('PS_ORDER_OUT_OF_STOCK'),
            'comparator_max_item'  => (int)Configuration::get('PS_COMPARATOR_MAX_ITEM'),
            'suppliers'            => Supplier::getSuppliers(),
            'CATEGORY_FILTER'=>Hook::exec('categoryFilter'),
            'body_classes'         => array($this->php_self.'-'.$this->category->id, $this->php_self.'-'.$this->category->link_rewrite),
            'HOOK_HOME_TAB_CONTENT' => Hook::exec('displayTop')
        ));
    }

    /**
     * Assigns scenes template variables
     */
    protected function assignScenes()
    {
        // Scenes (could be externalised to another controller if you need them)
        $scenes = Scene::getScenes($this->category->id, $this->context->language->id, true, false);
        $this->context->smarty->assign('scenes', $scenes);

        // Scenes images formats
        if ($scenes && ($scene_image_types = ImageType::getImagesTypes('scenes'))) {
            foreach ($scene_image_types as $scene_image_type) {
                if ($scene_image_type['name'] == ImageType::getFormatedName('m_scene')) {
                    $thumb_scene_image_type = $scene_image_type;
                } elseif ($scene_image_type['name'] == ImageType::getFormatedName('scene')) {
                    $large_scene_image_type = $scene_image_type;
                }
            }

            $this->context->smarty->assign(array(
                'thumbSceneImageType' => isset($thumb_scene_image_type) ? $thumb_scene_image_type : null,
                'largeSceneImageType' => isset($large_scene_image_type) ? $large_scene_image_type : null,
            ));
        }
    }

    /**
     * Assigns subcategory templates variables
     */
    protected function assignSubcategories()
    {
        if ($sub_categories = $this->category->getSubCategories($this->context->language->id)) {
            $this->context->smarty->assign(array(
                'subcategories'          => $sub_categories,
                'subcategories_nb_total' => count($sub_categories),
                'subcategories_nb_half'  => ceil(count($sub_categories) / 2)
            ));
        }
    }

    /**
     * Assigns product list template variables
     */
    public function assignProductList()
    {
        $hook_executed = false;


		if(strpos($_SERVER['REQUEST_URI'] ,'/103-hair-extensions') !== false){


		}else{

			Hook::exec('actionProductListOverride', array(
				'nbProducts'   => &$this->nbProducts,
				'catProducts'  => &$this->cat_products,
				'hookExecuted' => &$hook_executed,
			));

		}
        // The hook was not executed, standard working
        if (!$hook_executed) {
            $this->context->smarty->assign('categoryNameComplement', '');
            $this->nbProducts = $this->category->getProducts(null, null, null, $this->orderBy, $this->orderWay, true);
            $this->pagination((int)$this->nbProducts); // Pagination must be call after "getProducts"
            $this->cat_products = $this->category->getProducts($this->context->language->id, (int)$this->p, (int)$this->n, $this->orderBy, $this->orderWay);
        }
        // Hook executed, use the override
        else {
            // Pagination must be call after "getProducts"
            $this->pagination($this->nbProducts);
        }

        $this->addColorsToProductList($this->cat_products);


        Hook::exec('actionProductListModifier', array(
            'nb_products'  => &$this->nbProducts,
            'cat_products' => &$this->cat_products,
        ));

        foreach ($this->cat_products as &$product) {
            if (isset($product['id_product_attribute']) && $product['id_product_attribute'] && isset($product['product_attribute_minimal_quantity'])) {
                $product['minimal_quantity'] = $product['product_attribute_minimal_quantity'];
            }
        }

/* 		if(isset($_GET['admintest'])){
			echo  '----------------'.'<br>';
			foreach($this->cat_products as $a){


				echo  $a['id_product'].'<br>';
			}

			die('debug');
		} */

        $this->context->smarty->assign('nb_products', $this->nbProducts);
    }

    /**
     * Returns an instance of the current category
     *
     * @return Category
     */
    public function getCategory()
    {
        return $this->category;
    }
}
