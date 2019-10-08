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
            parent::canonicalRedirection($this->context->link->getCategoryLink($this->category));
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

        $category1 = new Category('40447', '1');
        $products1 = $category1->getProducts('1', 1, 18, null,null);
		
		$i=0;
		if($products1){
		foreach ($products1 as $a){
			$product = new Product($a['id_product']);
			
			$products1[$i]['myimg']=$product->getImages(1);
			//$this->cat_products[$i]['mytest']='aaaa';
			$i+=1;
		}
		}
        $category2 = new Category('40448', '1');
        $products2 = $category2->getProducts('1', 1, 18, null,null);
		
		$i=0;
		if($products2){
		foreach ($products2 as $a){
			$product = new Product($a['id_product']);
			
			$products2[$i]['myimg']=$product->getImages(1);
			//$this->cat_products[$i]['mytest']='aaaa';
			$i+=1;
		}
		}
		

        $category3 = new Category('40446', '1');
        $products3 = $category3->getProducts('1', 1, 18, null,null);
		$i=0;
		if($products3){
		foreach ($products3 as $a){
			$product = new Product($a['id_product']);
			
			$products3[$i]['myimg']=$product->getImages(1);
			//$this->cat_products[$i]['mytest']='aaaa';
			$i+=1;
		}
		}
        $category4 = new Category('40450', '1');
        $products4 = $category4->getProducts('1', 1, 18, null,null);
		$i=0;
		if($products4){
		foreach ($products4 as $a){
			$product = new Product($a['id_product']);
			
			$products4[$i]['myimg']=$product->getImages(1);
			//$this->cat_products[$i]['mytest']='aaaa';
			$i+=1;
		}
		}

        $category5 = new Category('40458', '1');
        $products5 = $category5->getProducts('1', 1, 8, null,null);
        $i=0;
		if($products5){
        foreach ($products5 as $a){
            $product = new Product($a['id_product']);
            
            $products5[$i]['myimg']=$product->getImages(1);
            //$this->cat_products[$i]['mytest']='aaaa';
            $i+=1;
        }
		}
        $products6 = $category5->getProducts('1', 1, 18, null,null);
        $i=0;
		if($products6){
        foreach ($products6 as $a){
            $product = new Product($a['id_product']);
            
            $products6[$i]['myimg']=$product->getImages(1);
            //$this->cat_products[$i]['mytest']='aaaa';
            $i+=1;
        }
		}

        $category7 = new Category('40453', '1');
        $products7 = $category7->getProducts('1', 1, 18, null,null);
        $i=0;
		if($products7){
        foreach ($products7 as $a){
            $product = new Product($a['id_product']);
            
            $products7[$i]['myimg']=$product->getImages(1);
            //$this->cat_products[$i]['mytest']='aaaa';
            $i+=1;
        }
		}
        $products71 = $category7->getProducts('1', 1, 8, null,null);
        $i=0;
		if($products71){
        foreach ($products71 as $a){
            $product = new Product($a['id_product']);
            
            $products71[$i]['myimg']=$product->getImages(1);
            //$this->cat_products[$i]['mytest']='aaaa';
            $i+=1;
        }
		}
        $category8 = new Category('40452', '1');
        $products8 = $category8->getProducts('1', 1, 18, null,null);
        $i=0;
		if($products8){
        foreach ($products8 as $a){
            $product = new Product($a['id_product']);
            
            $products8[$i]['myimg']=$product->getImages(1);
            //$this->cat_products[$i]['mytest']='aaaa';
            $i+=1;
        }
		}
        $products81 = $category8->getProducts('1', 1, 8, null,null);
        $i=0;
		if($products81){
        foreach ($products81 as $a){
            $product = new Product($a['id_product']);
            
            $products81[$i]['myimg']=$product->getImages(1);
            //$this->cat_products[$i]['mytest']='aaaa';
            $i+=1;
        }
		}
        $category9 = new Category('40449', '1');
        $products9 = $category9->getProducts('1', 1, 18, null,null);
        $i=0;
		if($products9){
        foreach ($products9 as $a){
            $product = new Product($a['id_product']);
            
            $products9[$i]['myimg']=$product->getImages(1);
            //$this->cat_products[$i]['mytest']='aaaa';
            $i+=1;
        }
		}
        $products91 = $category9->getProducts('1', 1, 4, null,null);
        $i=0;
		if($products91){
        foreach ($products91 as $a){
            $product = new Product($a['id_product']);
            
            $products91[$i]['myimg']=$product->getImages(1);
            //$this->cat_products[$i]['mytest']='aaaa';
            $i+=1;
        }
		}
        $category10 = new Category('108', '1');
        $products10 = $category10->getProducts('1', 1, 8, null,null);
        $i=0;
		if($products10){
        foreach ($products10 as $a){
            $product = new Product($a['id_product']);
            
            $products10[$i]['myimg']=$product->getImages(1);
            //$this->cat_products[$i]['mytest']='aaaa';
            $i+=1;
        }
		}
        $category11 = new Category('40439', '1');
        $products11 = $category11->getProducts('1', 1, 8, null,null);
        $i=0;
		if($products11){
        foreach ($products11 as $a){
            $product = new Product($a['id_product']);
            
            $products11[$i]['myimg']=$product->getImages(1);
            //$this->cat_products[$i]['mytest']='aaaa';
            $i+=1;
        }}

        $category12 = new Category('101', '1');
        $products12 = $category12->getProducts('1', 1, 16, null,null);
        $i=0;
		if($products12){
        foreach ($products12 as $a){
            $product = new Product($a['id_product']);
            
            $products12[$i]['myimg']=$product->getImages(1);
            //$this->cat_products[$i]['mytest']='aaaa';
            $i+=1;
        }}

        $category13 = new Category('40468', '1');
        $products13 = $category13->getProducts('1', 1, 4, null,null);
        $i=0;
		if($products13){
        foreach ($products13 as $a){
            $product = new Product($a['id_product']);
            
            $products13[$i]['myimg']=$product->getImages(1);
            //$this->cat_products[$i]['mytest']='aaaa';
            $i+=1;
        }}
        $category14 = new Category('40475', '1');
        $products14 = $category14->getProducts('1', 1, 16, null,null);
        $i=0;

		if($products14){
			 foreach ($products14 as $a){
            $product = new Product($a['id_product']);
            
				$products14[$i]['myimg']=$product->getImages(1);
				//$this->cat_products[$i]['mytest']='aaaa';
				$i+=1;
			}
			
		}
       
        $category15 = new Category('40476', '1');
        $products15 = $category15->getProducts('1', 1, 16, null,null);
        $i=0;
		if($products15){
        foreach ($products15 as $a){
            $product = new Product($a['id_product']);
            
            $products15[$i]['myimg']=$product->getImages(1);
            //$this->cat_products[$i]['mytest']='aaaa';
            $i+=1;
        }}
        $category16 = new Category('40477', '1');
        $products16 = $category16->getProducts('1', 1, 16, null,null);
        $i=0;
		if($products16){
        foreach ($products16 as $a){
            $product = new Product($a['id_product']);
            
            $products16[$i]['myimg']=$product->getImages(1);
            //$this->cat_products[$i]['mytest']='aaaa';
            $i+=1;
        }}
        $category17 = new Category('40478', '1');
        $products17 = $category17->getProducts('1', 1, 16, null,null);
        $i=0;
		if($products17){
        foreach ($products17 as $a){
            $product = new Product($a['id_product']);
            
            $products17[$i]['myimg']=$product->getImages(1);
            //$this->cat_products[$i]['mytest']='aaaa';
            $i+=1;
        }}
        $category18 = new Category('40479', '1');
        $products18 = $category18->getProducts('1', 1, 16, null,null);
        $i=0;
		if($products18){
        foreach ($products18 as $a){
            $product = new Product($a['id_product']);
            
            $products18[$i]['myimg']=$product->getImages(1);
            //$this->cat_products[$i]['mytest']='aaaa';
            $i+=1;
        }}
        $category19 = new Category('40480', '1');
        $products19 = $category19->getProducts('1', 1, 16, null,null);
        $i=0;
		if($products19){
        foreach ($products19 as $a){
            $product = new Product($a['id_product']);
            
            $products19[$i]['myimg']=$product->getImages(1);
            //$this->cat_products[$i]['mytest']='aaaa';
            $i+=1;
        }}
        $category20 = new Category('40471', '1');
        $products20 = $category20->getProducts('1', 1, 16, null,null);
        $i=0;
		if($products20){
        foreach ($products20 as $a){
            $product = new Product($a['id_product']);
            
            $products20[$i]['myimg']=$product->getImages(1);
            //$this->cat_products[$i]['mytest']='aaaa';
            $i+=1;
        }}
        $category21 = new Category('40472', '1');
        $products21 = $category21->getProducts('1', 1, 16, null,null);
        $i=0;
		if($products21){
        foreach ($products21 as $a){
            $product = new Product($a['id_product']);
            
            $products21[$i]['myimg']=$product->getImages(1);
            //$this->cat_products[$i]['mytest']='aaaa';
            $i+=1;
        }}
        $category22 = new Category('40473', '1');
        $products22 = $category22->getProducts('1', 1, 16, null,null);
        $i=0;
		if($products22){
        foreach ($products22 as $a){
            $product = new Product($a['id_product']);
            
            $products22[$i]['myimg']=$product->getImages(1);
            //$this->cat_products[$i]['mytest']='aaaa';
            $i+=1;
        }}
        $category23 = new Category('40474', '1');
        $products23 = $category23->getProducts('1', 1, 16, null,null);
        $i=0;
		if($products23){
        foreach ($products23 as $a){
            $product = new Product($a['id_product']);
            
            $products23[$i]['myimg']=$product->getImages(1);
            //$this->cat_products[$i]['mytest']='aaaa';
            $i+=1;
        }}
        $category24 = new Category('40470', '1');
        $products24 = $category24->getProducts('1', 1, 4, null,null);
        $i=0;
		if($products24){
        foreach ($products24 as $a){
            $product = new Product($a['id_product']);
            
            $products24[$i]['myimg']=$product->getImages(1);
            //$this->cat_products[$i]['mytest']='aaaa';
            $i+=1;
        }}
		//增加产品列表页面多张图片推送
		$i=0;
		foreach ($this->cat_products as $a){
			$product = new Product($a['id_product']);
			
			$this->cat_products[$i]['myimg']=$product->getImages(1);
			//$this->cat_products[$i]['mytest']='aaaa';
			$i+=1;
		}
		
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
		
		
		
/* 		echo '<pre>';
		var_dump($products4);
		echo '</pre>';
		exit; */
        $this->context->smarty->assign(array(
            'category'             => $this->category,
            'description_short'    => Tools::truncateString($this->category->description, 350),
            'products'             => (isset($this->cat_products) && $this->cat_products) ? $this->cat_products : null,
            'products1'            => $products1,
            'products2'            => $products2,
            'products3'            => $products3,
            'products4'            => $products4,
            'products5'            => $products5,
            'products6'            => $products6,
            'products7'            => $products7,
            'products71'            => $products71,
            'products8'            => $products8,
            'products81'            => $products81,
            'products9'            => $products9,
            'products91'            => $products91,
            'products10'            => $products10,
            'products11'            => $products11,
            'products12'            => $products12,
            'products13'            => $products13,
            'products14'            => $products14,
            'products15'            => $products15,
            'products16'            => $products16,
            'products17'            => $products17,
            'products18'            => $products18,
            'products19'            => $products19,
            'products20'            => $products20,
            'products21'            => $products21,
            'products22'            => $products22,
            'products23'            => $products23,
            'products24'            => $products24,
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
            'body_classes'         => array($this->php_self.'-'.$this->category->id, $this->php_self.'-'.$this->category->link_rewrite)
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
