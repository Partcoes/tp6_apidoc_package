<?php
/**
 * Take people's money and get rid of disasters
 * 拿人钱财 与人消灾
 *
 * User: 段帅强
 * Date: 2021/11/23
 * Time: 15:30
 */

namespace Qing\QingDoc;


class QingBase
{
    /**
     * 获取工具类文档
     */
    public function getMarkDown()
    {
        $class = get_class($this);
        $class = substr($class,strrpos($class,"\\") + 1);
        $class = mb_strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1_', $class), 'UTF-8');
        if(file_exists(dirname(__DIR__) . '/' . $class . '.md')){
            echo file_get_contents(dirname(__DIR__)  . '/'. $class .'.md');die;
        }
        echo '暂无文档';die;
    }
}