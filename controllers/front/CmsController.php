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

class CmsControllerCore extends FrontController
{
    public $php_self = 'cms';
    public $assignCase;
    public $cms;

    /** @var CMSCategory */
    public $cms_category;
    public $ssl = false;

    public function canonicalRedirection($canonicalURL = '')
    {
        if (Tools::getValue('live_edit')) {
            return;
        }
        if (Validate::isLoadedObject($this->cms) && ($canonicalURL = $this->context->link->getCMSLink($this->cms, $this->cms->link_rewrite, $this->ssl))) {
            parent::canonicalRedirection($canonicalURL);
        } elseif (Validate::isLoadedObject($this->cms_category) && ($canonicalURL = $this->context->link->getCMSCategoryLink($this->cms_category))) {
            parent::canonicalRedirection($canonicalURL);
        }
    }

    /**
     * Initialize cms controller
     * @see FrontController::init()
     */
    public function init()
    {
    	if(!empty(Tools::getValue('cms_path'))){
			$res=  Db::getInstance()->getValue("select id_cms from ps_cms_lang where link_rewrite ='".addslashes(Tools::getValue('cms_path'))."'"); 
			if(!empty($res)){
				$_GET['id_cms'] = $res;
			}
    	}elseif(!empty(Tools::getValue('cms_category_link_rewrite'))){
    		$res=  Db::getInstance()->getValue("select id_cms_category from ps_cms_category_lang where link_rewrite ='".addslashes(Tools::getValue('cms_category_link_rewrite'))."'"); 
			if(!empty($res)){
				$_GET['id_cms_category'] = $res;
			}
    	}
    	
        if ($id_cms = (int)Tools::getValue('id_cms')) {
	
            $this->cms = new CMS($id_cms, $this->context->language->id, $this->context->shop->id);	
        } elseif ($id_cms_category = (int)Tools::getValue('id_cms_category')) {
			$this->cms_category = new CMSCategory($id_cms_category, $this->context->language->id, $this->context->shop->id);
	   }

		$this->cms_category = new CMSCategory($id_cms_category, $this->context->language->id, $this->context->shop->id);
        if (Configuration::get('PS_SSL_ENABLED') && Tools::getValue('content_only') && $id_cms && Validate::isLoadedObject($this->cms)
            && in_array($id_cms, array((int)Configuration::get('PS_CONDITIONS_CMS_ID'), (int)Configuration::get('LEGAL_CMS_ID_REVOCATION')))) {
            $this->ssl = true;
        }

        parent::init();
		
	
		
		
		//blog page  rewrite
		if(  (strpos($_SERVER['REQUEST_URI'],'/blog') !==false 
		and (strpos($_SERVER['REQUEST_URI'],'/blog') == 0)  or $_SERVER['REQUEST_URI'] == '')){
			//blog index 
			if($_GET['id_cms'] == '' && $_GET['id_cms_category'] == ''){
				//分类页
				$id_cms_category = Db::getInstance()->getValue('select id_cms_category from  ps_cms_category_lang where link_rewrite like "blog"');
					
				$this->cms_category = new CMSCategory($id_cms_category, $this->context->language->id, $this->context->shop->id);
			}elseif($_GET['id_cms_category']){
				$this->cms_category = new CMSCategory($_GET['id_cms_category'], $this->context->language->id, $this->context->shop->id);
			}else{
				//博客页面
				if($_GET['id_cms']){
					$id_cms = Tools::getValue('id_cms');
					$this->cms = new CMS($id_cms, $this->context->language->id, $this->context->shop->id);	
					
					$comments = Db::getInstance()->ExecuteS("select * from  px_cms_comments where id_cms = $id_cms");
					$this->context->smarty->assign('comments',$comments);
					
					//更新页面浏览数 
					Db::getInstance()->Execute('update  
					ps_cms_lang  set  num  = num+1  where id_cms  =  '.$id_cms);
					
					$num =  Db::getInstance()->getValue('select num from  
					ps_cms_lang where id_cms  =  '.$id_cms);
					
					$this->context->smarty->assign('views',$num);
				}else{
					//blog category  rewrite
					//$id_cms_category = Db::getInstance()->getValue('select id_cms_category from  ps_cms_category_lang where link_rewrite like "'.$_REQUEST['id_cms'].'"');
					if($_GET['id_cms_category']){
						
						$this->cms_category = new CMSCategory($id_cms_category, $this->context->language->id, $this->context->shop->id);
		
					}else{
						 $this->canonicalRedirection();
					}
		
				}
			}
		}else{
			 $this->canonicalRedirection();
		}
      

        // assignCase (1 = CMS page, 2 = CMS category)
        if (Validate::isLoadedObject($this->cms)) {
            $adtoken = Tools::getAdminToken('AdminCmsContent'.(int)Tab::getIdFromClassName('AdminCmsContent').(int)Tools::getValue('id_employee'));
            //if (!$this->cms->isAssociatedToShop() || !$this->cms->active && Tools::getValue('adtoken') != $adtoken) {
            if (!$this->cms->active) {
                header('HTTP/1.1 404 Not Found');
                header('Status: 404 Not Found');
            } else {
                $this->assignCase = 1;
            }
        } elseif (Validate::isLoadedObject($this->cms_category) && $this->cms_category->active) {
            $this->assignCase = 2;
        } else {
            header('HTTP/1.1 404 Not Found');
            header('Status: 404 Not Found');
        }
    }

    public function setMedia()
    {
        parent::setMedia();
		if ($this->useMobileTheme()) {
			$this->addCSS(_THEME_MOBILE_CSS_DIR_.'qietu.css');
        	$this->addCSS(_THEME_MOBILE_CSS_DIR_.'style.css');
    	}
       /* if ($this->assignCase == 1) {
            $this->addJS(_THEME_JS_DIR_.'cms.js');
        }

        $this->addCSS(_THEME_CSS_DIR_.'cms.css');*/
    }

    /**
     * Assign template vars related to page content
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();
		
		Hook::exec('displayFooter');
        parent::initContent();

        if($this->context->shop->theme_name=='uniwigs2016-m'){
             $this->removeJS(array(
                '/js/jquery/plugins/fancybox/jquery.fancybox.js',
                '/js/jquery/plugins/jquery.scrollTo.js',
                '/themes/uniwigs2016-m/js/autoload/15-jquery.uniform-modified.js',
                '/themes/uniwigs2016-m/js/modules/blockwishlist/js/ajax-wishlist.js',
                '/themes/uniwigs2016-m/js/modules/blockcart/ajax-cart.js',
            ));
        }
        
        $this->context->smarty->assign(array(
            'HOOK_HOME' => Hook::exec('displayHome'),
            'HOOK_HOME_TAB' => Hook::exec('displayTopColumn'),
            'HOOK_HOME_TAB_CONTENT' => Hook::exec('displayTop'),
        ));

	/* 	echo '<pre>';
		var_dump($_SERVER['REQUEST_URI']  );
		echo '</pre>';
		die('debug!!!'); */
		
		
        $parent_cat = new CMSCategory(1, $this->context->language->id);
        $this->context->smarty->assign('id_current_lang', $this->context->language->id);
        $this->context->smarty->assign('home_title', $parent_cat->name);
        $this->context->smarty->assign('cgv_id', Configuration::get('PS_CONDITIONS_CMS_ID'));

        $category20 = new Category('40466', '1');
        $products20 = $category20->getProducts('1', 1, 32, null,null);
        $i=0;
        foreach ($products20 as $a){
            $product = new Product($a['id_product']);
            
            $products20[$i]['myimg']=$product->getImages(1);
            //$this->cat_products[$i]['mytest']='aaaa';
            $i+=1;
        }
        $i=0;
		foreach ($this->cat_products as $a){
			$product = new Product($a['id_product']);
			
			$this->cat_products[$i]['myimg']=$product->getImages(1);
			//$this->cat_products[$i]['mytest']='aaaa';
			$i+=1;
		}

        $this->context->smarty->assign('products20', $products20);

		
        if ($this->assignCase == 1) {
            if (isset($this->cms->id_cms_category) && $this->cms->id_cms_category) {
                $path = Tools::getFullPath($this->cms->id_cms_category, $this->cms->meta_title, 'CMS');

               
                   $res=  Db::getInstance()->getValue('select id_parent from ps_cms_category where id_cms_category ='.$this->cms->id_cms_category); 

				if($this->cms->id_cms_category == '9'  ){
					$path =   '<li class="bread-crumb-item"><a href="/blog" data-gg="" title="Hair Blog">Blog</a><span class="icon-right"></span></li>
					<li class="bread-crumb-item">'.$this->cms->meta_title.'<span class="icon-right"></li>';
				}

			    if($res =='9'){
			        $cname=  Db::getInstance()->getValue('select name from ps_cms_category_lang where id_cms_category ='.$this->cms->id_cms_category); 
			  		$link_rewrite=  Db::getInstance()->getValue('select link_rewrite from ps_cms_category_lang where id_cms_category ='.$this->cms->id_cms_category);		
					$path = '<li class="bread-crumb-item"><a href="/blog" data-gg="" title="Hair Blog">Hair Blog</a><span class="icon-right"></span></li>
					<li >'.$this->cms->meta_title.'</li>';
					/*<li class="bread-crumb-item"><a href="/blog'.$link_rewrite.'" data-gg="">'.$cname.'</a><span class="icon-right"></span></li>*/
				}
            } elseif (isset($this->cms_category->meta_title)) {
                $path = Tools::getFullPath(1, $this->cms_category->meta_title, 'CMS');
            }

             if($this->cms->id){
				$meta_title =	Db::getInstance()->getValue('select  meta_title  from 
				 ps_cms_lang  
				where  id_shop=1  and  id_lang =1 and  id_cms  = '.$this->cms->id);
				$description =	Db::getInstance()->getValue('select  meta_description  from 
				 ps_cms_lang  
				where  id_shop=1  and  id_lang =1 and  id_cms  = '.$this->cms->id);

				$this->context->smarty->assign('meta_title', $meta_title);
	            $this->context->smarty->assign('description', $description);
			}else{
                $this->context->smarty->assign('meta_title','');
            }

            $isblog = false;
			if(strpos($_SERVER['REQUEST_URI'],'/blog') !==false or $_SERVER['REQUEST_URI'] == ''  ){
				$isblog = true;
				$this->context->smarty->assign('isblog', true);	
			}else{
				$this->context->smarty->assign('isblog', false);
			}
				
            $this->context->smarty->assign(array(
                'cms' => $this->cms,
                'content_only' => (int)Tools::getValue('content_only'),
                'path' => $path,
                'body_classes' => array($this->php_self.'-'.$this->cms->id, $this->php_self.'-'.$this->cms->link_rewrite)
            ));

            if ($this->cms->indexation == 0) {
                $this->context->smarty->assign('nobots', true);
            }
        } else if ($this->assignCase == 2) {
        	//获取子品类
        	$arrSubCmsCategories = array();
        	if($this->cms_category->id == '9'){
	        	$arrSubCmsCategories = $this->cms_category->getSubCategories($this->context->language->id);

	        	//获取每个品类下面最新的两条博客
	        	if(!empty($arrSubCmsCategories)){
	        		foreach ($arrSubCmsCategories as $key => &$arrChildCategory) {
	        			$pagelist = CMS::getCMSPages($this->context->language->id,$arrChildCategory['id_cms_category'], true, (int)$this->context->shop->id);
	        			if(empty($pagelist)){
	        				unset($arrSubCmsCategories[$key]);
	        			}else{
	        				$arrChildCategory['pagelist'] = count($pagelist)>2?array_slice($pagelist, 0,2):$pagelist;
	        			}
	        		}
	        	}
        	}
        	//这里去掉注释，可以打印出子品类数组
        	//echo '<pre>';
        	//print_r($arrSubCmsCategories);
        	//die();
        	if($_GET['id_cms_category']){
	        	$page_size = 10;
				$pageid = Tools::getValue('page',1);
				$blogpagelist = CMS::getCMSPages($this->context->language->id,$this->cms_category->id, true, (int)$this->context->shop->id,$pageid,$page_size);
				$cms_total = CMS::getCMSPagesCount($this->context->language->id,$this->cms_category->id, true, (int)$this->context->shop->id);

				$pages = ceil($cms_total['total_count']/$page_size);
			}

			/*$parent_category =  Db::getInstance()->getValue('select id_parent from  ps_cms_category where id_cms_category = "'.$this->cms_category->id.'"');
			//isblog 
			if($parent_category == 9){
				 $this->context->smarty->assign('isblog', true);
			}else{
				
				if(
				
				(strpos($_SERVER['REQUEST_URI'],'/blog/') !==false 
				and strpos($_SERVER['REQUEST_URI'],'/blog/') == 0 )

					or  $_SERVER['REQUEST_URI'] == '/' 
				
				){
					 $this->context->smarty->assign('isblog', true);
					
				}else{
					 $this->context->smarty->assign('isblog', false);
					
				}
			}
            
            $pagelist14 = CMS::getCMSPages($this->context->language->id,14, true, (int)$this->context->shop->id);
            $this->context->smarty->assign('pagelist14', $pagelist14);

            $pagelist15 = CMS::getCMSPages($this->context->language->id,15, true, (int)$this->context->shop->id);
            $this->context->smarty->assign('pagelist15', $pagelist15);

            $pagelist16 = CMS::getCMSPages($this->context->language->id,16, true, (int)$this->context->shop->id);
            $this->context->smarty->assign('pagelist16', $pagelist16);

            $pagelist20 = CMS::getCMSPages($this->context->language->id,20, true, (int)$this->context->shop->id);
            $this->context->smarty->assign('pagelist20', $pagelist20);

            $pagelist21 = CMS::getCMSPages($this->context->language->id,21, true, (int)$this->context->shop->id);
            $this->context->smarty->assign('pagelist21', $pagelist21);

			$path =  ($this->cms_category->id !== 1) ? Tools::getPath($this->cms_category->id, $this->cms_category->name, false, 'CMS') : '';
			//分类页面 path 重写
            $newlist = Db::getInstance()->ExecuteS('select  a.id_cms_category,a.date_add,b.*  from  ps_cms a
				LEFT JOIN  ps_cms_lang  b  on  a.id_cms = b.id_cms  and b.id_shop=1 and b.id_lang=1
				LEFT JOIN  ps_cms_category  c on a.id_cms_category = c.id_cms_category
				where  a.id_cms_category  = 9  or  c.id_parent = 9
				#ORDER BY  id_cms desc 
				ORDER BY  id_cms  desc ');
			
			$mostview = Db::getInstance()->ExecuteS('select  a.id_cms_category,a.date_add,b.*  from  ps_cms a
				LEFT JOIN  ps_cms_lang  b  on  a.id_cms = b.id_cms  and b.id_shop=1 and b.id_lang=1
				LEFT JOIN  ps_cms_category  c on a.id_cms_category = c.id_cms_category
				where  a.id_cms_category  = 9  or  c.id_parent = 9
				#ORDER BY  id_cms desc 
				ORDER BY  num  desc ');

			$this->context->smarty->assign('newlist', $newlist);
			$this->context->smarty->assign('mostview', $mostview);*/

	        if($this->cms_category->id){
				
		$meta_title =	Db::getInstance()->getValue('select  meta_title  from 
 ps_cms_category_lang  
where  id_shop=1  and  id_lang =1 and  id_cms_category  = '.$this->cms_category->id);
$description =	Db::getInstance()->getValue('select  meta_description  from 
 ps_cms_category_lang  
where  id_shop=1  and  id_lang =1 and  id_cms_category  = '.$this->cms_category->id);


					$this->context->smarty->assign('meta_title', $meta_title);
                    $this->context->smarty->assign('description', $description);

			}else{

                $this->context->smarty->assign('meta_title','');
            }

			
			if($this->cms_category->id == '9'){
				$path ='<li class="bread-crumb-item">Hair Blog<span class="icon-right"></span></li>';
			}
			if($this->cms_category->id_parent == '9'){
				$path ='<li class="bread-crumb-item"><a href="/blog" data-gg="" title="Hair Blog">Hair Blog</a><span class="icon-right"></span></li>';
				$path .='<li class="bread-crumb-item">'.$this->cms_category->name.'<span class="icon-right"></span></li>';
			}
			
            $this->context->smarty->assign(array(
                'category' => $this->cms_category, //for backward compatibility
                'cms_category' => $this->cms_category,
                'sub_category' => $this->cms_category->getSubCategories($this->context->language->id),
                'cms_pages' =>$blogpagelist ,
                'path' => $path,
                'body_classes' => array($this->php_self.'-'.$this->cms_category->id, $this->php_self.'-'.$this->cms_category->link_rewrite),
                'arrSubCmsCategories'=>$arrSubCmsCategories,
                'pages'=>$pages,
                'pageid'=>$pageid
            ));
        }
		if(
		
		(strpos($_SERVER['REQUEST_URI'],'/blog') !==false  and strpos($_SERVER['REQUEST_URI'],'/blog') == 0  )
		
		or  $_SERVER['REQUEST_URI'] == '/' 
		
		
		){
			 $this->context->smarty->assign('isblog', true);
			
		}else{
			 $this->context->smarty->assign('isblog', false);
			
		}
		
		$this->context->smarty->assign('HOOK_HOME_TAB_CONTENT',Hook::exec('displayTop'));
        $this->setTemplate(_PS_THEME_DIR_.'cms.tpl');
    }
}
