<?php
/**
 * Take people's money and get rid of disasters
 * 拿人钱财 与人消灾
 *
 * User: 段帅强
 * Date: 2021/10/18
 * Time: 17:38
 */

namespace Qing\QingDoc\validate;

use think\Validate;

class QingDoc extends Validate
{

    public $rule = [
        'api_name' => 'require',
        'item_id' => 'requireWithout:item_name',
        'cat_name' => 'requireWithout:cat_id',
        'cat_id' => 'requireWithout:cat_name',
        'item_name' => 'requireWithout:item_id',
    ];

    public $message = [
        'api_name.require' => '接口文档名称不能为空',
        'item_id.requireWithout' => '项目ID不能为空',
        'item_name.requireWithout' => '接口所属项目不能为空',
        'cat_name.requireWithout' => '接口文档存放目录ID不能为空',
        'cat_id.requireWithout' => '接口文档存放目录不能为空',
    ];

}