<?php
/**
 * Take people's money and get rid of disasters
 * 拿人钱财 与人消灾
 *
 * User: 段帅强
 * Date: 2021/10/19
 * Time: 17:29
 */

namespace Qing\QingDoc;

use Qing\QingDoc\translate\Translate;
use think\facade\Db;
use app\common\utils\Arr;

class RandomForm extends QingBase
{
    /**
     * 公司名称字符串
     * @var string[]
     */
    private $string = [
        '这','是','什么','一','二','我','测试','语言','花香','暴风雨',
        '树木','书','码','马','鱼','test','中','国家','战略','合作',
        '工商','核心','联','信','盛开','中文','投资','海南',
        '河北','北京','科技','三','四','五','火星','彗星',
        '水星','极客','火','水','海','鸳鸯','远洋','泰坦尼克','小步',
        '思科','思','斯','落','洛','星河','兄弟','信仰','亚太','励星'
    ];
    /**
     * 文章拆分单字
     * @var array
     */
    private $article = [];

    /**
     * 手机号号段
     * @var array|int[]
     */
    private $numberSection = [31,32,33,34,35,36,37,38,39,51,52,53,55,56,57,58,59,77,81,82,85,86,87,88];

    private $method = [
        'string','int','mobile','email','name','companyName','site','district',
        'date','time','qq','alpha','category','double','json','english','idcard','img',
        'status'
    ];

    private $debug = true;

    public function __construct($string = [],$numberSection = [])
    {
        $article = $this -> getString();
        $this -> article = $article;
        $this -> string = array_merge($this -> string,$string);
        $this -> numberSection = array_merge($this -> numberSection,$numberSection);
    }

    /**
     * 获取表单假数据
     * @param array     $field 字段字段数组['int' => ['age'],'json' => ['json']
     * @param bool $debug
     * @return false|string
     */
    public function getForm(array $field,$debug = true)
    {
        $this -> debug = $debug;
        $params = [];
        //英文字母
        $alpha = $this -> createAlpha('a','z');
        //int类型数据生成测试数据
        $type = (!isset($params['type']) || $params['type'] === '') ? 'string' : $params['type'];
        foreach($field as $key => $item){
            if(in_array($key,['method','getMarkDown','form'])&& method_exists($this,$key)){
                $params = array_merge($params,$this -> $key($item));
            }else{
                $params = array_merge($params,$this -> string($item));
            }
        }
        if (!$debug){
            return json_encode($params,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        }
        $str = '';
        foreach($params as $key => $val){
            $str .= $key . ':' . $val . "\n";
        }
        return $str;
    }

    /**
     * 获取全国所有省市县数据
     * @param string $filename 文件名称
     * @return mixed
     */
    private function getJson(string $filename = 'district')
    {
        $district = file_get_contents(__DIR__ . '/json/' . $filename . '.json');
        return json_decode($district,true);
    }

    /**
     * 获取随机地区
     * @return string
     */
    private function getRandomDistrict()
    {
        try {
            $district = $this -> getJson();
            $str = $this -> getDistrictForId($district);
            $str = explode('|',$str);
            $str1 = isset($str[1]) ? $str[1] : '';
            $str = isset($str[0]) ? $str[0] : '';
            if(substr_count($str,'/') != 2 ){
                return $this -> getRandomDistrict();
            }
            $str = [ltrim($str,'/') , ltrim($str1,'.')];
            return $str;
        } catch (\Exception $e) {
            throw new \Exception($e -> getMessage());
        }


    }

    /**
     * 随机qq号
     * @param $item
     * @return array
     */
    public function qq($item)
    {
        $params = [];
        foreach($item as $k => $v){
            if(is_numeric($k)){
                $params[$v] = rand(100000, 9999999999);
            }
        }
        return $params;
    }

    /**
     * 获取地区字符串中文
     * @param        $district
     * @param string $str
     * @param string $str1 id字符串
     * @return mixed|string
     */
    private function getDistrictForId($district , $str = '',$str1 = '')
    {
        $allProvinceId = array_column($district,'id');
        $pCount = count($allProvinceId);
        $randomIndex = rand(1,$pCount);
        $provinceId = isset($district[$randomIndex]) ? $district[$randomIndex] : [];
        $provinceId = isset($provinceId['id']) ? $provinceId['id'] : '';
        foreach($district as $k => $item){
            if($provinceId == ''){
                return $str;
            }
            if($item['id'] == $provinceId){
                $str = $str . '/' .$item['categoryname'];
                $str1 = $str1 . '.' .$item['id'];
                if(empty($item['children'])){
                    $str = ltrim($str,'/');
                    return $str . '|' . $str1;
                }
                return $this -> getDistrictForId($item['children'],$str,$str1);
            }
        }
    }

    /**
     * 树形数据还原
     * @param $tree
     * @param string $children
     * @param array $list
     * @return array|mixed
     */
    public function dealCategory($tree, $children = 'sub', &$list = array())
    {
        if(is_array($tree)) {
            foreach ($tree as $key => $value) {
                $reffer = $value;
                if(isset($reffer[$children])){
                    unset($reffer[$children]);
                    $this -> dealCategory($value[$children], $children,$list);
                }
                $list[] = $reffer;
            }
        }
        return $list;
    }

    /**
     * 生成alpha 字母
     * @param $min //从哪个字母开始
     * @param $max //从哪个字母结束
     * @return array $data
     */
    public function createAlpha($min = 'a',$max = 'z')
    {
        $min = $this -> encode($min);
        $max = $this -> encode($max);
        $data = [];
        for($i = $min ;$i <= $max ; $i++){
            $code = $this -> decode($i);
            $data[] = $code;
        }
        return $data;
    }

    /**
     * 将字符串转换为ascii码
     * @param $c //要编码的字符串
     * @param $prefix //前缀，默认：&#
     * @return string
     */
    public function encode($c, $prefix="&#") {
        $len = strlen($c);
        $ascill = '';
        $a = 0;
        while ($a < $len) {
            $ud = 0;
            if (ord($c{$a}) >= 0 && ord($c{$a}) <= 127) {
                $ud = ord($c{$a});
                $a += 1;
            } else if (ord($c{$a}) >= 192 && ord($c{$a}) <= 223) {
                $ud = (ord($c{$a}) - 192) * 64 + (ord($c{$a + 1}) - 128);
                $a += 2;
            } else if (ord($c{$a}) >= 224 && ord($c{$a}) <= 239) {
                $ud = (ord($c{$a}) - 224) * 4096 + (ord($c{$a + 1}) - 128) * 64 + (ord($c{$a + 2}) - 128);
                $a += 3;
            } else if (ord($c{$a}) >= 240 && ord($c{$a}) <= 247) {
                $ud = (ord($c{$a}) - 240) * 262144 + (ord($c{$a + 1}) - 128) * 4096 + (ord($c{$a + 2}) - 128) * 64 + (ord($c{$a + 3}) - 128);
                $a += 4;
            } else if (ord($c{$a}) >= 248 && ord($c{$a}) <= 251) {
                $ud = (ord($c{$a}) - 248) * 16777216 + (ord($c{$a + 1}) - 128) * 262144 + (ord($c{$a + 2}) - 128) * 4096 + (ord($c{$a + 3}) - 128) * 64 + (ord($c{$a + 4}) - 128);
                $a += 5;
            } else if (ord($c{$a}) >= 252 && ord($c{$a}) <= 253) {
                $ud = (ord($c{$a}) - 252) * 1073741824 + (ord($c{$a + 1}) - 128) * 16777216 + (ord($c{$a + 2}) - 128) * 262144 + (ord($c{$a + 3}) - 128) * 4096 + (ord($c{$a + 4}) - 128) * 64 + (ord($c{$a + 5}) - 128);
                $a += 6;
            } else if (ord($c{$a}) >= 254 && ord($c{$a}) <= 255) { //error
                $ud = false;
            }
            $ascill = $ud;
//        $ascill .= $prefix.$ud.";";
        }
        return $ascill;
    }

    /**
     * 将ascii码转为字符串
     * @param type $str 要解码的字符串
     * @param type $prefix 前缀，默认:&#
     * @return type
     */
    public function decode($str, $prefix="&#") {
        $str = str_replace($prefix, "", $str);
        $a = explode(";", $str);
        $utf = '';
        foreach ($a as $dec) {
            if ($dec < 128) {
                $utf .= chr($dec);
            } else if ($dec < 2048) {
                $utf .= chr(192 + (($dec - ($dec % 64)) / 64));
                $utf .= chr(128 + ($dec % 64));
            } else {
                $utf .= chr(224 + (($dec - ($dec % 4096)) / 4096));
                $utf .= chr(128 + ((($dec % 4096) - ($dec % 64)) / 64));
                $utf .= chr(128 + ($dec % 64));
            }
        }
        return $utf;
    }

    /**
     * 生成随机浮点数
     * @param int $min
     * @param int $max
     * @return float
     */
    public function randFloat($min = 0, $max = 1)
    {
        $rand = $min + mt_rand() / mt_getrandmax() * ($max - $min);
        return floatval(number_format($rand,2));
    }

    /**
     * 假数据整数
     * @param array  $item
     * @return array
     */
    public function int($item = [],int $min = 1,int $max = 1000)
    {
        $params = [];
        foreach($item as $k => $v){
            if(is_numeric($k)){
                $params[$v] = rand(1, 1000);
            }
        }
        return $params;
    }

    /**
     * 字段名称带有cn后缀则返回中文+ID串
     * 假数据地区类型
     * @param $item
     * @return array
     */
    public function district($item)
    {
        $params = [];

        foreach($item as $k => $v){
            if(is_numeric($k)){
                //随机地区
                $district = $this -> getRandomDistrict();
                [$districtCn,$district] = $district;
                if(strpos($v,'_cn') !== false ){
                    $params[$v] = $districtCn;
                    $v = str_replace('_cn','',$v);
                }
                $params[$v] = $district;
            }
        }
        return $params;
    }

    /**
     * 假数据邮箱地址
     * @param $item
     * @return mixed
     */
    public function email($item)
    {
        $alpha = $this -> createAlpha('a','z');
        foreach($item as $k => $v){
            if(is_numeric($k)){
                $temp = '';
                for($i = 0; $i < 4 ; $i++){
                    $temp .= $alpha[rand(0,(count($alpha) - 1))] . $i;
                }
                $emailSource = ['126','163','qq.com','sdic'];
                $temp .= '@' . $emailSource[rand(0,3)] . '.com';
                $params[$v] = $temp;
            }
        }
        return $params;
    }

    /**
     * 随机字母串
     * @param     $item
     * @param int $max
     * @return mixed
     */
    public function alpha($item,$max = 15)
    {
        $alpha = $this -> createAlpha('a','z');
        foreach($item as $k => $v){
            if(is_numeric($k)){
                $temp = '';
                for($i = 0; $i < $max ; $i++){
                    $temp .= $alpha[rand(0,(count($alpha) - 1))];
                }
                $params[$v] = $temp;
            }
        }
        return $params;
    }

    /**
     * ['是否带后缀_cn' => 分类名称] 带cn则返回ID和中文，否则只返回中文
     * 随机生成系统分类相关假数据
     * @param $item
     * @return mixed
     */
    public function category($item)
    {
        $params = [];
        $where = [];
        foreach($item as $k => $v){
            if(is_numeric($k)){
                $where[] = 'GP_' . $v;
                $where[] = 'QS_' . $v;
            }
            $where[] = 'GP_' . $v;
            $where[] = 'QS_' . $v;
        }
        $where = array_unique($where);
        $category = Db::table('gp_category')
            -> field(['c_id','c_name','c_alias'])
            -> whereIn('c_alias',$where)
            -> select()
            -> toArray();
        $category = self::arrayGroupByField($category,'c_alias','GP_');
        foreach ($item as $k => $v){
            if(!isset($category[$v])){
                $b = count($this -> string) - 1;
                $temp = [
                    'c_name' => $this -> string[rand(0,$b)],'c_id' => rand(1, 1000)
                ];
            }else{
                $temp = $category[$v][rand(0,(count($category[$v]) - 1))];
            }

            if (is_numeric($k)){

                if(strpos($v,'_cn') !== false ){
                    $params[$v] = $temp['c_id'];
                    $k = str_replace('_cn','',$v);
                }
                $params[$v . '_cn'] = $temp['c_name'];
                continue;
            }
            if(strpos($k,'_cn') !== false ){
                $k = str_replace('_cn','',$k);
                $params[$k] = $temp['c_id'];
            }
            $params[$k . '_cn'] = $temp['c_name'];
        }
        return $params;
    }

    /**
     * 数据分组
     * @param array  $data
     * @param string $field
     * @return array
     */
    private static function arrayGroupByField(array $data,string $field)
    {
        $newArr = [];
        foreach($data as $v) {
            $v[$field] = str_replace('QS_','',str_replace('GP_','',$v[$field]));
            $newArr[$v[$field]][] = $v;
        }
        return $newArr;
    }

    /**
     * item: ["数据类型" => [参数名称]]
     * 英文翻译
     * @param $item
     * @return mixed
     */
    public function english($item)
    {
        $params = [];
        try {

            $translate = Translate::service();
            foreach($item as $k => $v) {
                if(!is_numeric($k)){
                    if(method_exists($this,$k)){
                        $data = $this -> $k($v);
                    }
                }else{
                    $data = $this -> string($v);
                }
                foreach ($data as $index => $datum) {
                    sleep(1);
                    $params[$index] = $translate -> translate(strval($datum));
                }
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
        return $params;
    }

    /**
     * 企业名称
     * @param $item
     * @return string[]
     */
    public function companyName($item)
    {
        $params = [];
        foreach ($item as $index => $v) {
            $str1 = $this -> string[rand(0,count($this -> string) - 1)];
            $str2 = $this -> string[rand(0,count($this -> string) - 1)];
            $str3 = $this -> string[rand(0,count($this -> string) - 1)];
           $params[$v] = $str4 = $str2 . $str1 . $str3  . '有限责任公司';
        }
        return $params;
    }
    

    /**
     * 人名称
     * @param string | array $item
     * @return string| array $params
     */
    public function name($item = [])
    {
        $params = [];
        $lastName = $this -> getJson('last_name');
        $probability = array_chunk($lastName,15);
        $probability = $probability[0];
        for ($i = 0 ; $i < 10 ; $i ++ ) {
            $probability = array_merge($probability,$probability);
        }
        $lastName = array_merge($probability,$lastName);
        $firstName = $this -> getJson('first_name');
        if(empty($item)){
            $last = $lastName[rand(0,count($lastName) - 1)];
            $first = (strlen($last) == 2) ? $firstName[rand(0,count($firstName) - 1)] : $firstName[rand(0,count($firstName) - 1)] . $firstName[rand(0,count($firstName) - 1)];
            return $last . $first;
        }
        foreach ($item as  $key => $v) {
            $last = $lastName[rand(0,count($lastName) - 1)];
            $first = (strlen($last) == 2) ? $firstName[rand(0,count($firstName) - 1)] : $firstName[rand(0,count($firstName) - 1)] . $firstName[rand(0,count($firstName) - 1)];
            $params[$v] = $last . $first;
        }
        return $params;
    }

    /**
     * 假数据网站地址
     * @param $item
     * @return array
     */
    public function site($item)
    {
        $params = [];

        $alpha = $this -> createAlpha('a','z');
        foreach ($item as $k => $v) {
            if(is_numeric($k)){
                $temp = 'http://www.';
                for($i = 0; $i < 5 ; $i++){
                    $temp .= $alpha[rand(0,(count($alpha) - 1))] . $i;
                }
                $params[$v] = $temp . '.com';
                $temp = '';
            }
        }
        return $params;
    }

    /**假数据oss地址
     * @param $item
     * @return array
     */
    public function img($item)
    {
        $params = [];

        $alpha = $this -> createAlpha('a','z');

        foreach ($item as $k => $v) {
            if(is_numeric($k)){
                $temp = $alpha[rand(0,(count($alpha) - 1))];
                $temp = $temp . $temp . $temp . $temp . $temp;
                $extend = ['.jpg','.png'];
                $params[$v] = md5($temp) . $extend[rand(0,1)];
            }
        }
        return $params;
    }

    /**假数据状态值
     * @param $item
     * @return array
     */
    public function status($item)
    {
        $params = [];
        foreach ($item as $k => $v) {
            if(is_numeric($k)){
                $params[$v] = rand(1,2);
            }elseif(is_array($v) && count($v) == 2){
                [$min,$max] = $v;
                $params[$k] = rand($min,$max);
            }
        }
        return $params;
    }

    /**
     * 生成json数据
     * @param $item
     * @return array
     */
    public function json($item)
    {
        $params = [];
        foreach ($item as $index => $v) {
            $temp = [];
            foreach($v as $method => $data) {
                if(method_exists($this ,$method)){
                    $temp = array_merge($temp,$this -> $method($data));
                    $params[$index] = ($this -> debug) ? json_encode($temp,JSON_UNESCAPED_UNICODE) : $temp;
                }
            }
        }
        return $params;

    }

    /**
     * 假数据手机号
     * @param $item
     * @return array
     */
    public function mobile($item)
    {
        $params = [];

        foreach ($item as $k => $v) {
            if(is_numeric($k)){
                $params[$v] = 1 . $this -> numberSection[rand(0,(count($this -> numberSection) - 1))] . rand(10000000,99999999);
            }
        }

        return $params;
    }

    /**
     * 假数据日期
     * @param $item
     * @return array
     */
    public function date($item)
    {
        $params = [];

        foreach ($item as $k => $v) {
            if(!is_numeric($k)){
                $rand = time();
                $params[$v] = date('Y-m-d',rand($rand - 86400 * 7,$rand) - 10);
            }else{
                $rand = time();
                $params[$v] = date('Y-m-d H:i:s',rand($rand - 86400 * 7,$rand) - 10);
            }
        }
        return $params;
    }

    /**
     * 假数据时间戳
     * @param $item
     * @return array
     */
    public function time($item)
    {
        $params = [];
        foreach ($item as $k => $v) {
            if(is_numeric($k)){
                $rand = time();
                $params[$v] =rand($rand - 86400 * 7,$rand) - 10;
            }
        }
        return $params;
    }

    /**
     * 假数据double类型
     * @param $item
     * @return array
     */
    public function double($item)
    {
        $params = [];

        foreach ($item as $k => $v) {
            if(is_numeric($k)){
                $params[$v] = $this -> randFloat(1,999);
            }
        }
        return $params;
    }

    /**
     * 假数据字符串类型
     * @param $item
     * @param $x 字符串长度
     * @return array
     */
    public function string($item,$x = 10)
    {
        $params = [];


        foreach ($item as $k => $v){
            if (is_numeric($k)){
                $temp = [];
                $b = count($this -> article) - 1;
                for($i = 0;$i <= $x ; $i ++ ){
                    $temp[] = $this -> article[rand(0,$b)];
                }
                $params[$v] = implode('',$temp);
            }
        }
        return $params;
    }

    /**
     * 获取文章的中文文本数组
     * @return array
     */
    private function getString()
    {
        $txt = file_get_contents(__DIR__ . '/txt/纪念刘和珍君.txt');
        $txt = preg_replace("#(\s)*|(\t)*|(\，)|(；)*|(\")*|(\《)*|(\》)*|(\。)*|(\")*|(\！)*|(\：)|(\“)*|(\”)*|(\？)*|(\d)*#",'',$txt);
        return str_split($txt,3);
    }

    public function form()
    {
        $param = request() -> param();
        $params = [];
        foreach ($param as $key => $item){
            if(!is_numeric($key) && is_string($key)){
                if (strpos($key,'count') !== false || strpos($key,'code') !== false || strpos($key,'id') !== false) {
                    $params[$key] = rand(1, 100);
                    continue;
                }elseif(strpos($key,'number') !== false ) {
                    $params[$key] = rand(1, 1000);
                    continue;
                }elseif(strpos($key,'json') !== false || strpos($key,'address') !== false ) {
                    //随机地区
                    $district = $this -> getRandomDistrict();
                    continue;
                }elseif(strpos($key,'email') !== false ) {
                    $temp = '';
                    for($i = 0; $i < 4 ; $i++){
                        $temp .= $alpha[rand(0,(count($alpha) - 1))] . $i;
                    }
                    $emailSource = ['126','163','qq.com','sdic'];
                    $temp .= '@' . $emailSource[rand(0,3)] . '.com';
                    $params[$key] = $temp;
                    continue;
                }elseif(strpos($key,'site') !== false ) {
                    $temp = 'http://www.';
                    for($i = 0; $i < 5 ; $i++){
                        $temp .= $alpha[rand(0,(count($alpha) - 1))] . $i;
                    }
                    $params[$key] = $temp . '.com';
                    $temp = '';
                    continue;
                }elseif(strpos($key,'img') !== false || strpos($key,'image') !== false
                    || strpos($key,'path') !== false || strpos($key,'file') !== false
                    || strpos($key,'logo') !== false){
                    $temp = $alpha[rand(0,(count($alpha) - 1))];
                    $temp = $temp . $temp . $temp . $temp . $temp;
                    $extend = ['.jpg','.png'];
                    $params[$key] = md5($temp) . $extend[rand(0,1)];
                    continue;
                }elseif(strpos($key,'name') !== false || strpos($key,'_cn') !== false
                    || strpos($key,'_desc' ) !== false ||  strpos($key,'content') !== false){
                    $b = count($this -> string) - 1;
                    $params[$key] = $this -> string[rand(0,$b)] . $this -> string[rand(0,$b)] . $this -> string[rand(0,$b)] . $this -> string[rand(0,$b)];
                    continue;
                }elseif(strpos($key,'status') !== false ){
                    $params[$key] = rand(1,2);
                    continue;
                }elseif(strpos($key,'time') !== false ){
                    $rand = time();
                    $params[$key] = rand($rand - 86400 * 7,$rand) - 10;
                    continue;
                }elseif(strpos($key,'mobile') !== false|| strpos($key,'tel') !== false){
                    $params[$key] = 1 . $this -> numberSection[rand(0,(count($this -> numberSection) - 1))] . rand(10000000,99999999);
                    continue;
                }elseif(strpos($key,'date') !== false ){
                    $rand = time();
                    $params[$key] = date('Y-m-d H:i:s',rand($rand - 86400 * 7,$rand) - 10);
                    continue;
                }elseif($type != 'int'){
                    $params[$key] = '';
                    continue;
                }else{
                    $params[$key] = 1;
                    continue;
                }
            }else{
                $params[$key] = $key;
            }
        }
        return $params;
    }

    /**
     * 生成身份证号
     * @param $item
     * @return array
     */
    public function idcard($item)
    {
        $instance = RandomIdCard::service();

        $params = [];


        foreach ($item as $k => $v){
            $params[$v] = $instance -> randomIdCard();
        }
        return $params;
    }

    /**
     * 内置的数据类型/数据方法
     * @return string[]
     */
    public function method()
    {
        return $this -> method;
    }

    
    /**
    * 获取服务层实例
    * @return static
    */
    public static function service()
    {
        return new static();
    }
}