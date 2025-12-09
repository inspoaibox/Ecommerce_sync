#赛盈分销平台分销商对接 API 文档

更新日志

| 日期   | 修改记录                                                                  |
|------------|-------------------------------------------------------------------------------|
| 2023-2-2   | 所有接口响应失败时 ，返回错误编码 ，订单价格预览接口响应增加字段 FailCode     |
| 2024-2-24  | 查询价格接口返回圈货价格 ，圈货实际价格会使用会员折扣                         |
| 2024-5-21  | 查询订单接口增加 TimeType字段 ，支持使用创建时间或者发货时间查询              |
| 2024-5-24  | 查询库存/查询库存 V2（根据更新时间查询）响应种新增圈货库存 MakeStockQty 字段  |
| 2024-6-6   | 查询订单接口 ，商品明细返回采购劵优惠金额                                     |
| 2024-7-29  | 创建订单时同一种保障服务支持传多个                                            |
| 2024-10-09 | 查询商品详情接口响应中增加发货地址信息                                        |
| 2025-3-27  | 创建订单支持自提物流 ，增加字段 IsPickUp、PickUpLogisticsList                 |
| 2025-3-27  | 查询商品价格接口响应中新增最大合并发货数量、发货包裹数量字段                  |
| 2025-7-2   | 查询商品价格接口响应中新增字段 SupportedLogisticsProvider【支持的物流承运商】 |

#一、 功能概述

赛盈分销平台为第三方提供信息交互接口。

#二、 技术概述

采用基于 REST 架构的 Web 服务，URL 表示操作的资源，操作类型通过 <Http> 方法（GET/HEAD、POST、 PUT 和 DELETE 等）<Http> 动词来标识。交互的数据采用 JSON 表示。通过 Token 识别用户身份，通过 DES 加 密方式验证请求。

## 地址

指定接口的 URL

正式：https://api.saleyee.com/

## 操作类型

Http动词:

Get

Post

## 请求消息

公共请求内容

| 字段      | 数据类型 | 描述         |
|-----------|----------|--------------|
| Version   | String   | API 版本号   |
| Token     | String   | 用户访问Key  |
| Sign      | String   | 加密凭证     |
| RequestId | String   | 唯一请求标识 |

| RequestTime | DateTime | 请求发起时间  |
|-------------|----------|---------------|
| Message     | String   | 请求数据 JSON |

Request 消息

包含有“公共请求内容 ”的所有字段。各个API 具体请求的字段，请查看接口明细。

## 响应消息

公共响应内容

| 字段           | 数据类型 | 描述                            |
|----------------|----------|---------------------------------|
| Version        | String   | API 版本号：1.0.0.0             |
| RequestId      | String   | 请求唯一标识                    |
| RequestTime    | DateTime | 请求时间：UTC 时间              |
| ResponseId     | String   | 响应唯一标识                    |
| ResponseTime   | DateTime | 响应时间                        |
| Result         | String   | 响应结果：0-Failure，1-Success  |
| Error          | 对象     | 响应错误：见 ResponseError 结构 |
| RequestMessage | String   | 请求数据 JSON                   |
| Message        | String   | 请求数据 JSON                   |

ResponseError 对象

| 字段         | 数据类型 | 描述                                             |
|--------------|----------|--------------------------------------------------|
| Code         | String   | 错误编码：（错误编码对应的原因请对照下方对照表） |
| ShortMessage | String   | 错误的短消息：暂未使用                           |
| LongMessage  | String   | 可凭此查找出错误原因                             |

5. 错误编码对照表

| 错误编码 | 错误原因                                                                        |
|----------|---------------------------------------------------------------------------------|
| 10100    | 请求参数错误                                                                    |
| 10200    | 身份验证不通过                                                                  |
| 10300    | 没有接口权限                                                                    |
| 10400    | 上一次相同类型的请求还在处理中，请等待上一次请求完成再发起新的请求              |
| 10500    | 接口请求成功，但是需要系统后台处理数据。请在后续调用相应的查询接口查 询最终结果 |
| 10600    | 请求的数据与相关联的数据不存在或者无效                                          |
| 10700    | 数据不允许此操作                                                                |
| 10800    | 请求处理出现异常                                                                |
| 10900    | 账户余额不足                                                                    |
| 11000    | 商品信息验证不通过                                                              |
| 11100    | 区域不存在或者无效                                                              |
| 11200    | 物流不存在或者无效                                                              |
| 11300    | 货币不存在或者无效                                                              |
| 11400    | 地址信息验证不通过                                                              |

| 11500 | Vat信息有误，无法下单 |
|-------|-----------------------|

# 请求示例

```
var url = "http://测试环境IP/api/test/GetOrder"; //接口请求地址
var data = new { OrderNo = "DS201812100001" }; //接口请求参数
var token = "IW0CxVq1bABsy0tPjgKnCA=="; //用户身份标识，DMS分配
var key = "V9hUUEkL"; // DES加密KEY，DMS分配
var request = new RequestBase();
request.RequestId = Guid.NewGuid().ToString();
request.RequestTime = DateTime.Now;
request.Version = "1.0.0.0";
request.Token = token;
request.Sign = null;
request.Message = data.ToJson();
request.Sign = EncryptHelper.DESEncrypt(request.ToJson(), key);//对请求报文进行DES加密
HttpClient client = new HttpClient();
client.Timeout = new TimeSpan(0, 10, 0);
var responseMessage = client.PostAsJsonAsync(url, request).Result;
string response = responseMessage.Content.ReadAsStringAsync().Result;
return response.ToObject<ResponseBase>();
```


待加密字符串示例：
```
{"Version":"1.0.0.0","Token":"XXXXXX","RequestId":"5dc116a26128d","RequestTime":"2019-11-05T
14:28:50","Message":"{\"OrderNoList\":[\"DS191201093408476673\"]}","Sign":null}
```


加密验证：

可用 http://tool.chacuo.net/cryptdes 进行 sign 验证；
请将加密后的 sign 传到 http://tool.chacuo.net/cryptdes 解密；
DES 加密模式为 CBC；填充为 pkcs7padding；密码和偏移量都是 KEY；输出为 base64；字符集为 utf8 编码；
若解密出的结果和加密前的一样，则为加密正确。
（Token 和 Key 可登陆赛盈商城--个人中心--开发者信息查看）

# 接口明细

## 获取系统区域 - 获取发货区域

URL: /api/Product/GetWarehouse  
Http: POST
RequestBody
无

ResponseBody

| 字段                               | 数据类型          | 描述             |
| -------------------------------- | ------------- | -------------- |
| StockCode                        | String\[32]   | 区域编码           |
| StockName                        | String\[255]  | 区域名称           |
| StockAbbreviation                | String\[256]  | 区域简称           |
| CountryCode                      | String\[256]  | 区域所在国家         |
| Street1                          | String\[256]  | 街道1            |
| Street2                          | String\[256]  | 街道2            |
| StateOrProvince                  | String\[50]   | 州/省            |
| PostCode                         | String\[32]   | 邮政编码           |
| ContactMan                       | String\[256]  | 联系人            |
| Tel                              | String\[128]  | 电话             |
| City                             | String\[256]  | 城市             |
| SupportedReturnPlatformWarehouse | List\<string> | 区域支持的可退货平台仓库编码 |
请求示例
```
{"Version":"1.0.0.0","Token":"LhdQAZR5tf3maBk3WE9HTg==","Sign":"ltNYS4QoXc3t5d5j6hsfhg4WIhIyXg5pgwMbF48ArxJXE/XckUV8rJh2Fwmvwv197ZsTdaDCZysu/lB4s5thVCwE2C1EBkjKI1YG/bwW819cnfG92oM31uQLzbzMJQmVWni8OUrANG3Cqzn0zLmWaRdYiA3d+itlM/f71NO1v+R2G9JNGt4MwMMfV4PW24wjxcfcdzrxtUo=","RequestTime":"2019-12-27T09:28:30Z","RequestId":"1577438910","Message":null}
```

返回示例
```
{
    "RequestId": "1577438910",
    "Message": "[{ \"StockCode\" : \"A162996\", \"StockName\" : \"US\", \"StockAbbreviation\" : \"A162996\", \"CountryCode\" : \"\", \"Street1\" : null, \"Street2\" : null, \"City\" : null, \"StateOrProvince\" : null, \"PostCode\" : null, \"ContactMan\" : null, \"Tel\" : null, \"Company\" : null }, { \"StockCode\" : \"A323174\", \"StockName\" : \"UK\", \"StockAbbreviation\" : \"A323174\", \"CountryCode\" : \"\", \"Street1\" : null, \"Street2\" : null, \"City\" : null, \"StateOrProvince\" : null, \"PostCode\" : null, \"ContactMan\" : null, \"Tel\" : null, \"Company\" : null }, { \"StockCode\" : \"SZ0011\", \"StockName\" : \"GB\", \"StockAbbreviation\" : \"SZ0011\", \"CountryCode\" : \"\", \"Street1\" : null, \"Street2\" : null, \"City\" : null, \"StateOrProvince\" : null, \"PostCode\" : null, \"ContactMan\" : null, \"Tel\" : null, \"Company\" : null }, { \"StockCode\" : \"SZ0012\", \"StockName\" : \"US\", \"StockAbbreviation\" : \"SZ0012\", \"CountryCode\" : \"\", \"Street1\" : null, \"Street2\" : null, \"City\" : null, \"StateOrProvince\" : null, \"PostCode\" : null, \"ContactMan\" : null, \"Tel\" : null, \"Company\" : null }, { \"StockCode\" : \"SZ0013\", \"StockName\" : \"CN\", \"StockAbbreviation\" : \"SZ0013\", \"CountryCode\" : \"\", \"Street1\" : null, \"Street2\" : null, \"City\" : null, \"StateOrProvince\" : null, \"PostCode\" : null, \"ContactMan\" : null, \"Tel\" : null, \"Company\" : null }]",
    "Version": "1.0.0.0",
    "RequestMessage": null,
    "ResponseId": "89876af9-6e41-4ae8-a169-74ee656c25a3",
    "Error": null,
    "RequestTime": "2020-05-23 01:57:56",
    "ResponseTime": "2020-05-23 01:57:56",
    "Result": 1
}
```

## 获取标准物流产品

URL: /api/Product/GetLogisticsProductStandard
Http:POST
RequestBody
无
ResponseBody

| 字段                          | 数据类型     | 描述                     |
|-------------------------------|--------------|--------------------------|
| LogisticsProductCode          | String[255]  | 标准物流产品编码         |
| LogisticsProductName          | String[255]  | 标准物流产品名称         |
| ExternalLogisticsCom pany     | String[200]  | 外部物流公司             |
| ExternalLogisticsPro duct     | String[200]  | 外部物流产品             |
| LogisticsProductType          | Int[11]      | 物流类型：0-发货，1-退件 |
| PlatformLogisticsPro ductCode | String [256] | 平台物流编码             |
| PlatformLogisticsPro ductName | String[256]  | 平台物流名称             |

请求示例

```
{"Version":"1.0.0.0","Token":"LhdQAZR5tf3maBk3WE9HTg==","Sign":"ltNYS4QoXc3t5d5j6hsfhg4WIhIyXg5pgwMbF48ArxJXE/XckUV8rJh2Fwmvwv197ZsTdaDCZysu/lB4s5thVCwE2C1EBkjKI1YG/bwW81+4a6xUbB6wF9EyVc91vXGZs5IQkwm/ISWy7O8UmpwTvfdrvv/93KO8KpRz6ih7Gy2dZHqwzTFnbixUT5DRgL7BrxCQwoBnAl2muJqP5SfwlCdnXIlvKHU3/gqDeDatGyD29TWMbJzBNw==","RequestTime":"2018-12-14T15:08:53.3772+08:00","RequestId":"fe06fbff-bd25-4c52-b9b8-5eeb5e1bdfa3","Message":null}
```

返回示例

```
{"Version":"1.0.0.0","RequestId":"fe06fbff-bd25-4c52-b9b8-5eeb5e1bdfa3","RequestTime":"2020-05-23 02:26:52","ResponseTime":"2020-05-23 02:26:52","ResponseId":"697fddec-bded-453d-bd47-e4beea4f8a5d","Result":1,"Error":null,"RequestMessage":null,"Message":"[{ \"LogisticsProductCode\" : \"TC0015\", \"LogisticsProductName\" : \"物流111\", \"ExternalLogisticsCompany\" : \"other\", \"ExternalLogisticsProduct\" : \"other\", \"LogisticsProductType\" : 0 }, { \"LogisticsProductCode\" : \"TC0022\", \"LogisticsProductName\" : \"物流888\", \"ExternalLogisticsCompany\" : null, \"ExternalLogisticsProduct\" : null, \"LogisticsProductType\" : 0 }, { \"LogisticsProductCode\" : \"TC0023\", \"LogisticsProductName\" : \"物流999\", \"ExternalLogisticsCompany\" : null, \"ExternalLogisticsProduct\" : null, \"LogisticsProductType\" : 0 }, { \"LogisticsProductCode\" : \"TC0024\", \"LogisticsProductName\" : \"物流AAA\", \"ExternalLogisticsCompany\" : null, \"ExternalLogisticsProduct\" : null, \"LogisticsProductType\" : 0 }, { \"LogisticsProductCode\" : \"TC0026\", \"LogisticsProductName\" : \"HY\", \"ExternalLogisticsCompany\" : null, \"ExternalLogisticsProduct\" : null, \"LogisticsProductType\" : 0 }, { \"LogisticsProductCode\" : \"TC0028\", \"LogisticsProductName\" : \"退件物流EE\", \"ExternalLogisticsCompany\" : null, \"ExternalLogisticsProduct\" : null, \"LogisticsProductType\" : 1 }, { \"LogisticsProductCode\" : \"TC0029\", \"LogisticsProductName\" : \"退件物流TT\", \"ExternalLogisticsCompany\" : null, \"ExternalLogisticsProduct\" : null, \"LogisticsProductType\" : 1 }, { \"LogisticsProductCode\" : \"TC0012\", \"LogisticsProductName\" : \"顺丰大包即日达\", \"ExternalLogisticsCompany\" : null, \"ExternalLogisticsProduct\" : null, \"LogisticsProductType\" : 0 }, { \"LogisticsProductCode\" : \"TC0016\", \"LogisticsProductName\" : \"物流222\", \"ExternalLogisticsCompany\" : null, \"ExternalLogisticsProduct\" : null, \"LogisticsProductType\" : 0 }, { \"LogisticsProductCode\" : \"TC0018\", \"LogisticsProductName\" : \"物流444\", \"ExternalLogisticsCompany\" : null, \"ExternalLogisticsProduct\" : null, \"LogisticsProductType\" : 0 }, { \"LogisticsProductCode\" : \"TC0025\", \"LogisticsProductName\" : \"物流BBB\", \"ExternalLogisticsCompany\" : null, \"ExternalLogisticsProduct\" : null, \"LogisticsProductType\" : 0 }, { \"LogisticsProductCode\" : \"TC0030\", \"LogisticsProductName\" : \"退件物流FF\", \"ExternalLogisticsCompany\" : null, \"ExternalLogisticsProduct\" : null, \"LogisticsProductType\" : 1 }]"}
```

## 获取地区列表

URL: /api/Product/GetCountry
Http:POST
RequestBody
无
ResponseBody

| 字段        | 数据类型             | 描述     |
|-------------|----------------------|----------|
| CountryList | List\<CountryModel\> | 国家列表 |

CountryModel

| 字段             | 数据类型              | 描述     |
|------------------|-----------------------|----------|
| TwoLetterIsoCode | String[20]            | 国家代码 |
| CountryName      | String[100]           | 国家名称 |
| ProvinceList     | List\<ProvinceModel\> | 省份列表 |
| WarehouseList    | List\<string\>        | 区域编码 |

ProvinceModel

| 字段         | 数据类型    | 描述     |
|--------------|-------------|----------|
| ProvinceName | String[100] | 省份名称 |
| StateCode    | String[100] | 省份缩写 |

请求示例
```
{"Version":"1.0.0.0","Token":"GMICafmEM0nKmg0Z2syJlQ==","Sign":"nRyhWVlHX3Ff0+KcDMy1qDdT8PNR+ggb3SCqWDDDIbCcSWrgVRt6xTXxe284gYHG9ifGsOH/3vIGESgI4DGvTfj4g/e7tLS/LaNZzHm2TlmNBsPj4ziLH0Ubaxr4tbqr+uk6UrwjQgPqJYVjL3tEPtsT+FHLJZ9R8qulPewDE6PiftUBHD1EUZfpkKm4BAhhlzqthl2bMbZ+i/QSMVpS7PsYoQoX7fCU9ZsIgPVevVlKmTMxs+OWWA==","RequestTime":"2019-04-02T22:01:21+08:00","RequestId":"5a47b637-a2d8-0004-45ec-c250b3602638","Message":null}
```

返回示例
```
{"Version":"1.0.0.0","RequestId":"5a47b637-a2d8-0004-45ec-c250b3602638","RequestTime":"2020-01-09 09:26:23","ResponseTime":"2020-01-09 09:26:23","ResponseId":"f033effc-bbb4-4add-8b58-e6ddffb42fdf","Result":1,"Error":null,"RequestMessage":null,"Message":"[{ \"CountryList\" : [{ \"CountryName\" : \"United States\", \"TwoLetterIsoCode\" : \"US\", \"WarehouseList\" : [\"W0153\", \"W0154\", \"WC3638\"], \"ProvinceList\" : [{ \"ProvinceName\" : \"AA (Armed Forces Americas)\", \"StateCode\" : \"AA\" }, { \"ProvinceName\" : \"AE (Armed Forces Europe)\" }}}
```

## 查询增值服务
此接口可查询线上已启用的增值服务

请求说明：
URL: /api/Order/GetValueAddService

请求参数信息
RequestBody  无

返回参数说明
ResponseBody

| 字段   | 数据类型                               | 描述         |
| ---- | ---------------------------------- | ---------- |
| Data | List\<ValueAddServiceApiSiteModel> | 站点对应增值服务列表 |

ValueAddServiceApiSiteModel

| 字段                       | 数据类型                           | 描述     |
| ------------------------ | ------------------------------ | ------ |
| SiteHosts                | String\[400]                   | 站点地址   |
| ValueAddServiceApiModels | List\<ValueAddServiceApiModel> | 增值服务列表 |

ValueAddServiceApiModel

| 字段                    | 数据类型         | 描述       |
| --------------------- | ------------ | -------- |
| ValueAddServiceCode   | String\[256] | 增值服务编码   |
| ValueAddServiceName   | String\[256] | 增值服务中文名称 |
| ValueAddServiceNameEN | String\[256] | 增值服务英文名称 |

请求示例
```
{"Version": "1.0.0.0", "Token": "SAvWssN0oosaKHjNd/mN2g==", "Sign": "+1P4WrVxbFe/UfMX2gYMFo4uitDh5rdptLhmPbWCcqoHHURNi6Nyy7aC6+h8aOFFjmRkysLgwx1pj1B9BHbmSm0I7Cb3fEwKOj1kGSojKexMjD74faE6mzrLOYLR/vZgZycD/h5Em8cCBV/Ag9jDmhyeiMpQdhgcZN2KEjnj17B7qkUFYlGHg4A+M7f06+r70RMfP3SW/J6uoylqxvb5YSQrGd6rGtAg8XsnPPcw4fCSrpzKHKiuqA==", "RequestTime": "2019-04-08T23:06:29", "RequestId": "a5a9e83f-c0e7-665c-cabc-726588c51b6d", "Message": "{\"Site\":null}"}
```

返回示例
```
{"Version":"1.0.0.0","RequestId":"a5a9e83f-c0e7-665c-cabc-726588c51b6d","RequestTime":"2020-06-28 02:32:53","ResponseTime":"2020-06-28 02:32:54","ResponseId":"baa37015-4598-4eea-adcf-b21a5df27deb","Result":1,"Error":null,"RequestMessage":"{\"Site\":null}","Message":"{\"data\":[{\"SiteHosts\":\"test-dms.eminxing.com\",\"ValueAddServiceApiModels\":[{\"ValueAddServiceCode\":\"C001\",\"ValueAddServiceName\":\"签名\"},{\"ValueAddServiceCode\":\"C003\",\"ValueAddServiceName\":\"18岁验证\"},{\"ValueAddServiceCode\":\"C005\",\"ValueAddServiceName\":\"FDS费用\"}]}]}"}
```

## 查询销售平台
URL: /api/Order/GetSalesPlatform

请求参数信息
无

返回参数说明
ResponseBody
| 字段	  | 数据类型	  | 描述|
|-----------------|-------------------------|----------|
| Data  | List<String>  | 销售平台名称列表|

请求示例

```
{"Version": "1.0.0.0", "Token": "SAvWssN0oosaKHjNd/mN2g==", "Sign": "+1P4WrVxbFe/UfMX2gYMFo4uitDh5rdptLhmPbWCcqoHHURNi6Nyy7aC6+h8aOFFjmRkysLgwx1pj1B9BHbmSm0I7Cb3fEwKOj1kGSojKexMjD74faE6mzrLOYLR/vZgZycD/h5Em8cCBV/Ag9jDmhyeiMpQdhgcZN2KEjnj17B7qkUFYlGHg4A+M7f06+r70RMfP3SW/J6uoylqxvb5YbUixjiwyQCw1ao6Fi0QluM=", "RequestTime": "2019-04-08T23:06:29", "RequestId": "a5a9e83f-c0e7-665c-cabc-726588c51b6d", "Message": "null"}
```

返回示例

```
{"Version":"1.0.0.0","RequestId":"a5a9e83f-c0e7-665c-cabc-726588c51b6d","RequestTime":"2020-06-28 02:33:29","ResponseTime":"2020-06-28 02:33:29","ResponseId":"d95af7c3-b6b7-4dc7-840c-fe9beb7aedc7","Result":1,"Error":null,"RequestMessage":"null","Message":"{\"data\":[\"wish\",\"ebay\"]}"}
```

## 获取商品分类

URL: /api/Product/GetCategory
Http:POST
RequestBody
无
ResponseBody

| 字段            | 数据类型                | 描述     |
|-----------------|-------------------------|----------|
| Store           | String[100]             | 站点     |
| ProductCategory | List\<ProductCategory\> | 分类集合 |

ProductCategory

| 字段              | 数据类型                | 描述         |
|-------------------|-------------------------|--------------|
| Code              | String[400]             | 分类编码     |
| Name              | String[400]             | 分类中文名称 |
| EnName            | String[400]             | 分类英文名称 |
| ChildCategoryList | List\<ProductCategory\> | 子级分类集合 |

请求示例
```     
{"Version":"1.0.0.0","Token":"GMICafmEM0nKmg0Z2syJlQ==","Sign":"nRyhWVlHX3Ff0+KcDMy1qDdT8PNR+ggb3SCqWDDDIbCcSWrgVRt6xTXxe284gYHG9ifGsOH/3vIGESgI4DGvTfj4g/e7tLS/LaNZzHm2TlmNBsPj4ziLH/Vr8rPJeI3iyvHNfJw5QewE6vj24l15XUfrgsVM6p4uZc40FN+K5f5tgM1cLKshpXBr9W05qFdPXGPr3SVNeMg=","RequestTime":"2019-12-27T09:28:30Z","RequestId":"1577438910","Message":null}
```

返回示例
```            
{"Version":"1.0.0.0","RequestId":"1577438910","RequestTime":"2020-01-09 09:34:50","ResponseTime":"2020-01-09 09:34:50","ResponseId":"b8a64469-5932-4711-b754-bad746a9fdb1","Result":1,"Error":null,"RequestMessage":null,"Message":"[{ \"Store\" : \"test-dms.eminxing.com\", \"ProductCategory\" : [{ \"Code\" : \"5381\", \"Name\" : \"服装\", \"ChildCategoryList\" : [{ \"Code\" : \"53810470\", \"Name\" : \"女装\", \"ChildCategoryList\" : [{ \"Code\" : \"538104706087\", \"Name\" : \"裙装\", \"ChildCategoryList\" : null }, { \"Code\" : \"538104706317\", \"Name\" : \"上衣\", \"ChildCategoryList\" : null }, { \"Code\" : \"538104707835\", \"Name\" : \"裤子\", \"ChildCategoryList\" : null }] }, { \"Code\" : \"53811183\", \"Name\" : \"男装\", \"ChildCategoryList\" : [{ \"Code\" : \"538111838154\", \"Name\" : \"上衣\", \"ChildCategoryList\" : null }, { \"Code\" : \"538111833338\", \"Name\" : \"裤子\", \"ChildCategoryList\" : null }] }] }, { \"Code\" : \"5118\", \"Name\" : \"电子产品\", \"ChildCategoryList\" : [{ \"Code\" : \"51182781\", \"Name\" : \"电脑\", \"ChildCategoryList\" : [{ \"Code\" : \"511827817142\", \"Name\" : \"笔记本电脑\", \"ChildCategoryList\" : null }, { \"Code\" : \"511827819014\", \"Name\" : \"台式主机\", \"ChildCategoryList\" : null }, { \"Code\" : \"511827812067\", \"Name\" : \"台式显示屏\", \"ChildCategoryList\" : null }] }, { \"Code\" : \"51181904\", \"Name\" : \"手机111111111111\", \"ChildCategoryList\" : [{ \"Code\" : \"511819043349\", \"Name\" : \"苹果\", \"ChildCategoryList\" : null }, { \"Code\" : \"511819047193\", \"Name\" : \"华为\", \"ChildCategoryList\" : null }] }] }, { \"Code\" : \"9048\", \"Name\" : \"健康与美容\", \"ChildCategoryList\" : [{ \"Code\" : \"90483275\", \"Name\" : \"化妆\", \"ChildCategoryList\" : [{ \"Code\" : \"904832755080\", \"Name\" : \"化妆包箱\", \"ChildCategoryList\" : null }, { \"Code\" : \"904832753017\", \"Name\" : \"化妆工具及配件\", \"ChildCategoryList\" : null }] }, { \"Code\" : \"90480566\", \"Name\" : \"按摩\", \"ChildCategoryList\" : [{ \"Code\" : \"904805668691\", \"Name\" : \"按摩桌椅\", \"ChildCategoryList\" : null }, { \"Code\" : \"904805667495\", \"Name\" : \"按摩器\", \"ChildCategoryList\" : null }] }] }, { \"Code\" : \"4552\", \"Name\" : \"手机及配件\", \"ChildCategoryList\" : [{ \"Code\" : \"45520314\", \"Name\" : \"手机配件\", \"ChildCategoryList\" : [{ \"Code\" : \"455203147137\", \"Name\" : \"电池\", \"ChildCategoryList\" : null }, { \"Code\" : \"455203142452\", \"Name\" : \"充电器＆底座\", \"ChildCategoryList\" : null }] }] }, { \"Code\" : \"4616\", \"Name\" : \"图书\", \"ChildCategoryList\" : [{ \"Code\" : \"46166676\", \"Name\" : \"IT书籍\", \"ChildCategoryList\" : [{ \"Code\" : \"461666764231\", \"Name\" : \"测试技术图书\", \"ChildCategoryList\" : null }] }] }] }, { \"Store\" : \"192.168.109.214:50082\", \"ProductCategory\" : [{ \"Code\" : \"5118\", \"Name\" : \"电子产品\", \"ChildCategoryList\" : [{ \"Code\" : \"51182781\", \"Name\" : \"电脑\", \"ChildCategoryList\" : [{ \"Code\" : \"511827817142\", \"Name\" : \"笔记本电脑\", \"ChildCategoryList\" : null }, { \"Code\" : \"511827819014\", \"Name\" : \"台式主机\", \"ChildCategoryList\" : null }, { \"Code\" : \"511827812067\", \"Name\" : \"台式显示屏\", \"ChildCategoryList\" : null }] }, { \"Code\" : \"51181904\", \"Name\" : \"手机111111111111\", \"ChildCategoryList\" : [{ \"Code\" : \"511819043349\", \"Name\" : \"苹果\", \"ChildCategoryList\" : null }, { \"Code\" : \"511819047193\", \"Name\" : \"华为\", \"ChildCategoryList\" : null }] }] }] }]"}
```

# 查询商品信息 - 商品信息查询

URL: /api/Product/QueryProductDetail
Http:POST

## 请求参数信息
RequestBody

| 字段            | 数据类型          | 是否必填 | 描述                                                                       |
| ------------- | ------------- | ---- | ------------------------------------------------------------------------ |
| Skus          | List\<String> | 否    | 商品编码集合：每次最多30条；商品sku编码、spu编码、更新开始时间、更新结束时间四选一必填                          |
| StartTime     | Datetime?     | 否    | 更新开始时间：精确至年月日，不能大于EndTime , 时间仅不验证时分秒；商品sku编码、spu编码、更新开始时间、更新结束时间四选一必填   |
| EndTime       | Datetime?     | 否    | 更新结束时间：不能小于StartTime , 时间仅精确至年月日，不验证时分秒；商品sku编码、spu编码、更新开始时间、更新结束时间四选一必填 |
| PageIndex     | Int           | 否    | 页码：根据更新时间查询时,PageIndex必须大于0；Skus为空,更新时间不能为空时必填                           |
| WarehouseCode | String        | 否    | 区域编码                                                                     |
| Spus          | List\<String> | 否    | 商品SPU编码集合：指定商品SPU编码查询,每次最多30条；商品sku编码、spu编码、更新开始时间、更新结束时间四选一必填           |


## 返回参数说明：

Responsebody

| 字段              | 数据类型                      | 描述                  |
| --------------- | ------------------------- | ------------------- |
| PageIndex       | Int                       | 当前页码：根据更新时间查询时返回数据  |
| TotalCount      | Int                       | 总数据量：根据更新时间查询时返回数据  |
| PageTotal       | Int                       | 页码总数：根据更新时间查询时返回数据  |
| PageSize        | Int                       | 每页数据量：根据更新时间查询时返回数据 |
| ProductInfoList | List\<ProductDetailModel> | 商品信息集合：实体定义见下文      |

ProductDetailModel

| 字段                                  | 数据类型                                      | 描述                             |
| ----------------------------------- | ----------------------------------------- | ------------------------------ |
| sku                                 | String\[400]                              | 商品编码：若只返回SKU，是因为无商品权限          |
| CategoryFirstCode                   | String\[500]                              | 一级分类编码                         |
| CategoryFirstName                   | String\[500]                              | 一级分类名                          |
| CategorySecondCode                  | String\[500]                              | 二级分类编码                         |
| CategorySecondName                  | String\[500]                              | 二级分类名                          |
| CategoryThirdCode                   | String\[500]                              | 三级分类编码                         |
| CategoryThirdName                   | String\[500]                              | 三级分类名                          |
| CnName                              | longtext                                  | 中文名                            |
| EnName                              | longtext                                  | 英文名                            |
| SpecLength                          | Decimal?\[8,2]                            | 长                              |
| SpecWidth                           | Decimal?\[8,2]                            | 宽                              |
| SpecHeight                          | Decimal?\[8,2]                            | 高                              |
| SpecWeight                          | Decimal?\[8,2]                            | 重                              |
| Published                           | bool                                      | 是否上架                           |
| IsClear                             | bool                                      | 是否清仓                           |
| CreateTime                          | datetime                                  | 创建时间：UTC时间                     |
| UpdateTime                          | Datetime?                                 | 更新时间：UTC时间                     |
| TortInfo                            | TortDetail                                | 侵权信息：实体定义见下文                   |
| GoodsImageList                      | List\<GoodsImage>                         | 图片明细列表：实体定义见下文                 |
| GoodsDescriptionList                | List\<GoodsDescription>                   | 商品描述列表：实体定义见下文                 |
| FreightAttrCode                     | string                                    | 货运属性编码                         |
| FreightAttrName                     | string                                    | 货运属性名称                         |
| GoodsAttachmentList                 | List\<GoodsAttachment>                    | 商品附件明细列表：实体定义见下文               |
| SiteList                            | List\<String>                             | 适用站点                           |
| IsProductAuth                       | bool                                      | 是否有商品权限                        |
| ProductSiteModelList                | List\<ProductSiteModel>                   | 站点明细列表：实体定义见下文                 |
| PlatformCommodityCode               | String\[255]                              | 平台商品编码                         |
| Commoditywarehousemode              | Int                                       | 商品仓库模式：0-认证仓 1-平台仓             |
| BrandName                           | String\[400]                              | 品牌名称                           |
| Spu                                 | String\[400]                              | 商品SPU编码                        |
| ProductAttributeList                | List\<ProductAttributeModel>              | 商品SPU属性列表：实体定义见下文              |
| DistributionLimit                   | Int                                       | 分销平台限制，0:不允许分销;1:无限制           |
| GoodsDistributionPlatformDetailList | List\<ExtGoodsDistributionPlatformDetail> | 禁售列表（DistributionLimit=0时候才响应） |
| ProductReturnAddressList            | List\<ExtNbpGoodsReturnAddress>           | 商品退货地址列表，实体定义见下文               |
| CategoryFirstNameEN                 | String\[500]                              | 一级分类英文名                        |
| CategorySecondNameEN                | String\[500]                              | 二级分类英文名                        |
| CategoryThirdNameEN                 | String\[500]                              | 三级分类英文名                        |
| Url                                 | String\[400]                              | 访问路径（相对路径）                     |

ExtGoodsDistributionPlatformDetail

| 字段                          | 数据类型         | 描述      |
| --------------------------- | ------------ | ------- |
| DistributionPlatformValue   | String\[255] | 分销平台值   |
| DistributionPlatformDisplay | String\[255] | 分销平台显示值 |

TortDetail

| 字段          | 数据类型         | 描述                    |
| ----------- | ------------ | --------------------- |
| TortReasons | String\[500] | 侵权原因                  |
| TortTypes   | String\[500] | 侵权类型                  |
| TortStatus  | Int          | 侵权状态：0-待审核,1-侵权,2-不侵权 |

GoodsImage

| 字段          | 数据类型         | 描述   |
| ----------- | ------------ | ---- |
| ImageUrl    | String\[500] | 图片地址 |
| Sort        | Int          | 排序号  |
| IsMainImage | Bool         | 是否主图 |

GoodsDescription

| 字段                            | 数据类型                             | 描述            |
| ----------------------------- | -------------------------------- | ------------- |
| Title                         | String\[500]                     | 标题            |
| GoodsDescriptionKeywordList   | List\<GoodsDescriptionKeyword>   | 关键字列表：实体定义见下文 |
| GoodsDescriptionLabelList     | List\<GoodsDescriptionLabel>     | 标签列表：实体定义见下文  |
| GoodsDescriptionParagraphList | List\<GoodsDescriptionParagraph> | 段落列表：实体定义见下文  |

GoodsDescriptionKeyword

| 字段      | 数据类型         | 描述  |
| ------- | ------------ | --- |
| KeyWord | String\[500] | 关键字 |

GoodsDescriptionLabel

| 字段        | 数据类型         | 描述   |
| --------- | ------------ | ---- |
| LabelName | String\[500] | 标签名称 |

GoodsDescriptionParagraph

| 字段               | 数据类型         | 描述   |
| ---------------- | ------------ | ---- |
| ParagraphName    | String\[500] | 段落名称 |
| SortNo           | int          | 排序号  |
| GoodsDescription | longtext     | 段落描述 |

GoodsAttachment

| 字段             | 数据类型         | 描述   |
| -------------- | ------------ | ---- |
| AttachmentName | String\[500] | 附件名称 |
| AttachmentUrl  | String\[500] | 附件地址 |
| AttachmentSize | String\[500] | 附件大小 |

ProductSiteModel

| 字段                | 数据类型          | 描述   |
| ----------------- | ------------- | ---- |
| SiteHosts         | String        | 站点地址 |
| WarehouseCodeList | List\<String> | 区域列表 |

ProductAttributeModel

| 字段              | 数据类型   | 描述     |
| --------------- | ------ | ------ |
| AttributeCode   | String | 属性编码   |
| AttributeName   | String | 属性名称中文 |
| AttributeNameEn | String | 属性名称英文 |
| AttributeValue  | String | 属性值    |

ExtNbpGoodsReturnAddress

| 字段                  | 数据类型   | 描述       |
| ------------------- | ------ | -------- |
| WarehouseCode       | String | 区域编码     |
| WarehouseName       | String | 区域名称     |
| ThirdpartyStockCode | String | 退货仓库编码   |
| AddressCode         | String | 退货仓库地址编码 |
| ThirdpartyStockName | String | 退货仓库名称   |
| CountryCode         | String | 国家       |
| Province            | String | 洲省       |
| City                | String | 城市       |
| Address1            | String | 街道地址     |
| PostCode            | String | 邮编       |
| ReceiveMan          | String | 联系人      |
| Tel                 | String | 电话       |

ExtNbpGoodsSendAddress

| 字段               | 数据类型   | 描述    |
| ---------------- | ------ | ----- |
| WarehouseCode    | String | 区域编码  |
| WarehouseName    | String | 区域名称  |
| AddressCode      | String | 地址编码  |
| StockCountryCode | String | 国家    |
| StockProvince    | String | 州省    |
| StockCity        | String | 城市    |
| StockAddress     | String | 街道地址  |
| StockAddressTwo  | String | 街道地址2 |
| StockPostCode    | String | 邮编    |
| ReceiveMan       | String | 联系人   |
| Tel              | String | 电话    |

ExtNbpGoodsSendAddress
| 字段               | 数据类型   | 描述    |
| ---------------- | ------ | ----- |
| WarehouseCode    | String | 区域编码  |
| WarehouseName    | String | 区域名称  |
| AddressCode      | String | 地址编码  |
| StockCountryCode | String | 国家    |
| StockProvince    | String | 州省    |
| StockCity        | String | 城市    |
| StockAddress     | String | 街道地址  |
| StockAddressTwo  | String | 街道地址2 |
| StockPostCode    | String | 邮编    |
| ReceiveMan       | String | 联系人   |
| Tel              | String | 电话    |


### 请求示例

```
{"Version":"1.0.0.0","Token":"yOJMuEApHsIQsmQevlNRnw==","Sign":"hvc+IIrqahqtAFAB26yrgiqvkhv1RSmV/EPPQei8PEnT2xIgFFgkkoFAlxcBRkERpABa4S5Yt4QSqg4iJL+800YYuJ3UFnbAJATfnyi+xJNMpYKfkVcEwMam4GBQbvNH/3LxOipyoJiD4heiI5BjW3O1z/TeqrCC456OwNxvMclldYonU/na71f/3ra4iJ2yxRo/H+8HD6gFw2kmpy0yA9Rjr9R6W5xKN0JDyneYf236l8rzMtV5VrhbPy1YrKsYWIgsdErTxSo2gQmgETULvQ==","RequestTime":"2019-10-28T10:01:13.7302+08:00","RequestId":"d1f5a5e5-fe38-4c3b-821c-f56f7a613129","Message":"{\"Skus\":[\"62387071\"]}"}

{"Version": "1.0.0.0","Token": "gzI8e/wGySu0Rz/2yXDG8A==","Sign": "V/uxT3UwdpvIPv+qLgdI/5p8wY7pB2ekXrb8ISQav35eVmUyJmhdspKbSDDS2dB4JO/Nb2trFvbDpB/+2pwd5DVAuyjHpbZ2YM0JMtoos0qQk0k8fucff8BSniIDZqSKjO1J1digo4tSt6HWq6tcJcSEKeZTq/OflnSOF2KBFnL3oYps/+XHWUA6zTA8+Z8ty4LreLeugc/IU8JT9d2cn+0V9F2GOqsze3kotUz6B7HPaAdq8gCvsRXiEbYV7iA4IaRTpyLWuYW8A4k7kOHqBJfG5VeqVnBbqnmaRrsYIYLdTcXOTzwRGgK+q3McEXch+4/YY97BHxYS8MoobGO1xhk55TxHrUekrJuO5MlH1hVHN4SEXK/6zSDKRUzpyk+ZPS00tTK0920=","RequestTime": "2022-01-28T08:08:08","RequestId": "a5a9e83f-c0e7-665c-cabc-726588c51b6d","Message": "{\"Skus\":[],\"Spus\":[],\"Site\":null,\"WarehouseCode\":null,\"StartTime\":null,\"EndTime\":\"2022-01-28\",\"PageIndex\":1}"}
```

### 返回示例
```
{"Version":"1.0.0.0","RequestId":"d1f5a5e5-fe38-4c3b-821c-f56f7a613129","RequestTime":"2020-02-24 09:46:34","ResponseTime":"2020-02-24 09:46:34","ResponseId":"0c4902b2-0d19-4cca-9306-1b9122c47068","Result":1,"Error":null,"RequestMessage":"{\"Skus\":[\"62387071\"]}","Message":"{\"ProductInfoList\":[{\"Sku\":\"62387071\",\"CategoryFirstCode\":\"5118\",\"CategoryFirstName\":\"电子产品\",\"CategorySecondCode\":\"51182781\",\"CategorySecondName\":\"电脑\",\"CategoryThirdCode\":\"511827817142\",\"CategoryThirdName\":\"笔记本电脑\",\"CnName\":\"[Test]Fashion Women Geometric  Ear Studs earrings 138866\",\"EnName\":\"test-描述00132423\",\"SpecLength\":1.00,\"SpecWidth\":1.00,\"SpecHeight\":1.00,\"SpecWeight\":434.00,\"Published\":true,\"IsClear\":false,\"FreightAttrCode\":\"X0003\",\"FreightAttrName\":\"普货\",\"PlatformCommodityCode\":\"C-qx11217\",\"CommodityWarehouseMode\":0,\"GoodsImageList\":[{\"ImageUrl\":\"https://imgtest.saleyee.cn/Resources/GoodsImages/2021/202111/202111151526066171_7f7de650-0f35-414b-b559-ce3c99c9be8d.jpg\",\"ThumbnailUrl\":\"https://imgtest.saleyee.cn/Resources/GoodsImages/2021/202111/202111151526069761_f2c193aa-4ac3-40f0-b099-3cbad65fb034.Jpeg\",\"Sort\":0}],\"GoodsAttachmentList\":[{\"AttachmentName\":\"商品说明书\",\"AttachmentUrl\":\"https://imgtest.saleyee.cn/Resources/GoodsAttachment/2021/202111/201911151525514122_214081a1-9558-424e-b5d1-c07329fa7582.docx\",\"AttachmentSize\":\"514.0\"}],\"GoodsDescriptionList\":[{\"Title\":\"test-描述00132423\",\"GoodsDescriptionKeywordList\":[{\"KeyWord\":\"Introduction\"},{\"KeyWord\":\"rerewrwer\"}],\"GoodsDescriptionLabelList\":[{\"LabelName\":\"英文\"}],\"GoodsDescriptionParagraphList\":[{\"ParagraphName\":\"首段描述\",\"SortNo\":1,\"GoodsDescription\":\"<p><strong>Introduction:</strong><br/><br/>Small and lovely design, smooth lines, full style, suitable for all kinds of clothing collocationweaswdasdasd</p>\"},{\"ParagraphName\":\"特征\",\"SortNo\":2,\"GoodsDescription\":\"<p><strong>Features:</strong><br/><br/>1. High Quality and New Conditions<br/><br/>2. High quality metallic silver material, not easy to rust.ddd</p>\"},{\"ParagraphName\":\"规格\",\"SortNo\":3,\"GoodsDescription\":\"<p>ddddd<br/></p>\"},{\"ParagraphName\":\"包装内含\",\"SortNo\":4,\"GoodsDescription\":\"<p><strong>Specifications:</strong><br/><br/>1. Shape: Heart-shaped, cherry-shaped and other styles<br/><br/>2. Weight: 10-20 grams<br/><br/>Packaging includes:<br/><br/>1X exquisite packing box, 1x transparent earplug</p><p><br/></p>\"}]}],\"CreateTime\":\"2019-07-23T11:05:35+08:00\",\"UpdateTime\":\"2020-02-24T11:47:05+08:00\",\"TortInfo\":{\"TortReasons\":\"\",\"TortTypes\":\"\",\"TortStatus\":2},\"IsProductAuth\":true,\"ProductSiteModelList\":[{\"SiteHosts\":\"test-dms.eminxing.com\",\"WarehouseCodeList\":[\"WC1257\"]},{\"SiteHosts\":\"192.168.109.214:50082\",\"WarehouseCodeList\":[\"WC1257\"]}]}],\"PageIndex\":0,\"TotalCount\":0,\"PageTotal\":0,\"PageSize\":0}"}

{
    "Version": "1.0.0.0",
    "RequestId": "a5a9e83f-c0e7-665c-cabc-726588c51b6d",
    "RequestTime": "2022-01-29 02:33:19",
    "ResponseTime": "2022-01-29 02:33:21",
    "ResponseId": "fc47c464-89cb-4332-b57d-494764612a27",
    "Result": 1,
    "Error": null,
    "RequestMessage": "{\"Skus\":[],\"Spus\":[],\"Site\":null,\"WarehouseCode\":null,\"StartTime\":null,\"EndTime\":\"2022-01-28\",\"PageIndex\":1}",
    "Message": "{\"ProductInfoList\":[{\"Sku\":\"05376156\",\"CategoryFirstCode\":null,\"CategoryFirstName\":null,\"CategoryFirstNameEN\":null,\"CategorySecondCode\":null,\"CategorySecondName\":null,\"CategorySecondNameEN\":null,\"CategoryThirdCode\":null,\"CategoryThirdName\":null,\"CategoryThirdNameEN\":null,\"CnName\":null,\"EnName\":null,\"SpecLength\":null,\"SpecWidth\":null,\"SpecHeight\":null,\"SpecWeight\":null,\"Published\":false,\"IsClear\":false,\"FreightAttrCode\":null,\"FreightAttrName\":null,\"PlatformCommodityCode\":null,\"CommodityWarehouseMode\":0,\"GoodsImageList\":[],\"GoodsAttachmentList\":[],\"GoodsDescriptionList\":[],\"CreateTime\":\"0001-01-01T00:00:00\",\"UpdateTime\":null,\"TortInfo\":{\"TortReasons\":null,\"TortTypes\":null,\"TortStatus\":0},\"IsProductAuth\":false,\"ProductSiteModelList\":[],\"BrandName\":null,\"Spu\":\"BQUQHBVDEM\",\"ProductSpuModelList\":[],\"ProductAttributeList\":[]},{\"Sku\":\"06049277\",\"CategoryFirstCode\":\"9048\",\"CategoryFirstName\":\"健康和美容\",\"CategoryFirstNameEN\":\"jiankangyumeirong\",\"CategorySecondCode\":\"90483275\",\"CategorySecondName\":\"化妆\",\"CategorySecondNameEN\":\"huazhuang\",\"CategoryThirdCode\":\"904832755080\",\"CategoryThirdName\":\"化妆包箱\",\"CategoryThirdNameEN\":\"huazhuangbaoxiang\",\"CnName\":\"13028264\",\"EnName\":\"4\",\"SpecLength\":2.00,\"SpecWidth\":2.00,\"SpecHeight\":2.00,\"SpecWeight\":2.00,\"Published\":true,\"IsClear\":false,\"FreightAttrCode\":\"X0003\",\"FreightAttrName\":\"普货\",\"PlatformCommodityCode\":\"C20040829101\",\"CommodityWarehouseMode\":0,\"GoodsImageList\":[{\"ImageUrl\":\"https://imgtest.goten.com/Resources/GoodsImages//2020/202012/202012151154026854_2e385252-af06-4efe-a6f9-75274d9ff8a7.jpg\",\"ThumbnailUrl\":\"https://imgtest.goten.com/Resources/GoodsImages//2020/202012/202012151154040165_2bd38ba2-6653-44e5-ab1b-9fd6adc21142.Jpeg\",\"Sort\":0,\"IsMainImage\":true,\"GooodsThumbnailList\":[{\"StandardLength\":350,\"StandardWidth\":350,\"StandardImageUrl\":\"https://imgtest.goten.com/Resources/GoodsImages//2020/202012/0ea9da29-4680-42cb-b156-434888ad747e.Jpeg\"},{\"StandardLength\":600,\"StandardWidth\":600,\"StandardImageUrl\":\"https://imgtest.goten.com/Resources/GoodsImages//2020/202012/cae5038b-8bd8-4a8c-b223-2109ec5792f1.Jpeg\"}]},{\"ImageUrl\":\"https://imgtest.goten.com/Resources/GoodsImages//2020/202012/202012151154026844_b5451f20-3b31-4912-9978-63b93a239ec4.jpg\",\"ThumbnailUrl\":\"https://imgtest.goten.com/Resources/GoodsImages//2020/202012/202012151154040165_f0ab0aaf-a5f6-4587-af76-1d6b630271f0.Jpeg\",\"Sort\":1,\"IsMainImage\":false,\"GooodsThumbnailList\":[{\"StandardLength\":350,\"StandardWidth\":350,\"StandardImageUrl\":\"https://imgtest.goten.com/Resources/GoodsImages//2020/202012/9f6dcbe2-2264-4322-a872-7077c3627c02.Jpeg\"},{\"StandardLength\":600,\"StandardWidth\":600,\"StandardImageUrl\":\"https://imgtest.goten.com/Resources/GoodsImages//2020/202012/79685047-3c16-422c-a7d0-3466e04c5683.Jpeg\"}]},{\"ImageUrl\":\"https://imgtest.goten.com/Resources/GoodsImages//2020/202012/202012151154026844_c3fa8362-8c63-42c6-9942-ef25e9a5bcb7.jpg\",\"ThumbnailUrl\":\"https://imgtest.goten.com/Resources/GoodsImages//2020/202012/202012151154040155_109d692f-00ff-478a-aea0-5a87c2bfa22b.Jpeg\",\"Sort\":2,\"IsMainImage\":false,\"GooodsThumbnailList\":[{\"StandardLength\":350,\"StandardWidth\":350,\"StandardImageUrl\":\"https://imgtest.goten.com/Resources/GoodsImages//2020/202012/7771bddc-b499-4132-8701-ed801a4fe998.Jpeg\"},{\"StandardLength\":600,\"StandardWidth\":600,\"StandardImageUrl\":\"https://imgtest.goten.com/Resources/GoodsImages//2020/202012/77ac9d84-a5dd-4414-8889-e730be717c01.Jpeg\"}]},{\"ImageUrl\":\"https://imgtest.goten.com/Resources/GoodsImages//2020/202012/202012151154401246_a3aa9ea4-7659-4fad-af0b-edb106f377c2.jpg\",\"ThumbnailUrl\":\"https://imgtest.goten.com/Resources/GoodsImages//2020/202012/202012151154406907_25ada0bf-f4f3-4161-894e-7a02a28f55ab.Jpeg\",\"Sort\":3,\"IsMainImage\":false,\"GooodsThumbnailList\":[{\"StandardLength\":350,\"StandardWidth\":350,\"StandardImageUrl\":\"https://imgtest.goten.com/Resources/GoodsImages//2020/202012/1a5f88a6-b35c-4e2f-95d0-e0476568422e.Jpeg\"},{\"StandardLength\":600,\"StandardWidth\":600,\"StandardImageUrl\":\"https://imgtest.goten.com/Resources/GoodsImages//2020/202012/1e8ac7f2-997e-430e-b346-d9458d881d90.Jpeg\"}]},{\"ImageUrl\":\"https://imgtest.goten.com/Resources/GoodsImages//2020/202012/202012151154401146_fd5deb24-0837-400e-a027-7290ea6dcfc6.jpg\",\"ThumbnailUrl\":\"https://imgtest.goten.com/Resources/GoodsImages//2020/202012/202012151154404557_0f1da68c-71f1-4668-a043-ecb4aecde06c.Jpeg\",\"Sort\":4,\"IsMainImage\":false,\"GooodsThumbnailList\":[{\"StandardLength\":350,\"StandardWidth\":350,\"StandardImageUrl\":\"https://imgtest.goten.com/Resources/GoodsImages//2020/202012/5dcd5a53-4934-4206-bff3-ccec681e6ea2.Jpeg\"},{\"StandardLength\":600,\"StandardWidth\":600,\"StandardImageUrl\":\"https://imgtest.goten.com/Resources/GoodsImages//2020/202012/19e5cd21-0906-4c8e-905b-9b099d39bf43.Jpeg\"}]},{\"ImageUrl\":\"https://imgtest.goten.com/Resources/GoodsImages//2020/202012/202012151154401266_6e9a362c-74c6-4dae-b461-7dcfcd2d7290.jpg\",\"ThumbnailUrl\":\"https://imgtest.goten.com/Resources/GoodsImages//2020/202012/202012151154403867_35803171-85f1-4791-8d5a-2c381728fb8c.Jpeg\",\"Sort\":5,\"IsMainImage\":false,\"GooodsThumbnailList\":[{\"StandardLength\":350,\"StandardWidth\":350,\"StandardImageUrl\":\"https://imgtest.goten.com/Resources/GoodsImages//2020/202012/78ab21a1-36dc-48b9-ac10-952044e9fff4.Jpeg\"},{\"StandardLength\":600,\"StandardWidth\":600,\"StandardImageUrl\":\"https://imgtest.goten.com/Resources/GoodsImages//2020/202012/454b7e9a-80a0-4fff-8ba1-e8e1548ca017.Jpeg\"}]}],\"GoodsAttachmentList\":[],\"GoodsDescriptionList\":[{\"Title\":\"4\",\"GoodsDescriptionKeywordList\":[{\"KeyWord\":\"4\"}],\"GoodsDescriptionLabelList\":[{\"LabelName\":\"英文\"}],\"GoodsDescriptionParagraphList\":[{\"ParagraphName\":\"首段描述\",\"SortNo\":1,\"GoodsDescription\":\"<p>2</p>\"}]}],\"CreateTime\":\"2020-04-10T10:15:03\",\"UpdateTime\":\"2021-06-09T15:14:39\",\"TortInfo\":{\"TortReasons\":null,\"TortTypes\":null,\"TortStatus\":2},\"IsProductAuth\":true,\"ProductSiteModelList\":[{\"SiteHosts\":\"test-dms.eminxing.com\",\"WarehouseCodeList\":[\"A162996\"]}],\"BrandName\":\"毒奶粉\",\"Spu\":\"BUPRNUMKEI\",\"ProductSpuModelList\":[{\"AttributeCode\":\"SX002\",\"AttributeName\":\"尺码\",\"AttributeNameEn\":\"尺码\",\"AttributeValue\":\"s\"},{\"AttributeCode\":\"SX001\",\"AttributeName\":\"色彩\",\"AttributeNameEn\":\"色彩\",\"AttributeValue\":\"红色\"}],\"ProductAttributeList\":[{\"AttributeCode\":\"SX002\",\"AttributeName\":\"尺码\",\"AttributeNameEn\":\"尺码\",\"AttributeValue\":\"s\"},{\"AttributeCode\":\"SX001\",\"AttributeName\":\"色彩\",\"AttributeNameEn\":\"色彩\",\"AttributeValue\":\"红色\"}]},\"PageIndex\":1,\"TotalCount\":94,\"PageTotal\":2,\"PageSize\":50}"
```

# 库存查询（v2.0）

URL: /api/Product/QueryProductInventoryV2
Http:POST
RequestBody
PS：对比 1.0 接口，增加了按时间范围查询的支持。拥有更好的查询效率。

##请求参数信息

RequestBody

| 字段                   | 数据类型          | 是否必填 | 描述                                                                            |
| -------------------- | ------------- | ---- | ----------------------------------------------------------------------------- |
| SkuList              | List\<String> | 否    | 商品编码集合：一次最多30条（SkuList、SpuList、StartUpdateTimeOnUtc、EndUpdateTimeOnUtc四选一必填）  |
| SpuList              | List\<String> | 否    | SPU编码集合：一次最多30条（SkuList、SpuList、StartUpdateTimeOnUtc、EndUpdateTimeOnUtc四选一必填） |
| WarehouseCode        | String        | 否    | 区域编码：不填则返回该客户认证的站点的所有区域                                                       |
| StartUpdateTimeOnUtc | DateTime?     | 否    | 起始库存更新时间（SkuList、SpuList、StartUpdateTimeOnUtc、EndUpdateTimeOnUtc四选一必填）        |
| EndUpdateTimeOnUtc   | DateTime?     | 否    | 结束库存更新时间（SkuList、SpuList、StartUpdateTimeOnUtc、EndUpdateTimeOnUtc四选一必填）        |
| NextToken            | String        | 否    | 下一页Token（当NextToken有值且有效时, 其他所有请求参数值将被覆盖为初始页的请求参数值）                           |

## 返回参数说明

ResponseBody

| 字段        | 数据类型                                      | 描述             |
| --------- | ----------------------------------------- | -------------- |
| NextToken | String                                    | 下一页token       |
| DataList  | List\<QueryProductInventoryResponseModel> | 商品信息集合：实体定义见下文 |

QueryProductInventoryResponseModel

| 字段                       | 数据类型                             | 描述                |
| ------------------------ | -------------------------------- | ----------------- |
| Sku                      | String\[400]                     | 商品编码              |
| Spu                      | String\[400]                     | SPU编码             |
| ProductInventorySiteList | List\<ProductInventorySiteModel> | 用户已认证站点集合：实体定义见下文 |

ProductInventorySiteModel

| 字段                   | 数据类型                         | 描述           |
| -------------------- | ---------------------------- | ------------ |
| SiteHosts            | String\[400]                 | 站点           |
| ProductInventoryList | List\<ProductInventoryModel> | 库存集合：实体定义见下文 |

ProductInventoryModel

| 字段                       | 数据类型         | 描述                    |
| ------------------------ | ------------ | --------------------- |
| StockCode                | String\[32]  | 区域编码                  |
| StockName                | String\[255] | 区域名称                  |
| Qty                      | Int          | 可用库存                  |
| TotalStockThisMonthADAFS | Int          | 总仓本月日均销售可售天数          |
| MinExpectedArrivalDate   | DateTime?    | 最小预计到货日期              |
| DistributionMode         | Int          | 配送模式:0、海外仓配送，1、国内直邮配送 |
| MakeStockQty             | Int          | 圈货可用库存                |

## 请求示例
```
NextToken传空，返回信息获取到下一次的NextToken值；{"Version":"1.0.0.0","Token":"gzI8e/wGySu0Rz/2yXDG8A==","Sign":"V/uxT3UwdpvIPv+qLgdI/5p8wY7pB2ekXrb8ISQav35eVmUyJmhdspKbSDDS2dB4JO/Nb2trFvbDpB/+2pwd5IaK23t/vnLv51FT+XzUNHE/OXNpxa8k2BnocStehf2al9NW8CMsYew6rD1O68+wLyafr9lN3uA9mqeWf9D5PQ5PngpWlvdXqdf+mTPLN7kq","Message":"{\"StartUpdateTimeOnUtc\":\"2021-01-30\",\"NextToken\":null}"}

NextToken传值：{"Version":"1.0.0.0","Token":"gzI8e/wGySu0Rz/2yXDG8A==","Sign":"V/uxT3UwdpvIPv+qLgdI/5p8wY7pB2ekXrb8ISQav35eVmUyJmhdspKbSDDS2dB4JO/Nb2trFvbDpB/+2pwd5IaK23t/vnLv51FT+XzUNHE/OXNpxa8k2BnocStehf2al9NW8CMsYew6rD1O68+wLyafr9lN3uA9mqeWf9D5PQ5MspNB+cvxJNoqIoc87mXLgH3CYHFj5xV5mxsL6VsQjr7QrFQ4dBovozdvMkRXViyyyfEVjPdqBQUy11XMf2YE3WRFD1hVJn36JiDOoJqGc6k+ZyrccJn7cFu+DJN+Kdmyt6lrfwf8ltLCEI/v5Qjl4Aqh4ux6u3X4/9jfxPidGzVRgq046IhWeKN084lQaWjOrflpoAxFMw4aEa5/EnboKnC73RegPvnHg5GBfXigJFvr4Ye0AsCl0CI0AhvqFE1LmpQS9K14nHIhuwpfs8hQvpPnLygtCA831Vi4rpfXyGM+mxM+IkVEbLE+GETogSRRmUcIXVr/4J/zaSMFZsbQ","Message":"{\"StartUpdateTimeOnUtc\":\"2021-01-30\",\"NextToken\":\"8B3Ucx992WUwEbki1v58tkIRGRz7hOcpnJv1+yD7lamPIGlKclUFKRi5MHNHyxurY2v8AhUIJ9KaZW4mjgmR+Dt+frSbdtQHZxQ0JOekw4pODx4Pj40bnIQqyTDlhZGQ4nrb3fwH3/wkiQaoC6EAsQ1nty5RcjSU18y8wqL8SFgPw1pDInvoENQR0EvPXf6WGpXec512KjeUA9mvJSepoGJdm1eNnqp4s6K6BccH1qE=\"}"}
```

## 返回示例
``
{"Version":"1.0.0.0","RequestId":null,"RequestTime":"2022-06-20 06:08:54","ResponseTime":"2022-06-20 06:08:55","ResponseId":"48da8f2b-ea16-4184-9497-206c08f75bff","Result":1,"Error":null,"RequestMessage":"{\"StartUpdateTimeOnUtc\":\"2021-01-30\",\"NextToken\":null}","Message":"{\"DataList\":[{\"Sku\":\"06049277\",\"Spu\":\"BUPRNUMKEI\",\"ProductInventorySiteList\":[{\"SiteHosts\":\"test-dms.eminxing.com\",\"ProductInventoryList\":[{\"StockCode\":\"A162996\",\"StockName\":\"US\",\"Qty\":0,\"TotalStockThisMonthADAFS\":0,\"MinExpectedArrivalDate\":null,\"DistributionMode\":0}]}]},{\"Sku\":\"72153613\",\"Spu\":\"BBJEHW9K7Y\",\"ProductInventorySiteList\":[]},{\"Sku\":\"87824548\",\"Spu\":\"BDMCKBPZRM\",\"ProductInventorySiteList\":[]},{\"Sku\":\"53089635\",\"Spu\":\"B4VXWMRDWV\",\"ProductInventorySiteList\":[{\"SiteHosts\":\"test-dms.eminxing.com\",\"ProductInventoryList\":[{\"StockCode\":\"A162996\",\"StockName\":\"US\",\"Qty\":366,\"TotalStockThisMonthADAFS\":0,\"MinExpectedArrivalDate\":null,\"DistributionMode\":0}]}]},{\"Sku\":\"46067446\",\"Spu\":\"BVA2GA4CQ9\",\"ProductInventorySiteList\":[]}],\"NextToken\":\"8B3Ucx992WUwEbki1v58tkIRGRz7hOcpnJv1+yD7lamPIGlKclUFKRi5MHNHyxurY2v8AhUIJ9KaZW4mjgmR+Dt+frSbdtQHZxQ0JOekw4pODx4Pj40bnIQqyTDlhZGQ4nrb3fwH3/wkiQaoC6EAsQ1nty5RcjSU18y8wqL8SFgPw1pDInvoENQR0EvPXf6WGpXec512KjeUA9mvJSepoGJdm1eNnqp4s6K6BccH1qE=\"}"}
``

# 价格查询（v2.0）
URL: /api/Product/QueryProductInventoryV2
Http:POST
RequestBody
PS：对比 1.0 接口，增加了按时间范围查询的支持。拥有更好的查询效率。

## 请求参数信息
RequestBody

| 字段                   | 数据类型          | 是否必填 | 描述                                                                                 |
| -------------------- | ------------- | ---- | ---------------------------------------------------------------------------------- |
| SkuList              | List\<String> | 否    | 商品编码集合：不能为空，不能超过30条（SkuList、SpuList、StartUpdateTimeOnUtc、EndUpdateTimeOnUtc四选一必填）  |
| SpuList              | List\<String> | 否    | SPU编码集合：不能为空，不能超过30条（SkuList、SpuList、StartUpdateTimeOnUtc、EndUpdateTimeOnUtc四选一必填） |
| WarehouseList        | List\<String> | 否    | 区域编码集合：可多个，不填则回传所有有权限的价格                                                           |
| StartUpdateTimeOnUtc | DateTime?     | 否    | 起始价格更新时间（SkuList、SpuList、StartUpdateTimeOnUtc、EndUpdateTimeOnUtc四选一必填）             |
| EndUpdateTimeOnUtc   | DateTime?     | 否    | 结束价格更新时间（SkuList、SpuList、StartUpdateTimeOnUtc、EndUpdateTimeOnUtc四选一必填）             |
| NextToken            | String        | 否    | 下一页Token（非必填，当NextToken有值且有效时, 其他所有请求参数值将被覆盖为初始页的请求参数值）                            |

## 返回参数说明

ResponseBody

| 字段      | 数据类型                               | 描述          |
| ------- | ---------------------------------- | ----------- |
| Message | QueryProductPricePageResponseModel | 响应基类Message |

QueryProductPricePageResponseModel

| 字段                      | 数据类型                       | 描述       |
| ----------------------- | -------------------------- | -------- |
| NextToken               | String                     | 下一页token |
| Sku                     | String\[400]               | 商品编码     |
| Spu                     | String\[400]               | SPU编码    |
| WarehousePriceList      | List\<WarehousePrice>      | 区域价格列表   |
| MakeStockBatchPriceList | List\<MakeStockBatchPrice> | 圈货批次价格列表 |
| TaxRateList             | List\<TaxRate>             | 税率版本列表   |

WarehousePrice

| 字段                    | 数据类型          | 描述                        |
| --------------------- | ------------- | ------------------------- |
| Store                 | String\[100]  | 站点                        |
| StockCode             | String\[32]   | 区域编码                      |
| LogisticsProductCode  | String\[32]   | 平台物流产品编码                  |
| OriginalPrice         | Decimal(11,2) | 原价                        |
| SellingPrice          | Decimal(11,2) | 售价                        |
| ValidityTimeOnUtc     | DateTime      | 会员等级有效期：UTC时间             |
| PromotionsLimitedTime | DateTime?     | 促销活动有效期：UTC时间             |
| PromotionsQty         | Int?          | 促销活动剩余库存数量,为null表示活动库存不限制 |
| CurrencyCode          | String\[8]    | 币别编码：如USD                 |
| LogisticsProductName  | String\[100]  | 平台物流产品名称                  |

MakeStockBatchPrice

| 字段                | 数据类型          | 描述        |
| ----------------- | ------------- | --------- |
| Store             | String\[100]  | 站点        |
| BatchNo           | String\[100]  | 圈货批次号     |
| MakeStockPrice    | Decimal(11,2) | 圈货价格      |
| SellingPrice      | Decimal(11,2) | 实际售价      |
| ValidityTimeOnUtc | DateTime      | 有效期：UTC时间 |
| CurrencyCode      | String\[8]    | 币别编码：如USD |

TaxRateList

| 字段                | 数据类型               | 描述                 |
| ----------------- | ------------------ | ------------------ |
| StockCode         | String\[32]        | 区域编码               |
| VersionNumber     | String\[32]        | 版本编码               |
| TaxCategoryName   | String\[100]       | 税种名称               |
| DistributionType  | Int                | 分销商类型(0-有税号 1-无税号) |
| EffectiveDateTime | DateTime           | 生效时间：UTC时间         |
| TaxRateRuleList   | List\<TaxRateRule> | 税率规则               |

TaxRateRuleList

| 字段                 | 数据类型          | 描述     |
| ------------------ | ------------- | ------ |
| ReceiveCountryCode | String\[32]   | 收货国家编码 |
| TaxRate            | Decimal(11,2) | 税率     |

## 请求示例

```
首次请求，NextToken值为空，请求后获取到NextToken值作为下一次的请求值；
{"Version":"1.0.0.0","Token":"gzI8e/wGySu0Rz/2yXDG8A==","Sign":"V/uxT3UwdpvIPv+qLgdI/5p8wY7pB2ekXrb8ISQav35eVmUyJmhdspKbSDDS2dB4JO/Nb2trFvbDpB/+2pwd5IaK23t/vnLv51FT+XzUNHE/OXNpxa8k2BnocStehf2al9NW8CMsYew6rD1O68+wLyafr9lN3uA9mqeWf9D5PQ5PngpWlvdXqdf+mTPLN7kq","Message":"{\"StartUpdateTimeOnUtc\":\"2021-01-30\",\"NextToken\":null}"}
```
## 返回示例

```
{"Version":"1.0.0.0","RequestId":null,"RequestTime":"2022-06-28 06:23:23","ResponseTime":"2022-06-28 06:23:26","ResponseId":"d7d38a87-b79f-49ee-a68c-fa3969cc20cc","Result":1,"Error":null,"RequestMessage":"{\"StartUpdateTimeOnUtc\":\"2021-01-30\",\"NextToken\":null}","Message":"{\"DataList\":[{\"Sku\":\"05376156\",\"Spu\":\"BQUQHBVDEM\",\"WarehousePriceList\":[],\"MakeStockBatchPriceList\":[],\"TaxRateList\":[{\"StockCode\":\"A162996\",\"VersionNumber\":\"VE2103050002\",\"TaxCategoryName\":\"VAT\",\"DistributionType\":0,\"EffectiveDateTime\":\"2021-03-05T00:00:00\",\"TaxRateRuleList\":[{\"ReceiveCountryCode\":\"US\",\"TaxRate\":15.55},{\"StockCode\":\"A162996\",\"VersionNumber\":\"VE2103050003\",\"TaxCategoryName\":\"VAT\",\"DistributionType\":1,\"EffectiveDateTime\":\"2021-03-10T00:00:00\",\"TaxRateRuleList\":[{\"ReceiveCountryCode\":\"CN\",\"TaxRate\":42.00},{\"Sku\":\"46067446\",\"Spu\":\"BVA2GA4CQ9\",\"WarehousePriceList\":[],\"MakeStockBatchPriceList\":[],\"TaxRateList\":[{\"StockCode\":\"A162996\",\"VersionNumber\":\"VE2103050002\",\"TaxCategoryName\":\"VAT\",\"DistributionType\":0,\"EffectiveDateTime\":\"2021-03-05T00:00:00\",\"TaxRateRuleList\":[{\"ReceiveCountryCode\":\"US\",\"TaxRate\":15.55},{\"ReceiveCountryCode\":\"AE\",\"TaxRate\":20.00},{\"ReceiveCountryCode\":\"AF\",\"TaxRate\":20.66}]},{\"StockCode\":\"A162996\",\"VersionNumber\":\"VE2103050003\",\"TaxCategoryName\":\"VAT\",\"DistributionType\":1,\"EffectiveDateTime\":\"2021-03-10T00:00:00\",\"TaxRateRuleList\":[{\"ReceiveCountryCode\":\"CN\",\"TaxRate\":42.00},{\"ReceiveCountryCode\":\"AG\",\"TaxRate\":14.00}]}]}],\"NextToken\":\"8B3Ucx992WUwEbki1v58tkIRGRz7hOcpnJv1+yD7lanG4fAvjZeU4GE6bjhooJMo6wcWJe3yKlRb7qXgg7ywHaFCKQoJ4Tc9GKdZlb82GkU6CDGt6YDSMNXjUBNhCPsDpGRit0h3LVTpa6KJtHVgUqrUhZjPJ/cRNSxsRYXTW7FhYrO1d0ChzwvHNdTMD5WLoBPAfehmRuW/99kzqJ80koJayUgqYFHPCgs58OQnS8E=\"}"}
```

# 创建订单

URL: /api/Product/QueryProductPriceV2
Http:POST
RequestBody

请求参数信息
| 字段              | 数据类型    | 描述           | 是否必填 |
|-------------------|-------------|----------------|----------|
| Store             | String[100] | 站点           | 否       |
| CustomOrderNumber | String[100] | 客户自定义单号 | 否       |
| StockCode         | String[32]  | 区域编码       | 否       |
| LogProCode        | String[32]  | 物流产品编码   | 否       |
| Receiver          | String[50]  | 收件人         | 是       |
| PhoneNumber       | String[20]  | 收件人电话     | 否       |
| Email             | String[256] | 收件人邮箱     |          |

| Address1             | String[1000]                       | 街道 1                                                                                                                                                                                                                                                                                   | 是                                                                                                                                                 |
|----------------------|------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------|
| Address2             | String[1000]                       | 街道2                                                                                                                                                                                                                                                                                    | 否                                                                                                                                                 |
| City                 | String[50]                         | 收件城市                                                                                                                                                                                                                                                                                 | 是                                                                                                                                                 |
| StateCode            | String[20]                         | 收件省份                                                                                                                                                                                                                                                                                 | 是                                                                                                                                                 |
| ZipPostalCode        | String[20]                         | 收件邮编                                                                                                                                                                                                                                                                                 | 是                                                                                                                                                 |
| CountryCode          | String[5]                          | 收件国家代码                                                                                                                                                                                                                                                                             | 是                                                                                                                                                 |
| AddedServiceCodes    | List\<string\>                     | 增值服务编码                                                                                                                                                                                                                                                                             | 否                                                                                                                                                 |
| PlatformServiceCodes | List\<string\>                     | 保障服务编码 BX0001：退货保障服务基础版 BX0002：物流保障服务基础版 BX0003：退货保障服务标准版                                                                                                                                                                                            | 否 同一种保障服务 支持一起传基础 版与标准版，按照 订单金额使用基 础版或者标准版 （ 例 如 同 时 传 BX0001 与 BX0003,订单会根 据金额购买其 中 一种） |
| VatNumber            | String[256]                        | VAT 税号                                                                                                                                                                                                                                                                                 | 否                                                                                                                                                 |
| SalePlatform         | String [32]                        | 销售平 台 ：Amazon 、Wish 、 eBay 、 Walmart 、 Groupon 、 IrobotBoxERP 、 Shopify 、 Aliexpress 、 OverStock 、 TopHatter 、 JoyBuy 、 Homedepot 、 Facebookshop 、 Mercari 、 Facebook Marketplace 、EKM 、其它销售平台（填 写销售平台，可以让系统根据 该平台使用合适的物流进行发 货） | 是                                                                                                                                                 |
| ProductDetailList    | List\<ProductDetailRequestMo del\> | 商品明细列表：实体定义见下文                                                                                                                                                                                                                                                             | 是                                                                                                                                                 |
| IsPickUp             | Bool                               | 是否自提                                                                                                                                                                                                                                                                                 | 否                                                                                                                                                 |
| PickUpLogisticsList  | List\<PickUpLogisticsModel\>       | 自提物流Label 信息                                                                                                                                                                                                                                                                       | 当 IsPickUp=true 时必填                                                                                                                            |

ProductDetailRequestModel

| 字段          | 数据类型    | 描述                    | 是否必填               |
|---------------|-------------|-------------------------|------------------------|
| Sku           | String[400] | 商品编码                | 是                     |
| Qty           | Int         | 购买数量：必须大于 0    | 是                     |
| ItemId        | String[128] | Ebay 平台 Itemid        | 销售平台为 ebay 时必填 |
| TransactionID | String[128] | Ebay 平台 TransactionID | 销售平台为 ebay 时必填 |

PickUpLogisticsModel

| 字段             | 数据类型    | 描述                       | 是否必填                                               |
|------------------|-------------|----------------------------|--------------------------------------------------------|
| LogisticsProduct | String[128] | 物流产品                   | 是                                                     |
| TrackingNo       | String[128] | 跟踪号                     | 是                                                     |
| FileExtension    | String[20]  | Label 文件扩展名(例：.pdf) | 使用 Base64String 上传 Label 文件必填，只支持 pdf 格式 |
| FileName         | String[300] | Label文件名称,不带扩展名   | 使用 Base64String 上传 Label 文件必填                  |
| FileUrl          | String[500] | Label 文件 Url             | FileUrl、Base64String 二选一 必填                      |
| Base64String     | String      | Label 文件 Base64 字符串   | FileUrl、Base64String 二选一 必填                      |

l ResponseBody

| 字段    | 数据类型    | 描述                                                                                                          |
|---------|-------------|---------------------------------------------------------------------------------------------------------------|
| Message | String[128] | 订单号，成功创建订单时返回                                                                                    |
| Result  | Int         | 0：创建订单失败；1：创建订单成功并且成功支付 2：创建订单成功未成功支付（处理中，后续需要 查询订单状态并支付） |

8. 查询订单

URL:/api/Order/QueryOrder  
Http:POST
RequestBody

| 字段            | 数据类型       | 描述                                                        | 是否必填           |
|-----------------|----------------|-------------------------------------------------------------|--------------------|
| OrderNoList     | List\<String\> | 订单号集合                                                  |                    |
| CustomOrderList | List\<String\> | 客户自定义单号集合：订单号，客户自定义单 号，时间三选一必填 |                    |
| StartTime       | DateTime       | 开始时间：UTC 时间，时间跨度需小于等于 31 天                |                    |
| EndTime         | DateTime       | 结束时间：UTC 时间，时间跨度需小于等于 31 天                |                    |
| TimeType        | Int            | 时间查询类型：0、创建时间，2、发货时间                      | 不填默认为创建时间 |
| PageIndex       | Int            | 页码：不填默认第一页                                        |                    |

l ResponseBody

| 字段 | 数据类型                | 描述         |
|------|-------------------------|--------------|
| Data | List\<QueryOrderModel\> | 订单信息列表 |

QueryOrderModel

| 字段    | 数据类型    | 描述    |
|---------|-------------|---------|
| Store   | String[100] | 站点    |
| OrderId | Int         | 订单 Id |

| OrderNo                 | String[20]                  | 订单号                                                                                                                                          |
|-------------------------|-----------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------|
| OrderStatus             | Int                         | 订单状态：3-库存验证中，7-报价中， 10-待付款， 12-支付确认中，14-组合支付处理中，16-风控审 核处理中,20-配货中，30-待收货，40-已完成，50- 已关闭 |
| ShippingStatus          | Int                         | 配送状态：10-不需要发货，20-未发货，25-部分 发货，30-已发货，40-已收货                                                                          |
| CustomOrderNumber       | String[100]                 | 客户自定义单号                                                                                                                                  |
| CustomerCurrencyCode    | String[100]                 | 币别                                                                                                                                            |
| WarehouseCode           | String[64]                  | 区域编码                                                                                                                                        |
| CouponNumber            | String[64]                  | 采购券编号                                                                                                                                      |
| CreateTime              | DateTime                    | 订单创建时间                                                                                                                                    |
| PaidTime                | DateTime?                   | 支付完成时间                                                                                                                                    |
| SendTime                | DateTime?                   | 发货时间                                                                                                                                        |
| CompletedTime           | DateTime?                   | 收货时间                                                                                                                                        |
| ShippingInformationList | List\<ShippingInformation\> | 发货信息集合                                                                                                                                    |
| ProductDetailList       | List\<OrderProductDetail\>  | 商品明细                                                                                                                                        |
| CostInfo                | CostInfo                    | 费用明细                                                                                                                                        |
| AddValuesServices       | List\<AddValuesService\>    | 增值服务明细                                                                                                                                    |
| PlatformServices        | List\<PlatformService\>     | 保障服务明细                                                                                                                                    |

OrderProductDetail

| 字段                | 数据类型    | 描述           |
|---------------------|-------------|----------------|
| Sku                 | String[400] | 商品编码       |
| Quantity            | Int         | 商品数量       |
| Price               | Decimal     | 商品实际单价   |
| OriginalPrice       | Decimal     | 商品原价       |
| DiscountCouponMoney | Decimal?    | 采购劵优惠金额 |

ShippingInformation

| 字段            | 数据类型                | 描述         |
|-----------------|-------------------------|--------------|
| LogProName      | String[256]             | 物流产品名称 |
| TrackingNumber  | String[256]             | 跟踪号       |
| ShippingProduct | List\<ShippingProduct\> | 发货商品集合 |
| DeliveryTime    | DateTime?               | 配送时间     |
| LogProCode      | String[256]             | 物流产品编码 |
| LogComCode      | String[256]             | 物流公司编码 |
| LogComName      | String[256]             | 物流公司名称 |

ShippingProduct

| 字段   | 数据类型    | 描述         |
|--------|-------------|--------------|
| Sku    | String[400] | 发货商品编码 |
| BuySku | String[400] | 购买商品编码 |

| ShippingQuantity | Int | 发货数量    |
|------------------|-----|-------------|
| ShipmentItemId   | Int | 配送明细 ID |

CostInfo

| 字段         | 数据类型 | 描述       |
|--------------|----------|------------|
| ProductTotal | decimal  | 商品合计   |
| Freight      | decimal  | 运费       |
| OrderTax     | decimal  | 税费       |
| PayCharge    | decimal  | 支付手续费 |
| OrderTotal   | decimal  | 订单总金额 |

AddValuesService

| 字段                  | 数据类型 | 描述         |
|-----------------------|----------|--------------|
| AddValuesService Name | String   | 增值服务名称 |
| Money                 | decimal  | 增值服务金额 |

PlatformService

| 字段                 | 数据类型 | 描述         |
|----------------------|----------|--------------|
| PlatformServiceN ame | String   | 保障服务名称 |
| Money                | decimal  | 保障服务金额 |

9. 查询异常订单

URL: /api/Order/GetOrderException  
Http:POST
RequestBody

| 字段              | 数据类型 | 描述             | 是否必填     |
|-------------------|----------|------------------|--------------|
| PageIndex         | Int      | 页码：默认第一页 | 三者必填一项 |
| OrderNo           | string   | 订单号           | 三者必填一项 |
| CustomOrderNumber | string   | 客户自定义单号   | 三者必填一项 |

l ResponseBody

| 字段             | 数据类型    | 描述     |
|------------------|-------------|----------|
| OrderNo          | String[20]  | 订单号   |
| HandlingOpinions | String[400] | 处理意见 |

10. 创建退款单

URL: /api/order/AddRefundOrder
Http:POST
RequestBody

| 字段     | 数据类型    | 描述     | 是否必填 |
|----------|-------------|----------|----------|
| OrderNo  | String[20]  | 订单号   | 是       |
| Reason   | String[256] | 退款原因 | 是       |
| Remark   | String[500] | 备注     | 否       |
| Contacts | String[255] | 联系人   | 是       |
| Phone    | String[255] | 联系方式 | 是       |

ResponseBody

| 字段     | 数据类型   | 描述     |
|----------|------------|----------|
| RefundNo | String[20] | 退款单号 |

11. 查询退款单

URL: /api/order/QueryRefundOrder  
Http:POST
RequestBody

| 字段            | 数据类型       | 描述                                                   | 是否必填 |
|-----------------|----------------|--------------------------------------------------------|----------|
| RefundNoList    | List\<string\> | 退款单号集合                                           |          |
| OrderNoList     | List\<string\> | 订单号集合：订单号、客户自定义单号，退款单号三选一必填 |          |
| CustomOrderList | List\<string\> | 客户自定义单号集合                                     |          |

l ResponseBody

| 字段 | 数据类型                 | 描述         |
|------|--------------------------|--------------|
| Data | List\<QueryRefundModel\> | 退款信息列表 |

QueryRefundModel

| 字段                 | 数据类型    | 描述                                                                                                                      |
|----------------------|-------------|---------------------------------------------------------------------------------------------------------------------------|
| RefundNo             | String[128] | 退款单号                                                                                                                  |
| OrderNo              | String[20]  | 订单号                                                                                                                    |
| ProcessStatus        | Int         | 处理状态：1、待平台确认，2、待客户确认，3、平台处理中，4、处理完成，5、已关 闭                                            |
| ProcessMode          | Int         | 处理方式：1、仅退款，2、退货退款，3、换货，4、补发                                                                        |
| ProcessProgress      | Int         | 处理进度：1、待确定，2、待发 RL，3、待退回，4、待补发，5、待退款，6、待重发， 7、已退款，8、已补发，9、已重发，10、已关闭 |
| Reason               | String[500] | 退款原因                                                                                                                  |
| Remark               | String[500] | 备注                                                                                                                      |
| Contacts             | String[256] | 联系人                                                                                                                    |
| Phone                | String[256] | 联系电话                                                                                                                  |
| RefundRequestStatu s | Int         | 退款状态：0-待审核，1-成功，2-拒绝，3-已退款                                                                              |
| AuditOpinion         | String[500] | 审核意见                                                                                                                  |

| Money                   | decimal                        | 退款金额     |
|-------------------------|--------------------------------|--------------|
| RefundDateTime          | DateTime?                      | 退款时间     |
| RefundProductMode ls    | List\<RefundPro ductModel\>    | 商品明细     |
| RefundReturnLogMo dels  | List\<RefundRet urnLogModel\>  | 协商记录列表 |
| RefundReturnBillM odels | List\<RefundRet urnBillModel\> | 退款详情     |

RefundProductModel

| 字段 | 数据类型 | 描述         |
|------|----------|--------------|
| Sku  | String   | 商品 SKU     |
| Qty  | Int      | 退款商品数量 |

RefundReturnLogModel

| 字段            | 数据类型               | 描述                                                                                                                                                                                                                                                             |
|-----------------|------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| CustomerId      | String                 | 客户 ID                                                                                                                                                                                                                                                          |
| CustomerName    | String                 | 客户名                                                                                                                                                                                                                                                           |
| OperationTime   | DateTime               | 操作时间                                                                                                                                                                                                                                                         |
| OperationType   | Int                    | 操作类型(1-售中申请 2-售后申请 3-拒绝售中申请 4-同意售后申请 5-拒绝售后申请 6-提交退货物流信息 7-发起退款 8-取消 9-留言 10 修改申诉 11-确认无问题 12-重发 13-补发 14-上传Rl 15-关闭售后单 16-完成售后单 17- 发送客户确认 18- 拦截订单 19- 同意变更 20- 拒绝变更) |
| OperationRole   | Int                    | 操作角色(0-客户 1-客服)                                                                                                                                                                                                                                          |
| Remark          | String                 | 备注                                                                                                                                                                                                                                                             |
| RequestReason   | String                 | 申请原因                                                                                                                                                                                                                                                         |
| CreateMan       | String                 | 操作人                                                                                                                                                                                                                                                           |
| RefundAmount    | decimal                | 退款金额                                                                                                                                                                                                                                                         |
| Contacts        | String                 | 联系人                                                                                                                                                                                                                                                           |
| Phone           | String                 | 联系电话                                                                                                                                                                                                                                                         |
| ProblemDescript | String                 | 问题描述                                                                                                                                                                                                                                                         |
| DownloadList    | List\<Download Model\> | 附件列表                                                                                                                                                                                                                                                         |

DownloadModel

| 字段        | 数据类型 | 描述              |
|-------------|----------|-------------------|
| FileName    | String   | 文件名            |
| Extension   | String   | 拓展名(示例.jpg） |
| DownloadUrl | String   | 地址              |

RefundReturnBillModel

| 字段             | 数据类型 | 描述         |
|------------------|----------|--------------|
| RefundDealNo     | String   | 退款单号     |
| OriginalAmount   | decimal  | 申请退款金额 |
| OriginalCurrency | String   | 申请退款币别 |

| PenceRate           | String  | 付款汇率     |
|---------------------|---------|--------------|
| RefundMoney         | decimal | 实际退款金额 |
| RefundCurrency      | String  | 实际退款币别 |
| RefundType          | String  | 退款方式     |
| RefundOperationM an | String  | 操作人       |

12. 取消退款单

URL: /api/order/CancelRefundOrder  
Http:POST
RequestBody

| 字段     | 数据类型 | 描述                                 | 是否必填 |
|----------|----------|--------------------------------------|----------|
| OrderNo  | string   | 订单号                               |          |
| RefundNo | String   | 退款单号：订单号，退款单号二选一必填 |          |

13. 处理工单变更

URL: /api/order/HandChangeRefundOrder  
Http:POST
RequestBody

| 字段       | 数据类型 | 描述                               | 是否必填 |
|------------|----------|------------------------------------|----------|
| ChangeType | String   | 变更类型（填数字 1：同意 2：拒绝） | 是       |
| RefundNo   | String   | 退款单号                           | 是       |

14. 创建退货单

URL:api/Order/AddReturnOrder  
Http:POST
RequestBody

| 字段        | 数据类型    | 描述                                                | 是否必填 |
|-------------|-------------|-----------------------------------------------------|----------|
| OrderNo     | String[20]  | 订单号                                              | 是       |
| Reason      | String[256] | 原因                                                | 是       |
| ProcessMode | Int         | 处理方式：1、仅退款，2、退货退款，3、换货，4、 补发 | 是       |
| ReturnMoney | Int         | 退款金额                                            | 否       |
| Remark      | String[500] | 备注                                                | 否       |
| Contacts    | String[255] | 联系人                                              | 是       |

| Phone                 | String[255]                             | 联系方式                        | 是                            |
|-----------------------|-----------------------------------------|---------------------------------|-------------------------------|
| ReturnForecast        | int                                     | 退货预报(1:已自行退货,2:未退货) | 是                            |
| LogisticsProductC ode | String[200]                             | 退货物流产品编码                | 退货预报 为已自行 退货时必 填 |
| TrackingNo            | String[100]                             | 物流跟踪号                      | 退货预报 为已自行 退货时必 填 |
| ThirdpartyStockC ode  | String[100]                             | 退货仓库编码                    | 退货预报 为已自行 退货时必 填 |
| ReturnDetail          | List\<AddReturnRequestItemModel\>       | 退货明细                        | 是                            |
| ReturnAttachment      | List\<AddReturnRequestAttachmentModel\> | 附件列表                        | 否                            |

AddReturnRequestItemModel

| 字段           | 数据类型    | 描述        | 是否必填 |
|----------------|-------------|-------------|----------|
| Sku            | String[400] | 商品编码    | 是       |
| ReturnQty      | Int         | 退货数量    | 是       |
| ShipmentItemId | Int         | 配送明细 ID | 是       |

AddReturnRequestAttachmentModel

| 字段                | 数据类型 | 描述                      | 是否必填                      |
|---------------------|----------|---------------------------|-------------------------------|
| AttachmentExtension | String   | 文件扩展名：示例（ .jpg） | 是                            |
| URL                 | String   | 文件地址                  | 否(地址与 base64 二 选一必填) |
| AttachString        | string   | Base64 字符串             | 否                            |

l ResponseBody

| 字段     | 数据类型    | 描述     |
|----------|-------------|----------|
| ReturnNo | String[128] | 退货单号 |

15. 查询退货单

URL:/api/Order/QueryReturnOrder  
Http:POST
RequestBody

| 字段         | 数据类型       | 描述                                                   | 是否必填 |
|--------------|----------------|--------------------------------------------------------|----------|
| ReturnNoList | List\<string\> | 退货单号集合                                           |          |
| OrderNoList  | List\<string\> | 订单号集合：订单号、客户自定义单号，退货单号三选一必填 |          |

| CustomOrderList | List\<string\> | 客户自定义单号集合 |   |
|-----------------|----------------|--------------------|---|

l ResponseBody

| 字段 | 数据类型                 | 描述         |
|------|--------------------------|--------------|
| Data | List\<QueryReturnModel\> | 退货信息列表 |

QueryReturnModel

| 字段                           | 数据类型                                 | 描述                                                                                                                       |
|--------------------------------|------------------------------------------|----------------------------------------------------------------------------------------------------------------------------|
| ReturnNo                       | String[128]                              | 退货单号                                                                                                                   |
| OrderNo                        | String                                   | 订单号                                                                                                                     |
| ReturnReason                   | String                                   | 退货原因                                                                                                                   |
| Remark                         | String                                   | 备注                                                                                                                       |
| AttachmentList                 | String[]                                 | 附件 URL 集合                                                                                                              |
| Contacts                       | String                                   | 联系人                                                                                                                     |
| Phone                          | String                                   | 联系方式                                                                                                                   |
| ProcessStatus                  | Int                                      | 处理状态：1、待平台确认，2、待客户确认， 3、平台处理中，4、处理完成，5、已关闭                                             |
| ProcessMode                    | Int                                      | 处理方式：1、仅退款，2、退货退款，3、换 货，4、补发                                                                        |
| ProcessProgress                | Int                                      | 处理进度：1、待确定，2、待发 RL，3、待退 回，4、待补发，5、待退款，6、待重发，7、 已退款，8、已补发，9、已重发，10、已关闭 |
| ReturnStatus                   | Int                                      | 退货状态：0 待审核，1 审核通过，2 已拒绝， 3 退款成功，4 已关闭                                                            |
| AuditOpinion                   | String                                   | 审核意见                                                                                                                   |
| RlList                         | String[]                                 | RL URL 集合                                                                                                                |
| Address                        | ReturnAddress                            | 退货地址                                                                                                                   |
| IsSend                         | Bool                                     | 是否寄货                                                                                                                   |
| Money                          | decimal                                  | 退款金额                                                                                                                   |
| LogisticsCode                  | String                                   | 寄货物流产品编码                                                                                                           |
| LogisticsName                  | String                                   | 寄货物流产品名称                                                                                                           |
| LogisticsNo                    | String                                   | 寄货物流单号                                                                                                               |
| SaleAfterOrderNo               | String                                   | 重发/补发单号                                                                                                              |
| RefundDateTime                 | DateTime?                                | 退款时间                                                                                                                   |
| LogisticsProductList           | List\<ReturnLogisticsPro duct\>          | 可寄货物流产品列表                                                                                                         |
| ReturnRequestConfirmLogAPIList | List\<ReturnRequestConfi rmLogAPIModel\> | 协商记录                                                                                                                   |
| ReturnProductList              | List\<ReturnProductMode l\>              | 商品明细                                                                                                                   |
| RefundReturnBillModels         | List\<RefundReturnBillM odel\>           | 退款详情                                                                                                                   |

ReturnProductModel

| 字段 | 数据类型 | 描述         |
|------|----------|--------------|
| Sku  | String   | 商品 SKU     |
| Qty  | Int      | 退货商品数量 |

ReturnLogisticsProduct

| 字段                 | 数据类型 | 描述         |
|----------------------|----------|--------------|
| LogisticsProductCode | String   | 物流产品编码 |
| LogisticsProductName | String   | 物流产品名称 |

ReturnRequestConfirmLogAPIModel

| 字段                | 数据类型       | 描述                                                                                                                                                                                                   |
|---------------------|----------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| OperationTime       | DateTime       | 时间                                                                                                                                                                                                   |
| OperationType       | Int            | 操作：1 售中申请 2 售后申请 3 拒绝售中申请 4 同意售后申请 5 拒绝售 后申请 6 提交退货物流信息 7 发起退款 8 取消 9 留言 10 修改申诉 11 确认无问题 12 重发 13 补发 14 上传 RL 15 关闭售后单 16 完成售后单 |
| OperationRole       | Int            | 发送方：0-客户 1-客服                                                                                                                                                                                  |
| Remark              | String         | 备注                                                                                                                                                                                                   |
| ReturnRequestReason | String         | 申述原因                                                                                                                                                                                               |
| ProcessMode         | Int            | 处理方式：1、仅退款，2、退货退款，3、换货，4、补发                                                                                                                                                     |
| Quantity            | Int            | 数量                                                                                                                                                                                                   |
| ReturnMethod        | Int            | 退货方式：1、RL，2、非 RL                                                                                                                                                                              |
| ReturnWarehouseCode | String         | 退货区域                                                                                                                                                                                               |
| RefundAmount        | decimal        | 退款金额                                                                                                                                                                                               |
| Contacts            | String         | 联系人                                                                                                                                                                                                 |
| Phone               | String         | 联系电话                                                                                                                                                                                               |
| ProblemDescript     | String         | 问题描述                                                                                                                                                                                               |
| ReturnForecast      | int            | 退货预报（1：已自行退货，2：未退货）                                                                                                                                                                   |
| Logistics           | String         | 退货物流产品                                                                                                                                                                                           |
| LogisticsNo         | String         | 物流跟踪号                                                                                                                                                                                             |
| AttachmentUrls      | List\<String\> | 附件地址集合                                                                                                                                                                                           |

l ReturnAddress

| 字段            | 数据类型 | 描述   |
|-----------------|----------|--------|
| Street1         | String   | 街道 1 |
| Street2         | String   | 街道2  |
| City            | String   | 城市   |
| StateOrProvince | String   | 州省   |
| PostCode        | String   | 邮编   |
| ContactMan      | String   | 联系人 |
| Tel             | String   | 电话   |

RefundReturnBillModel

| 字段                | 数据类型 | 描述         |
|---------------------|----------|--------------|
| RefundDealNo        | String   | 退款单号     |
| OriginalAmount      | decimal  | 申请退款金额 |
| OriginalCurrency    | String   | 申请退款币别 |
| PenceRate           | String   | 付款汇率     |
| RefundMoney         | decimal  | 实际退款金额 |
| RefundCurrency      | String   | 实际退款币别 |
| RefundType          | String   | 退款方式     |
| RefundOperationM an | String   | 操作人       |

16. 取消退货单

URL:/api/Order/CancelReturnOrder  
Http:POST
RequestBody

| 字段     | 数据类型 | 描述     | 是否必填   |
|----------|----------|----------|------------|
| OrderNo  | String   | 订单号   | 二选一必填 |
| ReturnNo | String   | 退货单号 | 二选一必填 |

17. 编辑退货单

URL:/api/Order/EditReturnOrder  
Http:POST
RequestBody

| 字段                 | 数据类型    | 描述                                                | 是否必填                                          |
|----------------------|-------------|-----------------------------------------------------|---------------------------------------------------|
| ReturnNo             | String[128] | 申诉单号                                            | 是                                                |
| ProcessMode          | Int         | 处理方式：1、仅退款，2、退货退款，3、换货， 4、补发 | 不填不更新                                        |
| ReturnAmount         | decimal     | 退款金额                                            | 不填不更新                                        |
| ReasonForReturn      | String[500] | 原因                                                | 不填不更新                                        |
| Remark               | String[500] | 备注                                                | 不填不更新                                        |
| Contacts             | String[255] | 联系人                                              | 不填不更新                                        |
| Phone                | String[255] | 联系方式                                            | 不填不更新                                        |
| ReturnQuantity       | Int         | 退货数量                                            | 不填不更新                                        |
| ReturnForecast       | Int         | 退货预报(1:已自行退货,2:未退货)                     | 不填不更新                                        |
| LogisticsProductCode | String[200] | 退货物流产品编码                                    | 退货预报为已自 行退货时必填， 不填退货预报不 更新 |
| TrackingNo           | String[100] | 物流跟踪号                                          | 退货预报为已自                                    |

|                     |                         |              | 行退货时必填， 不填退货预报不 更新                |
|---------------------|-------------------------|--------------|---------------------------------------------------|
| ThirdpartyStockCode | String[100]             | 退货仓库编码 | 退货预报为已自 行退货时必填， 不填退货预报不 更新 |
| AttachmentModelList | List\<AttachmentModel\> | 附件列表     | 不填不更新                                        |

AttachmentModel

| 字段                | 数据类型 | 描述                      | 是否必填 |
|---------------------|----------|---------------------------|----------|
| AttachmentExtension | String   | 文件扩展名：示例（ .jpg） | 是       |
| Url                 | String   | 文件地址                  | 是       |

18. 售后寄货

URL:/api/Order/AddAfterSaleSendGoods  
Http:POST
RequestBody

| 字段        | 数据类型    | 说明         | 是否必填   |
|-------------|-------------|--------------|------------|
| ReturnNo    | String[128] | 退货单号     | 是         |
| Logistics   | String[256] | 物流产品编码 | 非 RL 必填 |
| LogisticsNo | String[256] | 运单号       | 非 RL 必填 |

19. 查询账户信息

URL: /api/Account/QueryAccountInfo  
Http:POST
RequestBody
无
ResponseBody

| 字段         | 数据类型      | 描述     | 是否必填 |
|--------------|---------------|----------|----------|
| CurrencyCode | String[8]     | 币别编码 |          |
| Amount       | Decimal[11,2] | 总额     |          |
| Balance      | Decimal[11,2] | 可用余额 |          |
| Freeze       | Decimal[11,2] | 冻结金额 |          |

20. 查询收支明细

URL: /api/Account/QueryAccountIncomepay
Http:POST
RequestBody

| 字段      | 数据类型  | 描述                                                                            | 是否必填 |
|-----------|-----------|---------------------------------------------------------------------------------|----------|
| Currency  | String    | 币别：如 USD                                                                    | 否       |
| StartTime | Datetime? | 开始时间：UTC 时间，开始时间不能大于结束时间，时间仅精确至年月日，不 验证时分秒 | 否       |
| EndTime   | Datetime? | 结束时间：UTC 时间，结束时间不能小于开始时间，时间仅精确至年月日，不 验证时分秒 | 否       |
| PageInex  | Int       | 页码：必须大于 0                                                                | 是       |

ResponseBody

| 字段          | 数据类型                      | 描述                         |
|---------------|-------------------------------|------------------------------|
| PageIndex     | Int                           | 页码                         |
| TotalCount    | Int                           | 总数据量                     |
| PageTotal     | Int                           | 总页数                       |
| PageSize      | Int                           | 每页数据量                   |
| IncomepayList | List\<AccountIncomepayModel\> | 收支明细列表：实体定义见下文 |

AccountIncomepayModel

| 字段           | 数据类型      | 描述                                                                                  |
|----------------|---------------|---------------------------------------------------------------------------------------|
| DealNo         | String[128]   | 交易号                                                                                |
| CreateDateTime | Datetime      | 交易时间                                                                              |
| Money          | Decimal[11,2] | 交易金额                                                                              |
| DealType       | Int           | 交易类型：1 充值，2 提现，3 消费，4 退款，5 汇款，6 圈货支付， 7 圈货退款，8 违约支付 |

21. 查询商品价格（v1.0）

URL: /api/Product/QueryProductPrice  
Http:POST
RequestBody

| 字段          | 数据类型       | 描述                                             | 是否必填   |
|---------------|----------------|--------------------------------------------------|------------|
| SkuList       | List\<String\> | 商品编码集合：不能为空，不能超过 30 条           | 二选一必填 |
| SpuList       | List\<String\> | SPU 编码集合：不能为空，不能超过 30 条           | 二选一必填 |
| WarehouseList | List\<String\> | 区域编码集合：可多个，不填则回传所有有权限的价格 | 否         |

l ResponseBody

| 字段    | 数据类型                                     | 描述                       |
|---------|----------------------------------------------|----------------------------|
| Message | List\<QueryProductPriceResponseModel\>见下文 | 价格列表：响应基类 Message |

QueryProductPriceResponseModel

| 字段                    | 数据类型                    | 描述             |
|-------------------------|-----------------------------|------------------|
| Sku                     | String[400]                 | 商品编码         |
| Spu                     | String[400]                 | SPU 编码         |
| WarehousePriceList      | List\<WarehousePrice\>      | 区域价格列表     |
| MakeStockBatchPriceList | List\<MakeStockBatchPrice\> | 圈货批次价格列表 |
| TaxRateList             | List\<TaxRate\>             | 税率版本列表     |

WarehousePrice

| 字段                       | 数据类型      | 描述                                            |
|----------------------------|---------------|-------------------------------------------------|
| Store                      | String[100]   | 站点                                            |
| StockCode                  | String[32]    | 区域编码                                        |
| LogisticsProductCode       | String[32]    | 平台物流产品编码                                |
| OriginalPrice              | Decimal(11,2) | 原价                                            |
| SellingPrice               | Decimal(11,2) | 售价                                            |
| ValidityTimeOnUtc          | DateTime      | 会员等级有效期：UTC 时间                        |
| PromotionsLimitedTime      | DateTime?     | 促销活动有效期：UTC 时间                        |
| PromotionsQty              | Int?          | 促销活动剩余库存数量,为 null 表示活动库存不限制 |
| CurrencyCode               | String[8]     | 币别编码：如 USD                                |
| LogisticsProductName       | String[100]   | 平台物流产品名称                                |
| ShippedPackagesNumber      | Int?          | 发货包裹数量,为 null 表示非自 提物流            |
| MaxCombinedShippedNumber   | Int?          | 最大可合并发货数量,为 null表 示非自提物流       |
| SupportedLogisticsProvider | String[1024]  | 支持的物流承运商，为 null表示 非自提物流        |

MakeStockBatchPrice

| 字段              | 数据类型      | 描述             |
|-------------------|---------------|------------------|
| Store             | String[100]   | 站点             |
| BatchNo           | String[100]   | 圈货批次号       |
| MakeStockPrice    | Decimal(11,2) | 圈货价格         |
| SellingPrice      | Decimal(11,2) | 实际售价         |
| ValidityTimeOnUtc | DateTime      | 有效期：UTC 时间 |
| CurrencyCode      | String[8]     | 币别编码：如 USD |

TaxRateList

| 字段             | 数据类型    | 描述                          |
|------------------|-------------|-------------------------------|
| StockCode        | String[32]  | 区域编码                      |
| VersionNumber    | String[32]  | 版本编码                      |
| TaxCategoryName  | String[100] | 税种名称                      |
| DistributionType | Int         | 分销商类型(0-有税号 1-无税号) |

| EffectiveDateTime | DateTime            | 生效时间：UTC 时间 |
|-------------------|---------------------|--------------------|
| TaxRateRuleList   | List\<TaxRateRule\> | 税率规则           |

TaxRateRuleList

| 字段               | 数据类型      | 描述         |
|--------------------|---------------|--------------|
| ReceiveCountryCode | String[32]    | 收货国家编码 |
| TaxRate            | Decimal(11,2) | 税率         |

21.1. 查询商品价格（v2.0）

URL: /api/Product/QueryProductPriceV2
Http:POST
RequestBody
PS：对比 1.0 接口，增加了按时间范围查询的支持。拥有更好的查询效率。

| 字段                  | 数据类型       | 描述                                             | 是否必填                                                                        |
|-----------------------|----------------|--------------------------------------------------|---------------------------------------------------------------------------------|
| SkuList               | List\<String\> | 商品编码集合：不能为空，不能超过 30 条           | SkuList 、 SpuList 、 StartUpdateTimeOnUtc 、 EndUpdateTimeOnUtc 四 选 一 必 填 |
| SpuList               | List\<String\> | SPU 编码集合：不能为空，不能超过 30 条           | SkuList 、 SpuList 、 StartUpdateTimeOnUtc 、 EndUpdateTimeOnUtc 四 选 一 必 填 |
| WarehouseList         | List\<String\> | 区域编码集合：可多个，不填则回传所有有权限的价格 | 否                                                                              |
| StartUpdateT imeOnUtc | DateTime?      | 起始价格更新时间                                 | SkuList 、 SpuList 、 StartUpdateTimeOnUtc 、 EndUpdateTimeOnUtc 四 选 一 必 填 |
| EndUpdateTim eOnUtc   | DateTime?      | 结束价格更新时间                                 | SkuList 、 SpuList 、 StartUpdateTimeOnUtc 、 EndUpdateTimeOnUtc 四 选 一 必 填 |
| NextToken             | String         | 下一页 Token                                     | 否,当 NextToken 有值且有效时, 其他所有请求参数值将被覆盖为 初始页的请求参数值   |

l ResponseBody

| 字段    | 数据类型                                  | 描述             |
|---------|-------------------------------------------|------------------|
| Message | QueryProductPricePageResponseModel 见下文 | 响应基类 Message |

QueryProductPricePageResponseModel

| 字段 | 数据类型 | 描述 |
|------|----------|------|

| NextToken | String                                 | 下一页 token                                |
|-----------|----------------------------------------|---------------------------------------------|
| DataList  | List\<QueryProductPriceResponseModel\> | 商品信息集合：实体定义见接口 21查询商品价格 |

22. 查询增值服务

URL: /api/Order/GetValueAddService  
Http:POST
RequestBody

| 字段 | 数据类型 | 描述                             | 是否必填 |
|------|----------|----------------------------------|----------|
| Site | String   | 站点：不填则返回该客户认证的站点 | 否       |

无

ResponseBody

| 字段 | 数据类型                             | 描述                 |
|------|--------------------------------------|----------------------|
| data | List\<ValueAddServiceApi SiteModel\> | 站点对应增值服务列表 |

ValueAddServiceApiSiteModel

| 字段                     | 数据类型                         | 描述         |
|--------------------------|----------------------------------|--------------|
| SiteHosts                | String[400]                      | 站点地址     |
| ValueAddServiceApiModels | List\<ValueAddServiceApiMode l\> | 增值服务列表 |

ValueAddServiceApiModel

| 字段                  | 数据类型    | 描述             |
|-----------------------|-------------|------------------|
| ValueAddServiceCode   | String[256] | 增值服务编码     |
| ValueAddServiceName   | String[256] | 增值服务中文名称 |
| ValueAddServiceNameEN | String[256] | 增值服务英文名称 |

23. 查询销售平台

URL: /api/Order/GetSalesPlatform  
Http:POST
RequestBody
无
ResponseBody

| 字段 | 数据类型       | 描述             |
|------|----------------|------------------|
| data | List\<String\> | 销售平台名称列表 |

24. 查询平台物流产品

URL: /api/Product/GetLogisticsProduct
Http:POST
RequestBody
无
ResponseBody

| 字段              | 数据类型    | 描述         |
|-------------------|-------------|--------------|
| LogProCode        | String[32]  | 物流产品编码 |
| LogProName        | String[255] | 物流产品名称 |
| StockCode         | String[128] | 区域编码     |
| StockName         | String[255] | 区域名称     |
| TimeLinessFastest | Decimal     | 最快时效(天) |
| TimeLinessSlowest | Decimal     | 最慢时效(天) |

25. 支付订单

URL: /api/order/PayOrder  
Http:POST
RequestBody

| 字段    | 数据类型     | 描述                         | 是否必填 |
|---------|--------------|------------------------------|----------|
| OrderNo | String [128] | 订单号，待付款订单可调用接口 | 是       |

26. 查询退货平台仓库

URL: /api/Product/GetReturnPlatformWarehouse  
Http:POST
RequestBody
无
ResponseBody

| 字段              | 数据类型 | 描述         |
|-------------------|----------|--------------|
| PlatformStockCode | String   | 平台仓库编码 |
| PlatformStockName | String   | 平台仓库名称 |
| Address1          | String   | 地址 1       |
| Address2          | String   | 地址 2       |
| City              | String   | 城市         |
| Province          | String   | 省份         |
| Country           | String   | 国家         |
| PostCode          | String   | 邮编         |
| Contacts          | String   | 联系人       |
| Tel               | String   | 联系方式     |

27. 订单价格预览

URL: /api/order/QuoteOrder  
Http:POST
RequestBody

| 字段      | 数据类型                       | 描述     | 是否必填 |
|-----------|--------------------------------|----------|----------|
| OrderList | List\<QuoteOrderRequestModel\> | 订单集合 | 是       |

QuoteOrderRequestModel

| 字段                 | 数据类型                           | 描述                                                                                                                                                                                                                                                                                     | 是否必填 |
|----------------------|------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|----------|
| OrderNo              | String [256]                       | 请求唯一标识，响应集合中返回                                                                                                                                                                                                                                                             | 否       |
| StockCode            | String[32]                         | 区域编码                                                                                                                                                                                                                                                                                 | 否       |
| LogProCode           | String[32]                         | 物流产品编码                                                                                                                                                                                                                                                                             | 否       |
| Address1             | String[1000]                       | 街道 1                                                                                                                                                                                                                                                                                   | 是       |
| Address2             | String[1000]                       | 街道2                                                                                                                                                                                                                                                                                    | 否       |
| City                 | String[50]                         | 收件城市                                                                                                                                                                                                                                                                                 | 是       |
| StateCode            | String[20]                         | 收件省份                                                                                                                                                                                                                                                                                 | 否       |
| ZipPostalCode        | String[20]                         | 收件邮编                                                                                                                                                                                                                                                                                 | 是       |
| CountryCode          | String[5]                          | 收件国家代码                                                                                                                                                                                                                                                                             | 是       |
| CouponNumber         | String [32]                        | 采购券编码                                                                                                                                                                                                                                                                               | 否       |
| SalePlatform         | String [32]                        | 销售平 台 ：Amazon 、Wish 、 eBay 、 Walmart 、 Groupon 、 IrobotBoxERP 、 Shopify 、 Aliexpress 、 OverStock 、 TopHatter 、 JoyBuy 、 Homedepot 、 Facebookshop 、 Mercari 、 Facebook Marketplace 、EKM 、其它销售平台（填 写销售平台，可以让系统根据 该平台使用合适的物流进行发 货） | 否       |
| PlatformServiceCodes | List\<string\>                     | 保障服务编码 BX0001：退货保障服务基础版 BX0002：物流保障服务基础版 BX0003：退货保障服务标准版                                                                                                                                                                                            | 否       |
| VatNumber            | String[256]                        | VAT 税号                                                                                                                                                                                                                                                                                 | 否       |
| ProductDetailList    | List\<ProductDetailRequestMo del\> | 商品明细列表：实体定义见下文                                                                                                                                                                                                                                                             | 是       |

ProductDetailRequestModel

| 字段 | 数据类型    | 描述                 | 是否必填 |
|------|-------------|----------------------|----------|
| Sku  | String[400] | 商品编码             | 是       |
| Qty  | Int         | 购买数量：必须大于 0 | 是       |

| 字段      | 数据类型                        | 描述         |
|-----------|---------------------------------|--------------|
| OrderList | List\<QuoteOrderResponseModel\> | 订单响应集合 |

QuoteOrderResponseModel

| 字段                  | 数据类型 | 描述                                                        |
|-----------------------|----------|-------------------------------------------------------------|
| OrderNo               | String   | 请求唯一标识                                                |
| Result                | int      | 报价结果。1：成功，0：失败                                  |
| FailCode              | String   | 失败原因错误编码（错误编码对应的原因请参考错误编码对 照表） |
| FailReason            | String   | 失败原因                                                    |
| CurrencyCode          | String   | 币别                                                        |
| ProductAmount         | decimal  | 商品金额                                                    |
| DiscountAmount        | decimal  | 优惠金额                                                    |
| CouponAmount          | decimal  | 采购券减免金额                                              |
| VatAmount             | decimal  | VAT 税费                                                    |
| PlatformServiceAmount | decimal  | 保障服务费                                                  |
| IsRemoteArea          | bool     | 是否偏远                                                    |
| OrderTotal            | decimal  | 金额合计                                                    |
