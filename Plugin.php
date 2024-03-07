<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 标签自动生成插件
 * 
 * @package AutoTags
 * @author DT27
 * @version 2.0.0
 * @link https://dt27.cn/php/autotags-for-typecho/
 */
class AutoTags_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->write = array('AutoTags_Plugin', 'write');

    }
    
    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate(){}
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {

        $isActive = new Typecho_Widget_Helper_Form_Element_Radio('isActive',
            array(
                '1' => '是',
                '0' => '否',
            ),'1', _t('是否启用标签自动提取功能'), _t('自动提取功能在文章已存在标签时不生效.'));
        $form->addInput($isActive);
    
        $api_key = new Typecho_Widget_Helper_Form_Element_Text(
            'api_key', NULL, '',
            _t('百度自然语言处理应用的API Ke'),
            _t('<a href="https://ai.baidu.com/ai-doc/REFERENCE/Ck3dwjgn3">应用注册方法</a> 需开通<a href="https://console.bce.baidu.com/ai/#/ai/nlp/overview/index">服务列表</a>中的“文章标签”API接口（不是“关键词提取”），并<a href="https://console.bce.baidu.com/ai/?_=1652794810218&fromai=1#/ai/nlp/overview/resource/getFree">领取免费额度<a>')
        );
        $form->addInput($api_key);

        $secret_key = new Typecho_Widget_Helper_Form_Element_Text(
            'secret_key', NULL, '',
            _t('百度自然语言处理应用的Secret Key'),
            _t('')
        );
        $form->addInput($secret_key);
    }
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}

    /**
     * 发布文章时自动提取标签
     *
     * @access public
     * @return void
     */
    public static function write($contents, $edit)
    {
		$title = $contents['title'];
        $html = $contents['text'];
        $isMarkdown = (0 === strpos($html, '<!--markdown-->'));
        if($isMarkdown){
            $html = Markdown::convert($html);
        }
		//过滤 html 标签等无用内容
        $text = str_replace("\n", '', trim(strip_tags(html_entity_decode($html))));
        $autoTags = Typecho_Widget::widget('Widget_Options')->plugin('AutoTags');
        //插件启用,且未手动设置标签
        if($autoTags->isActive == 1 && !$contents['tags']) {
            Typecho_Widget::widget('Widget_Metas_Tag_Admin')->to($tags);
            foreach($tags->stack as $tag){
                $tagNames[] = $tag['name'];
            }
            $postData = array(
				'title' => $title,
				'content' => $text
			);
            $ch = curl_init('https://aip.baidubce.com/rpc/2.0/nlp/v1/keyword?charset=UTF-8&access_token='.self::getAccessToken());
            curl_setopt($ch, CURLOPT_TIMEOUT,10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER,
                array(
                    'Content-Type: application/json',
                    'Accept: application/json'
                )
            );
            $result = curl_exec($ch);
            curl_close($ch);
            $result = json_decode($result);
            $items = $result->items;
            if(count($items)<=0){
				return $contents;
            }
            $sourceTags = array();
            $i = 0;
            foreach($items as $key => $tag){
                if($i>6) break;
                $i++;
				if(in_array($tag->tag, $tagNames)){
					if(in_array($tag->tag, $sourceTags)) continue;
					$sourceTags[] = $tag->tag;
				}else{
				    $sourceTags[] = $tag->tag;
				}
            }
            $contents['tags'] = implode(',', array_unique($sourceTags));
        }
        return $contents;
    }
    /**
     * 百度平台授权
     */
    private static function getAccessToken()
    {
        $autoTags = Typecho_Widget::widget('Widget_Options')->plugin('AutoTags');
		$curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://aip.baidubce.com/oauth/2.0/token?client_id=".$autoTags->api_key."&client_secret=".$autoTags->secret_key."&grant_type=client_credentials",
            CURLOPT_TIMEOUT => 30,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST  => false,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Accept: application/json'
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $response = json_decode($response);
		return $response->access_token;
    }
}
