<?php
/*
* 2007-2014 PrestaShop
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
*  @copyright  2007-2014 PrestaShop SA
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class TagControllerCore extends FrontController
{
	public $php_self = 'tag';
	/**
	 * @var Tag
	 */
	public $tag;
	public $ssl = false;

	public function canonicalRedirection($canonicalURL = '')
	{
// 		if (Tools::getValue('live_edit'))
// 			return;
// 		if (Validate::isLoadedObject($this->tag) && ($canonicalURL = $this->context->link->getCMSLink($this->tag, $this->tag->link_rewrite, $this->ssl)))
// 			parent::canonicalRedirection($canonicalURL);
	}

	/**
	 * Initialize cms controller
	 * @see FrontController::init()
	 */
	public function init()
	{
		if ($url_key = Tools::getValue('url_key'))
			$this->tag = new Tag(null, str_ireplace(array('-','+'),' ',$url_key), $this->context->language->id);

		parent::init();

// 		$this->canonicalRedirection();
	}

	public function setMedia()
	{
		parent::setMedia();

// 		if ($this->assignCase == 1)
// 			$this->addJS(_THEME_JS_DIR_.'cms.js');

// 		$this->addCSS(_THEME_CSS_DIR_.'cms.css');
	}

	/**
	 * Assign template vars related to page content
	 * @see FrontController::initContent()
	 */
	public function initContent()
	{
		parent::initContent();

		$this->context->smarty->assign('id_current_lang', $this->context->language->id);
			
		$tagid = $this->tag->id ;
		$tagname = $this->tag->name ;

		if($tagid!=''){
			$res= Db::getInstance()->getRow('select * from px_tag_extra where id_tag='.$tagid);

			if($res){
		
				$catagory =  $res['catagory'];
			} else{
				$catagory = '';
			}
			
			$this->context->smarty->assign('tagid',$tagid);
			$this->context->smarty->assign('tagname',$tagname);
			$this->context->smarty->assign('res',$res);
			$this->context->smarty->assign('catagory',$catagory);
		}
		
		//推送图片
		
		$mydir= $_SERVER['DOCUMENT_ROOT']."/img/tag/".$tagid."/";
		if(file_exists($mydir)){
		
			$files=$this->get_allimg($mydir,$tagid);
			
			/* var_dump($files); */
			if($files!=null){
			
			$this->context->smarty->assign('tagimg', $files);//将次产品目录下图片搜索出来
			}
		 }
		$myproduct = $this->tag->getProductsArray();
	/* 	echo $myproduct[0][0]["id_product"];
		echo $myproduct[0][1]["id_product"];
		 */
		//$myproduct =array();
		$i=0;

		if(count($myproduct)>0){

			if(count($myproduct)>1){
					$b=0;
					foreach($myproduct as $a){
						$c =0;
						foreach($a as $ares){
							$product = new Product($ares['id_product']);
					
							$myproduct[$b][$c]['myimg']=$product->getImages(1);
							if($a['id_product']>0){
								$csql= "select a.id_product ,b.id_attribute,d.`name`,f.`name`  from  ps_product_attribute  a  
		LEFT JOIN  ps_product_attribute_combination  b  on a.id_product_attribute= b.id_product_attribute 
		LEFT JOIN  ps_attribute  c  on b.id_attribute= c.id_attribute 
		LEFT JOIN  ps_attribute_lang  d on c.id_attribute = d.id_attribute and id_lang=1
		LEFT JOIN  ps_attribute_group_lang f on c.id_attribute_group= f.id_attribute_group 
		where id_product =".$a['id_product']."  and  f.`name` = 'Color' and a.available_date = '0000-00-00'
		GROUP BY   c.id_attribute ";
							$imgcolor = Db::getInstance()->ExecuteS($csql);
							$myproduct[0][$i]['mycolorimg']=$imgcolor;
								
								
							}else{
								
								
								$myproduct[0][$i]['mycolorimg']='';
							}
							$c++;
						}
						
						$b++;

					}
				}else{

					foreach ($myproduct[0] as $a){
					$product = new Product($a['id_product']);
					
					$myproduct[0][$i]['myimg']=$product->getImages(1);
					//$myproduct[0][$i]['mytest']='aaaa';
					if($a['id_product']>0){
						$csql= "select a.id_product ,b.id_attribute,d.`name`,f.`name`  from  ps_product_attribute  a  
LEFT JOIN  ps_product_attribute_combination  b  on a.id_product_attribute= b.id_product_attribute 
LEFT JOIN  ps_attribute  c  on b.id_attribute= c.id_attribute 
LEFT JOIN  ps_attribute_lang  d on c.id_attribute = d.id_attribute and id_lang=1
LEFT JOIN  ps_attribute_group_lang f on c.id_attribute_group= f.id_attribute_group 
where id_product =".$a['id_product']."  and  f.`name` = 'Color' and a.available_date = '0000-00-00'
GROUP BY   c.id_attribute ";
					$imgcolor = Db::getInstance()->ExecuteS($csql);
					$myproduct[0][$i]['mycolorimg']=$imgcolor;
						
						
					}else{
						
						
						$myproduct[0][$i]['mycolorimg']='';
					}
					$i++;
				}

			}
			


		}
		

		
		$v1= Context::getContext()->customer->getVoteNum(1);
		$v2= Context::getContext()->customer->getVoteNum(2);
		$v3= Context::getContext()->customer->getVoteNum(3);
		$v4= Context::getContext()->customer->getVoteNum(4);
		$v5= Context::getContext()->customer->getVoteNum(5);
		$v6= Context::getContext()->customer->getVoteNum(6);
	 
		$vmessage= Context::getContext()->customer->getVoteMessage();
		$vmessage2= Context::getContext()->customer->getVoteMessage2();
		$id_customer= Context::getContext()->customer->id;
		
		
		
		if($this->tag->name == 'uniwigs trendy pink diamond lace front wig'){
		$insReview =   Db::getInstance()->ExecuteS("select * from pl where  (content is not  null  or content !='' ) and author not like \"%@%\" ");	
			
		}else{
		$insReview = '';	
		}

		if($this->tag->name == 'uniwigs new arrival ombre bambi wig'){
		$insReview2 =   Db::getInstance()->ExecuteS("select * from pl2 where  (content is not  null  or content !='' ) and author not like \"%@%\" ");	
			
		}else{
		$insReview2 = '';
		}

		if($this->tag->name == 'uniwigs new arrival ombre sirens song wig'){
		$insReview3 =   Db::getInstance()->ExecuteS("select * from pl3 where  (content is not  null  or content !='' ) and author not like \"%@%\" ");	
			
		}else{
		$insReview3 = '';
		}

		if($this->tag->name == 'uniwigs new arrival ombre magical mermaid wig'){
		$insReview4 =   Db::getInstance()->ExecuteS("select * from pl4 where  (content is not  null  or content !='' ) and author not like \"%@%\" ");	
			
		}else{
		$insReview4 = '';
		}
	/* 	echo '<pre>';
		var_dump($this->tag->name);
		echo '</pre>';
		echo $insReview;
		exit; */
		$this->context->smarty->assign(array(
			'tag' => $this->tag,
			'content_only' => (int)Tools::getValue('content_only'),
			'insReview' =>$insReview,
			'insReview2' =>$insReview2,
			'insReview3' =>$insReview3,
			'insReview4' =>$insReview4,
			'path' => $this->tag->name,
			'products' => $myproduct,
			'v1' => $v1[0]['num'],
			'v2' => $v2[0]['num'],
			'v3' => $v3[0]['num'],
			'v4' => $v4[0]['num'],
			'v5' => $v5[0]['num'],
			'v6' => $v6[0]['num'],
			'vmessage' => $vmessage,
			'vmessage2' => $vmessage2,
			'id_customer' => $id_customer,
		));
		
		$template = 'tag.tpl';
		if ($this->tag->getTemplate()) {
		
			$template = $this->tag->getTemplate();

           $weburl=$_SERVER['HTTP_HOST'];

		   if($weburl=="m.uniwigs.com"){
		   $template=str_replace('.html','-mobile.html',$template);
		   }
		   if($weburl=="lavivid.uniwigs.com"){
		   $template=str_replace('.html','-lavivid.html',$template);
		   }
		   
			if(!file_exists(_PS_THEME_DIR_.$template))
			{
			$template="404.tpl";
			}

			
		}
	
		$this->context->smarty->assign('taglink',$this->tag->getTagLink());
		$this->setTemplate(_PS_THEME_DIR_.$template);

		$this->getTagBanners();
	}
	
	//获取制定目录下图片路径
	public function get_allimg($dir,$id){
	
	$handler = opendir($dir);
    while (($filename = readdir($handler)) !== false) 
	{
        if ($filename != "." && $filename != "..") 
			{
                $files[] = "/"."img/tag/".$id."/".$filename ;
           }
       }
   
    closedir($handler);
	//判断此目录下 是否有文件存在
	if(isset($files)){
		return $files;
	}	
	else{
		return null;
	}
	}
	
	
	private function getTagBanners()
	{
		$key = "tag_banners_".$this->tag->id;
		if (!Cache::getInstance()->exists($key)) {
			$tag_banners = array();
			$tag_rewrite_link = $this->tag->getRewriteLink();
			if ($tag_rewrite_link) {
				if (is_file(_PS_IMG_DIR_."tbanners/".$tag_rewrite_link.".jpg")) {
					$tag_banners[] = _PS_IMG_."tbanners/".$tag_rewrite_link.".jpg";
				}
				if (is_file(_PS_IMG_DIR_."tbanners/".$tag_rewrite_link."_0.jpg")) {
					$tag_banners[] = _PS_IMG_."tbanners/".$tag_rewrite_link."_0.jpg";
				}
				for ($bind=1;$bind<6;$bind++) {
					if (is_file(_PS_IMG_DIR_."tbanners/".$tag_rewrite_link."_$bind.jpg")) {
						$tag_banners[] = _PS_IMG_."tbanners/".$tag_rewrite_link."_$bind.jpg";
					} else {
						break;
					}
				}
			}
			Cache::getInstance()->set($key, $tag_banners, 86400*7);
		}
		$tag_banners = Cache::getInstance()->get($key);
		$this->context->smarty->assign("tag_banners", $tag_banners);
	}
	
	
	private function getCatagory()
	{
		$tagname = $this->tag;
	
	}
	
}
