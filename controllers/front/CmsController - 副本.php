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
		if(strpos($_SERVER['REQUEST_URI'],'/blog/') !==false  and strpos($_SERVER['REQUEST_URI'],'/blog/') == 0){
			
			//blog index 
			if($_REQUEST['id_cms'] == ''){
				
				//分类页
				$id_cms_category = Db::getInstance()->getValue('select id_cms_category from  ps_cms_category_lang where link_rewrite like "blog"');
					
				$this->cms_category = new CMSCategory($id_cms_category, $this->context->language->id, $this->context->shop->id);
			
				
				
			}else{
				
				//博客页面
				$id_cms = Db::getInstance()->getValue('select id_cms from  ps_cms_lang where link_rewrite like "'.str_replace('.html','',$_REQUEST['id_cms']).'"');
			
				if($id_cms){
					
					
					$this->cms = new CMS($id_cms, $this->context->language->id, $this->context->shop->id);	
					
					$comments = Db::getInstance()->ExecuteS("select * from  px_cms_comments where id_cms = $id_cms");
					$this->context->smarty->assign('comments',$comments);
					
					//更新页面浏览数 
					
					
					
					Db::getInstance()->Execute('update  
					ps_cms_lang  set  num  = num+1  where id_cms  =  '.$id_cms);
					
					$num =  Db::getInstance()->getValue('select num from  
					ps_cms_lang where id_cms  =  '.$id_cms);
					
					$this->context->smarty->assign('views',$num);
	/* 				echo '<pre>';
					var_dump($num);
					echo '</pre>';
					die('debug!!!'); */
					
					
				}else{
					//blog category  rewrite
					$id_cms_category = Db::getInstance()->getValue('select id_cms_category from  ps_cms_category_lang where link_rewrite like "'.$_REQUEST['id_cms'].'"');
					
					
					if($id_cms_category){
						
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
            if (!$this->cms->isAssociatedToShop() || !$this->cms->active && Tools::getValue('adtoken') != $adtoken) {
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

        if ($this->assignCase == 1) {
            $this->addJS(_THEME_JS_DIR_.'cms.js');
        }

        $this->addCSS(_THEME_CSS_DIR_.'cms.css');
    }

    /**
     * Assign template vars related to page content
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

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
				
					$path =   '<span class="navigation_end"><a href="/blog" data-gg="">blog</a></span>
					<span class="navigation-pipe">></span> <span class="navigation_product">'.$this->cms->meta_title.'</span>';

				} 

    if(  $res =='9'){       


        $cname=  Db::getInstance()->getValue('select name from ps_cms_category_lang where id_cms_category ='.$this->cms->id_cms_category);    
$path = '
<span class="navigation_end"><a href="/blog" data-gg="">blog</a><span class="navigation-pipe">&gt;</span><a href="/blog/'.$cname.'" data-gg="">'.$cname.'</a></span><span class="navigation-pipe">&gt;</span> <span class="navigation_product">'.$this->cms->meta_title.'</span>';

	} 

              

            } elseif (isset($this->cms_category->meta_title)) {
                $path = Tools::getFullPath(1, $this->cms_category->meta_title, 'CMS');

         

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
        } elseif ($this->assignCase == 2) {
			$pagelist = CMS::getCMSPages($this->context->language->id,$this->cms_category->id, true, (int)$this->context->shop->id);


			$parent_category =  Db::getInstance()->getValue('select id_parent from  ps_cms_category where id_cms_category = "'.$this->cms_category->id.'"');
			//isblog 
			if($parent_category == 9){
			
				 $this->context->smarty->assign('isblog', true);
				
				 
			}else{
				
				if(strpos($_SERVER['REQUEST_URI'],'/blog/') !==false  and strpos($_SERVER['REQUEST_URI'],'/blog/') == 0){
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
			$path =  ($this->cms_category->id !== 1) ? Tools::getPath($this->cms_category->id, $this->cms_category->name, false, 'CMS') : '';
			
			$newlist = Db::getInstance()->ExecuteS('select  a.id_cms_category,b.*  from  ps_cms a
LEFT JOIN  ps_cms_lang  b  on  a.id_cms = b.id_cms  and b.id_shop=1 and b.id_lang=1
LEFT JOIN  ps_cms_category  c on a.id_cms_category = c.id_cms_category
where  a.id_cms_category  = 9  or  c.id_parent = 9
#ORDER BY  id_cms desc 
ORDER BY  id_cms  desc ');
			
			$mostview = Db::getInstance()->ExecuteS('select  a.id_cms_category,b.*  from  ps_cms a
LEFT JOIN  ps_cms_lang  b  on  a.id_cms = b.id_cms  and b.id_shop=1 and b.id_lang=1
LEFT JOIN  ps_cms_category  c on a.id_cms_category = c.id_cms_category
where  a.id_cms_category  = 9  or  c.id_parent = 9
#ORDER BY  id_cms desc 
ORDER BY  num  desc ');

			
			$this->context->smarty->assign('newlist', $newlist);
			$this->context->smarty->assign('mostview', $mostview);
			
			//分类页面 path 重写
			
			if($this->cms_category->id == '9'){
				
				
				$path ='';
				
			}
			if($this->cms_category->id_parent == '9'){
				
				
				$path ='<span class="navigation_end"><a href="/blog" data-gg="">blog</a>
				<span class="navigation-pipe">></span>'.$this->cms_category->name.'</span>';
				
			}
			

	
            $this->context->smarty->assign(array(
                'category' => $this->cms_category, //for backward compatibility
                'cms_category' => $this->cms_category,
                'sub_category' => $this->cms_category->getSubCategories($this->context->language->id),
                'cms_pages' =>$pagelist ,
                'path' => $path,
                'body_classes' => array($this->php_self.'-'.$this->cms_category->id, $this->php_self.'-'.$this->cms_category->link_rewrite)
            ));
        }
		if(strpos($_SERVER['REQUEST_URI'],'/blog/') !==false  and strpos($_SERVER['REQUEST_URI'],'/blog/') == 0){
			 $this->context->smarty->assign('isblog', true);
			
		}else{
			 $this->context->smarty->assign('isblog', false);
			
		}
		
		
        $this->setTemplate(_PS_THEME_DIR_.'cms.tpl');
    }
}
