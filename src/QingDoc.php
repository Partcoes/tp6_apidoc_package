<?php
/**
 * Take people's money and get rid of disasters
 * 拿人钱财 与人消灾
 *
 * User: 段帅强
 * Date: 2021/10/18
 * Time: 15:04
 */

namespace Qing\QingDoc;

use SplFileObject;
use think\Config;
use think\exception\ValidateException;
use think\facade\Db;

class QingDoc
{

    private $app;

    private $domain = '';

    private $wikiUrl = '';

    private $module = '';

    private $token = '';

    private $qing = [];

    /**
     * @var $db
     */
    private $db;

    public function __construct()
    {
        $this -> runtime();

        $this -> app = app();
        $module = app('http') -> getName();
        $this -> module = $module;
        $this -> setDbConfig();
        $this -> wikiUrl = config('qing.show_doc.url');
        $this -> domain = config('qing.show_doc.domain');
        //wiki登录
        $this -> login();

    }

    public function __call($param1,$param2)
    {

    }

    /**
     * 获取运行目录
     * @return mixed|string
     */
    public function runtime()
    {
        $runtimeQing = config('qing.runtime.path','qing_doc');
        $temp = substr($runtimeQing,0,1);
        $runtimePath = app() -> getRuntimePath();
        $runtimeQing = ($temp != '/' || empty($runtimeQing)) ? $runtimePath . $runtimeQing : $runtimeQing;
        if($runtimeQing){
            if(!file_exists($runtimeQing)){
                mkdir($runtimeQing,0777);
            }
            return $runtimeQing . '/';
        }

        throw new ValidateException('运行目录生成失败');

    }
    
    public function config()
    {
       return config('qing');
    }

    public function qingDoc($data)
    {
        $validate = (new \Qing\QingDoc\validate\QingDoc());
        if(!$validate -> check($data)){
            throw new ValidateException($validate -> getError());
        }
        $data = $this -> parseItemId($data);
        $data = (empty($data['cat_id'])) ? array_merge($data,$this -> getCateId($data['cat_name'],$data['item_id'])) : $data;
        $qing  = new Qing($data);
        $this -> qing = $qing;
        $this -> setDb();

        $mdText = $this -> buildText($qing);

        //debug只负责输出生成的文档 不推送远程
        if($qing -> debug){
            echo "<pre/>";
            echo $mdText;die;
        }
        $domain = $this -> domain;
        if(!empty($qing -> page_id)){
            $url = config('qing.show_doc.url.get');
            $apiDoc = $this -> http_post(['page_id' => $qing -> page_id],$domain . $url,$this -> token);
            if( !isset($apiDoc['data']) ||  empty($apiDoc['data'])){
                throw new ValidateException('未发现将要编辑的文档，可能已经被删除');
            }
            $apiDoc = $apiDoc['data'];
            $apiDoc['create_time'] = time();
            $this -> setDb('local');
            try {
                $this -> db -> table(config('qing.backup_table','apidoc_bak')) -> insert($apiDoc);
            } catch (\Exception $e) {
                file_put_contents($this -> runtime() . $qing -> api_name . '-' . time() . '.json',json_encode($apiDoc,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
                throw new ValidateException($e -> getMessage());
            }
        }
        $data = [
            'page_id' => $qing -> page_id,
            'page_title' => $qing -> api_name,
            'item_id' => $qing -> item_id,
            'is_urlencode' => empty($qing -> is_urlencode) ? false : $qing -> is_urlencode,
            'page_content' => $mdText,
            'cat_id' => $qing -> cat_id
        ];

        $url = $domain . config('qing.show_doc.url.push');
        $res = $this -> http_post($data,$url,$this -> token);
        if($res['error_code'] == 0){
            echo json_encode($res['data'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);die;
        }
        throw new ValidateException('文档推送失败');
    }

    /**获取目录ID
     * @param $catName
     * @param $itemID
     * @return array|false|int|string|string[]
     */
    private function getCateId($catName,$itemID){
        $domain = config('qing.show_doc.domain');
        $url = $domain . config('qing.show_doc.url.category');
        $data = ['item_id' => $itemID,'cate' => $catName];
        $res = $this -> http_post($data,$url,$this -> token);
        if($res['error_code'] == 0){
            $id = [];
            $category = $this -> dealCategory($res['data']);
            $list = [];
            $tempStr = '';
            foreach ($category as $key => $item) {
                if(empty($catName)){
                    $list[$item['cat_id']] = $item['cat_name'];
                    continue;
                }
                //实现数组内模糊搜索
                if(strstr($item['cat_name'],$catName) !== false){
                    $list[$item['cat_id']] = $item['cat_name'] ;
                    $tempStr = $item['cat_name'] . '(' . $item['cat_id'] . '),';
                }
            }
            if(count($list) == 1){
                $list = array_flip($list);
                $post['cat_id'] = array_pop($list);
                return $post;
            }
            throw new ValidateException('发现多个目录，请任选其中一个目录ID:' . $tempStr);
        }

    }

    /**
     * 树形数据还原
     * @param $tree
     * @param string $children
     * @param array $list
     * @return array|mixed
     */
    private function dealCategory($tree, $children = 'sub', &$list = array())
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
     * 解析出项目ID
     * @param $post
     */
    private function parseItemId($post)
    {
        if(!isset($post['item_name']) && isset($post['item_id'])){
            return $post;
        }
        $itemName = $post['item_name'];
        $itemList = $this -> http_post([],config('qing.show_doc.domain') . config('qing.show_doc.url.myList'),$this -> token);

        if(empty($itemList) || !isset($itemList['error_code']) || $itemList['error_code'] != 0){
            throw new ValidateException('wiki未找到该项目');
        }
        $itemList = $itemList['data'];
        $list = [];
        $tempStr = '';
        foreach ($itemList as $key => $item) {
            if(empty($itemName)){
                $list[$item['item_id']] = $item['item_name'] . '(' . $item['item_id'] . ')';
                continue;
            }
            //实现数组内模糊搜索
            if(strstr($item['item_name'],$itemName) !== false || strstr($item['item_id'],$itemName) !== false){
                $list[$item['item_id']] = $item['item_name'] . '(' . $item['item_id'] . ')';
                $tempStr = $item['item_name'] . '(' . $item['item_id'] . '),';
            }
        }
        $tempStr = trim($tempStr,',');
        if(empty($list)){
            throw new ValidateException("wiki未找到{" . $itemName . "}相关项目");
        }
        if(count($list) == 1){
            $list = array_flip($list);
            $post['item_id'] = array_pop($list);
            return $post;
        }
        throw new ValidateException('发现多个项目，请任选其中一个项目ID：' . $tempStr);
    }

    /**
     * 构建api文档文本
     * @param Qing $qing
     * @return string
     */
    private function buildText(Qing $qing)
    {
        $paramStr = $this -> validateText();

        $requestExp = $this -> getRequestExp();
        $qing -> result = isset($qing -> result['data']) ? $qing -> result : ['code' => 1,'time' => time(),'msg' => 'success','data' => $qing -> result];
        $result = [
            "```",
            json_encode($qing -> result,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),
            "```"
        ];
        $resultStr = implode("\n",$result);
        $resultDes = $this -> buildResultText($qing -> result);
        $title = isset($qing -> description['title']) ? $qing -> description['title'] : '简要描述';
        $content = isset($qing -> description['content']) ? $qing -> description['content'] : '简要描述';
        $url = strpos(request() -> server('REQUEST_URI'),'?') === false ? request() -> server('REQUEST_URI') : substr(request() -> server('REQUEST_URI'),0,strpos(request() -> server('REQUEST_URI'),'?'));
//        dd($this -> server,$paramStr,$this -> post,$resultStr,$resultDes);
        $domain = !empty($qing -> domain) ? $qing -> domain : 'http://***.xitalent.com';
        $text = "[TOC]\n\n###" . $title ."\n- `" . $content . "`\n\n###请求的域名\n\n- `" . $domain . "`\n\n####请求的URL\n\n- `" . $url ."`\n\n###请求方式\n\n- `" . request() -> server('REQUEST_METHOD') . "`\n\n";
        $text .= "###参数\n\n" . $paramStr . $requestExp . "\n\n###返回示例\n\n" . $resultStr . "\n\n" . $resultDes . "\n\n####备注\n\n- `更多返回错误代码请看首页的错误代码描述`";
        return $text;
    }

    /**
     * 构建接口结果说明文本
     * @param $data
     * @param string $str1
     * @return string
     */
    private function buildResultText($data,$str1 = '')
    {
        $defaultFieldDes = $this -> getFieldComment($this -> getLogDir($this -> parseServer()),config('qing.show_doc.log_num'));

        $start = config('qing.template.template.delimiter');

        $data = isset($data[0]) ? $data[0] : $data;
        if(is_array($data)){
            //将数组放到数据末尾处理解决递归终止bug
            foreach($data as $i => $d){
                if(is_array($d)){
                    array_push($data,$d);
                    unset($data[$i]);
                }
            }
            foreach($data as $key => $item){
                $indexCn = strrpos($key,'_cn');
                $indexText = strrpos($key,'_text');
                if($indexCn !== false){
                    $cnk = substr($key,0,$indexCn);
                    $temp = isset($defaultFieldDes[$cnk]) ? $defaultFieldDes[$cnk] : '';
                }elseif($indexText !== false){
                    $cnk = substr($key,0,$indexText);
                    $temp = isset($defaultFieldDes[$cnk]) ? $defaultFieldDes[$cnk] : '';
                }else{
                    $temp = isset($defaultFieldDes[$key]) ? $defaultFieldDes[$key] : '';
                }
                if(is_array($item)){
                    return $this -> buildResultText($item,$str1);
                }
                $type = is_null($item) ? 'string' : gettype($item);
                $str1 .=  '|' . $key . "|" . $type . '|' . $temp . "|\n";
            }
        }
        $str = $start . "返回参数说明\n\n";
        $str1 = $str . "|参数名|类型|说明\n|----|----|----|\n" . $str1;
        return $str1;
    }

    /**
     * 获取请求示例
     * @return string
     */
    private function getRequestExp()
    {
        $params = request() -> param();
        $paramsText = '';
        if(!empty($params)){
            $paramTemp = [
                '```',
                json_encode($params,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),
                '```'
            ];
            $paramTemp = implode("\n",$paramTemp);
            $paramsText .= "\n\n### 请求示例\n\n" . $paramTemp;
        }
        return $paramsText;
    }

    /**
     * 获取验证器接口文档文本
     * @return string|void
     */
    private function validateText()
    {
        try {
            $validate = $this -> getValidateFile($this -> parseServer());
            if(!empty($validate['validateFile'])) {
                if (!file_exists($validate['validateFile'])) {
                    throw new ValidateException('验证器文件不存在');
                }

                $validateText = file_get_contents($validate['validateFile']);

                $validateText = preg_replace("#(extends\s*Validate)|(extends\s*BaseValidate)|(use\s+(.*);)|namespace(.*);*|#", '', $validateText);
                $validateText = preg_replace("#protected\s*#", 'public ', $validateText);
                $filename = app() -> getRuntimePath() . 'qing_doc' . '/' . $validate['validate'] . '.php';
                if (!file_put_contents($filename, $validateText)) {
                   throw new ValidateException('验证器文件写入本地失败');
                }
                chmod($filename, 0777);

                include $filename;

                if (!class_exists($validate['validate'])) {
                    throw new ValidateException('验证器文件不存在');
                    return false;
                }

                $class = new $validate['validate'];
                $scene = $class -> scene;
            }
            $sceneRule = isset($scene[$validate['scene']]) ? $scene[$validate['scene']] : [];
            $rule = (isset($class)) ? $class -> rule : [];
            $param = request() -> param();
            $defaultFieldDes = $this -> getFieldComment($this -> getLogDir($this -> parseServer()),config('qing.show_doc.log_num'));

            $head = [
                '|参数' => '|---', "<center>必选</center>" => '--','<center>类型</center>' => '-','说明|' => '----|'
            ];
            $str = '';
            foreach($param as $field => $value){
                $rules[] = $field;
                if(isset($rule[$field])){
                    if(is_string($field)){
                        $ruleArr = (is_string($rule[$field])) ? explode('|',$rule[$field]) : $rule[$field];
                        $rules = array_merge($rules,$ruleArr);
                        //长度不够4 补齐
                        $rules = (count($rules) < 4) ? array_pad($rules,4,'') : $rules;
                    }
                }

                $temps = [
                    '',
                    '<center>否</center>',
                    '<center>string</center>',
                    //默认字段说明
                    isset($defaultFieldDes[$field]) ? $defaultFieldDes[$field] : ''
                ];
                foreach($rules as $key => $value){
                    if(is_array($value) && $key == 0){
                        $value = implode(',',$value);
                        $temps[0] = '|' . $field;
                        //假设验证器规则为数组形式且读取到的表注释不存在，则将验证器规则值赋予字段说明
                        $temps[3] = !isset($defaultFieldDes[$field]) ? $value : $defaultFieldDes[$field];
                        continue;
                    }
                    if($key == 0){
                        $temps[0] = '|' . $value;
                        continue;
                    }
                    if($value == 'require'){
                        $temps[1] = '<center>是</center>';
                    }
                    if($value == 'number' || $value == 'integer'){
                        $temps[2] = '<center>int</center>';
                    }
                    if($value == 'mobile'){
                        $temps[2] = '<center>int</center>';
                        $temps[3] = !empty($temps[3]) ? $temps[3] : '手机号';
                    }
                    if($value == 'email'){
                        $temps[3] = !empty($temps[3]) ? $temps[3] :'邮箱';
                    }
                    if($value == 'url'){
                        $temps[3] = !empty($temps[3]) ? $temps[3] :'链接';
                    }
                    if($value == 'idCard'){
                        $temps[3] = !empty($temps[3]) ? $temps[3] :'身份证号';
                    }
                    $value = strtolower($value);
                    if(strpos($value,'max:') !== false){
                        $maxMsg = '最多' . str_replace('max:','',$value) . '个字符';
                        $temps[3] = !empty($temps[3]) ? $temps[3] . ' , ' . $maxMsg :$maxMsg;
                    }

                    if(strpos($value,'min:') !== false){
                        $maxMsg = '最小' . str_replace('min:','',$value) . '个字符';
                        $temps[3] = !empty($temps[3]) ? $temps[3] . ' , ' . $maxMsg :$maxMsg;
                    }

                    if(strpos($value,'length:') !== false){
                        $t = str_replace('length:','',$value);
                        [$min,$max] = explode(',',$t);
                        $maxMsg = '最少' . $min . '个字符，最多' . $max . '个字符';
                        $temps[3] = !empty($temps[3]) ? $temps[3] . ' , ' . $maxMsg :$maxMsg;
                    }

                    if(strpos($value,'json') !== false){
                        $temps[3] = !empty($temps[3]) ? $temps[3]:'json字符串';
                    }
                    if(strpos($value,'IN:') !== false){
                        $temps[3] = str_replace('IN:','',$value);
                    }
                    if(strpos($value,'BETWEEN:') !== false){
                        $value = str_replace('BETWEEN:','',$value);
                        $temps[3] = str_replace(',','-',$value);
                    }
                    if(strpos($value,'date') !== false || strpos(strtolower($value),'time') !== false){
                        $temps[3] = !empty($temps[3]) ? $temps[3]:'日期格式 2021-01-01 00:00:00';
                    }
                }
                unset($rules);
                $str .= implode('|',$temps) . "|\n";
                unset($temps);
            }
            if(isset($filename) && file_exists($filename)) {
                //使用结束验证器文件后删除掉文件
                unlink($filename);
            }

            $str = implode('|',array_keys($head)) . "\n" . implode('|',$head) . "\n" . $str;
            return $str;
        } catch (\Exception $e) {
            throw new ValidateException($e -> getMessage());
        }


    }

    /**读取表注释生成文档说明
     * @param string $logDir
     * @return array
     */
    private function getFieldComment($logDir,$logNum = 10){
        try {
            $logContent = file_get_contents($logDir);
            $logContent = explode("\n",trim($logContent,''));
            $logContent = array_filter($logContent);
            //获取最后N条sql日志
            $offset = count($logContent) - $logNum;
            $offset = ($offset < 0) ? 0 : $offset;
            $logContent = array_slice($logContent,$offset,$logNum);
            $commentArr = [];
            foreach($logContent as $k => $item){
                if(strpos($item,'SHOW COLUMNS') !== 0){
//                //抓取日志中的sql
                    preg_match("#\[sql\](.*)\[ RunTime#",$item,$match);
                    if(!$match || (!isset($match[1]))){
                        continue;
                    }
                    $sql = $match[1];
                    if(strpos($sql,'SHOW COLUMNS') !== false || strpos($sql,'SHOW FULL COLUMNS') !== false){
                        $this -> setDb('mysql');
                        $comment = $this -> db -> query($sql);
                        $comment = array_column($comment,'Comment','Field');
                        $commentArr = array_merge($commentArr,$comment);
                    }
                }
            }
            $commentArr = array_filter($commentArr);
            $default = $this -> qing -> fieldDescription;
            $commentArr = array_merge($default,$commentArr);
            return $commentArr;
        } catch (\Exception $e) {
            throw new ValidateException('字段说明解析失败');
        }
    }

    /**
     * 获取本机项目日志存放位置 当天日志不存在则回回溯之前日志
     * @param $serverData
     * @param $day 默认当天日志
     * @return string
     */
    private function getLogDir($serverData,$day = '')
    {
        try {
            //当前天
            $day = empty($day) ? date('d') : $day;
            $runtimePath = app() -> getRuntimePath();
            $runtimePath = ($runtimePath) ? $runtimePath : $serverData['root'] . 'runtime/' . $serverData['module'] . '/';
            $logDir = $runtimePath . 'log/' . date('Ym') . '/' . $day . '.log';
            if(!file_exists($logDir)){
                $day = ($day <= 1) ? $day :  $day - 1;
                return $this -> getLogDir($serverData,$day);
            }
            return $logDir;
        } catch (\Exception $e) {
            throw new ValidateException($e -> getMessage());
        }

    }

    /**
     * 获取验证器文件路径以及场景
     * @param $serverData
     * @return array|false|string|string[]
     */
    public function getValidateFile($serverData)
    {
        try {

            extract($serverData);
            /** @var $controllerFile */
            /** @var $action */
            /**@var $app */

            $actionLine = 0;
            $SplCtrlFile = new SplFileObject($controllerFile);
            $actStr = 'publicfunction'.$action . '()';
            foreach ($SplCtrlFile as $lineNumber => $lineContent) {
                $lineContent = preg_replace("#\s*#",'',$lineContent);
                if ($actStr == $lineContent) {
                    $actionLine = $lineNumber;
                    break;
                }
            }
            //获取代码块起始行
            $content1 = $SplCtrlFile -> fread($SplCtrlFile->getSize());
            foreach($SplCtrlFile as $line => $c){
                $c = preg_replace("#\s*#",'',$c);
                $c = substr($c,0,14);
                if($line > $actionLine && $c == 'publicfunction' ){
                    $endLine = $line;
                    break;
                }
            }
            //获取代码块结束行
            $content2 = $SplCtrlFile -> fread($SplCtrlFile->getSize());
            $content = str_replace($content2,'',$content1);
            $content = preg_replace("#\s\S*public\s*function\s*(\w+)\((.*)\)#",'',$content);
            preg_match('#validate(.*)#',$content,$validateTemp);
            if(isset($validateTemp[1]) && !empty($validateTemp) && !empty($validateTemp[1])){
                $validateTemp = $validateTemp[1];
                //情况一 直接调用父类验证器+rule方式验证
                if(strpos($validateTemp,'::rule') !== false){
                    $validateTemp = preg_match("#\[(.*)\]#",$validateTemp,$vTemp);
                    $vTemp = isset($vTemp[0]) ? $vTemp[0] : '';
                    if(!empty($vTemp)){
                        $tempFile = RUNTIME_PATH . '/Temp.php';
                        $tempText = "<?php\nclass Temp {\npublic \$rule = $vTemp;\n}";
                        $res = file_put_contents($tempFile,$tempText);
                        return ($res) ? $tempFile : '';
                    }
                    //第二种情况 类实例不带命名空间 指向 场景scene 且顶部使用了这个类
                }elseif(preg_match('#scene\s*\([\'\"](.*)[\'\"]\)#',$content,$scene)){
                    preg_match('#\(.*\)#',$validateTemp,$v);
                    $validate = preg_replace('#(new)|\(|\)|\s*|(\-\>)|(scene\(.*\))|\;|\=#','',$validateTemp);
                    $scene = isset($scene[1]) ? $scene[1] : '';
                    foreach($SplCtrlFile as $l => $c){
                        if($l <= 30){
                            preg_match("#use(.*)((as\s*" . $validate . ")|\s*" . $validate . ")#",$c,$validateFile);
                            if(empty($validateFile) || !isset($validateFile[1])){
                                continue;
                            }
                            $validateFile = $validateFile[1];
                            $validateFile = trim($validateFile," \\");
                        }
                        break;
                    }
                    //验证器文件路径为空，并且在第二种情况中，则使用了命名空间指向scene的方式
                    if(empty($validateFile)){
                        $validateFile = str_replace("\\",'/',$validate);
                        $validate  = trim(substr($validateFile,strrpos($validateFile,'/')),'/');
                    }
                    //是否为其他模块验证器
                    $isOtherMod = substr($validateFile,0,4) == "/app";
                    if( $isOtherMod ){
                        $validateFile = $serverData['root'] . str_replace("\\",'/',$validateFile) . '.php';
                    }else{
                        $validateFile = $serverData['root'] . @$app . '/' . $serverData['module'] . '/validate/' . $validateFile . '.php';
                    }
                    return [
                        'scene' => $scene,
                        'validate' => $validate,
                        'validateFile' => $validateFile
                    ];
                }else{
                    //将无用字符去去除
                    $validateTemp = preg_replace('#(\$(.*),)|(\[(.*),)|((::class)\s*.\s*)#','',$validateTemp);
                    $validateTemp = preg_replace("#\[.*\]|\'|\(|\)|,|\;|\s*#",'',$validateTemp);
                    //分离场景和验证器文件
                    $validate = explode('.' , $validateTemp);
                    //反斜杠统一转化为斜杠
                    $transSlash = str_replace("\\",'/',$validate[0]);
                    //验证器抓取失败
                    if(!isset($validate[0]) && strrpos($transSlash,'/') === false){
                        return false;
                    }
                    //测试自定义模块验证器抓取
//                $validate = "app\\common\\v1\\Order\\getSftOrder";
                    $scene = isset($validate[1]) ? $validate[1] : '';
                    //验证器
                    $validate = $validate[0];
                    //验证器文件名
                    $validateFileName = trim(substr($transSlash,strrpos($transSlash,'/') +  1),'/');
                    $validateFileName = ucfirst($validateFileName);
                    $validate = trim($validate,'"');
                    //是否为其他模块验证器
                    $isOtherMod = (substr($validate,0,3) == "app" || substr($validate,0,1) == "\\");
                    if( $isOtherMod ){
                        $validate = $serverData['root'] . str_replace("\\",'/',$validate) . '.php';
                    }else{
                        $validate = $serverData['root'] . @$app . '/' . $serverData['module'] . '/validate/' . $validate . '.php';
                    }
                    return [
                        'scene' => $scene,
                        'validate' => $validateFileName,
                        'validateFile' => $validate
                    ];
                }
            }
            return [
                'scene' => '',
                'validate' => '',
                'validateFile' => ''
            ];
        } catch (\Exception $e) {
            throw new ValidateException('验证器文件位置解析失败' . $e -> getMessage());
        }
    }


    /**
     * 解析服务文件目录
     * @param $data
     * @param false $debug
     * @return array|mixed
     */
    private function parseServer()
    {
        try {
            $request = request();
            $app = !empty(config('qing.show_doc.app')) ? config('qing.show_doc.app') : 'app' ;
            //URI
            $uri = $request -> server('REQUEST_URI');
            //根目录
            $root = substr($request -> server('SCRIPT_FILENAME'),0,strpos($request -> server('SCRIPT_FILENAME'),'public'));
            //模块名
            $module = $this -> module;
            //路由控制器
            $controller = str_replace('.','/',$request -> controller());
            //路由文件
            $routeFile = $routerFile = $root . $app . '/' . $module .'/route/' . $module . '.php';
            //控制器文件
            $controllerFile = $root . $app . '/' . $module . '/controller/' . $controller . '.php';

            $serverData = [
                'uri' => $uri,
                'root' => $root,
                'module' => $module,
                'controller' => $controller,
                'controllerFile' => $controllerFile,
                'routeFile' => $routeFile,
                'method' => $request -> method(),
                'action' => $request -> action(),
                'app' => $app
            ];
            return $serverData;
        } catch (\Exception $e) {
            throw new ValidateException('解析服务信息失败：' . $e -> getMessage());
        }
    }

    /**
     * 切换存储库/查询库
     * @param string $db
     */
    private function setDb($db = 'local')
    {
        $this -> db = Db::connect($db);
    }

    /**
     * 设置数据库配置
     * @return bool
     */
    private function setDbConfig()
    {
        try {
            $default = config();
            $database = config('database');
            $database['connections']['local'] = config('qing.database');
            $default['database'] = $database;
            $obj = (new Config());
            $obj -> set($default);
            Db::setConfig($obj);
            return true;
        } catch (\Exception $e) {
            throw new ValidateException($e->getMessage());
        }

    }

    /**
     * wiki登录
     * @return bool
     */
    private function login()
    {
        $runtimePath = $this -> app -> getRuntimePath();
        $qingPath = $runtimePath . 'qing_doc';
        if(!file_exists($qingPath)){
            mkdir($qingPath,0777);
        }
        $userJson = $qingPath . DIRECTORY_SEPARATOR . 'userinfo.json';
        if(!file_exists($userJson)){
            $loginUrl = $this -> domain . $this -> wikiUrl['login'];
            $userInfo = config('qing.show_doc.user');
            $user = $this -> http_post($userInfo,$loginUrl);
            $token = [
                'PHPSESSID' => config('qing.show_doc.cookie.PHPSESSID'),
                'cookie_token' => $user['data']['user_token'],
            ];
            $token = $token + $user['data'];
            //设置token
            $this -> token = $token;
            $token = json_encode($token,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
            //创建登录cookie数据存储
            $res = file_put_contents($userJson,$token);
            if($res > 0){
                chmod($userJson,0777);
                return true;
            }
        }

        $userUrl = $this -> domain . config('qing.show_doc.url.userinfo');

        $validate = json_decode(file_get_contents($userJson),true);
        $header = $this -> buildHeader($validate);
        $isLogin = $this -> http_post([],$userUrl,$header);
        if($isLogin['error_code'] == 0){
            $this -> token = $header;
            return true;
        }else{
            unlink($userJson);
        }
        //存在cookie但过期了 重新登录
        $this -> login();
        return true;
    }

    /**
     * 构建请求wiki的header头
     * @param $userInfo
     * @return string[]
     */
    private function buildHeader($userInfo)
    {
        $str = '';
        foreach($userInfo as $k => $v){
            $str .= $k .'=' . $v . '; ';
        }
        $str = rtrim($str,'; ');
        $str = ['Cookie: ' . $str];
        return $str;
    }

    /**
     * 发送请求
     * @param $data
     * @param $url
     * @param array $header 请求头
     * @return mixed
     */
    private function http_post($data,$url,$header = []){
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => $header,
        ));

        $response = curl_exec($curl);
        $error = curl_error($curl);
        if($error){
            return ['code' => 500,'msg' => $error,'data' => $response];
        }
        curl_close($curl);
        $response = json_decode($response,true);
        return $response;
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