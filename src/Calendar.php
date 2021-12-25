<?php
/**
 * Take people's money and get rid of disasters
 * 拿人钱财 与人消灾
 *
 * User: 段帅强
 * Date: 2021/11/2
 * Time: 14:49
 */

namespace Qing\QingDoc;

use think\exception\ValidateException;

class Calendar
{

    /**
     *
     * //2022年
    $data = [
    '0101','0102','0103','0131','0201','0202','0203','0204',
    '0205','0206','0403','0404','0405','0430','0501','0502','0503',
    '0504','0603','0604','0605','0910','0911','0912','1001',
    '1002','1003','1004','1005','1006','1007'
    ];
    $data1 = [
    '0101' => '元旦','0131' => '除夕','0201' => '春节','0405' => '清明','0501' => '劳动节',
    '0603' => '端午节','0910' => '中秋节','1001' => '国庆节'
    ];
    $rest = [
    '0129','0130','0402','0424','0507','1008','1009'
    ];
     */


    /**
     * 假期月日值
     * @var string[]
     */
    private $holiday = [
        '0101','0102','0103',
        '0211','0212','0213','0214','0215','0216','0217',
        '0403','0404','0405',
        '0501','0502','0503','0504', '0505',
        '0612','0613','0614',
        '0919','0920','0921',
        '1001','1002','1003','1004','1005','1006','1007'
    ];
    /**
     * 调休月日值
     * @var string[]
     */
    private $rest = [
        '0207','0220',
        '0425','0508',
        '0918',
        '0926','1009'
    ];

    /**
     * 节假日中文
     * @var string[]
     */
    private $holidayCn = [
        '0101' => '元旦','0211' => '除夕','0212' => '春节','0404' => '清明节','0501' => '劳动节','0614' => '端午节',
        '0915' => '中秋节','1001' => '国庆节'
    ];

    /**
     * 周中文
     * @var string[]
     */
    private $weekCn = [0 => '周日',1 => '周一',2 => '周二',3 => '周三',4 => '周四',5 => '周五',6 => '周六'];

    private $debug = false;

    public function __construct($holiday,$holidayCn,$rest,$weekCn = [],$debug)
    {
        $this -> holiday = ($holiday) ? $holiday : $this -> holiday;
        $this -> holidayCn = ($holidayCn) ? $holidayCn : $this -> holidayCn;
        $this -> rest = ($rest) ? $rest : $this -> rest;
        $this -> weekCn = (empty($weekCn)) ? $this -> weekCn : $weekCn;
        $this -> debug = ($debug === true) ? $debug : $this -> debug;
    }

    public function buildCalendar($preYear = '+1')
    {
        $thirtyOne = [1,3,5,7,8,10,12];
        $thirty = [4,6,9,11];
        $yearCount = date('Y',time()) . ' ' . $preYear . ' year';
        $year = (empty($preYear)) ? date('Y',time()):date('Y',strtotime($yearCount));
        $date = [];
        for ($i = 1 ; $i <= 12 ; $i ++) {
            $month = str_pad($i,2,'0',STR_PAD_LEFT);
            if($year % 4 == 0 && $i == 2){
                $d = 29;
            }elseif(in_array($i,$thirty)){
                $d = 30;
            }elseif(in_array($i,$thirtyOne)){
                $d = 31;
            }else{
                $d = 28;
            }
            for($j = 1;$j <= $d ; $j++){
                $day = str_pad($j,2,'0',STR_PAD_LEFT);
                $temp = $year . '/' . $month . '/' . $day;
                $isWorkDay = $this -> isWorkDay($temp,$year);
                $date[] = [
                    'date' => $temp,
                    'year' => $year,
                    'month' => $month,
                    'day' => $day,
                    'week' => (date('w',strtotime($temp)) == 0) ? 7 : date('w',strtotime($temp)),
                    'week_num' => date('W',strtotime($temp)),
                    'type' => $isWorkDay['type'],
                    'create_time' => time(),
                    'update_time' => time()
                ];
                if($isWorkDay['type'] === 0){
                    $b[] =  [
                        'holiday_days' => '',
                        'rest' => '班',
                        'type' => 0,
                        'date' => date('Y-m-d',strtotime($temp))
                    ];
                }
            }

        }

    }

    /**
     * @param string $date 日期
     * @param string $jsonFile 节假日数据写入json
     * @return string
     */
    private  function isWorkDay(string $date , $jsonFile = '2022')
    {
        //2022年
        $data = $this -> holiday;
        $rest =  $this -> rest;
        $temp1 = date('md',strtotime($date));
        $week = date('w',strtotime($date));
        if(in_array($temp1,$data) || (in_array($week,[0,6]) && !in_array($temp1,$rest))){
            $a['type'] = 1;
            return $a;
        }elseif(!in_array($week,[0,6]) || in_array($temp1,$rest)){
            $a['type'] = 0;
            return $a;
        }
        return '未知';
    }

    /**
     * 录入当年节假日日历数据到json
     * @param string $jsonFile
     * @return false|string
     */
    public function writeHolidayJson($jsonFile = '2021')
    {
        $data = $this -> holiday;
        $rest =  $this -> rest;
        $holidayCn = $this -> holidayCn;
        $nowYear = date('Y',time());
        if(!empty($jsonFile)){
            $preYear = ($nowYear == $jsonFile) ? '' : $jsonFile - $nowYear;
            $preYear = (!empty($preYear) && $preYear > 0) ? '+' . $preYear : strval($preYear);
            $annals = $this -> getAnnalDate($preYear);
            $temp = [];
            foreach ($annals as $index => $item) {
                $timestamp = strtotime($item);
                $week = date('w',$timestamp);
                $weekNum = ltrim(date('W',$timestamp),'0');
                $month = date('m',$timestamp);
                $day = date('d',$timestamp);

                $month = str_pad($month,2,'0',STR_PAD_LEFT);
                $day = str_pad($day,2,'0',STR_PAD_LEFT);
                $continue = $month . $day;
                if((in_array($continue,$rest) && in_array($week,[0,6]))){
                    $temp['type'] = 2;
                    $temp['holiday_days'] = '调休';
                    $temp['rest'] = '调休';
                    $temp['week'] = $week;
                    $temp['week_cn'] = isset($this -> weekCn[$week]) ? $this -> weekCn[$week] : '';
                    $temp['week_num'] = $weekNum;
                    $temp['date'] = $item;
                }elseif((!in_array($week,[0,6]) && !in_array($continue,$data))){
                    $temp['type'] = 0;
                    $temp['holiday_days'] = '工作日';
                    $temp['rest'] = '班';
                    $temp['week'] = $week;
                    $temp['week_cn'] = isset($this -> weekCn[$week]) ? $this -> weekCn[$week] : '';
                    $temp['week_num'] = $weekNum;
                    $temp['date'] = $item;
                }elseif(in_array($continue,$data)){
                    $temp['type'] = 1;
                    $temp['holiday_days'] = isset($holidayCn[$continue]) ? $holidayCn[$continue] : '节假日';
                    $temp['rest'] = '休';
                    $temp['week'] = $week;
                    $temp['week_cn'] = isset($this -> weekCn[$week]) ? $this -> weekCn[$week] : '';
                    $temp['week_num'] = $weekNum;
                    $temp['date'] = $item;
                }elseif(in_array($week,[0,6])){
                    $weekCn = [0 => '周日',6 => '周六'];
                    $temp['type'] = 4;
                    $temp['holiday_days'] = isset($weekCn[$week]) ? $weekCn[$week] : '周六日';
                    $temp['rest'] = '休';
                    $temp['week'] = $week;
                    $temp['week_cn'] = isset($this -> weekCn[$week]) ? $this -> weekCn[$week] : '';
                    $temp['week_num'] = $weekNum;
                    $temp['date'] = $item;
                }
                $date[] = $temp;
            }
            $date = json_encode($date,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);

            $res = ($this -> debug) ? $this -> debug :  file_put_contents($this -> runtime() . $jsonFile . '.json',$date);
            if(!$res){
                throw new ValidateException('节假日数据写入失败');
            }
            return $date;
        }
    }

    /**
     * 获取运行目录
     * @return mixed|string
     */
    private function runtime()
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


    /**获取全年日期
     * @param string $preYear 上一年或下一年 +1 / -1
     * @return array
     */
    public function getAnnalDate($preYear = '')
    {
        $thirtyOne = [1,3,5,7,8,10,12];
        $thirty = [4,6,9,11];
        $yearCount = date('Y',time()) . ' ' . $preYear . ' year';
        $year = (empty($preYear)) ? date('Y',time()):date('Y',strtotime($yearCount));
        $date = [];
        for ($i = 1 ; $i <= 12 ; $i ++) {
            $month = str_pad($i,2,'0',STR_PAD_LEFT);
            if($year % 4 == 0 && $i == 2){
                $d = 29;
            }elseif(in_array($i,$thirty)){
                $d = 30;
            }elseif(in_array($i,$thirtyOne)){
                $d = 31;
            }else{
                $d = 28;
            }
            for($j = 1;$j <= $d ; $j++){
                $day = str_pad($j,2,'0',STR_PAD_LEFT);
                $temp = $year . '/' . $month . '/' . $day;
                $date[] = $temp;
            }
        }
        return $date;
    }

    /**
     * 获取某一天日历信息
     * @param        $dateTime
     * @param string $jsonFile
     * @return false|string
     */
    public function getOneDate($dateTime,$jsonFile = '')
    {
        $dateTime = date('Y/m/d',strtotime($dateTime));
        $filePath = $this -> runtime() . $jsonFile . '.json';
        if(file_exists($filePath)){
            $date = file_get_contents($filePath);

            $date = json_decode($date,true);
            if($date){
                foreach ($date as $index => $item) {
                    if($dateTime == $item['date']) return json_encode($item,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
                }
            }
            return false;
        }
        throw new ValidateException('本年json文件不存在');
    }

    /**
    * 获取服务层实例
    * @param array $holiday 假期月日值
    * @param array  $holidayCn 假期月日中文
    * @param array  $rest 调休月日
    * @param array  $weekCn 周中文
    * @param boolean  $debug debug模式
    * @return static
    */
    public static function service(array $holiday = [],array $holidayCn = [],array $rest = [],$weekCn = [],$debug = false)
    {
        return new static($holiday,$holidayCn,$rest,$weekCn,$debug);
    }
}