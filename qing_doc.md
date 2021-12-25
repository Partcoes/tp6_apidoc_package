#### `QingDoc文档`

- Composer

  composer.json中使用path软连接模式导入vendor
  例子：
  ```"repositories": [
          {
              "type": "path",
              "url": "/Applications/MAMP/htdocs/qingDoc"
          }
      ]
  ```
  
  require中引入qingDoc
  
    ```
    "qing/doc":"dev-master"
    ```

- 使用方式

```
use Qing/QingDoc;

$qing = (new QingDoc()) -> qingDoc([]);//参数

```
并采用规范的验证器代码书写tp6相关代码，开启sql日志配置，对数组验证器类不友好，对特定自定义方法校验不支持
例1：
```
$validate = (new \app\admin\validate\v1\Institution());
if (!$validate->scene('info')->check(['id' => $id])) {
    return $this->error($validate->getError());
}
```
例2：
```
$this->validate($params, 'v1/Order.audit');
```
例3：
```
 $validate = Validate::rule(['id' => 'require']);
数组中
```

- 请求方式

  `POST`

- 参数

  1.`api_name`

        - 必选
        
        - string
        
        - 接口名称
        
  4.`debug`

        - 非必选
        
        - bool
        
        - 是否推送远程

  5.`item_id`

        - 必选
        
        - int
        
        - 项目目录

  6.`cat_name`

        - 非必选
        
        - string
        
        - 接口文档所在 show_doc 目录

  7 `description`

        - 非必选
        
        - string
        
        - 接口描述，数组，title 标题，content 内容

  8.`result`

        - 非必选
        - array
        - 接口相应结果，序列化后的数组

  9.`fieldDescription`

        - 非必选
        
        - array
        
        - 自定义字段说明 主要用于抓取表注释为空 补充文字说明 参数为数组类型
  10.`domain`
    
        - 非必选
        
        - string
        
        - 接口文档中本接口的域名
        
  11.`item_name`
    
        - 非必选
        
        - string
        
        - wiki中的项目名称，如果多个则返回提示，项目名称和项目ID，请选择文档所归属的项目ID传递再次请求该服务(注：项目ID和项目名称必须传递其中一个参数)
