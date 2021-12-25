<?php
/**
 * Take people's money and get rid of disasters
 * 拿人钱财 与人消灾
 *
 * User: 段帅强
 * Date: 2021/10/18
 * Time: 15:05
 */

return [
    'show_doc' => [
        'domain' => 'https://wiki.iguopin.com/server/index.php?s=',
        'url' => [
            'push' => '/api/page/save',//推送接口文档到wiki
            'get' => '/api/page/info',//获取已存在的接口文档信息
            'category' => '/api/catalog/catListGroup',//获取文档存放目录
            'login' => '/api/user/login',//用户登录
            'userinfo' => '/api/user/info',//获取用户信息
            'myList' => '/api/item/myList'//获取项目列表
        ],
        'cookie' => [
            'PHPSESSID' => 'e81d4e1d2adfe2ac926171eb387e8a5c',
//            'UM_distinctid' => '',
//            'cookie_token' => 'f98bdb8af920a9fadf30792ca56c9e3d390d0173b9c5ee491a33a6173b8bdc29'
        ],
        'user' => [
            'username' => 'duanshuaiqiang',
            'password' => '123456',
//            'username' => 'tianbowen',
//            'password' => '123456'
        ],
        'app' => 'app',
        'log_num' => 50
    ],
    'template' => [
        'id' => '',
        'template' => [
            'delimiter' => '####',
        ]
    ],
    'database' => [
            // 数据库类型
            'type'     => 'mysql',
            // 主机地址
            'hostname' => 'localhost',
            'hostport' => '3306',
            // 用户名
            'username' => 'root',
            'password' => 'root',
            // 数据库名
            'database' => 'test',
            // 数据库编码默认采用utf8
            'charset'  => 'utf8',
            // 数据库表前缀
            'prefix'   => '',
            // 数据库调试模式
            'debug'    => true,
        ],
    'runtime' => [
//        'path' => 'qing_doc'
    ],
    'backup_table' => 'apidoc_bak',
    'translate' => [
        'translate' => [
            'appid' => '20211116001000571',
            'secret' => 'rzUPKFYRCP_K3ydrASYq',
            'api' => 'https://fanyi-api.baidu.com/api/trans/vip/translate',
        ]
    ]
];