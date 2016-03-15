<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 标签自动生成插件
 * 
 * @package AutoTags
 * @author DT27
 * @version 1.0.0
 * @link https://dt27.org
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
        //Typecho_Plugin::factory('admin/menu.php')->navBar = array('AutoTags_Plugin', 'render');
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
        $autoTags = Typecho_Widget::widget('Widget_Options')->plugin('AutoTags');
        //插件启用,且未手动设置标签
        if($autoTags->isActive == 1 && !$contents['tags']) {
            //str_replace("\n", '', trim(strip_tags($contents['text'])))
            //过滤 html 标签等无用内容
            $postString = json_encode(str_replace("\n", '', trim(strip_tags($contents['text']))));
            $ch = curl_init('http://api.bosonnlp.com/keywords/analysis?top_k=5');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS,$postString);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
            curl_setopt($ch, CURLOPT_HTTPHEADER,
                array(
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'X-Token: fpm1fDvA.5220.GimJs8QvViSK'
                )
            );
            $result = curl_exec($ch);
            curl_close($ch);
            $result = json_decode($result);
            foreach($result as $re){
                $a[] = $re[1];
            }
            $contents['tags'] = implode(',', $a);
        }
        return $contents;
    }
}
