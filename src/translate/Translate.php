<?php
/**
 * Take people's money and get rid of disasters
 * 拿人钱财 与人消灾
 *
 * User: 段帅强
 * Date: 2021/11/16
 * Time: 15:56
 */

namespace Qing\QingDoc\translate;


use Qing\QingDoc\Config;
use Qing\QingDoc\Http;

class Translate implements \Qing\QingDoc\qing\Translate
{
    private $appid;

    private $secret;

    private $api;


    private $error;

    /**
     * 需要翻译的文本
     * @var
     */
    public $word;

    public $from;

    public $to;

    public function __construct($from = 'auto',$to = 'en')
    {
        $this -> appid = Config::service() -> get('translate.translate.appid');
        $this -> api = Config::service() -> get('translate.translate.api');
        $this -> secret = Config::service() -> get('translate.translate.secret');
        $this -> from = $from;
        $this -> to = $to;
    }

    /**
     * 构建接口参数
     * @return mixed|string
     */
    public function buildParam()
    {
        $rand = rand(10000,99999);
        $query = [
            'appid' => $this -> appid,
            'q' => $this -> word,
            'salt' => $rand,
            'secret' => $this -> secret,
        ];
        $md5 = md5(implode($query));
        $query['sign'] = $md5;
        $query['from'] = $this -> from;
        $query['to'] = $this -> to;

        unset($query['secret']);
        $str = '';
        foreach ($query as $k => $v) {
            $str .= $k . '=' . $v .'&';
        }
        $str = '?' . trim($str,'&');
        return 'https://fanyi-api.baidu.com/api/trans/vip/translate' . $str;
    }

    /**
     * 翻译
     * @param string $word 需要翻译的文本
     * @param bool false  $isOrigin 原数据返回
     * @param string $from 来源语言
     * @param string $to   翻译的语言
     * @return array|mixed|string
     */
    public function translate(string $word = '', $isOrigin = false, $from = 'auto', $to = 'en')
    {
        try {
            if($word === '') return '';

            $this -> word = str_replace(' ','',$word);
            $this -> from = $from;
            $this -> to = $to;

            $url = $this -> buildParam();
            $res = Http::get($url,[],[],['Content-Type' => 'application/x-www-form-urlencoded']);
            $res = json_decode($res,true);
            if(!isset($res['error_code'])){
                if($isOrigin === false){
                    $str = isset($res['trans_result']) ? array_column($res['trans_result'],'dst') : [];
                    return implode($str);
                }
                return $res;
            }
            $this -> error[] = '百度翻译异常：' . $res['error_msg'];
        } catch (\Exception $e) {
            $this -> error[] = '异常文件：' . $e -> getFile() ;
            $this -> error[] = '异常行数：' . $e -> getLine() ;
            $this -> error[] = '异常信息：' . $e -> getMessage() ;
        }

        return '';
    }

    /**
     * 获取错误信息
     * @return mixed
     */
    public function getError()
    {
        return $this -> error;
    }
    
    /**
     * 获取实例
     * @param        $word
     * @param string $from
     * @param string $to
     * @return static
     */
    public static function service($from = 'auto',$to = 'en')
    {
        return new static($from = 'auto',$to = 'en');
    }
}