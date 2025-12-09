Authorization西月开放API

# 对接规范

### 请求地址

测试环境：

```
https://testing.westmonth.com/


app\_id：3acdfa31-c427-4f8f-922b-44e05b7d9766

app\_secret：d0c024ba5d5a699f54936eb298d605e8
```

正式环境：

```
https://westmonth.com/
```

注：真实环境密钥待测试联调正常，注册独立站账号且实名认证后，联系我司开通

### 请求 header 头

```
● Content-Type: application/json
● Authorization: Base64( json验证信息 )
```

### Authorization 的组成

| 参数名称  | 参数类型 | 参数描述       |
|-----------|----------|----------------|
| app_id    | string   | 客户应用ID     |
| nonce_str | string   | 随机串         |
| timestamp | int      | 当前时间戳(秒) |
| signature | string   | 签名值         |

- 需要将参数数据生成为 json 字符串，然后用 base64 编码：

```
Base64(json{"app\_id":"124ba5d5a12936eb298d61", "nonce\_str":"af464c76d7", "timestamp":1718418480, "signature":"1634ee9e5932758f088360e1cf6c7d27"})
```

### signature 签名值的组成

```
● 目前采用固定顺序字符串拼接最后 md5 小写形成的

 ● 需要拼接 app\_secret 客户应用密钥


md5(app\_id+app\_secret+nonce\_str+timestamp)
```

### PHP示例

```
<?php


// 业务数据

\$data = [

	"mapping" => "thailand",

	"start\_time" => "2024-04-29 00:00:01"

]；


\$app\_id = "124ba5d5a12936eb298d61";   // 客户应用ID

\$app\_secret = "88325813-dbd5-4093-a164";   // 客户应用密钥

\$nonce\_str = "fb22fff75c27";               // 随机串

\$timestamp = time();                       // 当前时间戳

\$signature = "";                           // 签名值


// 组成签名值(固定顺序) md5 小写

\$signature = md5(\$app\_id.\$app\_secret.\$nonce\_str.\$timestamp);


// 生成 authorization 数据

\$authorizationData = [

	'app\_id' => \$app\_id,

	'nonce\_str' => \$nonce\_str,

	'timestamp' => \$timestamp,

	'signature' => \$signature

];

// 将 authorization 数据转为 json 字符串

\$authorizationJson = json\_encode(authorizationData);


// 最后用 base64 生成最后的验证信息

\$authorization = base64\_encode(authorizationJson);
```

### JAVA示例

```
package org.example;


import org.json.JSONObject;


import java.util.Base64;


public class Main {



    public static void main(String[] args) {

        String app\_id = "xxxx";

        String app\_secret = "xxxx";

        String nonceStr = "xxxx";

        // 当前时间戳

        long timestamp = System.currentTimeMillis() / 1000;

        // 签名值

        String signature = md5(app\_id + app\_secret + nonceStr + timestamp);


        JSONObject authorizationData = new JSONObject();

        authorizationData.put("app\_id", app\_id);

        authorizationData.put("nonce\_str", nonceStr);

        authorizationData.put("timestamp", timestamp);

        authorizationData.put("signature", signature);


        String authorizationJson = authorizationData.toString();

        String authorization = Base64.getEncoder().encodeToString(authorizationJson.getBytes());


        System.out.println("请求成功！" + authorization);

    }


    private static String md5(String input) {

        try {

            java.security.MessageDigest md = java.security.MessageDigest.getInstance("MD5");

            md.update(input.getBytes());

            byte[] digest = md.digest();

            StringBuilder sb = new StringBuilder();

            for (byte b : digest) {

                sb.append(String.format("%02x", b));

            }

            return sb.toString();

        } catch (java.security.NoSuchAlgorithmException e) {

            throw new RuntimeException("MD5 algorithm not found", e);

        }

    }

}
```

# 访问频率限制

- 每个应用appid调用单个api接口不可超过 100次/分钟

# API列表

## 商品

### 获取全部商品分类

#### 请求参数

| 参数名称 | 参数类型 | 是否必填 | 参数描述                           |
|----------|----------|----------|------------------------------------|
| locale   | string   | N        | 语言，默认：zh_cn，可传：zh_cn，en |

#### 请求方式&地址

```
[get] /openapi/productCategory/getAll
```

#### 返回参数

| 参数名称                     | 参数类型            | 是否 回传 | 参数描述                  | 示例值                                             |
|------------------------------|---------------------|-----------|---------------------------|----------------------------------------------------|
| code                         | string              |           | 返回编码                  | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                          | string              |           | 执行成功                  |                                                    |
| data                         | array               |           | 业务数据                  |                                                    |
| -\| classification           | array               |           | 数据结果列表              |                                                    |
| -\| -\| id                   | bigint(20) unsigned |           | ID                        |                                                    |
| -\| -\| parent_id            | bigint(20) unsigned |           | 父级分类ID                |                                                    |
| -\| -\| icon                 | varchar(255)        |           | 分类icon                  |                                                    |
| -\| -\| change_icon          | varchar(255)        |           | 变化icon                  |                                                    |
| -\| -\| image                | varchar(191)        |           | 图片                      |                                                    |
| -\| -\| image_url            | varchar(255)        |           | 图片跳转路由              |                                                    |
| -\| -\| overseas_image       | varchar(255)        |           | 海外仓分类图片            |                                                    |
| -\| -\| position             | int(11)             |           | 排序                      |                                                    |
| -\| -\| active               | tinyint(1)          |           | 是否启用                  |                                                    |
| -\| -\| created_at           | timestamp           |           |                           |                                                    |
| -\| -\| updated_at           | timestamp           |           |                           |                                                    |
| -\| -\| description          | object              |           | 产品分类名称、描述等详情  |                                                    |
| -\| -\| children             | array               |           | 子分类                    |                                                    |
| -\| -\| -\| id               | bigint(20) unsigned |           | ID                        |                                                    |
| -\| -\| -\| category_id      | bigint(20) unsigned |           | 分类 ID                   |                                                    |
| -\| -\| -\| locale           | varchar(191)        |           | 语言                      |                                                    |
| -\| -\| -\| name             | varchar(191)        |           | 名称                      |                                                    |
| -\| -\| -\| content          | text                |           | 描述                      |                                                    |
| -\| -\| -\| meta_title       | varchar(191)        |           | meta 标题                 |                                                    |
| -\| -\| -\| meta_description | varchar(191)        |           | meta 描述                 |                                                    |
| -\| -\| -\| meta_keywords    | varchar(191)        |           | meta 关键词               |                                                    |
| -\| -\| -\| created_at       | timestamp           |           |                           |                                                    |
| -\| -\| -\| updated_at       | timestamp           |           |                           |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": {

        "classification": [

            {

                "id": 100121,

                "parent\_id": 0,

                "icon": "/catalog/cosmetics/slices-icon/17252580218\_美容护理.svg",

                "change\_icon": "/catalog/cosmetics/slices-icon/172525802155\_美容护理hover.svg",

                "image": "/catalog/cosmetics/导航banner/170453620350\_导航banner.png",

                "image\_url": "",

                "position": 99,

                "description": {

                    "id": 994,

                    "category\_id": 100121,

                    "locale": "zh\_cn",

                    "name": "美容护理",

                    "content": "美容护理",

                    "meta\_title": "",

                    "meta\_description": "",

                    "meta\_keywords": "",

                    "created\_at": "2024-09-02 14:21:29",

                    "updated\_at": "2024-09-02 14:21:29"

                },

                "children": [

                    {

                        "id": 100225,

                        "parent\_id": 100121,

                        "icon": "",

                        "change\_icon": "",

                        "image": "catalog/newImage/beauty.png",

                        "image\_url": null,

                        "position": 0,

                        "description": {

                            "id": 408,

                            "category\_id": 100225,

                            "locale": "zh\_cn",

                            "name": "面部美容护理",

                            "content": "面部美容护理",

                            "meta\_title": "",

                            "meta\_description": "",

                            "meta\_keywords": "",

                            "created\_at": "2023-10-07 08:12:35",

                            "updated\_at": "2023-10-07 08:12:35"

                        },

                        "children": [

                            {

                                "id": 100227,

                                "parent\_id": 100225,

                                "icon": "",

                                "change\_icon": "",

                                "image": "catalog/newImage/beauty.png",

                                "image\_url": null,

                                "position": 0,

                                "description": {

                                    "id": 412,

                                    "category\_id": 100227,

                                    "locale": "zh\_cn",

                                    "name": "洗面乳",

                                    "content": "洗面乳",

                                    "meta\_title": "",

                                    "meta\_description": "",

                                    "meta\_keywords": "",

                                    "created\_at": "2023-10-07 08:12:35",

                                    "updated\_at": "2023-10-07 08:12:35"

                                }

                            }

                        ]

                    }

                ]

            }

        ]

    }

}
```

### 根据父级id获取子分类

#### 请求参数

| 参数名称 | 参数类型 | 是否必填 | 参数描述                      |
|----------|----------|----------|-------------------------------|
| parentId | int      | N        | 父级id，不传为0，获取顶级分类 |
| locale   | string   | N        | 默认：zh_cn，可传：zh_cn，en  |

#### 请求参数示例

```
/openapi/productCategory/getCategoryChildren?parentId=0
```

#### 请求方式&地址

```
[get] /openapi/productCategory/getCategoryChildren
```

#### 返回参数

| 参数名称                     | 参数类型            | 是否 回传 | 参数描述                  | 示例值                                             |
|------------------------------|---------------------|-----------|---------------------------|----------------------------------------------------|
| code                         | string              |           | 返回编码                  | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                          | string              |           | 执行成功                  |                                                    |
| data                         | array               |           | 业务数据                  |                                                    |
| -\| classification           | array               |           | 数据结果列表              |                                                    |
| -\| -\| id                   | bigint(20) unsigned |           | ID                        |                                                    |
| -\| -\| parent_id            | bigint(20) unsigned |           | 父级分类ID                |                                                    |
| -\| -\| icon                 | varchar(255)        |           | 分类icon                  |                                                    |
| -\| -\| change_icon          | varchar(255)        |           | 变化icon                  |                                                    |
| -\| -\| image                | varchar(191)        |           | 图片                      |                                                    |
| -\| -\| image_url            | varchar(255)        |           | 图片跳转路由              |                                                    |
| -\| -\| overseas_image       | varchar(255)        |           | 海外仓分类图片            |                                                    |
| -\| -\| position             | int(11)             |           | 排序                      |                                                    |
| -\| -\| active               | tinyint(1)          |           | 是否启用                  |                                                    |
| -\| -\| created_at           | timestamp           |           |                           |                                                    |
| -\| -\| updated_at           | timestamp           |           |                           |                                                    |
| -\| -\| description          | object              |           | 产品分类名称、描述等详情  |                                                    |
| -\| -\| children             | array               |           | 子分类                    |                                                    |
| -\| -\| -\| id               | bigint(20) unsigned |           | ID                        |                                                    |
| -\| -\| -\| category_id      | bigint(20) unsigned |           | 分类 ID                   |                                                    |
| -\| -\| -\| locale           | varchar(191)        |           | 语言                      |                                                    |
| -\| -\| -\| name             | varchar(191)        |           | 名称                      |                                                    |
| -\| -\| -\| content          | text                |           | 描述                      |                                                    |
| -\| -\| -\| meta_title       | varchar(191)        |           | meta 标题                 |                                                    |
| -\| -\| -\| meta_description | varchar(191)        |           | meta 描述                 |                                                    |
| -\| -\| -\| meta_keywords    | varchar(191)        |           | meta 关键词               |                                                    |
| -\| -\| -\| created_at       | timestamp           |           |                           |                                                    |
| -\| -\| -\| updated_at       | timestamp           |           |                           |                                                    |

#### 返回参数示例

```
{

	"status": "success",

	"message": "success",

	"data": [

		{

			"id": 100167,

			"parent\_id": 100124,

			"icon": "",

			"change\_icon": "",

			"image": "catalog/newImage/beauty.png",

			"image\_url": "",

			"position": 0,

			"description": {

				"id": 774,

				"category\_id": 100167,

				"locale": "zh\_cn",

				"name": "牙龈护理",

				"content": "牙龈护理",

				"meta\_title": "",

				"meta\_description": "",

				"meta\_keywords": "",

				"created\_at": "2024-01-16 09:32:31",

				"updated\_at": "2024-01-16 09:32:31"

			}

		},

		{

			"id": 100193,

			"parent\_id": 100124,

			"icon": "",

			"change\_icon": "",

			"image": "catalog/newImage/beauty.png",

			"image\_url": null,

			"position": 0,

			"description": {

				"id": 344,

				"category\_id": 100193,

				"locale": "zh\_cn",

				"name": "假牙清洗泡腾片",

				"content": "假牙清洗泡腾片",

				"meta\_title": "",

				"meta\_description": "",

				"meta\_keywords": "",

				"created\_at": "2023-10-07 08:12:35",

				"updated\_at": "2023-10-07 08:12:35"

			}

		}

	]

}
```

### 获取发货区域编码

#### 请求参数

| 参数名称 | 参数类型 | 是否必填 | 参数描述   |
|----------|----------|----------|------------|
| code     | string   | N        | 国家二字码 |

#### 请求参数示例

```
{

    "code":"CN"

}
```

#### 请求方式&地址

```
[get] /openapi/productCategory/getDeliveryRegions
```

#### 返回参数

| 参数名称 | 参数类型         | 是否 回传 | 参数描述     | 示例值                                              |
|----------|------------------|-----------|--------------|-----------------------------------------------------|
| code     | string           |           | 返回编码     | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限  |
| msg      | string           |           | 执行成功     |                                                     |
| data     | array            |           | 业务数据     |                                                     |
| -\| id   | int(11) unsigned |           |              |                                                     |
| -\| name | varchar(255)     |           | 发货区域名称 |                                                     |
| -\| code | varchar(255)     |           | 编码         |                                                     |

#### 返回参数示例

```
{

    "status": "success",

    "message": "success",

    "data": [

        {

            "id": 5,

            "name": "中国",

            "code": "CN"

        }

    ]

}
```

### 获取所有品牌

#### 请求参数

无

#### 请求参数示例

```
/openapi/productCategory/getBrands
```

#### 请求方式&地址

```
[get] /openapi/productCategory/getBrands
```

#### 返回参数

| 参数名称       | 参数类型            | 是否 回传 | 参数描述 | 示例值                                              |
|----------------|---------------------|-----------|----------|-----------------------------------------------------|
| code           | string              |           | 返回编码 | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限  |
| msg            | string              |           | 执行成功 |                                                     |
| data           | array               |           | 业务数据 |                                                     |
| -\| id         | bigint(20) unsigned |           | ID       |                                                     |
| -\| name       | varchar(191)        |           | 名称     |                                                     |
| -\| first      | char(191)           |           | 首字母   |                                                     |
| -\| logo       | varchar(191)        |           | 图标     |                                                     |
| -\| sort_order | int(11)             |           | 排序     |                                                     |

#### 返回参数示例

```
{

	"status": "success",

	"message": "success",

	"data": [

		{

			"id": 44,

			"name": "ODM",

			"first": "O",

			"logo": "/catalog/westmonth/Brand/17116834706\_ODM.jpg",

			"sort\_order": 32

		},

		{

			"id": 42,

			"name": "OEM",

			"first": "O",

			"logo": "/catalog/westmonth/Brand/171168347069\_OEM.jpg",

			"sort\_order": 31

		}

    ]

}
```

### 获取所有平台

#### 请求参数

无

#### 请求参数示例

```
/openapi/productCategory/getPlatforms
```

#### 请求方式&地址

```
[get] /openapi/productCategory/getPlatforms
```

#### 返回参数

| 参数名称       | 参数类型            | 是否 回传 | 参数描述 | 示例值                                              |
|----------------|---------------------|-----------|----------|-----------------------------------------------------|
| code           | string              |           | 返回编码 | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限  |
| msg            | string              |           | 执行成功 |                                                     |
| data           | array               |           | 业务数据 |                                                     |
| id             | bigint(20) unsigned |           | ID       |                                                     |
| -\| name       | varchar(191)        |           | 名称     |                                                     |
| -\| first      | char(191)           |           | 首字母   |                                                     |
| -\| logo       | varchar(191)        |           | 图标     |                                                     |
| -\| sort_order | int(11)             |           | 排序     |                                                     |

#### 返回参数示例

```
{

	"status": "success",

	"message": "success",

	"data": [

		{

			"id": 26,

			"name": "shopline",

			"first": "S",

			"logo": "/catalog/170453751177\_平台爆款 亚马逊.png",

			"sort\_order": 99

		},

		{

			"id": 27,

			"name": "SHOP-FINE",

			"first": "S",

			"logo": "/catalog/平台Logo/平台爆款shein.png",

			"sort\_order": 99

		}

    ]

}
```

### 获取所有证书

#### 请求参数

无

#### 请求参数示例

```
/openapi/productCategory/getCertificates
```

#### 请求方式&地址

```
[get] /openapi/productCategory/getCertificates
```

#### 返回参数

| 参数名称         | 参数类型 | 是否 回传 | 参数描述      | 示例值                                              |
|------------------|----------|-----------|---------------|-----------------------------------------------------|
| code             | string   |           | 返回编码      | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限  |
| msg              | string   |           | 执行成功      |                                                     |
| data             | array    |           | 业务数据      |                                                     |
| -\| id           | int      |           | 证书分类id    |                                                     |
| -\| abbreviation | string   |           | 证书分类简称  |                                                     |

#### 返回参数示例

```
{

  "status": "success",

  "message": "success",

  "data": [

    {

      "id": 12,

      "abbreviation": "COAEN"

    },

    {

      "id": 11,

      "abbreviation": "COACN"

    },

    {

      "id": 10,

      "abbreviation": "THFDA"

    },

    {

      "id": 9,

      "abbreviation": "MSDS(NEW)"

    },

    {

      "id": 8,

      "abbreviation": "MSDS(CHEMICAL)"

    },

    {

      "id": 7,

      "abbreviation": "FDA"

    },

    {

      "id": 6,

      "abbreviation": "NDC"

    },

    {

      "id": 5,

      "abbreviation": "CN"

    },

    {

      "id": 4,

      "abbreviation": "CPNP"

    },

    {

      "id": 3,

      "abbreviation": "SCPN"

    },

    {

      "id": 2,

      "abbreviation": "MFDS"

    },

    {

      "id": 1,

      "abbreviation": "MSDS"

    }

  ]

}
```

### 获取证书

#### 请求参数

| 参数名称   | 参数类型 | 是否必填 | 参数描述                              |
|------------|----------|----------|---------------------------------------|
| skus       | array    | Y        | 商品sku列表，不超过50个               |
| start_time | string   | N        | 更新开始时间，例：2024-12-17 18:24:47 |
| end_time   | string   | N        | 更新结束时间，例：2024-12-17 18:24:47 |

#### 请求参数示例

```
{

    "skus": [

        "ROA10-A006-10-BU1",

        "ROA10-A006-10-PK1"

    ]

}
```

#### 请求方式&地址

```
[post] /openapi/sku\_certificats/query
```

#### 返回参数

| 参数名称               | 参数类型 | 是否 回传 | 参数描述                 | 示例值                                             |
|------------------------|----------|-----------|--------------------------|----------------------------------------------------|
| code                   | string   | Y         | 错误码                   | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                    | string   | Y         | 错误描述                 | 执行成功                                           |
| data                   | object   | Y         | 业务数据                 |                                                    |
|                        | array    | Y         | 结果列表                 |                                                    |
| -\| sku                | string   | Y         | 商品sku                  | EEA02-A144-30-WH1                                  |
| -\| certificates       | array    | Y         | 证书列表                 |                                                    |
| -\|-\| assort_name     | string   | Y         | 证书分类名称             | 韩国化妆品质量测试报告                             |
| -\|-\| abbreviation    | string   | Y         | 证书分类简写             | MFDS                                               |
| -\|-\| resource_url    | array    | Y         | 证书链接列表（所有版本） | ["oss.com/xxx.pdf"]                                |
| -\|-\| resource_url_v1 | array    | Y         | 证书链接列表（版本1）    |                                                    |
| -\|-\| resource_url_v2 | array    | Y         | 证书链接列表（版本2）    |                                                    |
| -\| latest_time        | string   | Y         | 证书列表更新最新时间     |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": [

        {

            "sku": "ROA10-A006-10-BU1",

            "certificates": [

                {

                    "assort\_name": "加拿大化妆品通报证明",

                    "abbreviation": "CN",

                    "resource\_url": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-CN-1732193915.png",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-CN-12224590.png"

                    ],

                    "resource\_url\_v1": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-CN-1732193915.png"

                    ],

                    "resource\_url\_v2": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-CN-12224590.png"

                    ]

                },

                {

                    "assort\_name": "欧盟化妆品通报证明",

                    "abbreviation": "CPNP",

                    "resource\_url": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-CPNP-1732193916.pdf",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-CPNP-72153981.pdf"

                    ],

                    "resource\_url\_v1": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-CPNP-1732193916.pdf"

                    ],

                    "resource\_url\_v2": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-CPNP-72153981.pdf"

                    ]

                },

                {

                    "assort\_name": "韩国化妆品质量测试报告",

                    "abbreviation": "MFDS",

                    "resource\_url": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-MFDS-1732193917.pdf",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-MFDS-35028109.pdf"

                    ],

                    "resource\_url\_v1": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-MFDS-1732193917.pdf"

                    ],

                    "resource\_url\_v2": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-MFDS-35028109.pdf"

                    ]

                },

                {

                    "assort\_name": "英国化妆品通报证明",

                    "abbreviation": "SCPN",

                    "resource\_url": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-SCPN-1732193919.pdf",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-SCPN-90755476.pdf"

                    ],

                    "resource\_url\_v1": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-SCPN-1732193919.pdf"

                    ],

                    "resource\_url\_v2": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-SCPN-90755476.pdf"

                    ]

                },

                {

                    "assort\_name": "化学品安全说明书（新版）",

                    "abbreviation": "MSDS(NEW)",

                    "resource\_url": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-MSDS(NEW)-1732194726.pdf",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-MSDS(NEW)-93655176.pdf"

                    ],

                    "resource\_url\_v1": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-MSDS(NEW)-1732194726.pdf"

                    ],

                    "resource\_url\_v2": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-MSDS(NEW)-93655176.pdf"

                    ]

                },

                {

                    "assort\_name": "美国化妆品监督证明",

                    "abbreviation": "FDA",

                    "resource\_url": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-1732356722.jpeg",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-1732356723.jpeg",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-1732356724.jpg",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-1732356725.jpg",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-99875695.png",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-27257789.png",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-29577078.png",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-31238601.png"

                    ],

                    "resource\_url\_v1": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-1732356722.jpeg",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-1732356723.jpeg",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-1732356724.jpg",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-1732356725.jpg"

                    ],

                    "resource\_url\_v2": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-99875695.png",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-27257789.png",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-29577078.png",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-31238601.png"

                    ]

                }

            ],

            "latest\_time": "2025-03-03 10:29:04"

        },

        {

            "sku": "ROA10-A006-10-PK1",

            "certificates": [

                {

                    "assort\_name": "加拿大化妆品通报证明",

                    "abbreviation": "CN",

                    "resource\_url": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-CN-1732193910.png",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-CN-37365469.png"

                    ],

                    "resource\_url\_v1": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-CN-1732193910.png"

                    ],

                    "resource\_url\_v2": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-CN-37365469.png"

                    ]

                },

                {

                    "assort\_name": "欧盟化妆品通报证明",

                    "abbreviation": "CPNP",

                    "resource\_url": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-CPNP-1732193911.pdf",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-CPNP-72970773.pdf"

                    ],

                    "resource\_url\_v1": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-CPNP-1732193911.pdf"

                    ],

                    "resource\_url\_v2": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-CPNP-72970773.pdf"

                    ]

                },

                {

                    "assort\_name": "韩国化妆品质量测试报告",

                    "abbreviation": "MFDS",

                    "resource\_url": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-MFDS-1732193912.pdf",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-MFDS-10890760.pdf"

                    ],

                    "resource\_url\_v1": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-MFDS-1732193912.pdf"

                    ],

                    "resource\_url\_v2": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-MFDS-10890760.pdf"

                    ]

                },

                {

                    "assort\_name": "英国化妆品通报证明",

                    "abbreviation": "SCPN",

                    "resource\_url": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-SCPN-1732193914.pdf",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-SCPN-56858628.pdf"

                    ],

                    "resource\_url\_v1": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-SCPN-1732193914.pdf"

                    ],

                    "resource\_url\_v2": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-SCPN-56858628.pdf"

                    ]

                },

                {

                    "assort\_name": "美国化妆品监督证明",

                    "abbreviation": "FDA",

                    "resource\_url": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-1732356493.png",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-1732356494.png",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-1732356495.png",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-1732356496.png",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-98825300.png",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-75317620.png",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-13607173.png",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-95774517.png"

                    ],

                    "resource\_url\_v1": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-1732356493.png",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-1732356494.png",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-1732356495.png",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-1732356496.png"

                    ],

                    "resource\_url\_v2": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-98825300.png",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-75317620.png",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-13607173.png",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-95774517.png"

                    ]

                },

                {

                    "assort\_name": "化学品安全说明书（新版）",

                    "abbreviation": "MSDS(NEW)",

                    "resource\_url": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-MSDS(NEW)-1732699836.pdf",

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-MSDS(NEW)-54637794.pdf"

                    ],

                    "resource\_url\_v1": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-MSDS(NEW)-1732699836.pdf"

                    ],

                    "resource\_url\_v2": [

                        "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-MSDS(NEW)-54637794.pdf"

                    ]

                }

            ],

            "latest\_time": "2025-03-03 10:29:26"

        }

    ]

}
```

### 获取六面图

#### 请求参数

| 参数名称   | 参数类型 | 是否必填 | 参数描述                              |
|------------|----------|----------|---------------------------------------|
| skus       | array    | Y        | 商品sku列表，不超过200个              |
| start_time | string   | N        | 更新开始时间，例：2024-12-17 18:24:47 |
| end_time   | string   | N        | 更新结束时间，例：2024-12-17 18:24:47 |

#### 请求参数示例

```
{

    "skus": [

        "ROA10-A006-10-BU1",

        "ROA10-A006-10-PK1"

    ]

}
```

#### 请求方式&地址

```
[post] /openapi/sku\_diagrams/query
```

#### 返回参数

| 参数名称        | 参数类型 | 是否 回传 | 参数描述             | 示例值                                             |
|-----------------|----------|-----------|----------------------|----------------------------------------------------|
| code            | string   | Y         | 错误码               | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg             | string   | Y         | 错误描述             | 执行成功                                           |
| data            | array    | Y         | 业务数据             |                                                    |
| -\| sku         | string   | Y         | 商品sku              | EEA02-A144-30-WH1                                  |
| -\| images      | array    | Y         | 图片列表（所有版本） |                                                    |
| -\| images_v1   | array    | Y         | 图片列表（版本1）    |                                                    |
| -\| images_v2   | array    | Y         | 图片列表（版本2）    |                                                    |
| -\| latest_time | string   | Y         | 图片列表最新更新时间 |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": [

        {

            "sku": "ROA10-A006-10-BU1",

            "images": [

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-0.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-2.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-1.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-3.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-4.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-6.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-5.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-1730779921.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-1730779922.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-1730779924.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-1730779923.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-1730779925.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-1730779926.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-49493822.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-49493823.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-49493824.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-49493825.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-49493826.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-49493827.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-49493828.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-56821299.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-90514145.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-30715737.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-23671180.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-99216958.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-71829421.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-66147301.jpg"

            ],

            "images\_v1": [

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-0.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-2.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-1.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-3.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-4.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-6.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-5.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-1730779921.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-1730779922.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-1730779924.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-1730779923.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-1730779925.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-1730779926.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-49493822.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-49493823.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-49493824.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-49493825.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-49493826.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-49493827.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-49493828.jpg"

            ],

            "images\_v2": [

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-56821299.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-90514145.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-30715737.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-23671180.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-99216958.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-71829421.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-BU1/view\_picture/ROA10-A006-10-BU1-66147301.jpg"

            ],

            "latest\_time": "2025-03-03 10:29:04"

        },

        {

            "sku": "ROA10-A006-10-PK1",

            "images": [

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-0.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-2.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-3.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-5.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-4.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-6.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1729048001.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1729048002.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1729048003.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1729048004.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1729048005.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1729048006.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1731733883.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1731733884.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1731733886.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1731733885.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1731733887.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1731733888.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-49493829.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-49493830.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-49493831.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-49493832.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-49493833.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-49493834.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-49493835.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-41273245.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-49431605.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-80903116.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-60141819.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-49239383.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-41771286.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-16357881.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-17455113.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-77255611.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-37265475.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-75694267.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-50866115.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-64458122.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-83853649.jpg"

            ],

            "images\_v1": [

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-0.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-2.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-3.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-5.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-4.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-6.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1729048001.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1729048002.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1729048003.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1729048004.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1729048005.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1729048006.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1731733883.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1731733884.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1731733886.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1731733885.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1731733887.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-1731733888.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-49493829.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-49493830.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-49493831.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-49493832.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-49493833.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-49493834.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-49493835.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-41273245.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-49431605.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-80903116.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-60141819.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-49239383.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-41771286.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-16357881.jpg"

            ],

            "images\_v2": [

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-17455113.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-77255611.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-37265475.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-75694267.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-50866115.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-64458122.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/ROA10-A006-10-PK1/view\_picture/ROA10-A006-10-PK1-83853649.jpg"

            ],

            "latest\_time": "2025-03-03 10:29:26"

        }

    ]

}
```

### 

### 获取商品图包

#### 请求参数

| 参数名称   | 参数类型 | 是否必填 | 参数描述                              |
|------------|----------|----------|---------------------------------------|
| skus       | array    | Y        | 商品sku列表，不超过200个              |
| start_time | string   | N        | 更新开始时间，例：2024-12-17 18:24:47 |
| end_time   | string   | N        | 更新结束时间，例：2024-12-17 18:24:47 |

#### 请求参数示例

```
{

    "skus": [

        "EEA02-A142-4-YE1",

        "WEA03-A051-30-WH1",

        "EEA08-A125-5-BK1"

    ]

}
```

#### 请求方式&地址

```
[post] /openapi/sku\_images\_package/query
```

#### 返回参数

| 参数名称           | 参数类型 | 是否 回传 | 参数描述             | 示例值                                             |
|--------------------|----------|-----------|----------------------|----------------------------------------------------|
| code               | string   | Y         | 错误码               | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                | string   | Y         | 错误描述             | 执行成功                                           |
| data               | array    | Y         | 业务数据             |                                                    |
| -\| sku            | string   | Y         | 商品sku              |                                                    |
| -\| images         | array    | Y         | 图片列表             |                                                    |
| -\|-\| product     | array    | Y         | 产品图列表           |                                                    |
| -\|-\| sku         | array    | Y         | SKU图片列表          |                                                    |
| -\|-\| description | array    | Y         | 详情图列表           |                                                    |
| -\| latest_time    | string   | Y         | 图片列表最新更新时间 |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": [

        {

            "sku": "EEA02-A142-4-YE1",

            "images": {

                "product": [

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA02-A142-4-YE1/mainpicture/EEA02-A142-4-YE1-1728361818017.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA02-A142-4-YE1/mainpicture/EEA02-A142-4-YE1-1728361818002.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA02-A142-4-YE1/mainpicture/EEA02-A142-4-YE1-1728361818015.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA02-A142-4-YE1/mainpicture/EEA02-A142-4-YE1-1728361818012.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA02-A142-4-YE1/mainpicture/EEA02-A142-4-YE1-1734080433015.png",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA02-A142-4-YE1/mainpicture/EEA02-A142-4-YE1-1728361818014.jpg"

                ],

                "sku": [

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA02-A142-4-YE1/mainpicture/EEA02-A142-4-YE1-1728361818017.jpg"

                ],

                "description": [

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA02-A142-4-YE1/mainpicture/EEA02-A142-4-YE1-1728361818001.gif",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA02-A142-4-YE1/mainpicture/EEA02-A142-4-YE1-1728361818018.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA02-A142-4-YE1/mainpicture/EEA02-A142-4-YE1-1728361818003.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA02-A142-4-YE1/mainpicture/EEA02-A142-4-YE1-1734080433015.png",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA02-A142-4-YE1/mainpicture/EEA02-A142-4-YE1-1728361818014.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA02-A142-4-YE1/mainpicture/EEA02-A142-4-YE1-1728361818008.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA02-A142-4-YE1/mainpicture/EEA02-A142-4-YE1-1728361818015.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA02-A142-4-YE1/mainpicture/EEA02-A142-4-YE1-1728361818009.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA02-A142-4-YE1/mainpicture/EEA02-A142-4-YE1-1728361818013.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA02-A142-4-YE1/mainpicture/EEA02-A142-4-YE1-1728361818012.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA02-A142-4-YE1/mainpicture/EEA02-A142-4-YE1-1728361818011.jpg"

                ]

            },

            "latest\_time": "2024-12-30 18:32:17"

        },

        {

            "sku": "EEA08-A125-5-BK1",

            "images": {

                "product": [

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA08-A125-5-BK1/mainpicture/EEA08-A125-5-BK1-1009.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA08-A125-5-BK1/mainpicture/EEA08-A125-5-BK1-1001.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA08-A125-5-BK1/mainpicture/EEA08-A125-5-BK1-1005.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA08-A125-5-BK1/mainpicture/EEA08-A125-5-BK1-1006.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA08-A125-5-BK1/mainpicture/EEA08-A125-5-BK1-1007.jpg"

                ],

                "sku": [

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA08-A125-5-BK1/mainpicture/EEA08-A125-5-BK1-1009.jpg"

                ],

                "description": [

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA08-A125-5-BK1/mainpicture/EEA08-A125-5-BK1-1010.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA08-A125-5-BK1/mainpicture/EEA08-A125-5-BK1-1001.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA08-A125-5-BK1/mainpicture/EEA08-A125-5-BK1-1007.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA08-A125-5-BK1/mainpicture/EEA08-A125-5-BK1-1006.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA08-A125-5-BK1/mainpicture/EEA08-A125-5-BK1-1002.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA08-A125-5-BK1/mainpicture/EEA08-A125-5-BK1-1003.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA08-A125-5-BK1/mainpicture/EEA08-A125-5-BK1-1004.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA08-A125-5-BK1/mainpicture/EEA08-A125-5-BK1-1005.jpg"

                ]

            },

            "latest\_time": "2024-09-13 16:59:30"

        },

        {

            "sku": "WEA03-A051-30-WH1",

            "images": {

                "product": [

                    "https://oss.westmonth.com/westmonth/SKUDatum/old/WEA03-A051-30-WH1/mainpicture/WEA03-A051-30-WH1-001.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/old/WEA03-A051-30-WH1/mainpicture/WEA03-A051-30-WH1-002.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/old/WEA03-A051-30-WH1/mainpicture/WEA03-A051-30-WH1-003.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/old/WEA03-A051-30-WH1/mainpicture/WEA03-A051-30-WH1-004.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/old/WEA03-A051-30-WH1/mainpicture/WEA03-A051-30-WH1-005.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/old/WEA03-A051-30-WH1/mainpicture/WEA03-A051-30-WH1-006.jpg"

                ],

                "sku": [

                    "https://oss.westmonth.com/westmonth/SKUDatum/old/WEA03-A051-30-WH1/mainpicture/WEA03-A051-30-WH1-001.jpg"

                ],

                "description": [

                    "https://oss.westmonth.com/westmonth/SKUDatum/old/WEA03-A051-30-WH1/product\_content/WEA03-A051-30-WH1-001.gif",

                    "https://oss.westmonth.com/westmonth/SKUDatum/old/WEA03-A051-30-WH1/product\_content/WEA03-A051-30-WH1-002.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/old/WEA03-A051-30-WH1/product\_content/WEA03-A051-30-WH1-003.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/old/WEA03-A051-30-WH1/product\_content/WEA03-A051-30-WH1-004.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/old/WEA03-A051-30-WH1/product\_content/WEA03-A051-30-WH1-005.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/old/WEA03-A051-30-WH1/product\_content/WEA03-A051-30-WH1-006.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/old/WEA03-A051-30-WH1/product\_content/WEA03-A051-30-WH1-007.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/old/WEA03-A051-30-WH1/product\_content/WEA03-A051-30-WH1-008.jpg",

                    "https://oss.westmonth.com/westmonth/SKUDatum/old/WEA03-A051-30-WH1/product\_content/WEA03-A051-30-WH1-009.jpg"

                ]

            },

            "latest\_time": "2024-12-21 17:39:18"

        }

    ]

}
```

### 获取视频

#### 请求参数

| 参数名称   | 参数类型 | 是否必填 | 参数描述                              |
|------------|----------|----------|---------------------------------------|
| skus       | array    | Y        | 商品sku列表，不超过200个              |
| start_time | string   | N        | 更新开始时间，例：2024-12-17 18:24:47 |
| end_time   | string   | N        | 更新结束时间，例：2024-12-17 18:24:47 |

#### 请求参数示

```
{

    "skus": [

        "EEA02-A142-4-YE1",

        "WEA03-A051-30-WH1"

    ]

}
```

#### 请求方式&地址

```
[post] /openapi/sku\_video/query
```

#### 返回参数

| 参数名称        | 参数类型 | 是否 回传 | 参数描述     | 示例值                                             |
|-----------------|----------|-----------|--------------|----------------------------------------------------|
| code            | string   | Y         | 错误码       | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg             | string   | Y         | 错误描述     | 执行成功                                           |
| data            | array    | Y         | 业务数据     |                                                    |
| -\| sku         | string   | Y         | 商品sku      |                                                    |
| -\| video       | string   | Y         | 视频链接     |                                                    |
| -\| latest_time | string   | Y         | 最新更新时间 |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": [

        {

            "sku": "EEA02-A142-4-YE1",

            "video": "https://oss.westmonth.com/westmonth/SKUDatum/EEA02-A142-4-YE1/mainpicture/EEA02-A142-4-YE1-1728361818000.mp4",

            "latest\_time": "2024-12-30 18:32:17"

        },

        {

            "sku": "WEA03-A051-30-WH1",

            "video": "https://oss.westmonth.com/westmonth/SKUDatum/old/WEA03-A051-30-WH1/video/WEA03-A051-30-WH1-001.mp4",

            "latest\_time": "2024-12-19 09:52:49"

        }

    ]

}
```

### 获取一体化标签

#### 请求参数

| 参数名称   | 参数类型 | 是否必填 | 参数描述                              |
|------------|----------|----------|---------------------------------------|
| skus       | array    | Y        | 商品sku列表，不超过200个              |
| start_time | string   | N        | 更新开始时间，例：2024-12-17 18:24:47 |
| end_time   | string   | N        | 更新结束时间，例：2024-12-17 18:24:47 |

#### 请求参数示例

```
{

    "skus": [

        "EEA02-A142-4-YE1",

        "WEA03-A051-30-WH1"

    ]

}
```

#### 请求方式&地址

#### 返回参数

| 参数名称           | 参数类型 | 是否 回传 | 参数描述             | 示例值                                             |
|--------------------|----------|-----------|----------------------|----------------------------------------------------|
| code               | string   | Y         | 错误码               | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                | string   | Y         | 错误描述             | 执行成功                                           |
| data               | array    | Y         | 业务数据             |                                                    |
| -\| v1             | array    | Y         | 版本1                |                                                    |
| -\|-\| sku         | string   | Y         | sku                  |                                                    |
| -\|-\| urls        | array    | Y         | 标签数组信息         |                                                    |
| -\|-\| latest_time | string   | Y         | 标签列表最新更新时间 |                                                    |
| -\| v2             | array    | Y         | 版本2                |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": {

        "v1": {

            "EEA02-A142-4-YE1": {

                "sku": "EEA02-A142-4-YE1",

                "urls": [

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA02-A142-4-YE1/integrationtag/EEA02-A142-4-YE1-SHEIN-1730112672.pdf",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA02-A142-4-YE1/integrationtag/EEA02-A142-4-YE1-TEMU-1730113257.pdf",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA02-A142-4-YE1/integrationtag/EEA02-A142-4-YE1-TEMU-95176820.pdf",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA02-A142-4-YE1/integrationtag/EEA02-A142-4-YE1-TEMU-80275874.pdf"

                ],

                "latest\_time": "2024-12-28 17:19:20"

            }

        },

        "v2": {

            "EEA02-A142-4-YE1": {

                "sku": "EEA02-A142-4-YE1",

                "urls": [

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA02-A142-4-YE1/integrationtag/EEA02-A142-4-YE1-SHEIN-SHEIN-46988280.pdf",

                    "https://oss.westmonth.com/westmonth/SKUDatum/EEA02-A142-4-YE1/integrationtag/EEA02-A142-4-YE1-TEMU-TEMU-36821655.pdf"

                ],

                "latest\_time": "2024-12-17 21:05:34"

            }

        }

    }

}
```

### 获取产品使用说明书

#### 请求参数

| 参数名称   | 参数类型 | 是否必填 | 参数描述                              |
|------------|----------|----------|---------------------------------------|
| start_time | string   | N        | 更新开始时间，例：2024-12-17 18:24:47 |
| end_time   | string   | N        | 更新结束时间，例：2024-12-17 18:24:47 |

#### 请求参数示例

```
/openapi/sku\_instruction/query
```

#### 请求方式&地址

```
[get] /openapi/sku\_instruction/query
```

#### 返回参数

| 参数名称        | 参数类型 | 是否 回传 | 参数描述           | 示例值                                             |
|-----------------|----------|-----------|--------------------|----------------------------------------------------|
| code            | string   | Y         | 错误码             | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg             | string   | Y         | 错误描述           | 执行成功                                           |
| data            | array    | Y         | 业务数据           |                                                    |
| -\| url         | string   | Y         | 产品使用说明书链接 |                                                    |
| -\| latest_time | string   | Y         | 最新更新时间       |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": [

        {

            "url": "https://oss.westmonth.com/westmonth/SKUDatum/sku\_instruction/通用产品使用说明书（英德法意西日）10X10.pdf",

            "latest\_time": "2024-10-23 17:19:58"

        }

    ]

}
```

### 获取英代欧代美代协议

#### 请求参数

| 参数名称   | 参数类型 | 是否必填 | 参数描述                              |
|------------|----------|----------|---------------------------------------|
| skus       | array    | Y        | 商品sku列表，不超过200个              |
| start_time | string   | N        | 更新开始时间，例：2024-12-17 18:24:47 |
| end_time   | string   | N        | 更新结束时间，例：2024-12-17 18:24:47 |

#### 请求参数示

```
{

    "skus": [

        "YE-G01-0160-01",

        "HO-A06-0109-01"

    ]

}
```

#### 请求方式&地址

```
[post] /openapi/sku\_protocol/query
```

#### 返回参数

| 参数名称        | 参数类型 | 是否 回传 | 参数描述     | 示例值                                             |
|-----------------|----------|-----------|--------------|----------------------------------------------------|
| code            | string   | Y         | 错误码       | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg             | string   | Y         | 错误描述     | 执行成功                                           |
| data            | array    | Y         | 业务数据     |                                                    |
| -\| sku         | string   | Y         | 商品sku      |                                                    |
| -\| urls        | array    | Y         | 资源列表     |                                                    |
| -\| latest_time | string   | Y         | 最新更新时间 |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": [

        {

            "sku": "HO-A06-0109-01",

            "urls": [

                "https://oss.westmonth.com/westmonth/SKUDatum/protocol\_new\_version/【新版】TEMU英代填写参考89920027.png",

                "https://oss.westmonth.com/westmonth/SKUDatum/protocol\_new\_version/【新版】TEMU欧代填写参考38770440.png",

                "https://oss.westmonth.com/westmonth/SKUDatum/protocol\_new\_version/【新版】TEMU美代填写参考50138617.png",

                "https://oss.westmonth.com/westmonth/SKUDatum/protocol\_new\_version/【新版】英代79705575.pdf",

                "https://oss.westmonth.com/westmonth/SKUDatum/protocol\_new\_version/【新版】美代86108901.pdf",

                "https://oss.westmonth.com/westmonth/SKUDatum/protocol\_new\_version/【新版】欧代16649299.pdf"

            ],

            "latest\_time": "2025-01-17 18:01:56"

        },

        {

            "sku": "YE-G01-0160-01",

            "urls": [

                "https://oss.westmonth.com/westmonth/SKUDatum/protocol\_new\_version/【新版】TEMU美代填写参考29262623.png",

                "https://oss.westmonth.com/westmonth/SKUDatum/protocol\_new\_version/【新版】TEMU英代填写参考75323371.png",

                "https://oss.westmonth.com/westmonth/SKUDatum/protocol\_new\_version/【新版】TEMU欧代填写参考75181321.png",

                "https://oss.westmonth.com/westmonth/SKUDatum/protocol\_new\_version/【新版】美代39337377.pdf",

                "https://oss.westmonth.com/westmonth/SKUDatum/protocol\_new\_version/【新版】英代55631845.pdf",

                "https://oss.westmonth.com/westmonth/SKUDatum/protocol\_new\_version/【新版】欧代13089163.pdf"

            ],

            "latest\_time": "2024-12-09 12:11:44"

        }

    ]

}
```

### 获取产品文案

#### 请求参数

| 参数名称   | 参数类型 | 是否必填 | 参数描述                              |
|------------|----------|----------|---------------------------------------|
| skus       | array    | Y        | 商品sku列表，不超过200个              |
| start_time | string   | N        | 更新开始时间，例：2024-12-17 18:24:47 |
| end_time   | string   | N        | 更新结束时间，例：2024-12-17 18:24:47 |

#### 请求参数示

```
{

    "skus": [

        "WEA02-A075-30-BK1", 

        "XIB04-A051-7-BU1"

    ]

}
```

#### 请求方式&地址

```
[post] /openapi/sku\_copywriting/query
```

#### 返回参数

| 参数名称        | 参数类型 | 是否 回传 | 参数描述             | 示例值                                             |
|-----------------|----------|-----------|----------------------|----------------------------------------------------|
| code            | string   | Y         | 错误码               | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg             | string   | Y         | 错误描述             | 执行成功                                           |
| data            | array    | Y         | 业务数据             |                                                    |
| -\| sku         | string   | Y         | 商品sku              |                                                    |
| -\| urls        | array    | Y         | 资源列表（所有版本） |                                                    |
| -\| urls_v1     | array    | Y         | 资源列表（版本1）    |                                                    |
| -\| urls_v2     | array    | Y         | 资源列表（版本2）    |                                                    |
| -\| latest_time | string   | Y         | 最新更新时间         |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": [

        {

            "sku": "WEA02-A075-30-BK1",

            "urls": [

                "https://oss.westmonth.com/westmonth/SKUDatum/WEA02-A075-30-BK1/product\_content/WEA02-A075-30-BK1-460.docx",

                "https://oss.westmonth.com/westmonth/SKUDatum/WEA02-A075-30-BK1/product\_content/WEA02-A075-30-BK1-1732006908.docx",

                "https://oss.westmonth.com/westmonth/SKUDatum/WEA02-A075-30-BK1/product\_content/WEA02-A075-30-BK1-37468423.docx"

            ],

            "urls\_v1": [

                "https://oss.westmonth.com/westmonth/SKUDatum/WEA02-A075-30-BK1/product\_content/WEA02-A075-30-BK1-460.docx",

                "https://oss.westmonth.com/westmonth/SKUDatum/WEA02-A075-30-BK1/product\_content/WEA02-A075-30-BK1-1732006908.docx"

            ],

            "urls\_v2": [

                "https://oss.westmonth.com/westmonth/SKUDatum/WEA02-A075-30-BK1/product\_content/WEA02-A075-30-BK1-37468423.docx"

            ],

            "latest\_time": "2024-12-17 18:27:17"

        },

        {

            "sku": "XIB04-A051-7-BU1",

            "urls": [

                "https://oss.westmonth.com/westmonth/SKUDatum/XIB04-A051-7-BU1/product\_content/XIB04-A051-7-BU1-509.docx"

            ],

            "urls\_v1": [

                "https://oss.westmonth.com/westmonth/SKUDatum/XIB04-A051-7-BU1/product\_content/XIB04-A051-7-BU1-509.docx"

            ],

            "urls\_v2": [],

            "latest\_time": "2024-12-17 18:26:24"

        }

    ]

}
```

### 获取推品资料

#### 请求参数

| 参数名称   | 参数类型 | 是否必填 | 参数描述                              |
|------------|----------|----------|---------------------------------------|
| skus       | array    | Y        | 商品sku列表，不超过200个              |
| start_time | string   | N        | 更新开始时间，例：2024-12-17 18:24:47 |
| end_time   | string   | N        | 更新结束时间，例：2024-12-17 18:24:47 |

#### 请求参数示

```
{

    "skus": [

        "WEA02-A075-30-BK1", 

        "XIB04-A051-7-BU1", 

        "HO-A05-0861-01"

    ]

}
```

#### 请求方式&地址

```
[post] /openapi/sku\_excel\_package/query
```

#### 返回参数

| 参数名称        | 参数类型 | 是否 回传 | 参数描述             | 示例值                                             |
|-----------------|----------|-----------|----------------------|----------------------------------------------------|
| code            | string   | Y         | 错误码               | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg             | string   | Y         | 错误描述             | 执行成功                                           |
| data            | array    | Y         | 业务数据             |                                                    |
| -\| sku         | string   | Y         | 商品sku              |                                                    |
| -\| urls        | array    | Y         | 资源列表（所有版本） |                                                    |
| -\| urls_v1     | array    | Y         | 资源列表（版本1）    |                                                    |
| -\| urls_v2     | array    | Y         | 资源列表（版本2）    |                                                    |
| -\| latest_time | string   | Y         | 最新更新时间         |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": [

        {

            "sku": "HO-A05-0861-01",

            "urls": [

                "https://oss.westmonth.com/westmonth/SKUDatum/HO-A05-0861-01/mainpicture/HO-A05-0861-01-11945248.xlsx"

            ],

            "urls\_v1": [],

            "urls\_v2": [

                "https://oss.westmonth.com/westmonth/SKUDatum/HO-A05-0861-01/mainpicture/HO-A05-0861-01-11945248.xlsx"

            ],

            "latest\_time": "2025-03-20 10:30:27"

        },

        {

            "sku": "WEA02-A075-30-BK1",

            "urls": [

                "https://oss.westmonth.com/westmonth/SKUDatum/WEA02-A075-30-BK1/mainpicture/WEA02-A075-30-BK1-1731555032.xlsx"

            ],

            "urls\_v1": [

                "https://oss.westmonth.com/westmonth/SKUDatum/WEA02-A075-30-BK1/mainpicture/WEA02-A075-30-BK1-1731555032.xlsx"

            ],

            "urls\_v2": [],

            "latest\_time": "2024-12-09 18:35:26"

        },

        {

            "sku": "XIB04-A051-7-BU1",

            "urls": [

                "https://oss.westmonth.com/westmonth/SKUDatum/XIB04-A051-7-BU1/mainpicture/XIB04-A051-7-BU1-1.xlsx"

            ],

            "urls\_v1": [

                "https://oss.westmonth.com/westmonth/SKUDatum/XIB04-A051-7-BU1/mainpicture/XIB04-A051-7-BU1-1.xlsx"

            ],

            "urls\_v2": [],

            "latest\_time": "2024-12-09 18:35:03"

        }

    ]

}
```

### 获取实拍图附一体化标签

#### 请求参数

| 参数名称   | 参数类型 | 是否必填 | 参数描述                              |
|------------|----------|----------|---------------------------------------|
| skus       | array    | Y        | 商品sku列表，不超过200个              |
| start_time | string   | N        | 更新开始时间，例：2024-12-17 18:24:47 |
| end_time   | string   | N        | 更新结束时间，例：2024-12-17 18:24:47 |

#### 请求参数示

```
{

    "skus": [

        "EA032211301-30ml"

    ]

}
```

#### 请求方式&地址

```
[post] /openapi/sku\_realpic\_tag/query
```

#### 返回参数

| 参数名称        | 参数类型 | 是否 回传 | 参数描述             | 示例值                                             |
|-----------------|----------|-----------|----------------------|----------------------------------------------------|
| code            | string   | Y         | 错误码               | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg             | string   | Y         | 错误描述             | 执行成功                                           |
| data            | array    | Y         | 业务数据             |                                                    |
| -\| sku         | string   | Y         | 商品sku              |                                                    |
| -\| urls        | array    | Y         | 资源列表（所有版本） |                                                    |
| -\| urls_v1     | array    | Y         | 资源列表（版本1）    |                                                    |
| -\| urls_v2     | array    | Y         | 资源列表（版本2）    |                                                    |
| -\| latest_time | string   | Y         | 最新更新时间         |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": [

        {

            "sku": "EA032211301-30ml",

            "urls": [

                "https://oss.westmonth.com/westmonth/SKUDatum/EA032211301-30ml/realpictag/EA032211301-30ml-93208673.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/EA032211301-30ml/realpictag/EA032211301-30ml-79866109.png"

            ],

            "urls\_v1": [],

            "urls\_v2": [

                "https://oss.westmonth.com/westmonth/SKUDatum/EA032211301-30ml/realpictag/EA032211301-30ml-93208673.jpg",

                "https://oss.westmonth.com/westmonth/SKUDatum/EA032211301-30ml/realpictag/EA032211301-30ml-79866109.png"

            ],

            "latest\_time": "2024-12-30 17:58:18"

        }

    ]

}
```

### 获取SKU

#### 请求参数

| 参数名称 | 参数类型 | 是否必填 | 参数描述                                                    |
|----------|----------|----------|-------------------------------------------------------------|
| cursor   | string   | N        | 用于分页查询的游标，由上一次调用返回，首次调用可不填        |
| limit    | int      | N        | 返回的最大记录数，最大值100，默认值50，超过最大值时取最大值 |

#### 请求参数示例

```
{

    "cursor": "eyJpdiI6ImRCaTFRb3dQa3Y1Y1g0SU1DWWd1NEE9PSIsInZhbHVlIjoiaytmeHVIQ0xWSCthTUE4OVZIeTZUQT09IiwibWFjIjoiMTQ2MGRhNWQ3Y2U2Yjg4NTlmYThkNmNiYzU2ZWVjYzg2NmI5ZTFmYzcwZjc4Y2M5OWI3Yzg0Mzg1NWJjNzY4YyIsInRhZyI6IiJ9",

    "limit": 100

}
```

#### 请求方式&地址

```
[post] /openapi/product\_skus/query
```

#### 返回参数

| 参数名称        | 参数类型 | 是否 回传 | 参数描述                                                                       | 示例值                                             |
|-----------------|----------|-----------|--------------------------------------------------------------------------------|----------------------------------------------------|
| code            | string   | Y         | 错误码                                                                         | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg             | string   | Y         | 错误描述                                                                       | 执行成功                                           |
| data            | object   | Y         | 业务数据                                                                       |                                                    |
| -\| total       | int      | Y         | 总数                                                                           | 100                                                |
| -\| list        | array    | Y         | sku列表                                                                        | ["EEA03-A213-30-GN1"]                              |
| -\| next_cursor | string   | Y         | 分页游标，再下次请求时填写以获取之后分页的记录，如果已经没有更多的数据则返回空 | 2121212                                            |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": {

        "total": 12674,

        "next\_cursor": "eyJpdiI6IldoQWcxN05tVGNJM0x3ZW95a0t3VHc9PSIsInZhbHVlIjoiN3pMOWNIejVSSVpYSmhCYmlvOFAzZz09IiwibWFjIjoiZjczZDMzYjAzN2VmODE1Nzg3ZmZhNTY2YWNjOWRlNmUwZTQxYzNjNzEwYWRlNjYyNTAxOTg2Y2JjOWQ1YTBjMCIsInRhZyI6IiJ9",

        "list": [

            "EEA03-A213-30-GN1",

            "W标mbjHy82yx01-30ml盒装",

            "XIA03-A002-20-BK1",

            "JAA03-A042-60-VT1",

            "OUA09-A017-60-GN1",

            "EEA07-A012-60-BU1",

            "EEA07-A016-1-WH1",

            "EEA07-A013-90-BN1",

            "qjmm220yx01-绿茶",

            "qjmm220yx01-茄子"

        ]

    }

}
```

### 获取SKU信息

#### 请求参数

| 参数名称 | 参数类型 | 是否必填 | 参数描述           |
|----------|----------|----------|--------------------|
| skus     | array    | Y        | SKU数组，最多200个 |

#### 请求参数示例

```
{

	"skus": [

		"OUA03-A046-30-WH2",

		"WEA03-A049-120-PK1",

		"EEA03-A150-5-GN1",

		"bns214yx01-40ml",

		"aijy114yx01-10ml",

		"sthl002-65g"

	]

}
```

#### 请求方式&地址

```
[post] /openapi/product\_skus/listInfo
```

#### 返回参数

| 参数名称          | 参数类型 | 是否 回传 | 参数描述    | 示例值                                             |
|-------------------|----------|-----------|-------------|----------------------------------------------------|
| code              | string   | Y         | 错误码      | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg               | string   | Y         | 错误描述    | 执行成功                                           |
| data              | object   | Y         | 业务数据    |                                                    |
| -\| sku           | object   | Y         | sku作为key  |                                                    |
| -\|-\| sku        | string   | Y         | 商品SKU     |                                                    |
| -\|-\| model      | string   | Y         | 规格        |                                                    |
| -\|-\| images     | string   | Y         | 图片        |                                                    |
| -\|-\| product_id | int      | Y         | 产品id      |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": {

        "aijy114yx01-10ml": {

            "product\_id": 99,

            "sku": "aijy114yx01-10ml",

            "model": "10ml",

            "images": "https://oss.westmonth.com/westmonth/SKUDatum/aijy114yx01/mainpicture/aijy114yx01-2002.jpg"

        },

        "bns214yx01-40ml": {

            "product\_id": 96,

            "sku": "bns214yx01-40ml",

            "model": "40ml",

            "images": "https://oss.westmonth.com/westmonth/SKUDatum/old/bns214yx01-40ml/mainpicture/bns214yx01-40ml-001.jpg"

        },

        "EEA03-A150-5-GN1": {

            "product\_id": 212,

            "sku": "EEA03-A150-5-GN1",

            "model": "4.7g",

            "images": "https://oss.westmonth.com/westmonth/SKUDatum/old/EEA03-A150-5-GN1/mainpicture/EEA03-A150-5-GN1-001.jpg"

        },

        "OUA03-A046-30-WH2": {

            "product\_id": 240,

            "sku": "OUA03-A046-30-WH2",

            "model": "30ml",

            "images": "https://oss.westmonth.com/westmonth/SKUDatum/OUA03-A046-30-WH2/mainpicture/OUA03-A046-30-WH2-1728714635001.jpg"

        },

        "sthl002-65g": {

            "product\_id": 108,

            "sku": "sthl002-65g",

            "model": "50ml",

            "images": "https://oss.westmonth.com/westmonth/SKUDatum/old/sthl002-65g/mainpicture/sthl002-65g-001.jpg"

        },

        "WEA03-A049-120-PK1": {

            "product\_id": 601,

            "sku": "WEA03-A049-120-PK1",

            "model": "100ml",

            "images": "https://oss.westmonth.com/westmonth/SKUDatum/old/WEA03-A049-120-PK1/mainpicture/WEA03-A049-120-PK1-001.jpg"

        }

    }

}
```

### 获取SKU信息及价格

#### 请求参数

| 参数名称         | 参数类型 | 是否必填 | 参数描述           |
|------------------|----------|----------|--------------------|
| skus             | array    | Y        | SKU数组，最多200个 |
| country_code     | string   | Y        | 国家二字码         |
| minimum_quantity | int      | N        | 起定量，默认1      |

#### 请求参数示例

```
{

	"skus": [

		"EEA01-A011-100-GN1",

		"EEA08-A070-144-GN4"

	],

	"country\_code": "US",

	"minimum\_quantity": 1

}
```

#### 请求方式&地址

```
[post] /openapi/product\_skus/listSkuPrice
```

#### 返回参数

| 参数名称               | 参数类型 | 是否 回传 | 参数描述                       | 示例值                                             |
|------------------------|----------|-----------|--------------------------------|----------------------------------------------------|
| code                   | string   | Y         | 错误码                         | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                    | string   | Y         | 错误描述                       | 执行成功                                           |
| data                   | object   | Y         | 业务数据                       |                                                    |
| -\| id                 | int      | N         | ID                             |                                                    |
| -\| product_id         | int      | N         | 产品ID                         |                                                    |
| -\| variants           | array    | N         | SKU规格                        |                                                    |
| -\| position           | int      | N         | 排序                           |                                                    |
| -\| images             | string   | N         | 图片                           |                                                    |
| -\| model              | string   | N         | 模型                           |                                                    |
| -\| sku                | string   | N         | SKU                            |                                                    |
| -\| delivery_region_id | int      | N         | 发货区域ID                     |                                                    |
| -\| weight             | string   | N         | 重量                           |                                                    |
| -\| weight_class       | string   | N         | 重量单位                       |                                                    |
| -\| combine            | int      | N         | 是否组合装：0否，1是           |                                                    |
| -\| onsale_at          | string   | N         | 上架时间，来自聚水潭的创建时间 |                                                    |
| -\| created_at         | string   | N         |                                |                                                    |
| -\| updated_at         | string   | N         |                                |                                                    |
| -\| price              | string   | N         | 价格                           |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": [

        {

            "id": 6656,

            "product\_id": 2277,

            "variants": [],

            "position": 0,

            "images": "https://oss.com/westmonth/SKUDatum/old/EEA08-A070-144-GN4/mainpicture/EEA08-A070-144-GN4-001.jpg",

            "model": "144pieces",

            "sku": "EEA08-A070-144-GN4",

            "delivery\_region\_id": 3,

            "weight": 8,

            "weight\_class": "g",

            "combine": 0,

            "onsale\_at": "2024-01-06 11:54:57",

            "created\_at": "2024-01-11 14:08:00",

            "updated\_at": "2024-08-23 14:23:44",

            "price": "28.89"

        },

        {

            "id": 6400,

            "product\_id": 2036,

            "variants": [],

            "position": 0,

            "images": "https://oss.com/westmonth/SKUDatum/EEA01-A011-100-GN1/mainpicture/EEA01-A011-100-GN1-1726817435011.jpg",

            "model": "100g",

            "sku": "EEA01-A011-100-GN1",

            "delivery\_region\_id": 3,

            "weight": 126,

            "weight\_class": "g",

            "combine": 0,

            "onsale\_at": "2023-04-22 13:49:26",

            "created\_at": "2024-01-08 10:13:53",

            "updated\_at": "2024-11-15 15:05:12",

            "price": "28.89"

        }

    ]

}
```

### 商品列表

#### 请求参数

| 参数名称              | 参数类型 | 是否必填 | 参数描述                                          |
|-----------------------|----------|----------|---------------------------------------------------|
| page                  | int      | N        | 当前页码 ， 默认1                                 |
| size                  | int      | N        | 每页记录数，默认20，最大50                        |
| delivery_region_id    | int      | N        | 发货区ID                                          |
| delivery_region_ids   | string   | N        | 发货区IDS，多个逗号连接，例：1,2,3                |
| category_id           | int      | N        | 分类ID                                            |
| brand_id              | string   | N        | 品牌ID                                            |
| locale                | string   | N        | 语言 zh_cn                                        |
| platform_id           | string   | N        | 平台ID                                            |
| newtime               | int      | N        | 上新时间(整数，例如5天前，传5)                    |
| newtime_start_time    | string   | N        | 上新开始时间，明文日期，例：2024-01-20 14:59:29   |
| newtime_end_time      | string   | N        | 上新结束时间，明文日期，例：2024-01-20 14:59:29   |
| low_price             | string   | N        | 价格范围起始                                      |
| high_price            | string   | N        | 价格范围结束                                      |
| keyword               | string   | N        | 关键字查询                                        |
| sort_field            | int      | N        | 排序字段 : 1 价格，2销量，3创建时间,4库存 默认是1 |
| sort_mode             | int      | N        | 排序方式 1升序 2降序 默认是1                      |
| certificate_assort_id | int      | N        | 证书分类ID（通过“获取所有证书”接口获取）          |
| onlyShow              | int      | N        | 仅看有货（1：是 ，0：否）                         |

#### 请求参数示例

```
/openapi/product/list?locale=zh\_cn&delivery\_region\_id=&brand\_id=&category\_id=100226&platform\_id=&newtime=&page=1&sort\_field=3&sort\_mode=2&low\_price=&high\_price=&keyword=&certificate\_assort\_id=
```

#### 请求方式&地址

```
[get] /openapi/product/list
```

#### 返回参数

| 参数名称                       | 参数类型 | 是否 回传 | 参数描述             | 示例值                                             |
|--------------------------------|----------|-----------|----------------------|----------------------------------------------------|
| code                           | string   | Y         | 错误码               | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                            | string   | Y         | 错误描述             | 执行成功                                           |
| data                           | object   | Y         | 业务数据             |                                                    |
| -\| total                      | int      | Y         | 总数                 | 100                                                |
| -\| page                       | int      | Y         | 页码                 | 1                                                  |
| -\| list                       | array    | Y         | 商品列表             |                                                    |
| -\|-\| product_id              | string   | Y         | 商品id               |                                                    |
| -\|-\| product_sku_id          | int      | Y         | SKU ID               |                                                    |
| -\|-\| product_price           | string   | Y         | 商品价格             |                                                    |
| -\|-\| product_sku             | string   | Y         | 商品sku              |                                                    |
| -\|-\| product_name            | string   | Y         | 商品名称             |                                                    |
| -\|-\| delivery_region_id      | int      | Y         | 发货区域id           |                                                    |
| -\|-\| product_image           | string   | Y         | 商品图片             |                                                    |
| -\|-\| product_sales           | int      | Y         | 商品销量             |                                                    |
| -\|-\| product_addtime         | string   | Y         | 商品上架时间         |                                                    |
| -\|-\| qty                     | int      | Y         | 库存                 |                                                    |
| -\|-\| image                   | string   | Y         | 图片                 |                                                    |
| -\|-\| stock_status            | string   | Y         | 库存状态：有货，缺货 |                                                    |
| -\|-\| certificate_assort_id   | int      | Y         | 证书分类ID           |                                                    |
| -\|-\| delivery_regions        | array    | Y         | 发货区域列表         |                                                    |
| -\|-\|-\| delivery_region_id   | int      | Y         | 发货区域id           |                                                    |
| -\|-\|-\| delivery_region_name | string   | Y         | 发货区域名称         |                                                    |
| -\|-\|-\| product_price        | string   | Y         | 商品价格             |                                                    |
| -\|-\|-\| qty                  | int      | Y         | 最大库存             |                                                    |
| -\|-\| product_categories      | array    | Y         | 产品分类数组         |                                                    |
| -\|-\|-\| category_id          | int      | Y         | 产品分类ID           |                                                    |
| -\|-\|-\| category_name        | string   | Y         | 产品分类名称         |                                                    |
| -\|-\|-\| parent_id            | int      | Y         | 产品分类上级ID       |                                                    |
| -\|-\|-\| level                | int      | Y         | 层级                 |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": {

        "total": 17969,

        "page": 1,

        "list": [

            {

                "product\_sku\_id": 7288,

                "product\_id": 2851,

                "product\_sku": "test\_sku\_westmonth\_01",

                "delivery\_region\_id": 5,

                "product\_sku\_is\_default": 1,

                "seckill\_product\_price": null,

                "customer\_wishlist\_id": null,

                "product\_name": "测试及运费补拍",

                "month\_sales": "0",

                "product\_price": "￥0.01",

                "qty": 999999,

                "product\_addtime": "2024-01-20 14:59:29",

                "is\_wish": 0,

                "is\_new": 0,

                "is\_seckill": 0,

                "guide\_price": "￥0.02",

                "is\_hot\_sale": 0,

                "month\_sale": 100,

                "product\_sales": 100,

                "product\_certificate": [],

                "delivery\_regions": {

                    "2": [

                        {

                            "delivery\_region\_id": 2,

                            "delivery\_region\_name": "泰国",

                            "product\_price": "￥2.51",

                            "qty": 0

                        }

                    ],

                    "4": [

                        {

                            "delivery\_region\_id": 4,

                            "delivery\_region\_name": "马来西亚",

                            "product\_price": "￥2.51",

                            "qty": 0

                        }

                    ],

                    "5": [

                        {

                            "delivery\_region\_id": 5,

                            "delivery\_region\_name": "中国",

                            "product\_price": "￥0.01",

                            "qty": 999999

                        }

                    ],

                    "6": [

                        {

                            "delivery\_region\_id": 6,

                            "delivery\_region\_name": "菲律宾",

                            "product\_price": "￥2.51",

                            "qty": 0

                        }

                    ]

                },

                "product\_categories": [

                    {

                        "category\_id": 100121,

                        "category\_name": "美容护理",

                        "parent\_id": 0,

                        "level": 1

                    },

                    {

                        "category\_id": 100225,

                        "category\_name": "面部美容护理",

                        "parent\_id": 100121,

                        "level": 2

                    },

                    {

                        "category\_id": 100227,

                        "category\_name": "洗面乳",

                        "parent\_id": 100225,

                        "level": 3

                    },

                    {

                        "category\_id": 100228,

                        "category\_name": "洗脸香皂",

                        "parent\_id": 100225,

                        "level": 3

                    },

                    {

                        "category\_id": 100229,

                        "category\_name": "爽肤水，化妆水",

                        "parent\_id": 100225,

                        "level": 3

                    },

                    {

                        "category\_id": 100230,

                        "category\_name": "面部精华液",

                        "parent\_id": 100225,

                        "level": 3

                    },

                    {

                        "category\_id": 100231,

                        "category\_name": "保湿乳液，乳霜与喷雾",

                        "parent\_id": 100225,

                        "level": 3

                    },

                    {

                        "category\_id": 100232,

                        "category\_name": "面霜",

                        "parent\_id": 100225,

                        "level": 3

                    },

                    {

                        "category\_id": 100233,

                        "category\_name": "眼部护理",

                        "parent\_id": 100225,

                        "level": 3

                    },

                    {

                        "category\_id": 100234,

                        "category\_name": "鼻部护理",

                        "parent\_id": 100225,

                        "level": 3

                    },

                    {

                        "category\_id": 100235,

                        "category\_name": "唇部护理",

                        "parent\_id": 100225,

                        "level": 3

                    },

                    {

                        "category\_id": 100236,

                        "category\_name": "面膜",

                        "parent\_id": 100225,

                        "level": 3

                    },

                    {

                        "category\_id": 100237,

                        "category\_name": "面部护理套装",

                        "parent\_id": 100225,

                        "level": 3

                    },

                    {

                        "category\_id": 100238,

                        "category\_name": "脸部防晒霜与晒后修复",

                        "parent\_id": 100225,

                        "level": 3

                    },

                    {

                        "category\_id": 100239,

                        "category\_name": "面部磨砂膏与去角质",

                        "parent\_id": 100225,

                        "level": 3

                    },

                    {

                        "category\_id": 100226,

                        "category\_name": "美体护理",

                        "parent\_id": 100121,

                        "level": 2

                    },

                    {

                        "category\_id": 100241,

                        "category\_name": "防晒霜",

                        "parent\_id": 100226,

                        "level": 3

                    },

                    {

                        "category\_id": 100242,

                        "category\_name": "美黑产品",

                        "parent\_id": 100226,

                        "level": 3

                    },

                    {

                        "category\_id": 100243,

                        "category\_name": "护手足霜，乳液与磨砂膏",

                        "parent\_id": 100226,

                        "level": 3

                    },

                    {

                        "category\_id": 100244,

                        "category\_name": "手膜&足膜",

                        "parent\_id": 100226,

                        "level": 3

                    },

                    {

                        "category\_id": 100245,

                        "category\_name": "指甲护理",

                        "parent\_id": 100226,

                        "level": 3

                    },

                    {

                        "category\_id": 100246,

                        "category\_name": "身体洗护",

                        "parent\_id": 100226,

                        "level": 3

                    },

                    {

                        "category\_id": 100247,

                        "category\_name": "身体磨砂",

                        "parent\_id": 100226,

                        "level": 3

                    },

                    {

                        "category\_id": 100248,

                        "category\_name": "身体精华",

                        "parent\_id": 100226,

                        "level": 3

                    },

                    {

                        "category\_id": 100249,

                        "category\_name": "身体乳",

                        "parent\_id": 100226,

                        "level": 3

                    },

                    {

                        "category\_id": 100250,

                        "category\_name": "丰胸膏/贴",

                        "parent\_id": 100226,

                        "level": 3

                    },

                    {

                        "category\_id": 100251,

                        "category\_name": "提臀膏/贴",

                        "parent\_id": 100226,

                        "level": 3

                    },

                    {

                        "category\_id": 100252,

                        "category\_name": "瘦身纤体护理",

                        "parent\_id": 100226,

                        "level": 3

                    },

                    {

                        "category\_id": 100253,

                        "category\_name": "腋下护理",

                        "parent\_id": 100226,

                        "level": 3

                    },

                    {

                        "category\_id": 100254,

                        "category\_name": "私处护理",

                        "parent\_id": 100226,

                        "level": 3

                    },

                    {

                        "category\_id": 100255,

                        "category\_name": "脱毛护理",

                        "parent\_id": 100226,

                        "level": 3

                    },

                    {

                        "category\_id": 100344,

                        "category\_name": "颈部护理",

                        "parent\_id": 100226,

                        "level": 3

                    }

                ],

                "stock\_status": "有货",

                "product\_image": "https://oss.westmonth.com/westmonth/SKUDatum/old/test\_sku\_westmonth\_01/mainpicture/test\_sku\_westmonth\_01-001.jpg?x-oss-process=image/resize,m\_lfit,w\_500",

                "image": ""

            },

            {

                "product\_sku\_id": 21132,

                "product\_id": 15499,

                "product\_sku": "khd93yx01-裸色",

                "delivery\_region\_id": 5,

                "product\_sku\_is\_default": 1,

                "seckill\_product\_price": null,

                "customer\_wishlist\_id": null,

                "product\_name": "EELHOE 棉签唇釉 便携式美妆创意不掉色口红染唇液",

                "month\_sales": null,

                "product\_price": "￥0.30",

                "qty": 999999,

                "product\_addtime": "2022-11-23 10:26:32",

                "is\_wish": 0,

                "is\_new": 0,

                "is\_seckill": 0,

                "guide\_price": "￥0.60",

                "is\_hot\_sale": 0,

                "month\_sale": 100,

                "product\_sales": 100,

                "product\_certificate": [],

                "delivery\_regions": {

                    "5": [

                        {

                            "delivery\_region\_id": 5,

                            "delivery\_region\_name": "中国",

                            "product\_price": "￥0.30",

                            "qty": 999999

                        }

                    ]

                },

                "product\_categories": [

                    {

                        "category\_id": 100123,

                        "category\_name": "美妆香水",

                        "parent\_id": 0,

                        "level": 1

                    },

                    {

                        "category\_id": 100204,

                        "category\_name": "口红与唇彩",

                        "parent\_id": 100123,

                        "level": 2

                    }

                ],

                "stock\_status": "有货",

                "product\_image": "https://oss.westmonth.com/westmonth/SKUDatum/jstProducts/khd93yx01-裸色/mainpicture/khd93yx01-裸色-1.jpg?x-oss-process=image/resize,m\_lfit,w\_500",

                "image": ""

            },

            {

                "product\_sku\_id": 20696,

                "product\_id": 15063,

                "product\_sku": "hsyx-黄色圆形海绵",

                "delivery\_region\_id": 5,

                "product\_sku\_is\_default": 1,

                "seckill\_product\_price": null,

                "customer\_wishlist\_id": null,

                "product\_name": "Jue-Fish 家用清洁吸水擦拭海绵 不沾灰皮革金属玻璃擦拭毛巾吸水无痕收水巾",

                "month\_sales": "0",

                "product\_price": "￥0.50",

                "qty": 999999,

                "product\_addtime": "2022-11-16 18:30:24",

                "is\_wish": 0,

                "is\_new": 0,

                "is\_seckill": 0,

                "guide\_price": "￥1.00",

                "is\_hot\_sale": 0,

                "month\_sale": 100,

                "product\_sales": 100,

                "product\_certificate": [],

                "delivery\_regions": {

                    "5": [

                        {

                            "delivery\_region\_id": 5,

                            "delivery\_region\_name": "中国",

                            "product\_price": "￥0.50",

                            "qty": 999999

                        }

                    ]

                },

                "product\_categories": [

                    {

                        "category\_id": 100130,

                        "category\_name": "家居用品",

                        "parent\_id": 0,

                        "level": 1

                    },

                    {

                        "category\_id": 100321,

                        "category\_name": "家居清洁",

                        "parent\_id": 100130,

                        "level": 2

                    },

                    {

                        "category\_id": 100326,

                        "category\_name": "清洁工具",

                        "parent\_id": 100321,

                        "level": 3

                    }

                ],

                "stock\_status": "有货",

                "product\_image": "https://oss.westmonth.com/westmonth/SKUDatum/jstProducts/hsyx-黄色圆形海绵/mainpicture/hsyx-黄色圆形海绵-1.jpg?x-oss-process=image/resize,m\_lfit,w\_500",

                "image": ""

            },

            {

                "product\_sku\_id": 20707,

                "product\_id": 15074,

                "product\_sku": "JHC01-A018-25-BU1",

                "delivery\_region\_id": 5,

                "product\_sku\_is\_default": 1,

                "seckill\_product\_price": null,

                "customer\_wishlist\_id": null,

                "product\_name": "Jakehoe 厨房清洁去污粉",

                "month\_sales": "0",

                "product\_price": "￥0.50",

                "qty": 999999,

                "product\_addtime": "2024-04-09 11:55:20",

                "is\_wish": 0,

                "is\_new": 0,

                "is\_seckill": 0,

                "guide\_price": "￥1.00",

                "is\_hot\_sale": 0,

                "month\_sale": 100,

                "product\_sales": 100,

                "product\_certificate": [

                    {

                        "abbreviation": "MSDS(NEW)",

                        "color": "#BC9D20",

                        "id": 9

                    }

                ],

                "delivery\_regions": {

                    "5": [

                        {

                            "delivery\_region\_id": 5,

                            "delivery\_region\_name": "中国",

                            "product\_price": "￥0.50",

                            "qty": 999999

                        }

                    ]

                },

                "product\_categories": [

                    {

                        "category\_id": 100130,

                        "category\_name": "家居用品",

                        "parent\_id": 0,

                        "level": 1

                    },

                    {

                        "category\_id": 100321,

                        "category\_name": "家居清洁",

                        "parent\_id": 100130,

                        "level": 2

                    },

                    {

                        "category\_id": 100323,

                        "category\_name": "厨房清洁",

                        "parent\_id": 100321,

                        "level": 3

                    }

                ],

                "stock\_status": "有货",

                "product\_image": "https://resource.westmonth.com/6647647d2e785f/2f4a27964e8843dd809ec352a3be0c6b\_b43b0f0c-2254-49d0-b68b-7619351758cb.jpg?x-oss-process=image/resize,m\_lfit,w\_500",

                "image": ""

            },

            {

                "product\_sku\_id": 6589,

                "product\_id": 2216,

                "product\_sku": "S标zt223yx01-2pcs袋装",

                "delivery\_region\_id": 5,

                "product\_sku\_is\_default": 1,

                "seckill\_product\_price": null,

                "customer\_wishlist\_id": null,

                "product\_name": "South Moon 天然草本足贴足部护理去湿去寒放松身心帮助睡眠足贴",

                "month\_sales": "200",

                "product\_price": "￥0.60",

                "qty": 999999,

                "product\_addtime": "2022-02-23 18:56:50",

                "is\_wish": 0,

                "is\_new": 0,

                "is\_seckill": 0,

                "guide\_price": "￥1.20",

                "is\_hot\_sale": 0,

                "month\_sale": 300,

                "product\_sales": 300,

                "product\_certificate": [

                    {

                        "abbreviation": "CPNP",

                        "color": "#2D3887",

                        "id": 4

                    },

                    {

                        "abbreviation": "MFDS",

                        "color": "#0B4C83",

                        "id": 2

                    },

                    {

                        "abbreviation": "MSDS(NEW)",

                        "color": "#BC9D20",

                        "id": 9

                    },

                    {

                        "abbreviation": "SCPN",

                        "color": "#6D96FC",

                        "id": 3

                    },

                    {

                        "abbreviation": "CN",

                        "color": "#47872D",

                        "id": 5

                    }

                ],

                "delivery\_regions": {

                    "1": [

                        {

                            "delivery\_region\_id": 1,

                            "delivery\_region\_name": "印尼",

                            "product\_price": "￥3.10",

                            "qty": "63"

                        }

                    ],

                    "4": [

                        {

                            "delivery\_region\_id": 4,

                            "delivery\_region\_name": "马来西亚",

                            "product\_price": "￥3.10",

                            "qty": "441"

                        }

                    ],

                    "5": [

                        {

                            "delivery\_region\_id": 5,

                            "delivery\_region\_name": "中国",

                            "product\_price": "￥0.60",

                            "qty": 999999

                        }

                    ]

                },

                "product\_categories": [

                    {

                        "category\_id": 100126,

                        "category\_name": "健康护理",

                        "parent\_id": 0,

                        "level": 1

                    },

                    {

                        "category\_id": 100173,

                        "category\_name": "足部护理",

                        "parent\_id": 100126,

                        "level": 2

                    },

                    {

                        "category\_id": 100281,

                        "category\_name": "足贴",

                        "parent\_id": 100173,

                        "level": 3

                    }

                ],

                "stock\_status": "有货",

                "product\_image": "https://oss.westmonth.com/westmonth/SKUDatum/old/S标zt223yx01-2pcs袋装/mainpicture/S标zt223yx01-2pcs袋装-001.jpg?x-oss-process=image/resize,m\_lfit,w\_500",

                "image": ""

            },

            {

                "product\_sku\_id": 9882,

                "product\_id": 5236,

                "product\_sku": "JHC01-A017-1-BU5",

                "delivery\_region\_id": 5,

                "product\_sku\_is\_default": 1,

                "seckill\_product\_price": null,

                "customer\_wishlist\_id": null,

                "product\_name": "Jakehoe 咖啡机清洁片 咖啡机冲泡器保养滤网清洗专用去垢清洁片",

                "month\_sales": null,

                "product\_price": "￥0.60",

                "qty": 999999,

                "product\_addtime": "2024-04-13 15:55:16",

                "is\_wish": 0,

                "is\_new": 0,

                "is\_seckill": 0,

                "guide\_price": "￥1.20",

                "is\_hot\_sale": 0,

                "month\_sale": 100,

                "product\_sales": 100,

                "product\_certificate": [

                    {

                        "abbreviation": "MSDS(CHEMICAL)",

                        "color": "#E1BB25",

                        "id": 8

                    }

                ],

                "delivery\_regions": {

                    "5": [

                        {

                            "delivery\_region\_id": 5,

                            "delivery\_region\_name": "中国",

                            "product\_price": "￥0.60",

                            "qty": 999999

                        }

                    ]

                },

                "product\_categories": [

                    {

                        "category\_id": 100130,

                        "category\_name": "家居用品",

                        "parent\_id": 0,

                        "level": 1

                    },

                    {

                        "category\_id": 100321,

                        "category\_name": "家居清洁",

                        "parent\_id": 100130,

                        "level": 2

                    },

                    {

                        "category\_id": 100322,

                        "category\_name": "家电清洁",

                        "parent\_id": 100321,

                        "level": 3

                    }

                ],

                "stock\_status": "有货",

                "product\_image": "https://oss.westmonth.com/westmonth/SKUDatum/old/JHC01-A017-1-BU5/mainpicture/JHC01-A017-1-BU5-001.jpg?x-oss-process=image/resize,m\_lfit,w\_500",

                "image": ""

            },

            {

                "product\_sku\_id": 9883,

                "product\_id": 5237,

                "product\_sku": "JHC01-A017-1-BU4",

                "delivery\_region\_id": 5,

                "product\_sku\_is\_default": 1,

                "seckill\_product\_price": null,

                "customer\_wishlist\_id": null,

                "product\_name": "Jakehoe 小白鞋清洗片 便携式独立包装小白鞋清洁去污护鞋清洗片",

                "month\_sales": null,

                "product\_price": "￥0.60",

                "qty": 999999,

                "product\_addtime": "2024-04-13 15:52:06",

                "is\_wish": 0,

                "is\_new": 0,

                "is\_seckill": 0,

                "guide\_price": "￥1.20",

                "is\_hot\_sale": 0,

                "month\_sale": 100,

                "product\_sales": 100,

                "product\_certificate": [

                    {

                        "abbreviation": "MSDS(CHEMICAL)",

                        "color": "#E1BB25",

                        "id": 8

                    }

                ],

                "delivery\_regions": {

                    "5": [

                        {

                            "delivery\_region\_id": 5,

                            "delivery\_region\_name": "中国",

                            "product\_price": "￥0.60",

                            "qty": 999999

                        }

                    ]

                },

                "product\_categories": [

                    {

                        "category\_id": 100130,

                        "category\_name": "家居用品",

                        "parent\_id": 0,

                        "level": 1

                    },

                    {

                        "category\_id": 100321,

                        "category\_name": "家居清洁",

                        "parent\_id": 100130,

                        "level": 2

                    },

                    {

                        "category\_id": 100327,

                        "category\_name": "服饰清洁",

                        "parent\_id": 100321,

                        "level": 3

                    }

                ],

                "stock\_status": "有货",

                "product\_image": "https://oss.westmonth.com/westmonth/SKUDatum/old/JHC01-A017-1-BU4/mainpicture/JHC01-A017-1-BU4-001.jpg?x-oss-process=image/resize,m\_lfit,w\_500",

                "image": ""

            },

            {

                "product\_sku\_id": 10090,

                "product\_id": 5429,

                "product\_sku": "JHC01-A017-1-MX4",

                "delivery\_region\_id": 5,

                "product\_sku\_is\_default": 1,

                "seckill\_product\_price": null,

                "customer\_wishlist\_id": null,

                "product\_name": "Jakehoe 衣服去污泡腾片 深度清洁白色衣物顽固污渍油渍持久留香",

                "month\_sales": null,

                "product\_price": "￥0.60",

                "qty": 999999,

                "product\_addtime": "2024-04-18 14:06:39",

                "is\_wish": 0,

                "is\_new": 0,

                "is\_seckill": 0,

                "guide\_price": "￥1.20",

                "is\_hot\_sale": 0,

                "month\_sale": 100,

                "product\_sales": 100,

                "product\_certificate": [

                    {

                        "abbreviation": "MSDS(CHEMICAL)",

                        "color": "#E1BB25",

                        "id": 8

                    }

                ],

                "delivery\_regions": {

                    "5": [

                        {

                            "delivery\_region\_id": 5,

                            "delivery\_region\_name": "中国",

                            "product\_price": "￥0.60",

                            "qty": 999999

                        }

                    ]

                },

                "product\_categories": [

                    {

                        "category\_id": 100130,

                        "category\_name": "家居用品",

                        "parent\_id": 0,

                        "level": 1

                    },

                    {

                        "category\_id": 100321,

                        "category\_name": "家居清洁",

                        "parent\_id": 100130,

                        "level": 2

                    },

                    {

                        "category\_id": 100327,

                        "category\_name": "服饰清洁",

                        "parent\_id": 100321,

                        "level": 3

                    }

                ],

                "stock\_status": "有货",

                "product\_image": "https://oss.westmonth.com/westmonth/SKUDatum/old/JHC01-A017-1-MX4/mainpicture/JHC01-A017-1-MX4-001.jpg?x-oss-process=image/resize,m\_lfit,w\_500",

                "image": ""

            },

            {

                "product\_sku\_id": 10844,

                "product\_id": 6098,

                "product\_sku": "JHC03-A007-1-BU2",

                "delivery\_region\_id": 5,

                "product\_sku\_is\_default": 1,

                "seckill\_product\_price": null,

                "customer\_wishlist\_id": null,

                "product\_name": "Jakehoe 马桶清洁片 家用卫生间马桶去异味清洁污垢尿渍清洁片",

                "month\_sales": null,

                "product\_price": "￥0.60",

                "qty": 999999,

                "product\_addtime": "2024-05-05 16:48:48",

                "is\_wish": 0,

                "is\_new": 0,

                "is\_seckill": 0,

                "guide\_price": "￥1.20",

                "is\_hot\_sale": 0,

                "month\_sale": 100,

                "product\_sales": 100,

                "product\_certificate": [

                    {

                        "abbreviation": "MSDS(CHEMICAL)",

                        "color": "#E1BB25",

                        "id": 8

                    }

                ],

                "delivery\_regions": {

                    "5": [

                        {

                            "delivery\_region\_id": 5,

                            "delivery\_region\_name": "中国",

                            "product\_price": "￥0.60",

                            "qty": 999999

                        }

                    ]

                },

                "product\_categories": [

                    {

                        "category\_id": 100130,

                        "category\_name": "家居用品",

                        "parent\_id": 0,

                        "level": 1

                    },

                    {

                        "category\_id": 100321,

                        "category\_name": "家居清洁",

                        "parent\_id": 100130,

                        "level": 2

                    },

                    {

                        "category\_id": 100325,

                        "category\_name": "浴室清洁",

                        "parent\_id": 100321,

                        "level": 3

                    }

                ],

                "stock\_status": "有货",

                "product\_image": "https://oss.westmonth.com/westmonth/SKUDatum/old/JHC03-A007-1-BU2/mainpicture/JHC03-A007-1-BU2-001.jpg?x-oss-process=image/resize,m\_lfit,w\_500",

                "image": ""

            },

            {

                "product\_sku\_id": 10856,

                "product\_id": 6110,

                "product\_sku": "JHC03-A007-1-BU4",

                "delivery\_region\_id": 5,

                "product\_sku\_is\_default": 1,

                "seckill\_product\_price": null,

                "customer\_wishlist\_id": null,

                "product\_name": "Jakehoe 洗碗机清洁泡腾片 厨具餐具专用洗涤去油污去异味除垢片",

                "month\_sales": "0",

                "product\_price": "￥0.60",

                "qty": 999999,

                "product\_addtime": "2024-05-07 18:03:58",

                "is\_wish": 0,

                "is\_new": 0,

                "is\_seckill": 0,

                "guide\_price": "￥1.20",

                "is\_hot\_sale": 0,

                "month\_sale": 100,

                "product\_sales": 100,

                "product\_certificate": [

                    {

                        "abbreviation": "MSDS(CHEMICAL)",

                        "color": "#E1BB25",

                        "id": 8

                    }

                ],

                "delivery\_regions": {

                    "5": [

                        {

                            "delivery\_region\_id": 5,

                            "delivery\_region\_name": "中国",

                            "product\_price": "￥0.60",

                            "qty": 999999

                        }

                    ]

                },

                "product\_categories": [

                    {

                        "category\_id": 100130,

                        "category\_name": "家居用品",

                        "parent\_id": 0,

                        "level": 1

                    },

                    {

                        "category\_id": 100321,

                        "category\_name": "家居清洁",

                        "parent\_id": 100130,

                        "level": 2

                    },

                    {

                        "category\_id": 100323,

                        "category\_name": "厨房清洁",

                        "parent\_id": 100321,

                        "level": 3

                    }

                ],

                "stock\_status": "有货",

                "product\_image": "https://oss.westmonth.com/westmonth/SKUDatum/old/JHC03-A007-1-BU4/mainpicture/JHC03-A007-1-BU4-001.jpg?x-oss-process=image/resize,m\_lfit,w\_500",

                "image": ""

            },

            {

                "product\_sku\_id": 11080,

                "product\_id": 6327,

                "product\_sku": "JHC03-A007-1-BU3",

                "delivery\_region\_id": 5,

                "product\_sku\_is\_default": 1,

                "seckill\_product\_price": null,

                "customer\_wishlist\_id": null,

                "product\_name": "Jakehoe 厨房清洁泡腾片系列 厨房油污清洁灶台油烟机多用途清洗片",

                "month\_sales": null,

                "product\_price": "￥0.60",

                "qty": 999999,

                "product\_addtime": "2024-05-07 10:45:25",

                "is\_wish": 0,

                "is\_new": 0,

                "is\_seckill": 0,

                "guide\_price": "￥1.20",

                "is\_hot\_sale": 0,

                "month\_sale": 100,

                "product\_sales": 100,

                "product\_certificate": [

                    {

                        "abbreviation": "MSDS(CHEMICAL)",

                        "color": "#E1BB25",

                        "id": 8

                    }

                ],

                "delivery\_regions": {

                    "5": [

                        {

                            "delivery\_region\_id": 5,

                            "delivery\_region\_name": "中国",

                            "product\_price": "￥0.60",

                            "qty": 999999

                        }

                    ]

                },

                "product\_categories": [

                    {

                        "category\_id": 100130,

                        "category\_name": "家居用品",

                        "parent\_id": 0,

                        "level": 1

                    },

                    {

                        "category\_id": 100321,

                        "category\_name": "家居清洁",

                        "parent\_id": 100130,

                        "level": 2

                    },

                    {

                        "category\_id": 100323,

                        "category\_name": "厨房清洁",

                        "parent\_id": 100321,

                        "level": 3

                    }

                ],

                "stock\_status": "有货",

                "product\_image": "https://oss.westmonth.com/westmonth/SKUDatum/old/JHC03-A007-1-BU3/mainpicture/JHC03-A007-1-BU3-001.jpg?x-oss-process=image/resize,m\_lfit,w\_500",

                "image": ""

            },

            {

                "product\_sku\_id": 11320,

                "product\_id": 6548,

                "product\_sku": "qwf218yx01-G款25g",

                "delivery\_region\_id": 5,

                "product\_sku\_is\_default": 1,

                "seckill\_product\_price": null,

                "customer\_wishlist\_id": null,

                "product\_name": "OEM 厨房去污粉清洁剂 厨具油污清洁污渍不锈钢除污粉",

                "month\_sales": null,

                "product\_price": "￥0.60",

                "qty": 999999,

                "product\_addtime": "2021-02-18 16:29:47",

                "is\_wish": 0,

                "is\_new": 0,

                "is\_seckill": 0,

                "guide\_price": "￥1.20",

                "is\_hot\_sale": 0,

                "month\_sale": 100,

                "product\_sales": 100,

                "product\_certificate": [

                    {

                        "abbreviation": "MSDS(CHEMICAL)",

                        "color": "#E1BB25",

                        "id": 8

                    }

                ],

                "delivery\_regions": {

                    "5": [

                        {

                            "delivery\_region\_id": 5,

                            "delivery\_region\_name": "中国",

                            "product\_price": "￥0.60",

                            "qty": 999999

                        }

                    ]

                },

                "product\_categories": [

                    {

                        "category\_id": 100130,

                        "category\_name": "家居用品",

                        "parent\_id": 0,

                        "level": 1

                    },

                    {

                        "category\_id": 100321,

                        "category\_name": "家居清洁",

                        "parent\_id": 100130,

                        "level": 2

                    },

                    {

                        "category\_id": 100323,

                        "category\_name": "厨房清洁",

                        "parent\_id": 100321,

                        "level": 3

                    }

                ],

                "stock\_status": "有货",

                "product\_image": "https://oss.westmonth.com/westmonth/SKUDatum/old/qwf218yx01-G款25g/mainpicture/qwf218yx01-G款25g-001.jpg?x-oss-process=image/resize,m\_lfit,w\_500",

                "image": ""

            },

            {

                "product\_sku\_id": 19926,

                "product\_id": 14297,

                "product\_sku": "JHC01-A017-1-MX7",

                "delivery\_region\_id": 5,

                "product\_sku\_is\_default": 1,

                "seckill\_product\_price": null,

                "customer\_wishlist\_id": null,

                "product\_name": "Jakehoe 瓷砖清洁片 家用大理石地面浴室马桶浴缸瓷砖去污增亮清洁",

                "month\_sales": null,

                "product\_price": "￥0.60",

                "qty": 999999,

                "product\_addtime": "2024-04-19 16:36:02",

                "is\_wish": 0,

                "is\_new": 0,

                "is\_seckill": 0,

                "guide\_price": "￥1.20",

                "is\_hot\_sale": 0,

                "month\_sale": 100,

                "product\_sales": 100,

                "product\_certificate": [

                    {

                        "abbreviation": "MSDS(CHEMICAL)",

                        "color": "#E1BB25",

                        "id": 8

                    }

                ],

                "delivery\_regions": {

                    "5": [

                        {

                            "delivery\_region\_id": 5,

                            "delivery\_region\_name": "中国",

                            "product\_price": "￥0.60",

                            "qty": 999999

                        }

                    ]

                },

                "product\_categories": [

                    {

                        "category\_id": 100130,

                        "category\_name": "家居用品",

                        "parent\_id": 0,

                        "level": 1

                    },

                    {

                        "category\_id": 100321,

                        "category\_name": "家居清洁",

                        "parent\_id": 100130,

                        "level": 2

                    },

                    {

                        "category\_id": 100326,

                        "category\_name": "清洁工具",

                        "parent\_id": 100321,

                        "level": 3

                    }

                ],

                "stock\_status": "有货",

                "product\_image": "https://oss.westmonth.com/westmonth/SKUDatum/JHC01-A017-1-MX7/mainpicture/JHC01-A017-1-MX7-2003.jpg?x-oss-process=image/resize,m\_lfit,w\_500",

                "image": ""

            },

            {

                "product\_sku\_id": 20452,

                "product\_id": 14819,

                "product\_sku": "JHC03-A007-1-BU1",

                "delivery\_region\_id": 5,

                "product\_sku\_is\_default": 1,

                "seckill\_product\_price": null,

                "customer\_wishlist\_id": null,

                "product\_name": "Jakehoe 洗衣机清洁片 家居滚筒洗衣槽清洁异味污垢留香净味清洁片",

                "month\_sales": "0",

                "product\_price": "￥0.60",

                "qty": 999999,

                "product\_addtime": "2024-05-05 14:33:26",

                "is\_wish": 0,

                "is\_new": 0,

                "is\_seckill": 0,

                "guide\_price": "￥1.20",

                "is\_hot\_sale": 0,

                "month\_sale": 100,

                "product\_sales": 100,

                "product\_certificate": [

                    {

                        "abbreviation": "MSDS(CHEMICAL)",

                        "color": "#E1BB25",

                        "id": 8

                    }

                ],

                "delivery\_regions": {

                    "5": [

                        {

                            "delivery\_region\_id": 5,

                            "delivery\_region\_name": "中国",

                            "product\_price": "￥0.60",

                            "qty": 999999

                        }

                    ]

                },

                "product\_categories": [

                    {

                        "category\_id": 100130,

                        "category\_name": "家居用品",

                        "parent\_id": 0,

                        "level": 1

                    },

                    {

                        "category\_id": 100321,

                        "category\_name": "家居清洁",

                        "parent\_id": 100130,

                        "level": 2

                    },

                    {

                        "category\_id": 100326,

                        "category\_name": "清洁工具",

                        "parent\_id": 100321,

                        "level": 3

                    }

                ],

                "stock\_status": "有货",

                "product\_image": "https://oss.westmonth.com/westmonth/SKUDatum/jstProducts/JHC03-A007-1-BU1/mainpicture/JHC03-A007-1-BU1-1.jpg?x-oss-process=image/resize,m\_lfit,w\_500",

                "image": ""

            },

            {

                "product\_sku\_id": 20453,

                "product\_id": 14820,

                "product\_sku": "JHC01-A017-1-MX1",

                "delivery\_region\_id": 5,

                "product\_sku\_is\_default": 1,

                "seckill\_product\_price": null,

                "customer\_wishlist\_id": null,

                "product\_name": "Jakehoe 除锈清洁泡腾片 金属工具去污增亮翻新多功能除锈清洁片",

                "month\_sales": "0",

                "product\_price": "￥0.60",

                "qty": 999999,

                "product\_addtime": "2024-04-18 11:29:12",

                "is\_wish": 0,

                "is\_new": 0,

                "is\_seckill": 0,

                "guide\_price": "￥1.20",

                "is\_hot\_sale": 0,

                "month\_sale": 100,

                "product\_sales": 100,

                "product\_certificate": [

                    {

                        "abbreviation": "MSDS(NEW)",

                        "color": "#BC9D20",

                        "id": 9

                    },

                    {

                        "abbreviation": "MSDS(CHEMICAL)",

                        "color": "#E1BB25",

                        "id": 8

                    }

                ],

                "delivery\_regions": {

                    "5": [

                        {

                            "delivery\_region\_id": 5,

                            "delivery\_region\_name": "中国",

                            "product\_price": "￥0.60",

                            "qty": 999999

                        }

                    ]

                },

                "product\_categories": [

                    {

                        "category\_id": 100130,

                        "category\_name": "家居用品",

                        "parent\_id": 0,

                        "level": 1

                    },

                    {

                        "category\_id": 100321,

                        "category\_name": "家居清洁",

                        "parent\_id": 100130,

                        "level": 2

                    },

                    {

                        "category\_id": 100325,

                        "category\_name": "浴室清洁",

                        "parent\_id": 100321,

                        "level": 3

                    }

                ],

                "stock\_status": "有货",

                "product\_image": "https://resource.westmonth.com/6647647d2e785f/631d247f218c4eb6a6b9f8f7f4598bb5\_096f513c-45ba-4028-a2b4-d917d8f3d8f7.jpg?x-oss-process=image/resize,m\_lfit,w\_500",

                "image": ""

            },

            {

                "product\_sku\_id": 5640,

                "product\_id": 1625,

                "product\_sku": "JAB01-A031-1-BU1",

                "delivery\_region\_id": 5,

                "product\_sku\_is\_default": 1,

                "seckill\_product\_price": null,

                "customer\_wishlist\_id": null,

                "product\_name": "Jaysuing 排水管堵塞清除片 疏通排水管道下水道防堵塞异味清洁片",

                "month\_sales": null,

                "product\_price": "￥0.80",

                "qty": 999999,

                "product\_addtime": "2023-10-20 14:03:16",

                "is\_wish": 0,

                "is\_new": 0,

                "is\_seckill": 0,

                "guide\_price": "￥1.60",

                "is\_hot\_sale": 0,

                "month\_sale": 100,

                "product\_sales": 100,

                "product\_certificate": [

                    {

                        "abbreviation": "MFDS",

                        "color": "#0B4C83",

                        "id": 2

                    },

                    {

                        "abbreviation": "MSDS(NEW)",

                        "color": "#BC9D20",

                        "id": 9

                    }

                ],

                "delivery\_regions": {

                    "5": [

                        {

                            "delivery\_region\_id": 5,

                            "delivery\_region\_name": "中国",

                            "product\_price": "￥0.80",

                            "qty": 999999

                        }

                    ]

                },

                "product\_categories": [

                    {

                        "category\_id": 100324,

                        "category\_name": "管道疏通",

                        "parent\_id": 100321,

                        "level": 3

                    },

                    {

                        "category\_id": 100130,

                        "category\_name": "家居用品",

                        "parent\_id": 0,

                        "level": 1

                    },

                    {

                        "category\_id": 100321,

                        "category\_name": "家居清洁",

                        "parent\_id": 100130,

                        "level": 2

                    }

                ],

                "stock\_status": "有货",

                "product\_image": "https://oss.westmonth.com/westmonth/SKUDatum/old/JAB01-A031-1-BU1/mainpicture/JAB01-A031-1-BU1-002.jpg?x-oss-process=image/resize,m\_lfit,w\_500",

                "image": ""

            },

            {

                "product\_sku\_id": 21150,

                "product\_id": 15517,

                "product\_sku": "wsjxl013-袋装5g",

                "delivery\_region\_id": 5,

                "product\_sku\_is\_default": 1,

                "seckill\_product\_price": null,

                "customer\_wishlist\_id": null,

                "product\_name": "EELHOE 补牙固体牙胶 舞会影视化妆假牙修饰临时牙胶假补牙洞牙缝补齐",

                "month\_sales": null,

                "product\_price": "￥0.80",

                "qty": 999999,

                "product\_addtime": "2020-11-09 17:25:52",

                "is\_wish": 0,

                "is\_new": 0,

                "is\_seckill": 0,

                "guide\_price": "￥1.60",

                "is\_hot\_sale": 0,

                "month\_sale": 100,

                "product\_sales": 100,

                "product\_certificate": [],

                "delivery\_regions": {

                    "5": [

                        {

                            "delivery\_region\_id": 5,

                            "delivery\_region\_name": "中国",

                            "product\_price": "￥0.80",

                            "qty": 999999

                        }

                    ]

                },

                "product\_categories": [

                    {

                        "category\_id": 100124,

                        "category\_name": "口腔护理",

                        "parent\_id": 0,

                        "level": 1

                    },

                    {

                        "category\_id": 100192,

                        "category\_name": "牙胶",

                        "parent\_id": 100124,

                        "level": 2

                    }

                ],

                "stock\_status": "有货",

                "product\_image": "https://oss.westmonth.com/westmonth/SKUDatum/jstProducts/wsjxl013-袋装5g/mainpicture/wsjxl013-袋装5g-1.jpg?x-oss-process=image/resize,m\_lfit,w\_500",

                "image": ""

            },

            {

                "product\_sku\_id": 6821,

                "product\_id": 2424,

                "product\_sku": "JAB06-A016-2-OG1",

                "delivery\_region\_id": 5,

                "product\_sku\_is\_default": 1,

                "seckill\_product\_price": null,

                "customer\_wishlist\_id": null,

                "product\_name": "Jaysuing 暖足贴 自发热小巧便携足底保暖脚垫快速升温加热暖脚宝",

                "month\_sales": "212",

                "product\_price": "￥1.00",

                "qty": 999999,

                "product\_addtime": "2024-01-09 18:06:48",

                "is\_wish": 0,

                "is\_new": 0,

                "is\_seckill": 0,

                "guide\_price": "￥2.00",

                "is\_hot\_sale": 0,

                "month\_sale": 400,

                "product\_sales": 400,

                "product\_certificate": [

                    {

                        "abbreviation": "MSDS(NEW)",

                        "color": "#BC9D20",

                        "id": 9

                    },

                    {

                        "abbreviation": "MFDS",

                        "color": "#0B4C83",

                        "id": 2

                    },

                    {

                        "abbreviation": "CPNP",

                        "color": "#2D3887",

                        "id": 4

                    },

                    {

                        "abbreviation": "CN",

                        "color": "#47872D",

                        "id": 5

                    },

                    {

                        "abbreviation": "SCPN",

                        "color": "#6D96FC",

                        "id": 3

                    },

                    {

                        "abbreviation": "FDA",

                        "color": "#004392",

                        "id": 7

                    }

                ],

                "delivery\_regions": {

                    "3": [

                        {

                            "delivery\_region\_id": 3,

                            "delivery\_region\_name": "美国",

                            "product\_price": "￥29.53",

                            "qty": "59"

                        }

                    ],

                    "4": [

                        {

                            "delivery\_region\_id": 4,

                            "delivery\_region\_name": "马来西亚",

                            "product\_price": "￥3.50",

                            "qty": "535"

                        }

                    ],

                    "5": [

                        {

                            "delivery\_region\_id": 5,

                            "delivery\_region\_name": "中国",

                            "product\_price": "￥1.00",

                            "qty": 999999

                        }

                    ]

                },

                "product\_categories": [

                    {

                        "category\_id": 100126,

                        "category\_name": "健康护理",

                        "parent\_id": 0,

                        "level": 1

                    },

                    {

                        "category\_id": 100173,

                        "category\_name": "足部护理",

                        "parent\_id": 100126,

                        "level": 2

                    },

                    {

                        "category\_id": 100281,

                        "category\_name": "足贴",

                        "parent\_id": 100173,

                        "level": 3

                    }

                ],

                "stock\_status": "有货",

                "product\_image": "https://oss.westmonth.com/westmonth/SKUDatum/old/JAB06-A016-2-OG1/mainpicture/JAB06-A016-2-OG1-001.jpg?x-oss-process=image/resize,m\_lfit,w\_500",

                "image": ""

            },

            {

                "product\_sku\_id": 6846,

                "product\_id": 2438,

                "product\_sku": "JAB01-A014-6-BU2",

                "delivery\_region\_id": 5,

                "product\_sku\_is\_default": 1,

                "seckill\_product\_price": null,

                "customer\_wishlist\_id": null,

                "product\_name": "Jaysuing 排水管堵塞清除粉 厕所厨房下水道防堵塞疏通清洁除臭粉",

                "month\_sales": null,

                "product\_price": "￥1.00",

                "qty": 999999,

                "product\_addtime": "2023-12-25 16:24:03",

                "is\_wish": 0,

                "is\_new": 0,

                "is\_seckill": 0,

                "guide\_price": "￥2.00",

                "is\_hot\_sale": 0,

                "month\_sale": 100,

                "product\_sales": 100,

                "product\_certificate": [

                    {

                        "abbreviation": "MSDS(NEW)",

                        "color": "#BC9D20",

                        "id": 9

                    },

                    {

                        "abbreviation": "MSDS(CHEMICAL)",

                        "color": "#E1BB25",

                        "id": 8

                    }

                ],

                "delivery\_regions": {

                    "5": [

                        {

                            "delivery\_region\_id": 5,

                            "delivery\_region\_name": "中国",

                            "product\_price": "￥1.00",

                            "qty": 999999

                        }

                    ]

                },

                "product\_categories": [

                    {

                        "category\_id": 100130,

                        "category\_name": "家居用品",

                        "parent\_id": 0,

                        "level": 1

                    },

                    {

                        "category\_id": 100321,

                        "category\_name": "家居清洁",

                        "parent\_id": 100130,

                        "level": 2

                    },

                    {

                        "category\_id": 100324,

                        "category\_name": "管道疏通",

                        "parent\_id": 100321,

                        "level": 3

                    }

                ],

                "stock\_status": "有货",

                "product\_image": "https://oss.westmonth.com/westmonth/SKUDatum/old/JAB01-A014-6-BU2/mainpicture/JAB01-A014-6-BU2-001.jpg?x-oss-process=image/resize,m\_lfit,w\_500",

                "image": ""

            },

            {

                "product\_sku\_id": 9726,

                "product\_id": 5084,

                "product\_sku": "EEA06-A065-25-GN1",

                "delivery\_region\_id": 5,

                "product\_sku\_is\_default": 1,

                "seckill\_product\_price": null,

                "customer\_wishlist\_id": null,

                "product\_name": "EELHOE 足浴盐 滋润足部肌肤深层清洁缓解肌肉酸痛纤体驱寒足浴盐",

                "month\_sales": "0",

                "product\_price": "￥1.00",

                "qty": 999999,

                "product\_addtime": "2023-12-15 16:01:34",

                "is\_wish": 0,

                "is\_new": 0,

                "is\_seckill": 0,

                "guide\_price": "￥2.00",

                "is\_hot\_sale": 0,

                "month\_sale": 100,

                "product\_sales": 100,

                "product\_certificate": [

                    {

                        "abbreviation": "CPNP",

                        "color": "#2D3887",

                        "id": 4

                    },

                    {

                        "abbreviation": "SCPN",

                        "color": "#6D96FC",

                        "id": 3

                    },

                    {

                        "abbreviation": "MSDS(NEW)",

                        "color": "#BC9D20",

                        "id": 9

                    }

                ],

                "delivery\_regions": {

                    "3": [

                        {

                            "delivery\_region\_id": 3,

                            "delivery\_region\_name": "美国",

                            "product\_price": "￥29.53",

                            "qty": "100"

                        }

                    ],

                    "5": [

                        {

                            "delivery\_region\_id": 5,

                            "delivery\_region\_name": "中国",

                            "product\_price": "￥1.00",

                            "qty": 999999

                        }

                    ]

                },

                "product\_categories": [

                    {

                        "category\_id": 100126,

                        "category\_name": "健康护理",

                        "parent\_id": 0,

                        "level": 1

                    },

                    {

                        "category\_id": 100173,

                        "category\_name": "足部护理",

                        "parent\_id": 100126,

                        "level": 2

                    },

                    {

                        "category\_id": 100282,

                        "category\_name": "足浴包",

                        "parent\_id": 100173,

                        "level": 3

                    }

                ],

                "stock\_status": "有货",

                "product\_image": "https://oss.westmonth.com/westmonth/SKUDatum/old/EEA06-A065-25-GN1/mainpicture/EEA06-A065-25-GN1-001.jpg?x-oss-process=image/resize,m\_lfit,w\_500",

                "image": ""

            }

        ]

    }

}
```

### 商品详情

#### 请求参数

| 参数名称   | 参数类型 | 是否必填 | 参数描述                 |
|------------|----------|----------|--------------------------|
| product_id | int      | Y        | 商品ID                   |
| locale     | string   | N        | 语言，默认zh_cn,英文传en |

#### 请求参数示例

```
/openapi/product/detail?product\_id=2155&locale=zh\_cn
```

#### 请求方式&地址

```
[get] /openapi/product/detail
```

#### 返回参数

| 参数名称                            | 参数类型 | 是否 回传 | 参数描述                           | 示例值                                             |
|-------------------------------------|----------|-----------|------------------------------------|----------------------------------------------------|
| code                                | string   | Y         | 错误码                             | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                                 | string   | Y         | 错误描述                           | 执行成功                                           |
| data                                | object   | Y         | 业务数据                           |                                                    |
| -\| id                              | int      | Y         | 产品 ID                            | 123                                                |
| -\| name                            | string   | Y         | 产品名称                           | 1                                                  |
| -\| description                     | string   | Y         | 产品描述                           |                                                    |
| -\| brand_id                        | string   | Y         | 品牌ID                             |                                                    |
| -\| brand_name                      | string   | Y         | 品牌名称                           |                                                    |
| -\| platform_id                     | string   | Y         | 平台id                             |                                                    |
| -\| platform_name                   | string   | Y         | 平台名称                           |                                                    |
| -\| video                           | string   | Y         | 视频链接                           |                                                    |
| -\| attributes                      | array    | N         | 属性数组                           |                                                    |
| -\|-\| attribute_group_name         | string   | N         | 属性组名称                         |                                                    |
| -\|-\| attributes                   | array    | N         | 属性列表                           |                                                    |
| -\|-\|-\| attribute                 | string   | N         | 属性名                             |                                                    |
| -\|-\|-\| attribute_value           | string   | N         | 属性值                             |                                                    |
| -\| images                          | array    | Y         | 图片数组                           |                                                    |
| -\|-\| thumb                        | string   | Y         | 缩略图 URL                         |                                                    |
| -\|-\| preview                      | string   | Y         | 预览图 URL                         |                                                    |
| -\|-\| popup                        | string   | Y         | 弹出图 URL                         |                                                    |
| -\|-\| max_preview                  | string   | Y         | 最大预览图 URL                     |                                                    |
| -\| variables                       | array    | Y         | 多规格数据列表                     |                                                    |
| -\|-\| name                         | string   | Y         | 规格名称                           |                                                    |
| -\|-\| values                       | array    | Y         | 规格列表                           |                                                    |
| -\|-\|-\| name                      | string   | Y         | 名称                               |                                                    |
| -\|-\|-\| image                     | string   | Y         | 图片链接                           |                                                    |
| -\| skus                            | array    | Y         | SKU信息                            |                                                    |
| -\|-\| id                           | int      | Y         | SKU-ID                             |                                                    |
| -\|-\| variants                     | string   | Y         | 规格值                             |                                                    |
| -\|-\| position                     | string   | Y         | 排序                               |                                                    |
| -\|-\| images                       | string   | Y         | 图片                               |                                                    |
| -\|-\|-\| preview                   | string   | Y         | 预览图 URL                         |                                                    |
| -\|-\|-\| popup                     | string   | Y         | 弹出图 URL                         |                                                    |
| -\|-\|-\| thumb                     | string   | Y         | 缩略图 URL                         |                                                    |
| -\|-\| price                        | string   | Y         | 价格                               |                                                    |
| -\|-\| price_format                 | string   | Y         | 货币格式的价格                     |                                                    |
| -\|-\| origin_price                 | string   | Y         | 原价划线价                         |                                                    |
| -\|-\| origin_price_format          | string   | Y         | 货币格式的划线价                   |                                                    |
| -\|-\| model                        | string   | Y         | 模型                               |                                                    |
| -\|-\| sku                          | string   | Y         | SKU 编码                           |                                                    |
| -\|-\| weight                       | string   | Y         | 重量                               |                                                    |
| -\|-\| weight_class                 | string   | Y         | 重量单位                           |                                                    |
| -\|-\| delivery_region_id           | int      | Y         | 默认发货区域id                     |                                                    |
| -\|-\| region_name                  | string   | Y         | 默认发货区域名称                   |                                                    |
| -\|-\| combine                      | int      | Y         | 是否组合装：0否，1是               |                                                    |
| -\|-\| regions                      | object   | Y         | sku发货区域信息                    |                                                    |
| -\|-\| pattern_type                 | object   | Y         | gabalnara：包邮， afhalen：自提    |                                                    |
| -\|-\| delivery_regions             | object   | Y         | 配送区域                           |                                                    |
| -\|-\|-\| id                        | int      | Y         | 配送区域 ID                        |                                                    |
| -\|-\|-\| product_sku_id            | int      | Y         | sku id                             |                                                    |
| -\|-\|-\| delivery_region_id        | int      | Y         | 发货区域id                         |                                                    |
| -\|-\|-\| delivery_region_code      | string   | Y         | 发货区域编码                       |                                                    |
| -\|-\|-\| delivery_region_name      | int      | Y         | 发货区域名称                       |                                                    |
| -\|-\|-\| wms_co_id                 | int      | Y         | 聚水潭仓库id                       |                                                    |
| -\|-\|-\| price                     | string   | Y         | 价格                               |                                                    |
| -\|-\|-\| price_format              | string   | Y         | 带符号的价格                       |                                                    |
| -\|-\|-\| origin_price              | string   | Y         | 原价                               |                                                    |
| -\|-\|-\| origin_price_format       | string   | Y         | 带符号的原价                       |                                                    |
| -\|-\|-\| qty                       | int      | Y         | 库存数量                           |                                                    |
| -\|-\|-\| in_stock_updated_at       | string   | Y         | 上架时间                           |                                                    |
| -\|-\|-\| ring_stocks               | array    | Y         | 圈货参数                           |                                                    |
| -\|-\|-\| in_stock                  | int      | Y         | 库存状态，0：缺货，1：有货         |                                                    |
| -\|-\|-\| guide_price_format        | string   | Y         | 带符号的指导价格                   |                                                    |
| -\|-\|-\| afhalen                   | array    | Y         | 美国发货区域价格数组（不包含运费） |                                                    |
| -\|-\|-\|-\| amount_format_CNY      | string   | Y         | 人民币价格                         |                                                    |
| -\|-\|-\|-\| amount_format_USD      | string   | Y         | 美元价格                           |                                                    |
| -\|-\|-\|-\| product_total          | string   | Y         | 商品价格（美元）                   |                                                    |
| -\|-\|-\|-\| freight_total          | string   | Y         | 运费价格（美元）                   |                                                    |
| -\|-\|-\| gabalnara                 | array    | Y         | 美国发货区域价格数组（预估运费）   |                                                    |
| -\|-\|-\|-\| amount_format_CNY      | string   | Y         | 人民币价格（含运费）               |                                                    |
| -\|-\|-\|-\| amount_format_USD      | string   | Y         | 美元价格（含运费）                 |                                                    |
| -\|-\|-\|-\| product_total          | string   | Y         | 商品价格（美元）                   |                                                    |
| -\|-\|-\|-\| freight_total          | string   | Y         | 运费价格（美元）                   |                                                    |
| -\|-\|-\| price_array               | array    | Y         | 多档价格数组                       |                                                    |
| -\|-\|-\|-\| priceGear              | int      | Y         | 索引                               |                                                    |
| -\|-\|-\|-\| price                  | string   | Y         | 价格                               |                                                    |
| -\|-\|-\|-\| price_format           | string   | Y         | 带符号的价格                       |                                                    |
| -\|-\|-\|-\| minimum_quantity       | int      | Y         | 起定量                             |                                                    |
| -\|-\|-\|-\| multiple_price         | string   | Y         | 范围价格                           |                                                    |
| -\|-\|-\|-\| paritie                | string   | Y         | 汇率                               |                                                    |
| -\|-\|-\|-\| minimum_quantity_range | string   | Y         | 起定量区间                         |                                                    |
| -\|-\|-\|-\| guide_price            | string   | Y         | 带符号的指导价格                   |                                                    |
| -\| product_categories              | array    | Y         | 产品分类数组                       |                                                    |
| -\|-\| category_id                  | int      | Y         | 产品分类ID                         |                                                    |
| -\|-\| category_name                | string   | Y         | 产品分类名称                       |                                                    |
| -\|-\| parent_id                    | int      | Y         | 产品分类上级ID                     |                                                    |
| -\|-\| level                        | int      | Y         | 层级                               |                                                    |
| -\| in_wishlist                     | int      | Y         | 客户收藏数据id                     |                                                    |
| -\| active                          | bool     | Y         | true:上架，false:下架              |                                                    |
| -\| is_new                          | int      | Y         | 是否一个月内上架，0：否，1：是     |                                                    |
| -\| is_seckill                      | int      | Y         | 是否秒杀商品，0：否，1：是         |                                                    |
| -\| is_hot_sale                     | int      | Y         | 是否热销，0：否，1：是             |                                                    |
| -\| month_sale                      | string   | Y         | 月销量                             |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": {

        "id": 2155,

        "name": "Jaysuing 草本纤体肚脐贴 懒人身体塑形肚脐丸紧致瘦大腿肉肚脐贴",

        "description": "<p>我们的草本纤体肚脐贴含有人参、白参、金银花。它可以快速燃烧脂肪，促进新陈代谢，防止脂肪合成，不断消耗体内积累的脂肪水肿，促进全身的血液循环和淋巴循环。它的使用方法很简单：首先取下肚脐贴纸，之后撕掉保护纸，把它贴在肚脐上，最后撕下释放条即可。使用后有助于更好塑身，让皮肤有弹性且牢固，使皮肤紧致，帮助通便，塑身减肥。<img class=\\"img-fluid\\" style=\\"display: block; margin-left: auto; margin-right: auto;\\" src=\\"https://oss.westmonth.com/westmonth/SKUDatum/J%E6%A0%87xtt224dn01/mainpicture/J%E6%A0%87xtt224dn01-1730253608024.gif\\"><img class=\\"img-fluid\\" src=\\"https://oss.westmonth.com/westmonth/SKUDatum/J%E6%A0%87xtt224dn01/mainpicture/J%E6%A0%87xtt224dn01-1730253608023.jpg\\"><img class=\\"img-fluid\\" src=\\"https://oss.westmonth.com/westmonth/SKUDatum/J%E6%A0%87xtt224dn01/mainpicture/J%E6%A0%87xtt224dn01-1730253608030.jpg\\"><img class=\\"img-fluid\\" src=\\"https://oss.westmonth.com/westmonth/SKUDatum/J%E6%A0%87xtt224dn01/mainpicture/J%E6%A0%87xtt224dn01-1730253608015.jpg\\"><img class=\\"img-fluid\\" src=\\"https://oss.westmonth.com/westmonth/SKUDatum/J%E6%A0%87xtt224dn01/mainpicture/J%E6%A0%87xtt224dn01-1730253608016.jpg\\"><img class=\\"img-fluid\\" src=\\"https://oss.westmonth.com/westmonth/SKUDatum/J%E6%A0%87xtt224dn01/mainpicture/J%E6%A0%87xtt224dn01-1730253608012.jpg\\"><img class=\\"img-fluid\\" src=\\"https://oss.westmonth.com/westmonth/SKUDatum/J%E6%A0%87xtt224dn01/mainpicture/J%E6%A0%87xtt224dn01-1730253608011.jpg\\"></p>\\n",

        "meta\_title": "",

        "meta\_keywords": "",

        "meta\_description": "",

        "brand\_id": 16,

        "brand\_name": "Jaysuing",

        "brand\_main\_name": "汕头市简素净清洁环保科技有限公司",

        "brand\_logo": "https://oss.westmonth.com/%2Fcatalog%2Fwestmonth%2FBrand%2F170919330667\_%E5%93%81%E7%89%8C%20jaysuing.png",

        "platform\_id": 0,

        "platform\_name": "",

        "video": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608025.mp4",

        "images": [

            {

                "thumb": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608000.jpg",

                "preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608000.jpg",

                "popup": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608000.jpg",

                "max\_preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608000.jpg"

            },

            {

                "thumb": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608001.jpg",

                "preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608001.jpg",

                "popup": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608001.jpg",

                "max\_preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608001.jpg"

            },

            {

                "thumb": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608017.jpg",

                "preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608017.jpg",

                "popup": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608017.jpg",

                "max\_preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608017.jpg"

            },

            {

                "thumb": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608018.jpg",

                "preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608018.jpg",

                "popup": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608018.jpg",

                "max\_preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608018.jpg"

            },

            {

                "thumb": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608022.jpg",

                "preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608022.jpg",

                "popup": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608022.jpg",

                "max\_preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608022.jpg"

            },

            {

                "thumb": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608011.jpg",

                "preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608011.jpg",

                "popup": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608011.jpg",

                "max\_preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608011.jpg"

            },

            {

                "thumb": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608027.jpg",

                "preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608027.jpg",

                "popup": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608027.jpg",

                "max\_preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608027.jpg"

            },

            {

                "thumb": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608030.jpg",

                "preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608030.jpg",

                "popup": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608030.jpg",

                "max\_preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608030.jpg"

            },

            {

                "thumb": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608015.jpg",

                "preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608015.jpg",

                "popup": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608015.jpg",

                "max\_preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608015.jpg"

            },

            {

                "thumb": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608000.jpg",

                "preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608000.jpg",

                "popup": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608000.jpg",

                "max\_preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608000.jpg"

            },

            {

                "thumb": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608018.jpg",

                "preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608018.jpg",

                "popup": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608018.jpg",

                "max\_preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608018.jpg"

            },

            {

                "thumb": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608017.jpg",

                "preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608017.jpg",

                "popup": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608017.jpg",

                "max\_preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608017.jpg"

            },

            {

                "thumb": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608001.jpg",

                "preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608001.jpg",

                "popup": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608001.jpg",

                "max\_preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608001.jpg"

            }

        ],

        "attributes": [

            {

                "attribute\_group\_name": "默认",

                "attributes": [

                    {

                        "attribute": "净含量",

                        "attribute\_value": "10pcs；30pcs",

                        "attribute\_id": 9,

                        "attribute\_value\_id": 20560

                    },

                    {

                        "attribute": "产品名称",

                        "attribute\_value": "草本纤体肚脐贴",

                        "attribute\_id": 12,

                        "attribute\_value\_id": 20558

                    },

                    {

                        "attribute": "关键词",

                        "attribute\_value": "纤体、塑形",

                        "attribute\_id": 13,

                        "attribute\_value\_id": 2151

                    },

                    {

                        "attribute": "毛重",

                        "attribute\_value": "带磁款：10pcs：22g；30pcs：47g；药丸款：10pcs：19g；30pcs：51g",

                        "attribute\_id": 14,

                        "attribute\_value\_id": 118244

                    },

                    {

                        "attribute": "成分",

                        "attribute\_value": "人参、白参、金银花",

                        "attribute\_id": 15,

                        "attribute\_value\_id": 20559

                    },

                    {

                        "attribute": "包装尺寸",

                        "attribute\_value": "10pcs：7.3\*6.5\*3cm；30pcs：7.5\*7.5\*5.1cm",

                        "attribute\_id": 16,

                        "attribute\_value\_id": 118248

                    },

                    {

                        "attribute": "产品尺寸",

                        "attribute\_value": "7\*6cm",

                        "attribute\_id": 17,

                        "attribute\_value\_id": 4045

                    },

                    {

                        "attribute": "箱规",

                        "attribute\_value": "56\*36\*21.5cm",

                        "attribute\_id": 18,

                        "attribute\_value\_id": 38

                    },

                    {

                        "attribute": "保质期",

                        "attribute\_value": "3年",

                        "attribute\_id": 21,

                        "attribute\_value\_id": 15552

                    },

                    {

                        "attribute": "贮存方法",

                        "attribute\_value": "请保存于阴凉遮蔽处",

                        "attribute\_id": 22,

                        "attribute\_value\_id": 15553

                    }

                ]

            }

        ],

        "variables": [

            {

                "name": "款式",

                "values": [

                    {

                        "name": "带磁款",

                        "image": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608001.jpg"

                    },

                    {

                        "name": "药丸款",

                        "image": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608018.jpg"

                    }

                ]

            },

            {

                "name": "规格",

                "values": [

                    {

                        "name": "10pcs",

                        "image": ""

                    },

                    {

                        "name": "30pcs",

                        "image": ""

                    }

                ]

            }

        ],

        "skus": [

            {

                "id": 9477,

                "variants": [

                    "0",

                    "0"

                ],

                "position": 0,

                "images": [

                    {

                        "preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608000.jpg",

                        "popup": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608000.jpg",

                        "thumb": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608000.jpg"

                    }

                ],

                "model": "带磁10pcs",

                "sku": "J标xtt224dn01-带磁10pcs",

                "weight": 22,

                "weight\_class": "g",

                "price": 117.55,

                "price\_format": "￥117.55",

                "origin\_price": 117.55,

                "origin\_price\_format": "￥117.55",

                "quantity": 0,

                "is\_default": 1,

                "delivery\_region\_id": 15,

                "region\_id": 68238,

                "region\_name": "新西兰",

                "delivery\_regions": {

                    "68238": {

                        "id": 68238,

                        "product\_sku\_id": 9477,

                        "delivery\_region\_id": 15,

                        "delivery\_region\_code": "NZ",

                        "delivery\_region\_name": "新西兰",

                        "wms\_co\_id": 14034585,

                        "price": "117.55",

                        "price\_format": "￥117.55",

                        "origin\_price": "117.55",

                        "origin\_price\_format": "￥117.55",

                        "is\_default": 1,

                        "price\_array": [

                            {

                                "priceGear": 0,

                                "price": 117.55,

                                "product\_price": 117.55,

                                "price\_format": "￥117.55",

                                "minimum\_quantity": 1,

                                "multiple\_price": 117.55,

                                "paritie": 0,

                                "minimum\_quantity\_range": 1,

                                "guide\_price": "￥235.1",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 1,

                                "price": 66.1,

                                "product\_price": 66.1,

                                "price\_format": "￥66.10",

                                "minimum\_quantity": 2,

                                "multiple\_price": 66.1,

                                "paritie": 0,

                                "minimum\_quantity\_range": "2-2",

                                "guide\_price": "￥132.2",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 2,

                                "price": 48.95,

                                "product\_price": 48.95,

                                "price\_format": "￥48.95",

                                "minimum\_quantity": 3,

                                "multiple\_price": 48.95,

                                "paritie": 0,

                                "minimum\_quantity\_range": "3-3",

                                "guide\_price": "￥97.9",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 3,

                                "price": 39.15,

                                "product\_price": 39.15,

                                "price\_format": "￥39.15",

                                "minimum\_quantity": 4,

                                "multiple\_price": 39.15,

                                "paritie": 0,

                                "minimum\_quantity\_range": ">=4",

                                "guide\_price": "￥78.3",

                                "real\_price": ""

                            }

                        ],

                        "ring\_stocks": [],

                        "in\_stock": 1,

                        "qty": "100",

                        "in\_stock\_updated\_at": "2022-02-25 11:29:52",

                        "guide\_price\_format": "CNY ￥235.1",

                        "real\_price": "￥0.00"

                    },

                    "48252": {

                        "id": 48252,

                        "product\_sku\_id": 9477,

                        "delivery\_region\_id": 1,

                        "delivery\_region\_code": "IN",

                        "delivery\_region\_name": "印尼",

                        "wms\_co\_id": 13184398,

                        "price": "4.50",

                        "price\_format": "￥4.50",

                        "origin\_price": "4.50",

                        "origin\_price\_format": "￥4.50",

                        "is\_default": 0,

                        "price\_array": [

                            {

                                "priceGear": 0,

                                "price": 4.5,

                                "product\_price": 4.5,

                                "price\_format": "￥4.50",

                                "minimum\_quantity": 1,

                                "multiple\_price": 4.5,

                                "paritie": 0,

                                "minimum\_quantity\_range": ">=1",

                                "guide\_price": "￥9",

                                "real\_price": ""

                            }

                        ],

                        "ring\_stocks": [],

                        "in\_stock": 0,

                        "qty": 0,

                        "in\_stock\_updated\_at": "2022-02-25 11:29:52",

                        "guide\_price\_format": "CNY ￥9",

                        "real\_price": "￥0.00"

                    },

                    "48251": {

                        "id": 48251,

                        "product\_sku\_id": 9477,

                        "delivery\_region\_id": 3,

                        "delivery\_region\_code": "US",

                        "delivery\_region\_name": "美国",

                        "wms\_co\_id": 13625047,

                        "price": "29.53",

                        "price\_format": "￥29.53",

                        "origin\_price": "28.89",

                        "origin\_price\_format": "￥28.89",

                        "is\_default": 0,

                        "price\_array": [

                            {

                                "priceGear": 0,

                                "price": 29.53,

                                "product\_price": 29.53,

                                "price\_format": "USD \$3.99",

                                "minimum\_quantity": 1,

                                "multiple\_price": 29.53,

                                "paritie": 0,

                                "minimum\_quantity\_range": 1,

                                "guide\_price": "\$7.98",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 1,

                                "price": 22.2,

                                "product\_price": 22.2,

                                "price\_format": "USD \$3.00",

                                "minimum\_quantity": 2,

                                "multiple\_price": 22.2,

                                "paritie": 0,

                                "minimum\_quantity\_range": "2-2",

                                "guide\_price": "\$6.00",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 2,

                                "price": 19.76,

                                "product\_price": 19.76,

                                "price\_format": "USD \$2.67",

                                "minimum\_quantity": 3,

                                "multiple\_price": 19.76,

                                "paritie": 0,

                                "minimum\_quantity\_range": "3-3",

                                "guide\_price": "\$5.34",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 3,

                                "price": 18.5,

                                "product\_price": 18.5,

                                "price\_format": "USD \$2.50",

                                "minimum\_quantity": 4,

                                "multiple\_price": 18.5,

                                "paritie": 0,

                                "minimum\_quantity\_range": ">=4",

                                "guide\_price": "\$5.00",

                                "real\_price": ""

                            }

                        ],

                        "ring\_stocks": [],

                        "in\_stock": 1,

                        "qty": "226",

                        "in\_stock\_updated\_at": "2022-02-25 11:29:52",

                        "guide\_price\_format": "USD \$7.98",

                        "afhalen": {

                            "amount\_format\_CNY": "CNY ￥29.53",

                            "amount\_format\_USD": "USD \$3.99",

                            "product\_total": "USD \$3.99",

                            "freight\_total": "USD \$0"

                        },

                        "gabalnara": {

                            "amount\_format\_CNY": "CNY ￥59.13",

                            "amount\_format\_USD": "USD \$7.99",

                            "product\_total": "USD \$3.99",

                            "freight\_total": "USD \$4.00"

                        },

                        "real\_price": "\$0.00"

                    },

                    "48253": {

                        "id": 48253,

                        "product\_sku\_id": 9477,

                        "delivery\_region\_id": 5,

                        "delivery\_region\_code": "CN",

                        "delivery\_region\_name": "中国",

                        "wms\_co\_id": 0,

                        "price": "2.00",

                        "price\_format": "￥2.00",

                        "origin\_price": "2.00",

                        "origin\_price\_format": "￥2.00",

                        "is\_default": 0,

                        "price\_array": [

                            {

                                "priceGear": 0,

                                "price": 2,

                                "product\_price": 2,

                                "price\_format": "￥2.00",

                                "minimum\_quantity": 1,

                                "multiple\_price": 2,

                                "paritie": 0,

                                "minimum\_quantity\_range": "1-99",

                                "guide\_price": "￥4",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 1,

                                "price": 2,

                                "product\_price": 2,

                                "price\_format": "￥2.00",

                                "minimum\_quantity": 100,

                                "multiple\_price": 2,

                                "paritie": 0,

                                "minimum\_quantity\_range": "100-499",

                                "guide\_price": "￥4",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 2,

                                "price": 2,

                                "product\_price": 2,

                                "price\_format": "￥2.00",

                                "minimum\_quantity": 500,

                                "multiple\_price": 2,

                                "paritie": 0,

                                "minimum\_quantity\_range": ">=500",

                                "guide\_price": "￥4",

                                "real\_price": ""

                            }

                        ],

                        "ring\_stocks": [],

                        "in\_stock": 1,

                        "qty": 99999,

                        "in\_stock\_updated\_at": "2022-02-25 11:29:52",

                        "guide\_price\_format": "CNY ￥4",

                        "real\_price": "￥0.00"

                    },

                    "66009": {

                        "id": 66009,

                        "product\_sku\_id": 9477,

                        "delivery\_region\_id": 10,

                        "delivery\_region\_code": "AD",

                        "delivery\_region\_name": "澳洲",

                        "wms\_co\_id": 14034585,

                        "price": "78.35",

                        "price\_format": "￥78.35",

                        "origin\_price": "78.35",

                        "origin\_price\_format": "￥78.35",

                        "is\_default": 0,

                        "price\_array": [

                            {

                                "priceGear": 0,

                                "price": 78.35,

                                "product\_price": 78.35,

                                "price\_format": "￥78.35",

                                "minimum\_quantity": 1,

                                "multiple\_price": 78.35,

                                "paritie": 0,

                                "minimum\_quantity\_range": 1,

                                "guide\_price": "￥156.7",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 1,

                                "price": 48.95,

                                "product\_price": 48.95,

                                "price\_format": "￥48.95",

                                "minimum\_quantity": 2,

                                "multiple\_price": 48.95,

                                "paritie": 0,

                                "minimum\_quantity\_range": "2-2",

                                "guide\_price": "￥97.9",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 2,

                                "price": 37.53,

                                "product\_price": 37.53,

                                "price\_format": "￥37.53",

                                "minimum\_quantity": 3,

                                "multiple\_price": 37.53,

                                "paritie": 0,

                                "minimum\_quantity\_range": "3-3",

                                "guide\_price": "￥75.06",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 3,

                                "price": 29.35,

                                "product\_price": 29.35,

                                "price\_format": "￥29.35",

                                "minimum\_quantity": 4,

                                "multiple\_price": 29.35,

                                "paritie": 0,

                                "minimum\_quantity\_range": ">=4",

                                "guide\_price": "￥58.7",

                                "real\_price": ""

                            }

                        ],

                        "ring\_stocks": [],

                        "in\_stock": 1,

                        "qty": "100",

                        "in\_stock\_updated\_at": "2022-02-25 11:29:52",

                        "guide\_price\_format": "CNY ￥156.7",

                        "real\_price": "￥0.00"

                    },

                    "69375": {

                        "id": 69375,

                        "product\_sku\_id": 9477,

                        "delivery\_region\_id": 16,

                        "delivery\_region\_code": "RU",

                        "delivery\_region\_name": "俄罗斯",

                        "wms\_co\_id": 14243073,

                        "price": "25.00",

                        "price\_format": "￥25.00",

                        "origin\_price": "25.00",

                        "origin\_price\_format": "￥25.00",

                        "is\_default": 0,

                        "price\_array": [

                            {

                                "priceGear": 0,

                                "price": 25,

                                "product\_price": 25,

                                "price\_format": "￥25.00",

                                "minimum\_quantity": 1,

                                "multiple\_price": 25,

                                "paritie": 0,

                                "minimum\_quantity\_range": 1,

                                "guide\_price": "￥50",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 1,

                                "price": 21,

                                "product\_price": 21,

                                "price\_format": "￥21.00",

                                "minimum\_quantity": 2,

                                "multiple\_price": 21,

                                "paritie": 0,

                                "minimum\_quantity\_range": "2-2",

                                "guide\_price": "￥42",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 2,

                                "price": 20,

                                "product\_price": 20,

                                "price\_format": "￥20.00",

                                "minimum\_quantity": 3,

                                "multiple\_price": 20,

                                "paritie": 0,

                                "minimum\_quantity\_range": "3-3",

                                "guide\_price": "￥40",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 3,

                                "price": 19.5,

                                "product\_price": 19.5,

                                "price\_format": "￥19.50",

                                "minimum\_quantity": 4,

                                "multiple\_price": 19.5,

                                "paritie": 0,

                                "minimum\_quantity\_range": ">=4",

                                "guide\_price": "￥39",

                                "real\_price": ""

                            }

                        ],

                        "ring\_stocks": [],

                        "in\_stock": 1,

                        "qty": "94",

                        "in\_stock\_updated\_at": "2022-02-25 11:29:52",

                        "guide\_price\_format": "CNY ￥50",

                        "real\_price": "￥0.00"

                    }

                },

                "combine": 0,

                "pattern\_type": {

                    "68238": [

                        "gabalnara"

                    ],

                    "48252": [

                        "afhalen",

                        "gabalnara"

                    ],

                    "48251": [

                        "gabalnara",

                        "afhalen"

                    ],

                    "48253": [

                        "gabalnara",

                        "afhalen"

                    ],

                    "66009": [

                        "gabalnara"

                    ],

                    "69375": [

                        "gabalnara"

                    ]

                },

                "regions": {

                    "68238": "新西兰",

                    "48252": "印尼",

                    "48251": "美国",

                    "48253": "中国",

                    "66009": "澳洲",

                    "69375": "俄罗斯"

                }

            },

            {

                "id": 9480,

                "variants": [

                    "1",

                    "1"

                ],

                "position": 3,

                "images": [

                    {

                        "preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608018.jpg",

                        "popup": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608018.jpg",

                        "thumb": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608018.jpg"

                    }

                ],

                "model": "药丸30pcs",

                "sku": "J标xtt224dn01-药丸30pcs",

                "weight": 51,

                "weight\_class": "g",

                "price": 47.32,

                "price\_format": "￥47.32",

                "origin\_price": 47.32,

                "origin\_price\_format": "￥47.32",

                "quantity": 0,

                "is\_default": 0,

                "delivery\_region\_id": 14,

                "region\_id": 90876,

                "region\_name": "欧盟",

                "delivery\_regions": {

                    "90876": {

                        "id": 90876,

                        "product\_sku\_id": 9480,

                        "delivery\_region\_id": 14,

                        "delivery\_region\_code": "EU",

                        "delivery\_region\_name": "欧盟",

                        "wms\_co\_id": 14242443,

                        "price": "47.32",

                        "price\_format": "￥47.32",

                        "origin\_price": "47.32",

                        "origin\_price\_format": "￥47.32",

                        "is\_default": 1,

                        "price\_array": [

                            {

                                "priceGear": 0,

                                "price": 47.32,

                                "product\_price": 47.32,

                                "price\_format": "￥47.32",

                                "minimum\_quantity": 1,

                                "multiple\_price": 47.32,

                                "paritie": 0,

                                "minimum\_quantity\_range": 1,

                                "guide\_price": "￥94.64",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 1,

                                "price": 39.5,

                                "product\_price": 39.5,

                                "price\_format": "￥39.50",

                                "minimum\_quantity": 2,

                                "multiple\_price": 39.5,

                                "paritie": 0,

                                "minimum\_quantity\_range": "2-2",

                                "guide\_price": "￥79",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 2,

                                "price": 35.55,

                                "product\_price": 35.55,

                                "price\_format": "￥35.55",

                                "minimum\_quantity": 3,

                                "multiple\_price": 35.55,

                                "paritie": 0,

                                "minimum\_quantity\_range": "3-3",

                                "guide\_price": "￥71.1",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 3,

                                "price": 31.6,

                                "product\_price": 31.6,

                                "price\_format": "￥31.60",

                                "minimum\_quantity": 4,

                                "multiple\_price": 31.6,

                                "paritie": 0,

                                "minimum\_quantity\_range": ">=4",

                                "guide\_price": "￥63.2",

                                "real\_price": ""

                            }

                        ],

                        "ring\_stocks": [],

                        "in\_stock": 1,

                        "qty": "100",

                        "in\_stock\_updated\_at": "2022-02-25 11:29:52",

                        "guide\_price\_format": "CNY ￥94.64",

                        "real\_price": "￥0.00"

                    },

                    "41412": {

                        "id": 41412,

                        "product\_sku\_id": 9480,

                        "delivery\_region\_id": 2,

                        "delivery\_region\_code": "TG",

                        "delivery\_region\_name": "泰国",

                        "wms\_co\_id": 13505983,

                        "price": "6.50",

                        "price\_format": "￥6.50",

                        "origin\_price": "6.50",

                        "origin\_price\_format": "￥6.50",

                        "is\_default": 0,

                        "price\_array": [

                            {

                                "priceGear": 0,

                                "price": 6.5,

                                "product\_price": 6.5,

                                "price\_format": "￥6.50",

                                "minimum\_quantity": 1,

                                "multiple\_price": 6.5,

                                "paritie": 0,

                                "minimum\_quantity\_range": ">=1",

                                "guide\_price": "￥13",

                                "real\_price": ""

                            }

                        ],

                        "ring\_stocks": [],

                        "in\_stock": 1,

                        "qty": "86",

                        "in\_stock\_updated\_at": "2022-02-25 11:29:52",

                        "guide\_price\_format": "CNY ￥13",

                        "real\_price": "￥0.00"

                    },

                    "41413": {

                        "id": 41413,

                        "product\_sku\_id": 9480,

                        "delivery\_region\_id": 5,

                        "delivery\_region\_code": "CN",

                        "delivery\_region\_name": "中国",

                        "wms\_co\_id": 0,

                        "price": "4.00",

                        "price\_format": "￥4.00",

                        "origin\_price": "4.00",

                        "origin\_price\_format": "￥4.00",

                        "is\_default": 0,

                        "price\_array": [

                            {

                                "priceGear": 0,

                                "price": 4,

                                "product\_price": 4,

                                "price\_format": "￥4.00",

                                "minimum\_quantity": 1,

                                "multiple\_price": 4,

                                "paritie": 0,

                                "minimum\_quantity\_range": "1-99",

                                "guide\_price": "￥8",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 1,

                                "price": 4,

                                "product\_price": 4,

                                "price\_format": "￥4.00",

                                "minimum\_quantity": 100,

                                "multiple\_price": 4,

                                "paritie": 0,

                                "minimum\_quantity\_range": "100-499",

                                "guide\_price": "￥8",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 2,

                                "price": 4,

                                "product\_price": 4,

                                "price\_format": "￥4.00",

                                "minimum\_quantity": 500,

                                "multiple\_price": 4,

                                "paritie": 0,

                                "minimum\_quantity\_range": ">=500",

                                "guide\_price": "￥8",

                                "real\_price": ""

                            }

                        ],

                        "ring\_stocks": [],

                        "in\_stock": 1,

                        "qty": 99999,

                        "in\_stock\_updated\_at": "2022-02-25 11:29:52",

                        "guide\_price\_format": "CNY ￥8",

                        "real\_price": "￥0.00"

                    },

                    "41411": {

                        "id": 41411,

                        "product\_sku\_id": 9480,

                        "delivery\_region\_id": 6,

                        "delivery\_region\_code": "FLB",

                        "delivery\_region\_name": "菲律宾",

                        "wms\_co\_id": 13679390,

                        "price": "6.50",

                        "price\_format": "￥6.50",

                        "origin\_price": "6.50",

                        "origin\_price\_format": "￥6.50",

                        "is\_default": 0,

                        "price\_array": [

                            {

                                "priceGear": 0,

                                "price": 6.5,

                                "product\_price": 6.5,

                                "price\_format": "￥6.50",

                                "minimum\_quantity": 1,

                                "multiple\_price": 6.5,

                                "paritie": 0,

                                "minimum\_quantity\_range": ">=1",

                                "guide\_price": "￥13",

                                "real\_price": ""

                            }

                        ],

                        "ring\_stocks": [],

                        "in\_stock": 0,

                        "qty": 0,

                        "in\_stock\_updated\_at": "2022-02-25 11:29:52",

                        "guide\_price\_format": "CNY ￥13",

                        "real\_price": "￥0.00"

                    },

                    "69748": {

                        "id": 69748,

                        "product\_sku\_id": 9480,

                        "delivery\_region\_id": 16,

                        "delivery\_region\_code": "RU",

                        "delivery\_region\_name": "俄罗斯",

                        "wms\_co\_id": 14243073,

                        "price": "25.00",

                        "price\_format": "￥25.00",

                        "origin\_price": "25.00",

                        "origin\_price\_format": "￥25.00",

                        "is\_default": 0,

                        "price\_array": [

                            {

                                "priceGear": 0,

                                "price": 25,

                                "product\_price": 25,

                                "price\_format": "￥25.00",

                                "minimum\_quantity": 1,

                                "multiple\_price": 25,

                                "paritie": 0,

                                "minimum\_quantity\_range": 1,

                                "guide\_price": "￥50",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 1,

                                "price": 21,

                                "product\_price": 21,

                                "price\_format": "￥21.00",

                                "minimum\_quantity": 2,

                                "multiple\_price": 21,

                                "paritie": 0,

                                "minimum\_quantity\_range": "2-2",

                                "guide\_price": "￥42",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 2,

                                "price": 20,

                                "product\_price": 20,

                                "price\_format": "￥20.00",

                                "minimum\_quantity": 3,

                                "multiple\_price": 20,

                                "paritie": 0,

                                "minimum\_quantity\_range": "3-3",

                                "guide\_price": "￥40",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 3,

                                "price": 19.5,

                                "product\_price": 19.5,

                                "price\_format": "￥19.50",

                                "minimum\_quantity": 4,

                                "multiple\_price": 19.5,

                                "paritie": 0,

                                "minimum\_quantity\_range": ">=4",

                                "guide\_price": "￥39",

                                "real\_price": ""

                            }

                        ],

                        "ring\_stocks": [],

                        "in\_stock": 1,

                        "qty": "100",

                        "in\_stock\_updated\_at": "2022-02-25 11:29:52",

                        "guide\_price\_format": "CNY ￥50",

                        "real\_price": "￥0.00"

                    }

                },

                "combine": 0,

                "pattern\_type": {

                    "90876": [

                        "gabalnara",

                        "afhalen"

                    ],

                    "41412": [

                        "afhalen",

                        "gabalnara"

                    ],

                    "41413": [

                        "gabalnara",

                        "afhalen"

                    ],

                    "41411": [

                        "afhalen"

                    ],

                    "69748": [

                        "gabalnara"

                    ]

                },

                "regions": {

                    "90876": "欧盟",

                    "41412": "泰国",

                    "41413": "中国",

                    "41411": "菲律宾",

                    "69748": "俄罗斯"

                }

            },

            {

                "id": 9479,

                "variants": [

                    "1",

                    "0"

                ],

                "position": 2,

                "images": [

                    {

                        "preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608017.jpg",

                        "popup": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608017.jpg",

                        "thumb": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608017.jpg"

                    }

                ],

                "model": "药丸10pcs",

                "sku": "J标xtt224dn01-药丸10pcs",

                "weight": 19,

                "weight\_class": "g",

                "price": 47.32,

                "price\_format": "￥47.32",

                "origin\_price": 47.32,

                "origin\_price\_format": "￥47.32",

                "quantity": 0,

                "is\_default": 0,

                "delivery\_region\_id": 14,

                "region\_id": 90875,

                "region\_name": "欧盟",

                "delivery\_regions": {

                    "90875": {

                        "id": 90875,

                        "product\_sku\_id": 9479,

                        "delivery\_region\_id": 14,

                        "delivery\_region\_code": "EU",

                        "delivery\_region\_name": "欧盟",

                        "wms\_co\_id": 14242443,

                        "price": "47.32",

                        "price\_format": "￥47.32",

                        "origin\_price": "47.32",

                        "origin\_price\_format": "￥47.32",

                        "is\_default": 1,

                        "price\_array": [

                            {

                                "priceGear": 0,

                                "price": 47.32,

                                "product\_price": 47.32,

                                "price\_format": "￥47.32",

                                "minimum\_quantity": 1,

                                "multiple\_price": 47.32,

                                "paritie": 0,

                                "minimum\_quantity\_range": 1,

                                "guide\_price": "￥94.64",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 1,

                                "price": 39.5,

                                "product\_price": 39.5,

                                "price\_format": "￥39.50",

                                "minimum\_quantity": 2,

                                "multiple\_price": 39.5,

                                "paritie": 0,

                                "minimum\_quantity\_range": "2-2",

                                "guide\_price": "￥79",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 2,

                                "price": 35.55,

                                "product\_price": 35.55,

                                "price\_format": "￥35.55",

                                "minimum\_quantity": 3,

                                "multiple\_price": 35.55,

                                "paritie": 0,

                                "minimum\_quantity\_range": "3-3",

                                "guide\_price": "￥71.1",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 3,

                                "price": 31.6,

                                "product\_price": 31.6,

                                "price\_format": "￥31.60",

                                "minimum\_quantity": 4,

                                "multiple\_price": 31.6,

                                "paritie": 0,

                                "minimum\_quantity\_range": ">=4",

                                "guide\_price": "￥63.2",

                                "real\_price": ""

                            }

                        ],

                        "ring\_stocks": [],

                        "in\_stock": 1,

                        "qty": "100",

                        "in\_stock\_updated\_at": "2022-02-25 11:29:52",

                        "guide\_price\_format": "CNY ￥94.64",

                        "real\_price": "￥0.00"

                    },

                    "41410": {

                        "id": 41410,

                        "product\_sku\_id": 9479,

                        "delivery\_region\_id": 5,

                        "delivery\_region\_code": "CN",

                        "delivery\_region\_name": "中国",

                        "wms\_co\_id": 0,

                        "price": "1.60",

                        "price\_format": "￥1.60",

                        "origin\_price": "1.60",

                        "origin\_price\_format": "￥1.60",

                        "is\_default": 0,

                        "price\_array": [

                            {

                                "priceGear": 0,

                                "price": 1.6,

                                "product\_price": 1.6,

                                "price\_format": "￥1.60",

                                "minimum\_quantity": 1,

                                "multiple\_price": 1.6,

                                "paritie": 0,

                                "minimum\_quantity\_range": "1-99",

                                "guide\_price": "￥3.2",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 1,

                                "price": 1.6,

                                "product\_price": 1.6,

                                "price\_format": "￥1.60",

                                "minimum\_quantity": 100,

                                "multiple\_price": 1.6,

                                "paritie": 0,

                                "minimum\_quantity\_range": "100-499",

                                "guide\_price": "￥3.2",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 2,

                                "price": 1.6,

                                "product\_price": 1.6,

                                "price\_format": "￥1.60",

                                "minimum\_quantity": 500,

                                "multiple\_price": 1.6,

                                "paritie": 0,

                                "minimum\_quantity\_range": ">=500",

                                "guide\_price": "￥3.2",

                                "real\_price": ""

                            }

                        ],

                        "ring\_stocks": [],

                        "in\_stock": 1,

                        "qty": 99999,

                        "in\_stock\_updated\_at": "2022-02-25 11:29:52",

                        "guide\_price\_format": "CNY ￥3.2",

                        "real\_price": "￥0.00"

                    },

                    "69747": {

                        "id": 69747,

                        "product\_sku\_id": 9479,

                        "delivery\_region\_id": 16,

                        "delivery\_region\_code": "RU",

                        "delivery\_region\_name": "俄罗斯",

                        "wms\_co\_id": 14243073,

                        "price": "25.00",

                        "price\_format": "￥25.00",

                        "origin\_price": "25.00",

                        "origin\_price\_format": "￥25.00",

                        "is\_default": 0,

                        "price\_array": [

                            {

                                "priceGear": 0,

                                "price": 25,

                                "product\_price": 25,

                                "price\_format": "￥25.00",

                                "minimum\_quantity": 1,

                                "multiple\_price": 25,

                                "paritie": 0,

                                "minimum\_quantity\_range": 1,

                                "guide\_price": "￥50",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 1,

                                "price": 21,

                                "product\_price": 21,

                                "price\_format": "￥21.00",

                                "minimum\_quantity": 2,

                                "multiple\_price": 21,

                                "paritie": 0,

                                "minimum\_quantity\_range": "2-2",

                                "guide\_price": "￥42",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 2,

                                "price": 20,

                                "product\_price": 20,

                                "price\_format": "￥20.00",

                                "minimum\_quantity": 3,

                                "multiple\_price": 20,

                                "paritie": 0,

                                "minimum\_quantity\_range": "3-3",

                                "guide\_price": "￥40",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 3,

                                "price": 19.5,

                                "product\_price": 19.5,

                                "price\_format": "￥19.50",

                                "minimum\_quantity": 4,

                                "multiple\_price": 19.5,

                                "paritie": 0,

                                "minimum\_quantity\_range": ">=4",

                                "guide\_price": "￥39",

                                "real\_price": ""

                            }

                        ],

                        "ring\_stocks": [],

                        "in\_stock": 1,

                        "qty": "100",

                        "in\_stock\_updated\_at": "2022-02-25 11:29:52",

                        "guide\_price\_format": "CNY ￥50",

                        "real\_price": "￥0.00"

                    }

                },

                "combine": 0,

                "pattern\_type": {

                    "90875": [

                        "gabalnara",

                        "afhalen"

                    ],

                    "41410": [

                        "gabalnara",

                        "afhalen"

                    ],

                    "69747": [

                        "gabalnara"

                    ]

                },

                "regions": {

                    "90875": "欧盟",

                    "41410": "中国",

                    "69747": "俄罗斯"

                }

            },

            {

                "id": 9478,

                "variants": [

                    "0",

                    "1"

                ],

                "position": 1,

                "images": [

                    {

                        "preview": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608001.jpg",

                        "popup": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608001.jpg",

                        "thumb": "https://oss.westmonth.com/westmonth/SKUDatum/J标xtt224dn01/mainpicture/J标xtt224dn01-1730253608001.jpg"

                    }

                ],

                "model": "带磁30pcs",

                "sku": "J标xtt224dn01-带磁30pcs",

                "weight": 47,

                "weight\_class": "g",

                "price": 5,

                "price\_format": "￥5.00",

                "origin\_price": 25,

                "origin\_price\_format": "￥25.00",

                "quantity": 0,

                "is\_default": 0,

                "delivery\_region\_id": 16,

                "region\_id": 69488,

                "region\_name": "俄罗斯",

                "delivery\_regions": {

                    "69488": {

                        "id": 69488,

                        "product\_sku\_id": 9478,

                        "delivery\_region\_id": 16,

                        "delivery\_region\_code": "RU",

                        "delivery\_region\_name": "俄罗斯",

                        "wms\_co\_id": 14243073,

                        "price": "25.00",

                        "price\_format": "￥25.00",

                        "origin\_price": "25.00",

                        "origin\_price\_format": "￥25.00",

                        "is\_default": 1,

                        "price\_array": [

                            {

                                "priceGear": 0,

                                "price": 25,

                                "product\_price": 25,

                                "price\_format": "￥25.00",

                                "minimum\_quantity": 1,

                                "multiple\_price": 25,

                                "paritie": 0,

                                "minimum\_quantity\_range": 1,

                                "guide\_price": "￥50",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 1,

                                "price": 21,

                                "product\_price": 21,

                                "price\_format": "￥21.00",

                                "minimum\_quantity": 2,

                                "multiple\_price": 21,

                                "paritie": 0,

                                "minimum\_quantity\_range": "2-2",

                                "guide\_price": "￥42",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 2,

                                "price": 20,

                                "product\_price": 20,

                                "price\_format": "￥20.00",

                                "minimum\_quantity": 3,

                                "multiple\_price": 20,

                                "paritie": 0,

                                "minimum\_quantity\_range": "3-3",

                                "guide\_price": "￥40",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 3,

                                "price": 19.5,

                                "product\_price": 19.5,

                                "price\_format": "￥19.50",

                                "minimum\_quantity": 4,

                                "multiple\_price": 19.5,

                                "paritie": 0,

                                "minimum\_quantity\_range": ">=4",

                                "guide\_price": "￥39",

                                "real\_price": ""

                            }

                        ],

                        "ring\_stocks": [],

                        "in\_stock": 1,

                        "qty": "100",

                        "in\_stock\_updated\_at": "2022-02-25 11:29:52",

                        "guide\_price\_format": "CNY ￥50",

                        "real\_price": "￥0.00"

                    },

                    "41409": {

                        "id": 41409,

                        "product\_sku\_id": 9478,

                        "delivery\_region\_id": 5,

                        "delivery\_region\_code": "CN",

                        "delivery\_region\_name": "中国",

                        "wms\_co\_id": 0,

                        "price": "5.00",

                        "price\_format": "￥5.00",

                        "origin\_price": "5.00",

                        "origin\_price\_format": "￥5.00",

                        "is\_default": 0,

                        "price\_array": [

                            {

                                "priceGear": 0,

                                "price": 5,

                                "product\_price": 5,

                                "price\_format": "￥5.00",

                                "minimum\_quantity": 1,

                                "multiple\_price": 5,

                                "paritie": 0,

                                "minimum\_quantity\_range": "1-99",

                                "guide\_price": "￥10",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 1,

                                "price": 5,

                                "product\_price": 5,

                                "price\_format": "￥5.00",

                                "minimum\_quantity": 100,

                                "multiple\_price": 5,

                                "paritie": 0,

                                "minimum\_quantity\_range": "100-499",

                                "guide\_price": "￥10",

                                "real\_price": ""

                            },

                            {

                                "priceGear": 2,

                                "price": 5,

                                "product\_price": 5,

                                "price\_format": "￥5.00",

                                "minimum\_quantity": 500,

                                "multiple\_price": 5,

                                "paritie": 0,

                                "minimum\_quantity\_range": ">=500",

                                "guide\_price": "￥10",

                                "real\_price": ""

                            }

                        ],

                        "ring\_stocks": [],

                        "in\_stock": 1,

                        "qty": 99999,

                        "in\_stock\_updated\_at": "2022-02-25 11:29:52",

                        "guide\_price\_format": "CNY ￥10",

                        "real\_price": "￥0.00"

                    }

                },

                "combine": 0,

                "pattern\_type": {

                    "69488": [

                        "gabalnara"

                    ],

                    "41409": [

                        "gabalnara",

                        "afhalen"

                    ]

                },

                "regions": {

                    "69488": "俄罗斯",

                    "41409": "中国"

                }

            }

        ],

        "in\_wishlist": 0,

        "active": true,

        "is\_new": 0,

        "is\_seckill": 0,

        "is\_hot\_sale": 0,

        "month\_sale": "100+",

        "parities": 0,

        "product\_categories": [

            {

                "category\_id": 100121,

                "category\_name": "美容护理",

                "position": 99,

                "parent\_id": 0,

                "level": 1

            },

            {

                "category\_id": 100226,

                "category\_name": "美体护理",

                "position": 0,

                "parent\_id": 100121,

                "level": 2

            },

            {

                "category\_id": 100252,

                "category\_name": "瘦身纤体护理",

                "position": 0,

                "parent\_id": 100226,

                "level": 3

            }

        ]

    }

}
```

### SKU查询客户价格

#### 请求方式&地址

```
[post] /openapi/skuPrice/customerPrice
```

#### 请求参数

| 参数名称     | 参数类型 | 是否必填 | 参数描述   |
|--------------|----------|----------|------------|
| skus         | array    | Y        | SKU列表    |
| region_codes | array    | N        | 国家二字码 |
| customer_id  | int      | Y        | 客户ID     |

#### 请求参数示例

```
{

	"skus": ["yfy217yx01-120ml", "EEA02-A144-30-WH1"],

	"region\_codes": ["CN","TG"],

	"customer\_id": 10449

}
```

#### 返回参数

| 参数名称                       | 参数类型 | 是否 回传 | 参数描述     | 示例值                                             |
|--------------------------------|----------|-----------|--------------|----------------------------------------------------|
| code                           | string   | Y         | 错误码       | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                            | string   | Y         | 错误描述     | 执行成功                                           |
| data                           | object   | Y         | 业务数据     |                                                    |
| -\| sku                        | string   | Y         | SKU编码      |                                                    |
| -\|-\| sku                     | string   | Y         | SKU编码      |                                                    |
| -\|-\| price                   | string   | Y         | 模型         |                                                    |
| -\|-\|-\| delivery_region_id   | int      | Y         | 发货区域ID   |                                                    |
| -\|-\|-\| delivery_region_name | string   | Y         | 发货区域名称 |                                                    |
| -\|-\|-\| price                | string   | Y         | 价格         |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": {

        "yfy217yx01-120ml": {

            "sku": "yfy217yx01-120ml",

            "price": [

                {

                    "delivery\_region\_id": 5,

                    "delivery\_region\_name": "中国",

                    "price": 5

                }

            ]

        },

        "EEA02-A144-30-WH1": {

            "sku": "EEA02-A144-30-WH1",

            "price": [

                {

                    "delivery\_region\_id": 5,

                    "delivery\_region\_name": "中国",

                    "price": 8

                }

            ]

        }

    }

}
```

## SKU获取一体化标签

### OpenAPI Specification

```yaml
openapi: 3.0.1
info:
  title: ''
  description: ''
  version: 1.0.0
paths:
  /openapi/sku_tag/query:
    post:
      summary: SKU获取一体化标签
      deprecated: false
      description: ''
      tags:
        - 开放API/商品
      parameters:
        - name: Content-Type
          in: header
          description: ''
          example: application/json
          schema:
            type: string
        - name: Authorization
          in: header
          description: ''
          example: '{{authorization}}'
          schema:
            type: string
            default: '{{authorization}}'
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                skus:
                  type: array
                  items:
                    type: string
                  description: 商品sku列表，不超过200个
                start_time:
                  type: string
                  description: 更新开始时间，例：2024-12-17 18:24:47
                end_time:
                  type: string
                  description: 更新结束时间，例：2024-12-17 18:24:47
              required:
                - skus
                - start_time
                - end_time
              x-apifox-orders:
                - skus
                - start_time
                - end_time
            example:
              skus:
                - DE-I03-0028-01
                - EEA02-A142-4-YE1
                - EEA02-A143-20-VT1
                - cbsft114yy02-一盒12pcs
              start_time: '2024-12-17 18:24:47'
              end_time: '2026-12-17 18:24:47'
      responses:
        '200':
          description: ''
          content:
            application/json:
              schema:
                type: object
                properties:
                  code:
                    type: integer
                    description: 错误码（0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限）
                  msg:
                    type: string
                    description: 错误描述
                  data:
                    type: object
                    properties:
                      v1:
                        type: object
                        properties:
                          cbsft114yy02-一盒12pcs:
                            type: object
                            properties:
                              sku:
                                type: string
                                description: 商品sku
                              urls:
                                type: array
                                items:
                                  type: string
                                description: 标签数组信息
                              latest_time:
                                type: string
                                description: 标签列表最新更新时间
                            required:
                              - sku
                              - urls
                              - latest_time
                            x-apifox-orders:
                              - sku
                              - urls
                              - latest_time
                            description: SKU
                          EEA02-A142-4-YE1:
                            type: object
                            properties:
                              sku:
                                type: string
                              urls:
                                type: array
                                items:
                                  type: string
                              latest_time:
                                type: string
                            required:
                              - sku
                              - urls
                              - latest_time
                            x-apifox-orders:
                              - sku
                              - urls
                              - latest_time
                          EEA02-A143-20-VT1:
                            type: object
                            properties:
                              sku:
                                type: string
                              urls:
                                type: array
                                items:
                                  type: string
                              latest_time:
                                type: string
                            required:
                              - sku
                              - urls
                              - latest_time
                            x-apifox-orders:
                              - sku
                              - urls
                              - latest_time
                        required:
                          - cbsft114yy02-一盒12pcs
                          - EEA02-A142-4-YE1
                          - EEA02-A143-20-VT1
                        x-apifox-orders:
                          - cbsft114yy02-一盒12pcs
                          - EEA02-A142-4-YE1
                          - EEA02-A143-20-VT1
                        description: 版本1
                      v2:
                        type: object
                        properties:
                          EEA02-A142-4-YE1:
                            type: object
                            properties:
                              sku:
                                type: string
                              urls:
                                type: array
                                items:
                                  type: string
                              latest_time:
                                type: string
                            required:
                              - sku
                              - urls
                              - latest_time
                            x-apifox-orders:
                              - sku
                              - urls
                              - latest_time
                        required:
                          - EEA02-A142-4-YE1
                        x-apifox-orders:
                          - EEA02-A142-4-YE1
                        description: 版本2
                    required:
                      - v1
                      - v2
                    x-apifox-orders:
                      - v1
                      - v2
                required:
                  - code
                  - msg
                  - data
                x-apifox-orders:
                  - code
                  - msg
                  - data
              example:
                code: 0
                msg: ''
                data:
                  v1:
                    cbsft114yy02-一盒12pcs:
                      sku: cbsft114yy02-一盒12pcs
                      urls:
                        - >-
                          https://westmonth/SKUDatum/cbsft114yy02-一盒12pcs/integrationtag/cbsft114yy02-一盒12pcs-TEMU-70200715.pdf
                      latest_time: '2024-12-28 18:28:47'
                    EEA02-A142-4-YE1:
                      sku: EEA02-A142-4-YE1
                      urls:
                        - >-
                          https://westmonth/SKUDatum/EEA02-A142-4-YE1/integrationtag/EEA02-A142-4-YE1-SHEIN-1730112672.pdf
                        - >-
                          https://westmonth/SKUDatum/EEA02-A142-4-YE1/integrationtag/EEA02-A142-4-YE1-TEMU-1730113257.pdf
                        - >-
                          https://westmonth/SKUDatum/EEA02-A142-4-YE1/integrationtag/EEA02-A142-4-YE1-TEMU-95176820.pdf
                      latest_time: '2024-12-28 17:19:18'
                    EEA02-A143-20-VT1:
                      sku: EEA02-A143-20-VT1
                      urls:
                        - >-
                          https://westmonth/SKUDatum/EEA02-A143-20-VT1/integrationtag/EEA02-A143-20-VT1-SHEIN-1730112714.pdf
                        - >-
                          https://westmonth/SKUDatum/EEA02-A143-20-VT1/integrationtag/EEA02-A143-20-VT1-TEMU-1730112775.pdf
                        - >-
                          https://westmonth/SKUDatum/EEA02-A143-20-VT1/integrationtag/EEA02-A143-20-VT1-TEMU-1730113299.pdf
                        - >-
                          https://westmonth/SKUDatum/EEA02-A143-20-VT1/integrationtag/EEA02-A143-20-VT1-TEMU-76020089.pdf
                      latest_time: '2024-12-28 17:18:00'
                  v2:
                    EEA02-A142-4-YE1:
                      sku: EEA02-A142-4-YE1
                      urls:
                        - >-
                          https://westmonth/SKUDatum/EEA02-A142-4-YE1/integrationtag/EEA02-A142-4-YE1-SHEIN-SHEIN-46988280.pdf
                        - >-
                          https://westmonth/SKUDatum/EEA02-A142-4-YE1/integrationtag/EEA02-A142-4-YE1-TEMU-TEMU-36821655.pdf
                      latest_time: '2024-12-17 21:05:34'
          headers: {}
          x-apifox-name: 成功
      security: []
      x-apifox-folder: 开放API/商品
      x-apifox-status: released
      x-run-in-apifox: https://app.apifox.com/web/project/6014135/apis/api-275170477-run
components:
  schemas: {}
  securitySchemes: {}
servers:
  - url: https://testing.westmonth.com
    description: 外部-测试环境
security: []

```

## SKU获取一体化标签平台

### OpenAPI Specification

```yaml
openapi: 3.0.1
info:
  title: ''
  description: ''
  version: 1.0.0
paths:
  /openapi/sku_tag/query_new:
    post:
      summary: SKU获取一体化标签平台
      deprecated: false
      description: ''
      tags:
        - 开放API/商品
      parameters:
        - name: Content-Type
          in: header
          description: ''
          example: application/json
          schema:
            type: string
        - name: Authorization
          in: header
          description: ''
          example: '{{authorization}}'
          schema:
            type: string
            default: '{{authorization}}'
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                skus:
                  type: array
                  items:
                    type: string
                  description: 商品sku列表，不超过200个
                start_time:
                  type: string
                  description: 更新开始时间，例：2024-12-17 18:24:47
                end_time:
                  type: string
                  description: 更新结束时间，例：2024-12-17 18:24:47
              required:
                - skus
                - start_time
                - end_time
              x-apifox-orders:
                - skus
                - start_time
                - end_time
            example:
              skus:
                - DE-I03-0028-01
                - EEA02-A142-4-YE1
                - EEA02-A143-20-VT1
                - cbsft114yy02-一盒12pcs
              start_time: '2024-12-17 18:24:47'
              end_time: '2026-12-17 18:24:47'
      responses:
        '200':
          description: ''
          content:
            application/json:
              schema:
                type: object
                properties:
                  code:
                    type: integer
                  msg:
                    type: string
                  data:
                    type: object
                    properties:
                      v1:
                        type: object
                        properties:
                          cbsft114yy02-一盒12pcs:
                            type: object
                            properties:
                              sku:
                                type: string
                                description: sku
                              data:
                                type: array
                                items:
                                  type: object
                                  properties:
                                    url:
                                      type: string
                                      description: 文件路径
                                    updated_at:
                                      description: 文件更新时间
                                      type: string
                                    platforms:
                                      type: array
                                      items:
                                        type: string
                                      description: 文件平台目前有TEMU与SHEIN
                                  x-apifox-orders:
                                    - url
                                    - updated_at
                                    - platforms
                            required:
                              - sku
                              - data
                            x-apifox-orders:
                              - sku
                              - data
                          EEA02-A142-4-YE1:
                            type: object
                            properties:
                              sku:
                                type: string
                              data:
                                type: array
                                items:
                                  type: object
                                  properties:
                                    url:
                                      type: string
                                    updated_at:
                                      type: string
                                    platforms:
                                      type: array
                                      items:
                                        type: string
                                  required:
                                    - url
                                    - updated_at
                                    - platforms
                                  x-apifox-orders:
                                    - url
                                    - updated_at
                                    - platforms
                            required:
                              - sku
                              - data
                            x-apifox-orders:
                              - sku
                              - data
                          EEA02-A143-20-VT1:
                            type: object
                            properties:
                              sku:
                                type: string
                              data:
                                type: array
                                items:
                                  type: object
                                  properties:
                                    url:
                                      type: string
                                    updated_at:
                                      type: string
                                    platforms:
                                      type: array
                                      items:
                                        type: string
                                  required:
                                    - url
                                    - updated_at
                                    - platforms
                                  x-apifox-orders:
                                    - url
                                    - updated_at
                                    - platforms
                            required:
                              - sku
                              - data
                            x-apifox-orders:
                              - sku
                              - data
                        required:
                          - cbsft114yy02-一盒12pcs
                          - EEA02-A142-4-YE1
                          - EEA02-A143-20-VT1
                        x-apifox-orders:
                          - cbsft114yy02-一盒12pcs
                          - EEA02-A142-4-YE1
                          - EEA02-A143-20-VT1
                      v2:
                        type: object
                        properties:
                          EEA02-A142-4-YE1:
                            type: object
                            properties:
                              sku:
                                type: string
                              data:
                                type: array
                                items:
                                  type: object
                                  properties:
                                    url:
                                      type: string
                                    updated_at:
                                      type: string
                                    platforms:
                                      type: array
                                      items:
                                        type: string
                                  required:
                                    - url
                                    - updated_at
                                    - platforms
                                  x-apifox-orders:
                                    - url
                                    - updated_at
                                    - platforms
                            required:
                              - sku
                              - data
                            x-apifox-orders:
                              - sku
                              - data
                        required:
                          - EEA02-A142-4-YE1
                        x-apifox-orders:
                          - EEA02-A142-4-YE1
                    required:
                      - v1
                      - v2
                    x-apifox-orders:
                      - v1
                      - v2
                required:
                  - code
                  - msg
                  - data
                x-apifox-orders:
                  - code
                  - msg
                  - data
              example:
                code: 0
                msg: ''
                data:
                  v1:
                    cbsft114yy02-一盒12pcs:
                      sku: cbsft114yy02-一盒12pcs
                      data:
                        - url: >-
                            https://westmonth-test.oss-cn-shenzhen.aliyuncs.com/westmonth/SKUDatum/cbsft114yy02-一盒12pcs/integrationtag/cbsft114yy02-一盒12pcs-TEMU-70200715.pdf
                          updated_at: '2024-12-28 18:28:47'
                          platforms:
                            - TEMU
                    EEA02-A142-4-YE1:
                      sku: EEA02-A142-4-YE1
                      data:
                        - url: >-
                            https://westmonth-test.oss-cn-shenzhen.aliyuncs.com/westmonth/SKUDatum/EEA02-A142-4-YE1/integrationtag/EEA02-A142-4-YE1-SHEIN-1730112672.pdf
                          updated_at: '2025-06-20 09:58:38'
                          platforms:
                            - SHEIN
                        - url: >-
                            https://westmonth-test.oss-cn-shenzhen.aliyuncs.com/westmonth/SKUDatum/EEA02-A142-4-YE1/integrationtag/EEA02-A142-4-YE1-TEMU-1730113257.pdf
                          updated_at: '2025-06-20 09:58:38'
                          platforms:
                            - TEMU
                        - url: >-
                            https://westmonth-test.oss-cn-shenzhen.aliyuncs.com/westmonth/SKUDatum/EEA02-A142-4-YE1/integrationtag/EEA02-A142-4-YE1-TEMU-95176820.pdf
                          updated_at: '2025-06-20 09:58:38'
                          platforms:
                            - TEMU
                    EEA02-A143-20-VT1:
                      sku: EEA02-A143-20-VT1
                      data:
                        - url: >-
                            https://westmonth-test.oss-cn-shenzhen.aliyuncs.com/westmonth/SKUDatum/EEA02-A143-20-VT1/integrationtag/EEA02-A143-20-VT1-SHEIN-1730112714.pdf
                          updated_at: '2024-12-17 21:03:42'
                          platforms:
                            - SHEIN
                        - url: >-
                            https://westmonth-test.oss-cn-shenzhen.aliyuncs.com/westmonth/SKUDatum/EEA02-A143-20-VT1/integrationtag/EEA02-A143-20-VT1-TEMU-1730112775.pdf
                          updated_at: '2024-12-17 21:03:43'
                          platforms:
                            - TEMU
                        - url: >-
                            https://westmonth-test.oss-cn-shenzhen.aliyuncs.com/westmonth/SKUDatum/EEA02-A143-20-VT1/integrationtag/EEA02-A143-20-VT1-TEMU-1730113299.pdf
                          updated_at: '2024-12-17 21:03:45'
                          platforms:
                            - TEMU
                        - url: >-
                            https://westmonth-test.oss-cn-shenzhen.aliyuncs.com/westmonth/SKUDatum/EEA02-A143-20-VT1/integrationtag/EEA02-A143-20-VT1-TEMU-76020089.pdf
                          updated_at: '2024-12-28 17:18:00'
                          platforms:
                            - TEMU
                  v2:
                    EEA02-A142-4-YE1:
                      sku: EEA02-A142-4-YE1
                      data:
                        - url: >-
                            https://westmonth-test.oss-cn-shenzhen.aliyuncs.com/westmonth/SKUDatum/EEA02-A142-4-YE1/integrationtag/EEA02-A142-4-YE1-SHEIN-SHEIN-46988280.pdf
                          updated_at: '2025-06-20 09:58:38'
                          platforms:
                            - SHEIN
                            - SHEIN
                        - url: >-
                            https://westmonth-test.oss-cn-shenzhen.aliyuncs.com/westmonth/SKUDatum/EEA02-A142-4-YE1/integrationtag/EEA02-A142-4-YE1-TEMU-TEMU-36821655.pdf
                          updated_at: '2025-06-20 09:58:38'
                          platforms:
                            - TEMU
                            - TEMU
          headers: {}
          x-apifox-name: 成功
      security: []
      x-apifox-folder: 开放API/商品
      x-apifox-status: released
      x-run-in-apifox: https://app.apifox.com/web/project/6014135/apis/api-316205215-run
components:
  schemas: {}
  securitySchemes: {}
servers:
  - url: https://testing.westmonth.com
    description: 外部-测试环境
security: []

```

## SKU获取证书

### OpenAPI Specification

```yaml
openapi: 3.0.1
info:
  title: ''
  description: ''
  version: 1.0.0
paths:
  /openapi/sku_certificats/query:
    post:
      summary: SKU获取证书
      deprecated: false
      description: ''
      tags:
        - 开放API/商品
      parameters:
        - name: Content-Type
          in: header
          description: ''
          example: application/json
          schema:
            type: string
        - name: Authorization
          in: header
          description: ''
          example: '{{authorization}}'
          schema:
            type: string
            default: '{{authorization}}'
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                skus:
                  type: array
                  items:
                    type: string
                  description: 商品sku列表，不超过50个
                start_time:
                  type: string
                  description: 更新开始时间，例：2024-12-17 18:24:47
                end_time:
                  type: string
                  description: 更新结束时间，例：2024-12-17 18:24:47
              required:
                - skus
              x-apifox-orders:
                - skus
                - start_time
                - end_time
            example:
              skus:
                - ROA10-A006-10-BU1
                - ROA10-A006-10-PK1
              start_time: '2024-12-17 18:24:47'
              end_time: ''
      responses:
        '200':
          description: ''
          content:
            application/json:
              schema:
                type: object
                properties:
                  code:
                    type: integer
                    description: 错误码（0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限）
                  msg:
                    type: string
                    description: 错误描述
                  data:
                    type: array
                    items:
                      type: object
                      properties:
                        sku:
                          type: string
                          description: 商品sku
                        certificates:
                          type: array
                          items:
                            type: object
                            properties:
                              assort_name:
                                type: string
                                description: 证书分类名称
                              abbreviation:
                                type: string
                                description: 证书分类简写
                              resource_url:
                                type: array
                                items:
                                  type: string
                                description: 证书链接列表（所有版本）
                              resource_url_v1:
                                type: array
                                items:
                                  type: string
                                description: 证书链接列表（版本1）
                              resource_url_v2:
                                type: array
                                items:
                                  type: string
                                description: 证书链接列表（版本2）
                            required:
                              - assort_name
                              - abbreviation
                              - resource_url
                              - resource_url_v1
                              - resource_url_v2
                            x-apifox-orders:
                              - assort_name
                              - abbreviation
                              - resource_url
                              - resource_url_v1
                              - resource_url_v2
                          description: 证书列表
                        latest_time:
                          type: string
                          description: 证书列表更新最新时间
                      required:
                        - sku
                        - certificates
                        - latest_time
                      x-apifox-orders:
                        - sku
                        - certificates
                        - latest_time
                    description: 业务数据
                required:
                  - code
                  - msg
                  - data
                x-apifox-orders:
                  - code
                  - msg
                  - data
              example:
                code: 0
                msg: ''
                data:
                  - sku: ROA10-A006-10-BU1
                    certificates:
                      - assort_name: 加拿大化妆品通报证明
                        abbreviation: CN
                        resource_url:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-CN-1732193915.png
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-CN-12224590.png
                        resource_url_v1:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-CN-1732193915.png
                        resource_url_v2:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-CN-12224590.png
                      - assort_name: 欧盟化妆品通报证明
                        abbreviation: CPNP
                        resource_url:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-CPNP-1732193916.pdf
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-CPNP-72153981.pdf
                        resource_url_v1:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-CPNP-1732193916.pdf
                        resource_url_v2:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-CPNP-72153981.pdf
                      - assort_name: 韩国化妆品质量测试报告
                        abbreviation: MFDS
                        resource_url:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-MFDS-1732193917.pdf
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-MFDS-35028109.pdf
                        resource_url_v1:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-MFDS-1732193917.pdf
                        resource_url_v2:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-MFDS-35028109.pdf
                      - assort_name: 英国化妆品通报证明
                        abbreviation: SCPN
                        resource_url:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-SCPN-1732193919.pdf
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-SCPN-90755476.pdf
                        resource_url_v1:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-SCPN-1732193919.pdf
                        resource_url_v2:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-SCPN-90755476.pdf
                      - assort_name: 化学品安全说明书（新版）
                        abbreviation: MSDS(NEW)
                        resource_url:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-MSDS(NEW)-1732194726.pdf
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-MSDS(NEW)-93655176.pdf
                        resource_url_v1:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-MSDS(NEW)-1732194726.pdf
                        resource_url_v2:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-MSDS(NEW)-93655176.pdf
                      - assort_name: 美国化妆品监督证明
                        abbreviation: FDA
                        resource_url:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-1732356722.jpeg
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-1732356723.jpeg
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-1732356724.jpg
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-1732356725.jpg
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-99875695.png
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-27257789.png
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-29577078.png
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-31238601.png
                        resource_url_v1:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-1732356722.jpeg
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-1732356723.jpeg
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-1732356724.jpg
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-1732356725.jpg
                        resource_url_v2:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-99875695.png
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-27257789.png
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-29577078.png
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-BU1/certificate/ROA10-A006-10-BU1-FDA-31238601.png
                    latest_time: '2025-03-03 10:29:04'
                  - sku: ROA10-A006-10-PK1
                    certificates:
                      - assort_name: 加拿大化妆品通报证明
                        abbreviation: CN
                        resource_url:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-CN-1732193910.png
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-CN-37365469.png
                        resource_url_v1:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-CN-1732193910.png
                        resource_url_v2:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-CN-37365469.png
                      - assort_name: 欧盟化妆品通报证明
                        abbreviation: CPNP
                        resource_url:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-CPNP-1732193911.pdf
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-CPNP-72970773.pdf
                        resource_url_v1:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-CPNP-1732193911.pdf
                        resource_url_v2:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-CPNP-72970773.pdf
                      - assort_name: 韩国化妆品质量测试报告
                        abbreviation: MFDS
                        resource_url:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-MFDS-1732193912.pdf
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-MFDS-10890760.pdf
                        resource_url_v1:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-MFDS-1732193912.pdf
                        resource_url_v2:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-MFDS-10890760.pdf
                      - assort_name: 英国化妆品通报证明
                        abbreviation: SCPN
                        resource_url:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-SCPN-1732193914.pdf
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-SCPN-56858628.pdf
                        resource_url_v1:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-SCPN-1732193914.pdf
                        resource_url_v2:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-SCPN-56858628.pdf
                      - assort_name: 美国化妆品监督证明
                        abbreviation: FDA
                        resource_url:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-1732356493.png
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-1732356494.png
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-1732356495.png
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-1732356496.png
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-98825300.png
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-75317620.png
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-13607173.png
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-95774517.png
                        resource_url_v1:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-1732356493.png
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-1732356494.png
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-1732356495.png
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-1732356496.png
                        resource_url_v2:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-98825300.png
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-75317620.png
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-13607173.png
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-FDA-95774517.png
                      - assort_name: 化学品安全说明书（新版）
                        abbreviation: MSDS(NEW)
                        resource_url:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-MSDS(NEW)-1732699836.pdf
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-MSDS(NEW)-54637794.pdf
                        resource_url_v1:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-MSDS(NEW)-1732699836.pdf
                        resource_url_v2:
                          - >-
                            https://westmonth/SKUDatum/ROA10-A006-10-PK1/certificate/ROA10-A006-10-PK1-MSDS(NEW)-54637794.pdf
                    latest_time: '2025-03-03 10:29:26'
          headers: {}
          x-apifox-name: 成功
      security: []
      x-apifox-folder: 开放API/商品
      x-apifox-status: released
      x-run-in-apifox: https://app.apifox.com/web/project/6014135/apis/api-275170473-run
components:
  schemas: {}
  securitySchemes: {}
servers:
  - url: https://testing.westmonth.com
    description: 外部-测试环境
security: []

```

## SKU获取六面图

### OpenAPI Specification

```yaml
openapi: 3.0.1
info:
  title: ''
  description: ''
  version: 1.0.0
paths:
  /openapi/sku_diagrams/query:
    post:
      summary: SKU获取六面图
      deprecated: false
      description: ''
      tags:
        - 开放API/商品
      parameters:
        - name: Content-Type
          in: header
          description: ''
          example: application/json
          schema:
            type: string
        - name: Authorization
          in: header
          description: ''
          example: '{{authorization}}'
          schema:
            type: string
            default: '{{authorization}}'
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                skus:
                  type: array
                  items:
                    type: string
                  description: 商品sku列表，不超过200个
                start_time:
                  type: string
                  description: 更新开始时间，例：2024-12-17 18:24:47
                end_time:
                  type: string
                  description: 更新结束时间，例：2024-12-17 18:24:47
              required:
                - skus
                - start_time
                - end_time
              x-apifox-orders:
                - skus
                - start_time
                - end_time
            example:
              skus:
                - ROA10-A006-10-BU1
                - ROA10-A006-10-PK1
              start_time: '2024-12-17 18:24:47'
              end_time: ''
      responses:
        '200':
          description: ''
          content:
            application/json:
              schema:
                type: object
                properties:
                  code:
                    type: integer
                    description: 错误码（0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限）
                  msg:
                    type: string
                    description: 错误描述
                  data:
                    type: array
                    items:
                      type: object
                      properties:
                        sku:
                          type: string
                          description: 商品sku
                        images:
                          type: array
                          items:
                            type: string
                          description: 图片列表（所有版本）
                        images_v1:
                          type: array
                          items:
                            type: string
                          description: 图片列表（版本1）
                        images_v2:
                          type: array
                          items:
                            type: string
                          description: 图片列表（版本2）
                        latest_time:
                          type: string
                          description: 图片列表最新更新时间
                      required:
                        - sku
                        - images
                        - images_v1
                        - images_v2
                        - latest_time
                      x-apifox-orders:
                        - sku
                        - images
                        - images_v1
                        - images_v2
                        - latest_time
                    description: 业务数据
                required:
                  - code
                  - msg
                  - data
                x-apifox-orders:
                  - code
                  - msg
                  - data
              example:
                code: 0
                msg: ''
                data:
                  - sku: ROA10-A006-10-BU1
                    images:
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-0.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-2.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-1.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-3.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-4.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-6.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-5.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-1730779921.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-1730779922.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-1730779924.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-1730779923.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-1730779925.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-1730779926.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-49493822.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-49493823.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-49493824.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-49493825.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-49493826.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-49493827.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-49493828.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-56821299.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-90514145.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-30715737.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-23671180.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-99216958.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-71829421.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-66147301.jpg
                    images_v1:
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-0.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-2.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-1.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-3.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-4.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-6.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-5.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-1730779921.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-1730779922.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-1730779924.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-1730779923.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-1730779925.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-1730779926.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-49493822.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-49493823.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-49493824.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-49493825.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-49493826.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-49493827.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-49493828.jpg
                    images_v2:
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-56821299.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-90514145.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-30715737.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-23671180.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-99216958.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-71829421.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-BU1/view_picture/ROA10-A006-10-BU1-66147301.jpg
                    latest_time: '2025-03-03 10:29:04'
                  - sku: ROA10-A006-10-PK1
                    images:
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-0.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-2.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-3.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-5.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-4.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-6.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1729048001.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1729048002.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1729048003.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1729048004.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1729048005.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1729048006.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1731733883.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1731733884.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1731733886.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1731733885.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1731733887.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1731733888.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-49493829.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-49493830.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-49493831.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-49493832.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-49493833.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-49493834.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-49493835.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-41273245.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-49431605.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-80903116.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-60141819.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-49239383.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-41771286.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-16357881.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-17455113.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-77255611.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-37265475.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-75694267.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-50866115.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-64458122.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-83853649.jpg
                    images_v1:
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-0.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-2.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-3.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-5.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-4.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-6.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1729048001.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1729048002.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1729048003.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1729048004.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1729048005.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1729048006.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1731733883.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1731733884.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1731733886.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1731733885.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1731733887.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-1731733888.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-49493829.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-49493830.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-49493831.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-49493832.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-49493833.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-49493834.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-49493835.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-41273245.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-49431605.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-80903116.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-60141819.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-49239383.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-41771286.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-16357881.jpg
                    images_v2:
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-17455113.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-77255611.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-37265475.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-75694267.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-50866115.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-64458122.jpg
                      - >-
                        https://westmonth/SKUDatum/ROA10-A006-10-PK1/view_picture/ROA10-A006-10-PK1-83853649.jpg
                    latest_time: '2025-03-03 10:29:26'
          headers: {}
          x-apifox-name: 成功
      security: []
      x-apifox-folder: 开放API/商品
      x-apifox-status: released
      x-run-in-apifox: https://app.apifox.com/web/project/6014135/apis/api-275170474-run
components:
  schemas: {}
  securitySchemes: {}
servers:
  - url: https://testing.westmonth.com
    description: 外部-测试环境
security: []

```

## SKU获取视频

### OpenAPI Specification

```yaml
openapi: 3.0.1
info:
  title: ''
  description: ''
  version: 1.0.0
paths:
  /openapi/sku_video/query:
    post:
      summary: SKU获取视频
      deprecated: false
      description: ''
      tags:
        - 开放API/商品
      parameters:
        - name: Content-Type
          in: header
          description: ''
          example: application/json
          schema:
            type: string
        - name: Authorization
          in: header
          description: ''
          example: '{{authorization}}'
          schema:
            type: string
            default: '{{authorization}}'
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                skus:
                  type: array
                  items:
                    type: string
                  description: 商品sku列表，不超过200个
                start_time:
                  type: string
                  description: 更新开始时间，例：2024-12-17 18:24:47
                end_time:
                  type: string
                  description: 更新结束时间，例：2024-12-17 18:24:47
              required:
                - skus
                - start_time
                - end_time
              x-apifox-orders:
                - skus
                - start_time
                - end_time
            example:
              skus:
                - ROA10-A006-10-BU1
                - ROA10-A006-10-PK1
              start_time: '2024-12-17 18:24:47'
              end_time: ''
      responses:
        '200':
          description: ''
          content:
            application/json:
              schema:
                type: object
                properties:
                  code:
                    type: integer
                    description: 错误码（0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限）
                  msg:
                    type: string
                    description: 错误描述
                  data:
                    type: array
                    items:
                      type: object
                      properties:
                        sku:
                          type: string
                          description: 商品sku
                        video:
                          type: string
                          description: 视频链接
                        latest_time:
                          type: string
                          description: 最新更新时间
                      required:
                        - sku
                        - video
                        - latest_time
                      x-apifox-orders:
                        - sku
                        - video
                        - latest_time
                    description: 业务数据
                required:
                  - code
                  - msg
                  - data
                x-apifox-orders:
                  - code
                  - msg
                  - data
              example:
                code: 0
                msg: ''
                data:
                  - sku: ROA10-A006-10-BU1
                    video: >-
                      https://westmonth/SKUDatum/old/ROA10-A006-10-BU1/video/ROA10-A006-10-BU1-001.mp4
                    latest_time: '2024-12-24 16:13:28'
                  - sku: ROA10-A006-10-PK1
                    video: >-
                      https://westmonth/SKUDatum/old/ROA10-A006-10-PK1/video/ROA10-A006-10-PK1-001.mp4
                    latest_time: '2024-12-26 22:54:18'
          headers: {}
          x-apifox-name: 成功
      security: []
      x-apifox-folder: 开放API/商品
      x-apifox-status: released
      x-run-in-apifox: https://app.apifox.com/web/project/6014135/apis/api-275170476-run
components:
  schemas: {}
  securitySchemes: {}
servers:
  - url: https://testing.westmonth.com
    description: 外部-测试环境
security: []

```

## 获取产品使用说明书

### OpenAPI Specification

```yaml
openapi: 3.0.1
info:
  title: ''
  description: ''
  version: 1.0.0
paths:
  /openapi/sku_instruction/query:
    get:
      summary: 获取产品使用说明书
      deprecated: false
      description: ''
      tags:
        - 开放API/商品
      parameters:
        - name: start_time
          in: query
          description: 更新开始时间，例：2024-12-17 18:24:47
          required: false
          example: '2023-12-17 18:24:47'
          schema:
            type: string
        - name: end_time
          in: query
          description: 更新结束时间，例：2024-12-17 18:24:47
          required: false
          example: '{{$date.timestamp|format(''yyyy-MM-dd HH:mm:ss'')}}'
          schema:
            type: string
        - name: Content-Type
          in: header
          description: ''
          example: application/json
          schema:
            type: string
        - name: Authorization
          in: header
          description: ''
          example: '{{authorization}}'
          schema:
            type: string
            default: '{{authorization}}'
      responses:
        '200':
          description: ''
          content:
            application/json:
              schema:
                type: object
                properties:
                  code:
                    type: integer
                    description: 错误码（0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限）
                  msg:
                    type: string
                    description: 错误描述
                  data:
                    type: array
                    items:
                      type: object
                      properties:
                        url:
                          type: string
                          description: 产品使用说明书链接
                        latest_time:
                          type: string
                          description: 最新更新时间
                      x-apifox-orders:
                        - url
                        - latest_time
                    description: 业务数据
                required:
                  - code
                  - msg
                  - data
                x-apifox-orders:
                  - code
                  - msg
                  - data
              example:
                code: 0
                msg: ''
                data:
                  - url: >-
                      https://westmonth/SKUDatum/sku_instruction/通用产品使用说明书（英德法意西日）10X10.pdf
                    latest_time: '2024-10-23 17:19:58'
          headers: {}
          x-apifox-name: 成功
      security: []
      x-apifox-folder: 开放API/商品
      x-apifox-status: released
      x-run-in-apifox: https://app.apifox.com/web/project/6014135/apis/api-275170478-run
components:
  schemas: {}
  securitySchemes: {}
servers:
  - url: https://testing.westmonth.com
    description: 外部-测试环境
security: []

```

## 订单

### 订单提交接口

#### 接口说明

由平台主动调用该接口进行订单上传，一次最多20单。

#### 请求参数示例

```
{

   "uID": "对应西月的uid",

    "orders": [

        {

            "products": [

                {

                    "quantity": 3,

                    "productSku": "E标nHs322yx01-20g"

                }

            ],

            "callingCode": 1,

            "sellerOrderNo": "576722608781890541",

            "city": "Sylacauga",

            "countryCode": "US",

            "address1": ", Alabama, Talladega, Sylacauga,",

            "address2": "301 S Broadway Ave",

            "email": "v4bDJX2CMXGM6G5AHXPEL2WXNDW24@scs.tiktokw.us",

            "telephone": "18965213578",

            "deliveryMode": "快递配送",

            "receiver": "Stacie R. Sheppard",

            "zone": "Alabama",

            "zipcode": "35150",

            "deliveryRegionCode": "CN",

            "isPurchase": false

        }

    ]

}
```

#### 请求方式&地址

```
[post] /openapi/order/upload
```

#### 请求参数

| 参数名称                 | 参数类型 | 是否必填 | 参数描述                                             |
|--------------------------|----------|----------|------------------------------------------------------|
| uID                      | string   | N        | 西月客户uid（指定选品网账号下单）                    |
| cancelOrders             | array    | N        | 防止重复下单，取消之前未支付订单（通过平台订单标识） |
| orders                   | array    | Y        | 订单集合，最多20单                                   |
| -\| deliveryRegionCode   | string   | Y        | 发货区域编码                                         |
| -\| sellerOrderNo        | string   | Y        | 平台订单号                                           |
| -\| email                | string   | N        | 邮箱                                                 |
| -\| callingCode          | int      | N        | 手机区域号                                           |
| -\| comment              | string   | N        | 买家备注                                             |
| -\| remark               | string   | N        | 卖家备注                                             |
| -\| deliveryMode         | string   | Y        | 模式(快递配送,第三方物流自提,个人自提)               |
| -\| telephone            | string   | N        | 手机号(快递配送必填)                                 |
| -\| receiver             | string   | N        | 收件人姓名(快递配送必填)                             |
| -\| countryCode          | string   | Y        | 国家二字码                                           |
| -\| zone                 | string   | N        | 省、州名称(快递配送必填)                             |
| -\| city                 | string   | N        | 城市名称(快递配送必填)                               |
| -\| county               | string   | N        | 县区、乡镇名称(快递配送必填)                         |
| -\| address1             | string   | N        | 地址1(快递配送必填)                                  |
| -\| address2             | string   | N        | 地址2                                                |
| -\| zipcode              | string   | N        | 邮编(快递配送必填)                                   |
| -\| expressUrl           | string   | N        | 面单url(第三方物流自提必填)                          |
| -\| otherFiles           | array    | N        | 其他附件(一维数组，里面传文件url)                    |
| -\| barcodes             | array    | N        | 条形码(一维数组，里面传文件url)                      |
| -\| logisticsCompany     | string   | N        | 物流公司编码(第三方物流自提必填)                     |
| -\| shipmentNo           | string   | N        | 物流单号(第三方物流自提必填)                         |
| -\| platformID           | int      | N        | 平台id                                               |
| -\| shopName             | string   | N        | 店铺名称                                             |
| -\| node                 | srting   | N        | 线下备注                                             |
| -\| labels               | array    | N        | 标签数组，例：[xxx,xxx,xxx]                          |
| -\| typesSources         | int      | N        | 下单类型，1:线上，2:线下                             |
| -\| products             | array    | Y        | 产品数据                                             |
| -\|-\| productSku        | string   | Y        | SKU名称                                              |
| -\|-\| quantity          | int      | Y        | 数量                                                 |
| -\| rawData              | array    | N        | 外部单原始数据                                       |
| -\|-\| shopNumber        | string   | N        | 店铺编号                                             |
| -\|-\| shopAccountNumber | string   | N        | 店铺账号                                             |
| -\|-\| shopName          | string   | N        | 店铺名称                                             |
| -\|-\| shopAddress       | string   | N        | 店铺站点                                             |
| -\|-\| orderAmount       | string   | N        | 订单金额（人民币）                                   |
| -\|-\| commodityAmount   | string   | N        | 商品金额                                             |
| -\|-\| platformFreight   | string   | N        | 平台运费                                             |
| -\|-\| caid              | string   | N        | 密钥                                                 |
| -\|-\| sellerFlag        | int      | N        | 旗帜，可选1:red，2:yellow，3:green，4:blue，5:purple |
| -\|-\| products          | array    | N        | 商品数组信息                                         |
| -\|-\|-\| product_sku    | string   | N        | sku                                                  |
| -\|-\|-\| price          | string   | N        | 价格                                                 |
| -\|-\|-\| quantity       | int      | N        | 数量                                                 |
| -\|-\|-\| subtotal       | string   | N        | 小计                                                 |

#### 返回参数

| 参数名称             | 参数类型 | 是否回传 | 参数描述                                 | 示例值                                             |
|----------------------|----------|----------|------------------------------------------|----------------------------------------------------|
| code                 | string   | Y        | 错误码                                   | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                  | string   | Y        | 错误描述                                 | 执行成功                                           |
| data                 | array    | Y        | 业务数据                                 |                                                    |
| -\| normalData       | array    | Y        | 成功数据（已创建订单）                   |                                                    |
| -\|-\| sellerOrderNo | string   | Y        | 平台订单号                               |                                                    |
| -\|-\| orderNoXy     | string   | Y        | 西月选品网订单号                         |                                                    |
| -\|-\| total         | string   | Y        | 订单金额                                 |                                                    |
| -\|-\| status        | string   | Y        | 订单状态（paid：已支付，unpaid：未支付） |                                                    |
| -\| abnormalData     | array    | Y        | 异常数据（不会创建订单）                 |                                                    |
| -\|-\| sellerOrderNo | string   | Y        | 平台订单号                               |                                                    |
| -\|-\| reason        | string   | Y        | 异常原因                                 |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "请求成功！",

    "data": {

        "normalData": [

            {

                "sellerOrderNo": "240924S10PY0Q4",

                "orderNoXy": "2024092777000",

                "total": 58.17,

                "status": "unpaid",

            }

        ],

        "abnormalData": []

    }

}
```

### 订单预计算接口

#### 接口说明

由平台主动调用该接口进行订单预计算，一次最多20单。

#### 请求参数示例

```
{

    "uID": "XXX",

    "orders": [

        {

            "sellerOrderNo": "2502240SPQC28K",

            "ecommercePlatformId": 25,

            "deliveryMode": "第三方物流自提",

            "receiver": "Y\*\*\*\*\*\*n",

            "countryCode": "MY",

            "platformID": 25,

            "isPurchase": false,

            "deliveryRegionCode": "ML",

            "comment": "",

            "remark": "",

            "rawData": {

                "shopNumber": "2022033615",

                "shopAccountNumber": "1305883501",

                "shopName": "Shopee-马来本土店-Belle Beauty Salon",

                "shopAddress": "MY",

                "orderAmount": "13.0412",

                "orderCurrency": "MYR",

                "orderCurrencyAmount": null,

                "products": [

                    {

                        "product\_sku": "tqbt1124mj01-通气鼻贴",

                        "price": "13.0412",

                        "quantity": 1,

                        "subtotal": 13.0412

                    }

                ]

            },

            "expressUrl": "https://oss.westmonth.com/mabang/waybill/489023\_2502240SPQC28K10x10ac\_SPXMY057782387132.pdf",

            "shipmentNo": "SPXMY057782387132",

            "logisticsCompany": "SE016",

            "products": {

                "J标zht803zx01-60pcs/盒": {

                    "productSku": "J标zht803zx01-60pcs/盒",

                    "quantity": 1

                }

            }

        },

         {

            "sellerOrderNo": "2502240SPQC28K",

            "ecommercePlatformId": 25,

            "deliveryMode": "第三方物流自提",

            "receiver": "Y\*\*\*\*\*\*n",

            "countryCode": "MY",

            "platformID": 25,

            "isPurchase": false,

            "deliveryRegionCode": "ML",

            "comment": "",

            "remark": "",

            "rawData": {

                "shopNumber": "2022033615",

                "shopAccountNumber": "1305883501",

                "shopName": "Shopee-马来本土店-Belle Beauty Salon",

                "shopAddress": "MY",

                "orderAmount": "13.0412",

                "orderCurrency": "MYR",

                "orderCurrencyAmount": null,

                "products": [

                    {

                        "product\_sku": "tqbt1124mj01-通气鼻贴",

                        "price": "13.0412",

                        "quantity": 1,

                        "subtotal": 13.0412

                    }

                ]

            },

            "expressUrl": "https://oss.westmonth.com/mabang/waybill/489023\_2502240SPQC28K10x10ac\_SPXMY057782387132.pdf",

            "shipmentNo": "SPXMY057782387132",

            "logisticsCompany": "SE016",

            "products": {

                "J标zht803zx01-60p1cs/盒": {

                    "productSku": "J标zht8031zx01-60pcs/盒",

                    "quantity": 1

                }

            }

        }

    ]

}
```

#### 请求方式&地址

```
[post] /openapi/order/predate
```

#### 请求参数

| 参数名称               | 参数类型 | 是否必填 | 参数描述                               |
|------------------------|----------|----------|----------------------------------------|
| uID                    | string   | N        | 西月客户uid（指定选品网账号下单）      |
| orders                 | array    | Y        | 订单集合，最多20单                     |
| -\| deliveryRegionCode | string   | Y        | 发货区域编码                           |
| -\| sellerOrderNo      | string   | Y        | 平台订单号                             |
| -\| email              | string   | N        | 邮箱                                   |
| -\| callingCode        | int      | N        | 手机区域号                             |
| -\| comment            | string   | N        | 买家备注                               |
| -\| deliveryMode       | string   | Y        | 模式(快递配送,第三方物流自提,个人自提) |
| -\| telephone          | string   | N        | 手机号(快递配送必填)                   |
| -\| receiver           | string   | N        | 收件人姓名(快递配送必填)               |
| -\| countryCode        | string   | Y        | 国家二字码                             |
| -\| zone               | string   | N        | 省、州名称(快递配送必填)               |
| -\| city               | string   | N        | 城市名称(快递配送必填)                 |
| -\| county             | string   | N        | 县区、乡镇名称(快递配送必填)           |
| -\| address1           | string   | N        | 地址1(快递配送必填)                    |
| -\| address2           | string   | N        | 地址2                                  |
| -\| zipcode            | string   | N        | 邮编(快递配送必填)                     |
| -\| expressUrl         | string   | N        | 面单url(第三方物流自提必填)            |
| -\| logisticsCompany   | string   | N        | 物流公司编码(第三方物流自提必填)       |
| -\| shipmentNo         | string   | N        | 物流单号(第三方物流自提必填)           |
| -\| platformID         | int      | N        | 平台id                                 |
| -\| products           | array    | Y        | 产品数据                               |
| -\|-\| productSku      | string   | Y        | SKU名称                                |
| -\|-\| quantity        | int      | Y        | 数量                                   |
| -\|-\| products        | array    | N        | 商品数组信息                           |
| -\|-\|-\| product_sku  | string   | N        | sku                                    |
| -\|-\|-\| price        | string   | N        | 价格                                   |
| -\|-\|-\| quantity     | int      | N        | 数量                                   |
| -\|-\|-\| subtotal     | string   | N        | 小计                                   |

#### 返回参数

| 参数名称              | 参数类型 | 是否回传 | 参数描述               | 示例值                                             |
|-----------------------|----------|----------|------------------------|----------------------------------------------------|
| code                  | string   | Y        | 错误码                 | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                   | string   | Y        | 错误描述               | 执行成功                                           |
| data                  | array    | Y        | 业务数据               |                                                    |
| -\| normalData        | array    | Y        | 成功数据               |                                                    |
| -\|-\| sellerOrderNo  | string   | Y        | 平台订单号             |                                                    |
| -\|-\| total          | float    | Y        | 商品总计               |                                                    |
| -\|-\| freight        | float    | Y        | 运费                   |                                                    |
| -\|-\| products       | array    | Y        | 订单产品数据           |                                                    |
| -\|-\|-\| productSku  | string   | Y        | 产品SKU                |                                                    |
| -\|-\|-\| price       | float    | Y        | 单价                   |                                                    |
| -\|-\|-\| quantity    | int      | Y        | 商品数量               |                                                    |
| -\|-\|-\| subtotal    | float    | Y        | 小计                   |                                                    |
| -\|-\| regularPrice   | float    | Y        | 订单原价               |                                                    |
| -\|-\| discount       | float    | Y        | 订单使用的折扣         |                                                    |
| -\|-\| discountAmount | float    | Y        | 订单优惠金额           |                                                    |
| -\|-\| aggregate      | float    | Y        | 订单总金额（最终价格） |                                                    |
| -\| abnormalOrderData | array    | Y        | 失败数据               |                                                    |
| -\|-\| sellerOrderNo  | string   | Y        | 平台订单号             |                                                    |
| -\|-\| reason         | string   | Y        | 异常原因               |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "data": {

        "normalData": [

            {

                "sellerOrderNo": "2502240SPQC28K",

                "total": 10.5,

                "freight": 0,

                "products": [

                    {

                        "productSku": "J标zht803zx01-60pcs/盒",

                        "price": 10.5,

                        "quantity": 1,

                        "subtotal": 10.5

                    }

                ],

                "regularPrice": 10.5,

                "discount": 1,

                "discountAmount": 0,

                "aggregate": 10.5,

                "isCircle": 0,

                "deductionImprest": 0,

                "deductionImprestFormat": 0,

                "isWhetherRMB": 0,

                "currencyCode": "CNY"

            }

        ],

         "abnormalOrderData": [

            {

                "sellerOrderNo": "2502240SPQC28K",

                "reason": "sku:J标zht8031zx01-60pcs/盒不存在"

            }

        ]

    },

    "msg": "请求成功"

}
```

### 订单取消接口

#### 接口说明

由平台主动调用该接口进行订单取消，仅在未发货时进行整单取消。

#### 请求方式&地址

```
[post] /openapi/order/cancel
```

#### 请求参数

| 参数名称           | 参数类型 | 是否必填 | 参数描述           |
|--------------------|----------|----------|--------------------|
| orders             | array    | Y        | 订单集合，最多20单 |
| -\| order_no_xy    | string   | Y        | 西月订单号         |
| -\| order_no_outer | string   | Y        | 平台订单号         |

#### 请求参数示例

```
{

    "orders": [

        {

            "order\_no\_xy": "2024092428142",

            "order\_no\_outer": "576722608781890541"

        }

    ]

}
```

#### 返回参数

| 参数名称              | 参数类型 | 是否回传 | 参数描述                     | 示例值                                             |
|-----------------------|----------|----------|------------------------------|----------------------------------------------------|
| code                  | string   | Y        | 错误码                       | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                   | string   | Y        | 错误描述                     | 执行成功                                           |
| data                  | object   | Y        | 业务数据                     |                                                    |
| -\| list              | array    | Y        | 数据结果列表                 |                                                    |
| -\|-\| order_no_xy    | string   | Y        | 西月选品网订单标识           |                                                    |
| -\|-\| order_no_outer | string   | Y        | 平台订单标识                 |                                                    |
| -\|-\| status         | string   | Y        | 取消状态                     | succeed                                            |
| -\|-\| message        | string   | Y        | 取消失败描述，仅在失败时有值 |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": {

        "list": [

            {

                "order\_no\_xy": "2024092428142",

                "order\_no\_outer": "576722608781890541",

                "status": "succeed"

            }

        ]

    }

}
```

### 订单详情接口

#### 请求参数

| 参数名称        | 参数类型 | 是否必填 | 参数描述                           |
|-----------------|----------|----------|------------------------------------|
| order_no        | string   | N        | 订单号（不能和平台订单号同时存在） |
| seller_order_no | string   | N        | 平台订单号（不能和订单号同时存在） |

#### 请求参数示例

```
/openapi/v2/order/detail?order\_no=AXY2411300992570
```

#### 请求方式&地址

```
[get] /openapi/v2/order/detail
```

#### 返回参数

| 参数名称                      | 参数类型 | 是否回传 | 参数描述                                               | 示例值                                             |
|-------------------------------|----------|----------|--------------------------------------------------------|----------------------------------------------------|
| code                          | string   | Y        | 错误码                                                 | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                           | string   | Y        | 错误描述                                               | 执行成功                                           |
| data                          | object   | Y        | 业务数据                                               |                                                    |
| -\| order_id                  | int      | Y        | 订单id                                                 |                                                    |
| -\| order_number              | string   | Y        | 订单号                                                 |                                                    |
| -\| place_order_time          | string   | Y        | 下单时间                                               |                                                    |
| -\| status                    | string   | Y        | 订单状态                                               |                                                    |
| -\| order_status              | string   | Y        | 订单状态枚举值                                         |                                                    |
| -\| customer_name             | string   | Y        | 收件人姓名                                             |                                                    |
| -\| phone                     | string   | Y        | 收件人电话                                             |                                                    |
| -\| address                   | string   | Y        | 收件人地址                                             |                                                    |
| -\| mode_payment              | string   | Y        | 支付方式                                               |                                                    |
| -\| pay_time                  | string   | Y        | 支付时间                                               |                                                    |
| -\| postcode                  | string   | Y        | 配送地址邮编                                           |                                                    |
| -\| entrepot                  | array    | Y        | 仓库                                                   |                                                    |
| -\|-\| address                | string   | Y        | 地址                                                   |                                                    |
| -\|-\| contact_name           | string   | Y        | 联系人                                                 |                                                    |
| -\|-\| contact_phone          | string   | Y        | 联系电话                                               |                                                    |
| -\|-\| postcode               | string   | Y        | 邮编                                                   |                                                    |
| -\| order_products            | array    | Y        | 商品列表                                               |                                                    |
| -\|-\| product_sku            | string   | Y        | 商品sku                                                |                                                    |
| -\|-\| name                   | string   | Y        | 商品名称                                               |                                                    |
| -\|-\| image                  | string   | Y        | 商品图片                                               |                                                    |
| -\|-\| price                  | string   | Y        | 单价                                                   |                                                    |
| -\|-\| quantity               | int      | Y        | 购买数量                                               |                                                    |
| -\|-\| subtotal               | string   | Y        | 小计                                                   |                                                    |
| -\| amount_payable            | string   | Y        | 订单总金额                                             |                                                    |
| -\| trade_platforms           | int      | Y        | 支付平台，1：微信，2：支付宝，3：额度支付，4：货到付款 |                                                    |
| -\| sub_total                 | string   | Y        | 商品总计                                               |                                                    |
| -\| freight                   | string   | Y        | 运费                                                   |                                                    |
| -\| comment                   | string   | Y        | 评论                                                   |                                                    |
| -\| delivery_mode_name        | string   | Y        | 提货方(快递配送 第三方物流自提 个人自提)               |                                                    |
| -\| name_supplier             | string   | Y        | 提货人姓名                                             |                                                    |
| -\| contact_number            | string   | Y        | 联系电话                                               |                                                    |
| -\| seller_order_no           | string   | Y        | 卖家订单号                                             |                                                    |
| -\| platform_name             | string   | Y        | 平台名称                                               |                                                    |
| -\| shipment_no               | string   | Y        | 第三方物流单号                                         |                                                    |
| -\| logistics_company_name    | string   | Y        | 物流公司中文名称                                       |                                                    |
| -\| logistics_company_name_en | string   | Y        | 物流公司英文名称                                       |                                                    |
| -\| logistics_company_code    | string   | Y        | 物流公司编码                                           |                                                    |

#### 返回参数示例

## 

### 订单详情列表接口

#### 请求参数

| 参数名称         | 参数类型 | 是否必填 | 参数描述                                          |
|------------------|----------|----------|---------------------------------------------------|
| order_nos        | array    | N        | 订单集合，最多100单（不能和平台订单集合同时存在） |
| seller_order_nos | array    | N        | 平台订单集合，最多100单（不能和订单集合同时存在） |

#### 请求参数示例

#### 请求方式&地址

#### 返回参数

| 参数名称                      | 参数类型 | 是否回传 | 参数描述                                               | 示例值                                             |
|-------------------------------|----------|----------|--------------------------------------------------------|----------------------------------------------------|
| code                          | string   | Y        | 错误码                                                 | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                           | string   | Y        | 错误描述                                               | 执行成功                                           |
| data                          | object   | Y        | 业务数据                                               |                                                    |
| -\| order_id                  | int      | Y        | 订单id                                                 |                                                    |
| -\| order_number              | string   | Y        | 订单号                                                 |                                                    |
| -\| place_order_time          | string   | Y        | 下单时间                                               |                                                    |
| -\| status                    | string   | Y        | 订单状态                                               |                                                    |
| -\| order_status              | string   | Y        | 订单状态枚举值                                         |                                                    |
| -\| customer_name             | string   | Y        | 收件人姓名                                             |                                                    |
| -\| phone                     | string   | Y        | 收件人电话                                             |                                                    |
| -\| address                   | string   | Y        | 收件人地址                                             |                                                    |
| -\| mode_payment              | string   | Y        | 支付方式                                               |                                                    |
| -\| pay_time                  | string   | Y        | 支付时间                                               |                                                    |
| -\| postcode                  | string   | Y        | 配送地址邮编                                           |                                                    |
| -\| entrepot                  | array    | Y        | 仓库                                                   |                                                    |
| -\|-\| address                | string   | Y        | 地址                                                   |                                                    |
| -\|-\| contact_name           | string   | Y        | 联系人                                                 |                                                    |
| -\|-\| contact_phone          | string   | Y        | 联系电话                                               |                                                    |
| -\|-\| postcode               | string   | Y        | 邮编                                                   |                                                    |
| -\| order_products            | array    | Y        | 商品列表                                               |                                                    |
| -\|-\| product_sku            | string   | Y        | 商品sku                                                |                                                    |
| -\|-\| name                   | string   | Y        | 商品名称                                               |                                                    |
| -\|-\| image                  | string   | Y        | 商品图片                                               |                                                    |
| -\|-\| price                  | string   | Y        | 单价                                                   |                                                    |
| -\|-\| quantity               | int      | Y        | 购买数量                                               |                                                    |
| -\|-\| subtotal               | string   | Y        | 小计                                                   |                                                    |
| -\| amount_payable            | string   | Y        | 订单总金额                                             |                                                    |
| -\| trade_platforms           | int      | Y        | 支付平台，1：微信，2：支付宝，3：额度支付，4：货到付款 |                                                    |
| -\| sub_total                 | string   | Y        | 商品总计                                               |                                                    |
| -\| freight                   | string   | Y        | 运费                                                   |                                                    |
| -\| comment                   | string   | Y        | 评论                                                   |                                                    |
| -\| delivery_mode_name        | string   | Y        | 提货方(快递配送 第三方物流自提 个人自提)               |                                                    |
| -\| name_supplier             | string   | Y        | 提货人姓名                                             |                                                    |
| -\| contact_number            | string   | Y        | 联系电话                                               |                                                    |
| -\| seller_order_no           | string   | Y        | 卖家订单号                                             |                                                    |
| -\| platform_name             | string   | Y        | 平台名称                                               |                                                    |
| -\| shipment_no               | string   | Y        | 第三方物流单号                                         |                                                    |
| -\| logistics_company_name    | string   | Y        | 物流公司名称                                           |                                                    |
| -\| logistics_company_name_en | string   | Y        | 物流公司英文名称                                       |                                                    |
| -\| logistics_company_code    | string   | Y        | 物流公司编码                                           |                                                    |

#### 返回参数示例

### 订单状态接口

#### 请求参数

| 参数名称      | 参数类型 | 是否必填 | 参数描述          |
|---------------|----------|----------|-------------------|
| order_numbers | array    | N        | 订单号列表        |
| type          | int      | Y        | 订单类型，固定传4 |
| start_time    | string   | N        | 更新时间开始      |
| end_time      | string   | N        | 更新时间结束      |
| page          | int      | N        | 当前页数，默认1   |
| per_page      | int      | N        | 每页条数，默认10  |

#### 请求参数示例

#### 请求方式&地址

#### 返回参数

| 参数名称                   | 参数类型 | 是否回传 | 参数描述                                                 | 示例值                                             |
|----------------------------|----------|----------|----------------------------------------------------------|----------------------------------------------------|
| code                       | string   | Y        | 错误码                                                   | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                        | string   | Y        | 错误描述                                                 | 执行成功                                           |
| data                       | object   | Y        | 业务数据                                                 |                                                    |
| -\| id                     | int      | Y        | 订单id                                                   |                                                    |
| -\| type                   | int      | Y        | 订单类型                                                 |                                                    |
| -\| number                 | string   | Y        | 订单号                                                   |                                                    |
| -\| customer_id            | int      | Y        | 客户ID                                                   |                                                    |
| -\| status                 | string   | Y        | 订单状态                                                 |                                                    |
| -\| total                  | string   | Y        | 总金额                                                   |                                                    |
| -\| total_original         | string   | Y        | 原价                                                     |                                                    |
| -\| pay_amount             | string   | Y        | 实付金额                                                 |                                                    |
| -\| pay_status             | int      | Y        | 支付状态，0:待支付，1:支付成功，2:支付失败               |                                                    |
| -\| pay_time               | string   | Y        | 支付时间                                                 |                                                    |
| -\| currency_code          | string   | Y        | 当前货币                                                 |                                                    |
| -\| currency_value         | string   | Y        | 当前汇率                                                 |                                                    |
| -\| trade_platforms        | int      | Y        | 支付平台，1：微信，2：支付宝，3：额度支付，4：货到付款   |                                                    |
| -\| o_id                   | int      | Y        | 聚水潭ERP订单界面-内部单号                               |                                                    |
| -\| push_status            | int      | Y        | 聚水潭上传状态。0:未上传，1:成功，2:失败                 |                                                    |
| -\| outbound_status        | int      | Y        | 推送海外仓出库单状态；0未上传；1为成功；2为失败；3为作废 |                                                    |
| -\| shipping_method_code   | string   | Y        | 配送方式编码                                             |                                                    |
| -\| shipping_method_name   | string   | Y        | 配送方式名称                                             |                                                    |
| -\| delivery_region_id     | int      | Y        | 区域条目ID                                               |                                                    |
| -\| delivery_mode          | int      | Y        | 配送模式，0快递配送 1第三方物流自提 2个人自提            |                                                    |
| -\| seller_order_no        | string   | Y        | 卖家订单号                                               |                                                    |
| -\| status_format          | string   | Y        | 订单状态                                                 |                                                    |
| -\| total_format           | string   | Y        | 总金额                                                   |                                                    |
| -\| total_original_format  | string   | Y        | 原价                                                     |                                                    |
| -\| outbound_status_format | string   | Y        | 推送海外仓出库单状态                                     |                                                    |
| -\| push_status_format     | string   | Y        | 聚水潭上传状态                                           |                                                    |
| -\| trade_platforms_name   | string   | Y        | 支付平台                                                 |                                                    |
| -\| type_format            | string   | Y        | 订单类型                                                 |                                                    |
| -\| freight                | string   | Y        | 运费                                                     |                                                    |
| -\| delivery_mode_name     | string   | Y        | 配送模式                                                 |                                                    |
| -\| product_total          | string   | Y        | 商品总价                                                 |                                                    |
| -\| subarea_name           | string   | Y        | 地区名                                                   |                                                    |
| -\| shipment_no            | string   | Y        | 第三方物流单号                                           |                                                    |
| -\| logistics_company_id   | int      | Y        | 第三方物流公司ID                                         |                                                    |
| -\| logistics_company_name | string   | Y        | 第三方物流公司名称                                       |                                                    |
| -\| logistics_company_code | string   | Y        | 第三方物流公司编码                                       |                                                    |
| -\| skus                   | array    | Y        | 产品sku列表                                              |                                                    |
| -\|-\| product_sku         | string   | Y        | sku                                                      |                                                    |
| -\|-\| depot_id            | int      | Y        | 仓库ID                                                   |                                                    |
| -\|-\| depost_name         | string   | Y        | 仓库名称                                                 |                                                    |
| -\|-\| delivery_region_id  | int      | Y        | 区域条目ID                                               |                                                    |
| -\|-\| region_name         | string   | Y        | 发货区域名称                                             |                                                    |
| -\|-\| price_format        | string   | Y        | 单价                                                     |                                                    |
| -\|-\| total_price_format  | string   | Y        | 小计                                                     |                                                    |
| -\|-\| subtotal_format     | string   | Y        | 小计                                                     |                                                    |

#### 返回参数示例

### 订单支付状态接口

#### 请求参数

| 参数名称    | 参数类型 | 是否必填 | 参数描述 |
|-------------|----------|----------|----------|
| orderNumber | string   | Y        | 订单号   |

#### 请求参数示例

#### 请求方式&地址

#### 返回参数

| 参数名称       | 参数类型 | 是否回传 | 参数描述                                   | 示例值                                             |
|----------------|----------|----------|--------------------------------------------|----------------------------------------------------|
| code           | string   | Y        | 错误码                                     | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg            | string   | Y        | 错误描述                                   | 执行成功                                           |
| data           | object   | Y        | 业务数据                                   |                                                    |
| -\| number     | string   | Y        | 订单号                                     |                                                    |
| -\| status     | sring    | Y        | 订单状态                                   |                                                    |
| -\| pay_status | int      | Y        | 支付状态，0:待支付，1:支付成功，2:支付失败 |                                                    |

#### 返回参数示例

### 订单物流轨迹接口

#### 请求参数

| 参数名称        | 参数类型 | 是否必填 | 参数描述        |
|-----------------|----------|----------|-----------------|
| order_nos       | array    | N        | 订单号集合      |
| express_numbers | array    | N        | 物流单号集合    |
| page            | int      | N        | 页数,默认1      |
| size            | int      | N        | 每页条数,默认20 |

#### 请求参数示例

#### 请求方式&地址

#### 返回参数

| 参数名称                 | 参数类型 | 是否回传 | 参数描述     | 示例值                                             |
|--------------------------|----------|----------|--------------|----------------------------------------------------|
| code                     | string   | Y        | 错误码       | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                      | string   | Y        | 错误描述     | 执行成功                                           |
| data                     | object   | Y        | 业务数据     |                                                    |
| -\| list                 | array    | Y        | 订单列表     |                                                    |
| -\|-\| number            | sring    | Y        | 订单号码     |                                                    |
| -\|-\| type              | string   | Y        | 物流类型     |                                                    |
| -\|-\| logistics_company | string   | Y        | 物流公司名称 |                                                    |
| -\|-\| shipment_no       | string   | Y        | 物流单号     |                                                    |
| -\|-\| events            | array    | Y        | 物流信息     |                                                    |
| -\|-\|-\| time_raw       | string   | Y        | 物流时间     |                                                    |
| -\|-\|-\| description    | string   | Y        | 物流描述     |                                                    |
| -\| total                | int      | Y        | 条数         |                                                    |

#### 返回参数示例

### 订单列表查询接口

#### 请求参数

| 参数名称   | 参数类型 | 是否必填 | 参数描述                                   |
|------------|----------|----------|--------------------------------------------|
| status     | string   | N        | 订单状态，可查看订单状态枚举值，例：unpaid |
| pay_status | int      | N        | 支付状态，0:待支付，1:支付成功，2:支付失败 |
| created_at | array    | N        | 下单时间，包含开始时间，结束时间           |
| page       | int      | N        | 页码,默认1                                 |
| size       | int      | N        | 每页条数,默认20                            |

#### 请求参数示例

#### 请求方式&地址

#### 返回参数

| 参数名称                       | 参数类型 | 是否回传 | 参数描述                                               | 示例值                                             |
|--------------------------------|----------|----------|--------------------------------------------------------|----------------------------------------------------|
| code                           | string   | Y        | 错误码                                                 | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                            | string   | Y        | 错误描述                                               | 执行成功                                           |
| data                           | object   | Y        | 业务数据                                               |                                                    |
| -\| data                       | array    | Y        | 订单列表                                               |                                                    |
| -\|-\| order_id                | int      | Y        | 订单id                                                 |                                                    |
| -\|-\| number                  | string   | Y        | 订单号                                                 |                                                    |
| -\|-\| status                  | string   | Y        | 订单状态                                               |                                                    |
| -\|-\| quantity                | int      | Y        | 订单商品购买数量                                       |                                                    |
| -\|-\| total                   | string   | Y        | 总金额                                                 |                                                    |
| -\|-\| total_original          | string   | Y        | 原价                                                   |                                                    |
| -\|-\| total_format            | srting   | Y        | 总金额（带货币符号）                                   |                                                    |
| -\|-\| total_original_format   | string   | Y        | 原价（带货币符号）                                     |                                                    |
| -\|-\| currency_code           | string   | Y        | 当前货币                                               |                                                    |
| -\|-\| currency_value          | string   | Y        | 当前汇率                                               |                                                    |
| -\|-\| pay_status              | int      | Y        | 支付状态，0:待支付，1:支付成功，2:支付失败             |                                                    |
| -\|-\| created_at              | string   | Y        | 下单时间                                               |                                                    |
| -\|-\| updated_at              | string   | Y        | 更新时间                                               |                                                    |
| -\|-\| delivery_region_id      | int      | Y        | 商品发货区域id                                         |                                                    |
| -\|-\| delivery_region_name    | string   | Y        | 商品发货区域名称                                       |                                                    |
| -\|-\| trade_platforms         | int      | Y        | 支付平台，1：微信，2：支付宝，3：额度支付，4：货到付款 |                                                    |
| -\|-\| products                | array    | Y        | 商品列表                                               |                                                    |
| -\|-\|-\| product_id           | int      | Y        | 商品id                                                 |                                                    |
| -\|-\|-\| image                | string   | Y        | 商品图片                                               |                                                    |
| -\|-\|-\| name                 | string   | Y        | 商品名称                                               |                                                    |
| -\|-\|-\| quantity             | int      | Y        | 商品数量                                               |                                                    |
| -\|-\|-\| price                | string   | Y        | 商品价格                                               |                                                    |
| -\|-\|-\| price_format         | string   | Y        | 商品价格（带货币符号）                                 |                                                    |
| -\|-\|-\| subtotal             | string   | Y        | 商品总计价格                                           |                                                    |
| -\|-\|-\| subtotal_format      | string   | Y        | 商品总计价格（带货币符号）                             |                                                    |
| -\|-\|-\| product_sku          | string   | Y        | 商品sku                                                |                                                    |
| -\|-\|-\| delivery_region_id   | int      | Y        | 商品发货区域id                                         |                                                    |
| -\|-\|-\| delivery_region_name | string   | Y        | 商品发货区域名称                                       |                                                    |
| -\|-\| delivery_mode_name      | string   | Y        | 配送模式                                               |                                                    |
| -\|-\| name_supplier           | string   | Y        | 配送地址姓名                                           |                                                    |
| -\|-\| contact_number          | string   | Y        | 配送地址电话号码                                       |                                                    |
| -\|-\| seller_order_no         | string   | Y        | 卖家订单号                                             |                                                    |
| -\|-\| entrepot                | array    | Y        | 仓库列表                                               |                                                    |
| -\|-\|-\| address              | string   | Y        | 地址                                                   |                                                    |
| -\|-\|-\| contact_name         | string   | Y        | 联系人                                                 |                                                    |
| -\|-\|-\| contact_phone        | string   | Y        | 联系电话                                               |                                                    |
| -\|-\|-\| postcode             | string   | Y        | 邮编                                                   |                                                    |
| -\|-\| platform_name           | string   | Y        | 卖家订单平台名称                                       |                                                    |
| -\|-\| detpName                | string   | Y        | 商品仓库名称                                           |                                                    |
| -\|-\| status_format           | string   | Y        | 订单状态                                               |                                                    |
| -\|-\| after_status            | int      | Y        | 售后状态                                               |                                                    |
| -\|-\| pay_trade_no            | string   | Y        | 交易流水号                                             |                                                    |
| -\|-\| shop_relation           | array    | Y        | 电商平台信息                                           |                                                    |
| -\|-\| ring_stock_id           | int      | Y        | 圈货单id                                               |                                                    |
| -\|-\| ring_stock_type         | int      | Y        | 圈货类型 0:圈货 1:备货                                 |                                                    |
| -\|-\| ring_stock_type_name    | string   | Y        | 圈货类型名称                                           |                                                    |
| -\|-\| shop_name               | string   | Y        | 店铺名称                                               |                                                    |
| -\| total                      | int      | Y        | 总条数                                                 |                                                    |
| -\| page                       | int      | Y        | 页码                                                   |                                                    |
| -\| size                       | int      | Y        | 每页条数                                               |                                                    |
| -\| numPage                    | int      | Y        | 最后一页页码                                           |                                                    |

#### 返回参数示例

## 库存

### 可用库存查询

#### 请求方式&地址

```
[post] /openapi/v2/inventory/query
```

#### 请求参数

| 参数名称  | 参数类型 | 是否必填 | 参数描述                             |
|-----------|----------|----------|--------------------------------------|
| page      | int      | N        | 页码，不填默认1                      |
| size      | int      | N        | 每页显示数量，默认50，最大100        |
| skus      | array    | N        | SKU列表，不超过20个，传sku时不做分页 |
| depot_ids | array    | N        | 仓库id列表，不超过50个               |

#### 请求参数示例

```
{

    "skus": ["WEA03-A051-30-WH1","WEA02-A039-10-WH1"],

    "page": 1,

    "size": 50,

    "depot\_ids":[2,5]

}
```

#### 返回参数

| 参数名称                     | 参数类型 | 是否 回传 | 参数描述     | 示例值                                             |
|------------------------------|----------|-----------|--------------|----------------------------------------------------|
| code                         | string   | Y         | 错误码       | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                          | string   | Y         | 错误描述     | 执行成功                                           |
| data                         | object   | Y         | 业务数据     |                                                    |
| -\| page                     | integer  | Y         | 页码         | 1                                                  |
| -\| size                     | integer  | Y         | 每页显示数量 | 30                                                 |
| -\| data_count               | integer  | Y         | 总数量       | 1000                                               |
| -\| page_count               | integer  | Y         | 总页数       | 34                                                 |
| -\| has_next                 | boolean  | Y         | 是否有下一页 | true                                               |
| -\| list                     | array    | Y         | 数据结果列表 |                                                    |
| -\|-\| sku                   | string   | Y         | SKU编码      | SKU-001                                            |
| -\|-\| name                  | string   | Y         | SKU商品名称  | ProductName                                        |
| -\|-\| region_list           | array    | Y         | 区域列表     |                                                    |
| -\|-\|-\| region_name        | string   | Y         | 区域名称     | 泰国                                               |
| -\|-\|-\| region_code        | string   | Y         | 区域国家码   |                                                    |
| -\|-\|-\| delivery_region_id | string   | Y         | 发货区域ID   | 3                                                  |
| -\|-\|-\| depots             | array    | Y         | 仓库列表     |                                                    |
| -\|-\|-\|-\| quantity        | int      | Y         | 库存         | 121                                                |
| -\|-\|-\|-\| depot_id        | int      | Y         | 仓库id       | 2                                                  |
| -\|-\|-\|-\| depot_name      | string   | Y         | 仓库名称     | 泰国1仓                                            |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": {

        "page": 1,

        "size": 1,

        "data\_count": 12674,

        "page\_count": 1,

        "has\_next": true,

        "list": [

            {

                "sku": "EEA02-A144-30-WH1",

                "name": "EELHOE 抗皱面部精华 淡斑补水抗皱防衰老精华",

                "region\_list": [

                    {

                        "region\_name": "泰国",

                        "delivery\_region\_id": 2,

                        "region\_code": "TG",

                        "depots": [

                            {

                                "quantity": 278,

                                "depot\_id": 2,

                                "depot\_name": "泰国1仓"

                            }

                        ]

                    },

                    {

                        "region\_name": "中国",

                        "delivery\_region\_id": 5,

                        "region\_code": "CN",

                        "depots": [

                            {

                                "quantity": 0,

                                "depot\_id": 5,

                                "depot\_name": "中国仓"

                            }

                        ]

                    }

                ]

            }

        ]

    }

}
```

### 圈货、备货库存查询

#### 请求方式&地址

```
[post] /openapi/v2/CircleGoods/query
```

#### 请求参数

| 参数名称           | 参数类型 | 是否必填 | 参数描述                                |
|--------------------|----------|----------|-----------------------------------------|
| skus               | array    | N        | SKU列表，不超过20个（不传默认查询全部） |
| deliveryRegionCode | string   | N        | 发货区域编码，SKU列表传时该字段必传     |
| uID                | string   | N        | 用户ID（需要找西之月开通！）            |

#### 请求参数示例

```
{

    "skus":[

        "HO-A05-0353-01"

    ],

    "deliveryRegionCode":"us"

}
```

#### 返回参数

| 参数名称           | 参数类型 | 是否 回传 | 参数描述           | 示例值                                             |
|--------------------|----------|-----------|--------------------|----------------------------------------------------|
| code               | string   | Y         | 错误码             | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                | string   | Y         | 错误描述           | 执行成功                                           |
| data               | object   | Y         | 业务数据           |                                                    |
| -\| typeName       | string   | Y         | 类型（圈货、备货） | 34                                                 |
| -\| stockBatch     | string   | Y         | 圈货、备货批次     | true                                               |
| -\| deliveryMode   | string   | Y         | 服务类型           |                                                    |
| -\| sku            | string   | Y         | SKU                |                                                    |
| -\| depotName      | string   | Y         | 库存名称           |                                                    |
| -\| qty            | int      | Y         | 可用库存           |                                                    |
| -\| expirationTime | string   | Y         | 到期时间           |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": [

        {

            "typeName": "圈货",

            "stockBatch": "BA20241126234541541",

            "deliveryMode": "包邮",

            "sku": "HO-A05-0353-01",

            "depotName": "美国1仓",

            "qty": 992,

            "expirationTime": "2025-01-12 20:02:14"

        }

    ]

}
```

### SKU库存查询

#### 请求方式&地址

```
[post] /openapi/v2/inventory/skuInventory
```

#### 请求参数

| 参数名称     | 参数类型 | 是否必填 | 参数描述            |
|--------------|----------|----------|---------------------|
| skus         | array    | Y        | SKU列表，不超过20个 |
| depot_ids    | array    | N        | 仓库id列表          |
| region_codes | array    | N        | 发货国家code列表    |

#### 请求参数示例

```
{

	"skus": ["yfy217yx01-120ml"],

	"depot\_ids": [],

	"region\_codes": ["CN","TG"]

}
```

#### 返回参数

| 参数名称                       | 参数类型 | 是否 回传 | 参数描述                           | 示例值                                             |
|--------------------------------|----------|-----------|------------------------------------|----------------------------------------------------|
| code                           | string   | Y         | 错误码                             | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                            | string   | Y         | 错误描述                           | 执行成功                                           |
| data                           | object   | Y         | 业务数据                           |                                                    |
| -\| sku                        | string   | Y         | SKU编码                            |                                                    |
| -\|-\| id                      | int      | Y         | SKUid                              |                                                    |
| -\|-\| sku                     | string   | Y         | SKU编码                            |                                                    |
| -\|-\| model                   | string   | Y         | 模型                               |                                                    |
| -\|-\| product_id              | int      | Y         | 产品ID                             |                                                    |
| -\|-\| name                    | string   | Y         | 产品名称                           |                                                    |
| -\|-\| inventories             | array    | Y         | 仓库列表（仓库有按发货优先级排序） |                                                    |
| -\|-\|-\| delivery_region_id   | int      | Y         | 发货区域ID                         |                                                    |
| -\|-\|-\| delivery_region_name | string   | Y         | 发货区域名称                       |                                                    |
| -\|-\|-\| depot_name           | string   | Y         | 仓库名称                           |                                                    |
| -\|-\|-\| depot_id             | int      | Y         | 仓库ID                             |                                                    |
| -\|-\|-\| qty                  | int      | Y         | 库存                               |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": {

        "yfy217yx01-120ml": {

            "id": 7869,

            "sku": "yfy217yx01-120ml",

            "model": "120ml",

            "product\_id": 3389,

            "name": "Jaysuing 头发密发液 强韧秀发滋养发根生长浓密头皮按摩营养液",

            "inventories": [

                {

                    "delivery\_region\_id": 2,

                    "delivery\_region\_name": "印尼",

                    "depot\_name": "印尼1仓",

                    "depot\_id": 2,

                    "qty": 146

                },

                {

                    "delivery\_region\_id": 5,

                    "delivery\_region\_name": "印尼",

                    "depot\_name": "印尼1仓",

                    "depot\_id": 5,

                    "qty": 9999

                }

            ],

            "product\_image": "https://westmonth-oss.oss-rg-china-mainland.aliyuncs.com/westmonth/SKUDatum/old/yfy217yx01-120ml/mainpicture/yfy217yx01-120ml-001.jpg",

            "sku\_image": "https://westmonth-oss.oss-rg-china-mainland.aliyuncs.com/westmonth/SKUDatum/old/yfy217yx01-120ml/mainpicture/yfy217yx01-120ml-001.jpg"

        }

    }

}
```

## 支付

### 获取支付方式

#### 请求方式&地址

```
[get] /openapi/pay/list
```

#### 请求参数

无

#### 返回参数

| 参数名称  | 参数类型 | 是否 回传 | 参数描述 | 示例值                                             |
|-----------|----------|-----------|----------|----------------------------------------------------|
| code      | string   | Y         | 错误码   | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg       | string   | Y         | 错误描述 | 执行成功                                           |
| data      | object   | Y         | 业务数据 |                                                    |
| -\| value | int      | Y         |          | 支付类型code                                       |
| -\| name  | string   | Y         |          | 支付类型名称                                       |

#### 返回参数示例

```
{

	"code": 0,

	"msg": "",

	"data": [

		{

			"value": 2,

			"name": "支付宝"

		},

		{

			"value": 3,

			"name": "额度支付"

		}

	]

}
```

### 获取支付订单信息

#### 请求方式&地址

```
[post] /openapi/pay/payInfo
```

#### 请求参数

| 参数名称        | 参数类型 | 是否必填 | 参数描述 |
|-----------------|----------|----------|----------|
| orderNumberList | array    | Y        | 订单编号 |

#### 请求参数示例

```
{

	"orderNumberList": [

		"2024092897758","2024092876363","2024092863904"

	]

}
```

#### 返回参数

| 参数名称             | 参数类型 | 是否 回传 | 参数描述  | 示例值                                             |
|----------------------|----------|-----------|-----------|----------------------------------------------------|
| code                 | string   | Y         | 错误码    | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                  | string   | Y         | 错误描述  | 执行成功                                           |
| data                 | object   | Y         | 业务数据  |                                                    |
| -\| orderCount       | int      | Y         | 订单数    |                                                    |
| -\| productTypeCount | int      | Y         | 商品种类  |                                                    |
| -\| productCount     | int      | Y         | 商品数量  |                                                    |
| -\| totalAmount      | float    | Y         | 应付金额  |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": {

        "orderCount": 2,

        "productTypeCount": 1,

        "productCount": 4,

        "totalAmount": "32.00"

    }

}
```

### 

### 发起支付

#### 请求方式&地址

```
[post] /openapi/pay/pay
```

#### 请求参数

| 参数名称        | 参数类型 | 是否必填 | 参数描述                           |
|-----------------|----------|----------|------------------------------------|
| type            | int      | Y        | 支付类型code                       |
| orderNumberList | array    | Y        | 订单编号                           |
| password        | string   | N        | 支付密码（额度支付或货到付款必填） |

#### 注：支付密码需要RSA公钥加密后BASE64的字符串

```
-----BEGIN PUBLIC KEY-----

MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAtoohrn6S3C8KnlbK0RSc

0OFh7MxO/1KiT0t9cAKq222VfuTE1oOCAl9FUli2OoH3wh5H4hsZe+B29N4757ml

IeuF0RdEK/9d68dYzmiAaSnhOtfGXzbV/CgFeZFQUsOQYUWuX854m171+Tc0Kp6B

kfJ2Lw6r1xnAwaW9Mtm76i95X7aHAY4JNEBl2pWucdzOhaZTJM59iMghR681OSgw

4YS0s6Xe2kqgM+v1vLbrwuXU8VcD9cQhyC9srdGv+iKzVZqPSgM/PHSpv7tWgq3c

N7cMQ1U3o8RNu3/KuW7UBYGUkWSUlg3GOwwl8QxF4QSs/zfX/iSd06AapdFtU07Y

xQIDAQAB

-----END PUBLIC KEY-----
```

#### 请求参数示例

```
{

	"type": 6,

	"orderNumberList": [

		"2024092897758","2024092876363","2024092863904"

	]}
```

#### 返回参数

| 参数名称 | 参数类型 | 是否 回传 | 参数描述 | 示例值                                             |
|----------|----------|-----------|----------|----------------------------------------------------|
| code     | string   | Y         | 错误码   | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg      | string   | Y         | 错误描述 | 执行成功                                           |
| data     | object   | Y         | 业务数据 |                                                    |

#### 返回参数示例

```
// 万里汇

{

	"code": 0,

	"msg": "",

	"data": {

		"actionFormType": "RedirectActionForm",

		"method": "GET",

		"redirectUrl": "https://icashierprod-qk-sim.alipay.com/m/business/cashier/checkout?partnerId=2188140386674455&cashierOrderId=09280160951680d1dfb03b8731eb6247"

	}

}


// 支付宝

{

	"code": 0,

	"msg": "",

	"data": {

		"result": "<form id='alipay\_submit' name='alipay\_submit' action='https://openapi.alipay.com/gateway.do?charset=utf-8' method='POST'><input type='hidden' name='app\_id' value='2021004138650630'/><input type='hidden' name='method' value='alipay.trade.page.pay'/><input type='hidden' name='format' value='JSON'/><input type='hidden' name='return\_url' value='http://a.westmonth.com:8801/shop\_api/alipay/sync'/><input type='hidden' name='charset' value='utf-8'/><input type='hidden' name='sign\_type' value='RSA2'/><input type='hidden' name='timestamp' value='2024-09-28 10:53:38'/><input type='hidden' name='version' value='1.0'/><input type='hidden' name='notify\_url' value='http://a.westmonth.com:8801/shop\_api/alipay/notify'/><input type='hidden' name='app\_cert\_sn' value='65cfe538d303fad822f67f89339ba0d8'/><input type='hidden' name='alipay\_root\_cert\_sn' value='687b59193f3f462dd5336e5abf83c5d8\_02941eef3187dddf3d3b83462e1dfcf6'/><input type='hidden' name='biz\_content' value='{\\"product\_code\\":\\"FAST\_INSTANT\_TRADE\_PAY\\",\\"out\_trade\_no\\":\\"2024092876363\\",\\"total\_amount\\":\\"8.00\\",\\"subject\\":\\"\\\\u897f\\\\u6708\\\\u9009\\\\u54c1\\\\u7f51\\\\u8ba2\\\\u5355\\\\u652f\\\\u4ed8\\"}'/><input type='hidden' name='sign' value='al8bqZTURSOQ6RqWAvUAtxbILB0pRTUTNoRGKzRZ0O5gmbUWghDCR0UbrbNput1Swc+zooJyF5qSwGuY2fZFCc4BfAl67YAXC1F2YMJ2JCaUnz3nXfSpWhyjaiHAy5bjU9Fsgt8n7B9MNHT8sYsIDmcwnfpxC6jjaR2pupSeZQsg8KZ1rtbyHpZ9k/P0njIpUdnlaD6VR5qYNaQaiYxlxkLey4nSYT11VAh6PQ4/TMurUPY+aJ+G1eSVZU1SDIsiCJ6ScwT5lhY2B6RVibyJmye6jabxYspnVd720D1xaD7Xk/wq3BsdmGemH5K+PnEemOL5GFiIuPpFdT1FK5UpgQ=='/><input type='submit' value='ok' style='display:none;'></form><script>document.forms['alipay\_submit'].submit();</script>"

	}

}


// 额度或货到付款

{

    "code": 0,

    "msg": "",

    "data": {

        "total": "￥32.00",

        "remain": "￥99,968.00"

    }

}
```

### 订单支付页面

#### 请求参数

| 参数名称  | 参数类型 | 是否必填 | 参数描述                                                   |
|-----------|----------|----------|------------------------------------------------------------|
| order_nos | string   | Y        | 订单编号，多个逗号连接，例：xxx,xxx                        |
| app_id    | string   | Y        | 客户应用id，需要加密，加密方式：RAS加密+base64编码+url编码 |

#### 注：客户应用id需要RSA公钥加密后BASE64编码后url编码的字符串

#### 请求方式&地址

#### 返回支付页面

![descript](media/a890a621cfad7046bb251d4d90a0b6ba.png)

## 平台

### 平台列表信息

#### 请求方式&地址

```
[get]/openapi/platform/list
```

#### 请求参数

#### 无

| 参数名称  | 参数类型 | 是否 回传 | 参数描述 | 示例值                                             |
|-----------|----------|-----------|----------|----------------------------------------------------|
| code      | string   | Y         | 错误码   | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg       | string   | Y         | 错误描述 | 执行成功                                           |
| data      | array    | Y         | 业务数据 |                                                    |
| -\| id    | int      | Y         | 平台id   |                                                    |
| -\| name  | string   | Y         | 平台名称 |                                                    |
| -\| logo  | string   | Y         | logo     |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": [

        {

            "id": 30,

            "name": "Tokopedia",

            "logo": "https://oss.westmonth.com/catalog/平台Logo/e5b8438b.svg"

        },

        {

            "id": 31,

            "name": "1688",

            "logo": "https://oss.westmonth.com/westmonth/upload/20250110154841998822.jpg"

        },

        {

            "id": 26,

            "name": "shopline",

            "logo": "https://oss.westmonth.com/catalog/172751773323\_288x90.jpg"

        },

        {

            "id": 28,

            "name": "WB",

            "logo": "https://oss.westmonth.com/%2Fcatalog%2Fwestmonth%2F172726519447\_%E5%93%81%E7%89%8C%20WB.png"

        },

        {

            "id": 27,

            "name": "Ozon",

            "logo": "https://oss.westmonth.com/%2Fcatalog%2Fwestmonth%2F172726519452\_%E5%93%81%E7%89%8C%20OZON.png"

        },

        {

            "id": 33,

            "name": "Yandex",

            "logo": "https://oss.westmonth.com/westmonth/upload/Help/20241230153638577709.jpg"

        },

        {

            "id": 19,

            "name": "wish",

            "logo": "https://oss.westmonth.com/catalog/170468601450\_平台爆款wish.png"

        },

        {

            "id": 17,

            "name": "GROUPON",

            "logo": "https://oss.westmonth.com/catalog/170453875640\_平台爆款groupon.png"

        },

        {

            "id": 18,

            "name": "overstock",

            "logo": "https://oss.westmonth.com/catalog/170453878136\_平台爆款overstock.png"

        },

        {

            "id": 15,

            "name": "ebay",

            "logo": "https://oss.westmonth.com/catalog/170453872231\_平台爆款ebay.png"

        },

        {

            "id": 14,

            "name": "Walmart",

            "logo": "https://oss.westmonth.com/catalog/170529930183\_平台爆款walmart.png"

        },

        {

            "id": 20,

            "name": "shopify",

            "logo": "https://oss.westmonth.com/catalog/170468603012\_平台爆款shopify.png"

        },

        {

            "id": 29,

            "name": "Shoplazza",

            "logo": "https://oss.westmonth.com/catalog/平台Logo/172839043422\_店匠科技ico.jpg"

        },

        {

            "id": 25,

            "name": "Shopee",

            "logo": "https://oss.westmonth.com/%2Fcatalog%2F17045373974\_%E5%B9%B3%E5%8F%B0%E7%88%86%E6%AC%BE%20shopee.png"

        },

        {

            "id": 13,

            "name": "Amazon",

            "logo": "https://oss.westmonth.com/%2Fcatalog%2F170453869794\_%E5%B9%B3%E5%8F%B0%E7%88%86%E6%AC%BE%20%E4%BA%9A%E9%A9%AC%E9%80%8A.png"

        },

        {

            "id": 22,

            "name": "Lazada",

            "logo": "https://oss.westmonth.com/catalog/170453862226\_平台爆款lazada.png"

        },

        {

            "id": 16,

            "name": "SHEIN",

            "logo": "https://oss.westmonth.com/catalog/170453873971\_平台爆款shein.png"

        },

        {

            "id": 21,

            "name": "AliExpress",

            "logo": "https://oss.westmonth.com/catalog/170453705361\_平台爆款aliexpress.png"

        },

        {

            "id": 24,

            "name": "TikTok",

            "logo": "https://oss.westmonth.com/catalog/170453741088\_平台爆款tiktok.png"

        },

        {

            "id": 23,

            "name": "TEMU",

            "logo": "https://oss.westmonth.com/catalog/170453860278\_平台爆款temu.png"

        },

        {

            "id": 34,

            "name": "1688国际站",

            "logo": "https://oss.westmonth.com/westmonth/upload/20250110154841227460.jpg"

        }

    ]

}
```
## 平台列表信息-新

### OpenAPI Specification

```yaml
openapi: 3.0.1
info:
  title: ''
  description: ''
  version: 1.0.0
paths:
  /openapi/platform/list:
    get:
      summary: 平台列表信息-新
      deprecated: false
      description: ''
      tags:
        - 开放API/平台
      parameters:
        - name: Content-Type
          in: header
          description: ''
          example: application/json
          schema:
            type: string
        - name: Authorization
          in: header
          description: ''
          example: '{{authorization}}'
          schema:
            type: string
            default: '{{authorization}}'
      responses:
        '200':
          description: ''
          content:
            application/json:
              schema:
                type: object
                properties:
                  code:
                    type: integer
                    description: 错误码（0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限）
                  msg:
                    type: string
                    description: 错误描述
                  data:
                    type: array
                    items:
                      type: object
                      properties:
                        id:
                          type: integer
                          description: 业务数据
                        name:
                          type: string
                          description: 平台名称
                        logo:
                          type: string
                          description: 图标
                      required:
                        - id
                        - name
                        - logo
                      x-apifox-orders:
                        - id
                        - name
                        - logo
                    description: 业务数据
                required:
                  - code
                  - msg
                  - data
                x-apifox-orders:
                  - code
                  - msg
                  - data
              example:
                code: 0
                msg: ''
                data:
                  - id: 26
                    name: shopline
                    logo: >-
                      https://%2Fcatalog%2F170453751177_%E5%B9%B3%E5%8F%B0%E7%88%86%E6%AC%BE%20%E4%BA%9A%E9%A9%AC%E9%80%8A.png
                  - id: 27
                    name: SHOP-FINE
                    logo: https://catalog/平台Logo/平台爆款shein.png
                  - id: 19
                    name: wish
                    logo: https://catalog/170468601450_平台爆款wish.png
                  - id: 17
                    name: GROUPON
                    logo: https://catalog/170453875640_平台爆款groupon.png
                  - id: 18
                    name: overstock
                    logo: https://catalog/170453878136_平台爆款overstock.png
                  - id: 15
                    name: ebay
                    logo: https://catalog/170453872231_平台爆款ebay.png
                  - id: 29
                    name: OZON
                    logo: >-
                      https://%2Fcatalog%2F172724472442_%E4%BC%81%E4%B8%9A%E5%BE%AE%E4%BF%A1%E6%88%AA%E5%9B%BE_17272444757404%20%281%29.png
                  - id: 30
                    name: WB
                    logo: >-
                      https://%2Fcatalog%2F172724538958_%E5%93%81%E7%89%8C%20WB.png
                  - id: 31
                    name: 测试平台
                    logo: https://catalog/平台Logo/平台爆款tiktok.png
                  - id: 33
                    name: Yandex
                    logo: >-
                      https://catalog/173018084683_296d05a1-c52a-4f5e-abf2-0d49d4c0d6b3.png
                  - id: 14
                    name: Walmart
                    logo: https://catalog/170529930183_平台爆款walmart.png
                  - id: 20
                    name: shopify
                    logo: https://catalog/170468603012_平台爆款shopify.png
                  - id: 25
                    name: Shopee
                    logo: https://oss-test/20250221152021229562.jpg
                  - id: 13
                    name: Amazon
                    logo: https://oss-test/20250221152021229562.jpg
                  - id: 22
                    name: Lazada
                    logo: https://oss-test/20241116091148572449.jpg
                  - id: 16
                    name: SHEIN
                    logo: https://catalog/170453873971_平台爆款shein.png
                  - id: 21
                    name: AliExpress
                    logo: https://oss-test/20250221152021229562.jpg
                  - id: 24
                    name: TikTok
                    logo: https://oss-test/20250221152021229562.jpg
                  - id: 23
                    name: TEMU
                    logo: https://oss-test/20250221152021229562.jpg
                  - id: 28
                    name: KFC
                    logo: https://catalog/平台Logo/平台爆款shein.png
                  - id: 32
                    name: '1688'
                    logo: >-
                      https://catalog/173018084683_296d05a1-c52a-4f5e-abf2-0d49d4c0d6b3.png
          headers: {}
          x-apifox-name: 成功
      security: []
      x-apifox-folder: 开放API/平台
      x-apifox-status: released
      x-run-in-apifox: https://app.apifox.com/web/project/6014135/apis/api-276635816-run
components:
  schemas: {}
  securitySchemes: {}
servers:
  - url: https://testing.westmonth.com
    description: 外部-测试环境
security: []

```

## 物流公司

### 物流公司列表信息

#### 请求方式&地址

```
[post] /openapi/logisticsCompany/list
```

#### 请求参数

| 参数名称      | 参数类型 | 是否必填 | 参数描述         |
|---------------|----------|----------|------------------|
| country_codes | array    | N        | 国家二字码       |
| size          | int      | N        | 每页条数，默认20 |
| page          | int      | N        | 页码，默认1      |

#### 请求参数示例

```
{

    "page": 1,

    "size": 10,

    "country\_codes": ["CN"]

}
```

#### 返回参数

| 参数名称            | 参数类型 | 是否 回传 | 参数描述     | 示例值                                             |
|---------------------|----------|-----------|--------------|----------------------------------------------------|
| code                | string   | Y         | 错误码       | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                 | string   | Y         | 错误描述     | 执行成功                                           |
| data                | object   | Y         | 业务数据     |                                                    |
| -\| list            | array    | Y         | 列表         |                                                    |
| -\|-\| name         | string   | Y         | 中文名称     |                                                    |
| -\|-\| name_en      | string   | Y         | 英文名称     |                                                    |
| -\|-\| code         | string   | Y         | 编码         |                                                    |
| -\|-\| country_code | string   | Y         | 国家二字码   |                                                    |
| -\| total           | int      | Y         | 总条数       |                                                    |
| -\| page            | int      | Y         | 页码         |                                                    |
| -\| size            | int      | Y         | 每页条数     |                                                    |
| -\| numPage         | int      | Y         | 最后一页页码 |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": {

        "list": [

            {

                "name": " JX2U",

                "name\_en": " JX2U",

                "code": "JX006",

                "country\_code": "CN"

            },

            {

                "name": "物邮供应链",

                "name\_en": " QIANYU",

                "code": "QI002",

                "country\_code": "CN"

            },

            {

                "name": "139Express",

                "name\_en": "139Express",

                "code": "EX011",

                "country\_code": "CN"

            },

            {

                "name": "17EXP",

                "name\_en": "17EXP",

                "code": "EX006",

                "country\_code": "CN"

            },

            {

                "name": "17FEIA",

                "name\_en": "17FEIA",

                "code": "FE011",

                "country\_code": "CN"

            },

            {

                "name": "一代集团",

                "name\_en": "1ST",

                "code": "ST001",

                "country\_code": "CN"

            },

            {

                "name": "一站到岸",

                "name\_en": "1STOP",

                "code": "ST027",

                "country\_code": "CN"

            },

            {

                "name": "1TONG",

                "name\_en": "1TONG",

                "code": "TO009",

                "country\_code": "CN"

            },

            {

                "name": "二一八国际物流",

                "name\_en": "218 Logistics",

                "code": "LO004",

                "country\_code": "CN"

            },

            {

                "name": "3CLIQUES",

                "name\_en": "3CLIQUES",

                "code": "CL002",

                "country\_code": "CN"

            }

        ],

        "total": 2783,

        "page": 1,

        "size": 10,

        "numPage": 279

    }

}
```

## 仓库

### 仓库列表信息

#### 请求方式&地址

```
[post] /openapi/depot/list
```

#### 请求参数

| 参数名称      | 参数类型 | 是否必填 | 参数描述                                              |
|---------------|----------|----------|-------------------------------------------------------|
| country_codes | array    | N        | 国家二字码                                            |
| pattern_types | array    | N        | 发货模式：0：快递配送，1：第三方物流自提，2：个人自提 |

#### 请求参数示例

```
{

    "country\_codes": ["US"],

    "pattern\_types": []

}
```

#### 返回参数

| 参数名称                 | 参数类型 | 是否 回传 | 参数描述                                              | 示例值                                             |
|--------------------------|----------|-----------|-------------------------------------------------------|----------------------------------------------------|
| code                     | string   | Y         | 错误码                                                | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                      | string   | Y         | 错误描述                                              | 执行成功                                           |
| data                     | array    | Y         | 业务数据                                              |                                                    |
| -\| id                   | int      | Y         | 仓库id                                                |                                                    |
| -\| name                 | string   | Y         | 仓库代号                                              |                                                    |
| -\| depost_name          | string   | Y         | 仓库名称                                              |                                                    |
| -\| delivery_region_id   | int      | Y         | 发货区域id                                            |                                                    |
| -\| delivery_region_name | string   | Y         | 发货区域名称                                          |                                                    |
| -\| sort                 | int      | Y         | 权重                                                  |                                                    |
| -\| pattern_type         | array    | Y         | 发货模式：0：快递配送，1：第三方物流自提，2：个人自提 |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": [

        {

            "id": 3,

            "name": "美国1仓",

            "depost\_name": "美国万邑通仓",

            "delivery\_region\_id": 3,

            "delivery\_region\_name": "美国",

            "wms\_co\_id": 13625047,

            "wms\_server\_id": 1,

            "sort": 1,

            "pattern\_type": [

                "0",

                "1",

                "2"

            ]

        },

        {

            "id": 11,

            "name": "美国2仓",

            "depost\_name": "美国万邑通美西仓",

            "delivery\_region\_id": 3,

            "delivery\_region\_name": "美国",

            "wms\_co\_id": 14194305,

            "wms\_server\_id": 1,

            "sort": 2,

            "pattern\_type": [

                "0",

                "1",

                "2"

            ]

        },

        {

            "id": 13,

            "name": "美国3仓",

            "depost\_name": "美国直新",

            "delivery\_region\_id": 3,

            "delivery\_region\_name": "美国",

            "wms\_co\_id": 13799844,

            "wms\_server\_id": 8,

            "sort": 3,

            "pattern\_type": [

                "0",

                "1"

            ]

        },

        {

            "id": 7,

            "name": "美国4仓",

            "depost\_name": "美国新势力仓",

            "delivery\_region\_id": 3,

            "delivery\_region\_name": "美国",

            "wms\_co\_id": 13839184,

            "wms\_server\_id": 5,

            "sort": 4,

            "pattern\_type": [

                "0",

                "1"

            ]

        },

        {

            "id": 24,

            "name": "美国6仓",

            "depost\_name": "美国-阔仓美西仓",

            "delivery\_region\_id": 3,

            "delivery\_region\_name": "美国",

            "wms\_co\_id": 14236683,

            "wms\_server\_id": 8,

            "sort": 5,

            "pattern\_type": []

        },

        {

            "id": 17,

            "name": "美国12仓",

            "depost\_name": "美国-京东仓",

            "delivery\_region\_id": 3,

            "delivery\_region\_name": "美国",

            "wms\_co\_id": 14198179,

            "wms\_server\_id": 9,

            "sort": 6,

            "pattern\_type": [

                "0",

                "1"

            ]

        },

        {

            "id": 31,

            "name": "美国13仓",

            "depost\_name": "美国-易达云7仓",

            "delivery\_region\_id": 3,

            "delivery\_region\_name": "美国",

            "wms\_co\_id": 14382105,

            "wms\_server\_id": 0,

            "sort": 7,

            "pattern\_type": []

        },

        {

            "id": 20,

            "name": "美国14仓",

            "depost\_name": "美国-易达云8仓",

            "delivery\_region\_id": 3,

            "delivery\_region\_name": "美国",

            "wms\_co\_id": 14197035,

            "wms\_server\_id": 11,

            "sort": 8,

            "pattern\_type": [

                "0",

                "1"

            ]

        },

        {

            "id": 27,

            "name": "美国8仓",

            "depost\_name": "美国休斯顿自建仓",

            "delivery\_region\_id": 3,

            "delivery\_region\_name": "美国",

            "wms\_co\_id": 14251327,

            "wms\_server\_id": 10,

            "sort": 10,

            "pattern\_type": []

        },

        {

            "id": 34,

            "name": "美国15仓",

            "depost\_name": "美国-洛杉矶仓",

            "delivery\_region\_id": 3,

            "delivery\_region\_name": "美国",

            "wms\_co\_id": 14653464,

            "wms\_server\_id": 2,

            "sort": 11,

            "pattern\_type": []

        }

    ]

}
```

# 发货区域列表

## OpenAPI Specification

```yaml
openapi: 3.0.1
info:
  title: ''
  description: ''
  version: 1.0.0
paths:
  /openapi/productCategory/getDeliveryRegions:
    get:
      summary: 发货区域列表
      deprecated: false
      description: ''
      tags:
        - 开放API/仓库
      parameters:
        - name: code
          in: query
          description: 国家二字码（非必填）
          required: false
          example: cn
          schema:
            type: string
        - name: Content-Type
          in: header
          description: ''
          example: application/json
          schema:
            type: string
        - name: Authorization
          in: header
          description: ''
          example: '{{authorization}}'
          schema:
            type: string
            default: '{{authorization}}'
      responses:
        '200':
          description: ''
          content:
            application/json:
              schema:
                type: object
                properties:
                  status:
                    type: string
                    description: 状态。success：成功
                  message:
                    type: string
                  data:
                    type: array
                    items:
                      type: object
                      properties:
                        id:
                          type: integer
                          description: 区域ID
                        name:
                          type: string
                          description: 区域名称
                        code:
                          type: string
                          description: 区域编码
                        logo:
                          type: string
                          description: 区域LOGO
                      required:
                        - id
                        - name
                        - code
                        - logo
                      x-apifox-orders:
                        - id
                        - name
                        - code
                        - logo
                    description: |
                      业务数据
                required:
                  - status
                  - message
                  - data
                x-apifox-orders:
                  - status
                  - message
                  - data
              example:
                status: success
                message: success
                data:
                  - id: 5
                    name: 中国
                    code: CN
                    logo: https://catalog/172630185958_中国国旗.png
                  - id: 22
                    name: test_fahuo
                    code: ''
                    logo: https://westmonth/20250326174635356076.jpg
                  - id: 23
                    name: test
                    code: ''
                    logo: https://westmonth/20250403170638318223.png
          headers: {}
          x-apifox-name: 成功
      security: []
      x-apifox-folder: 开放API/仓库
      x-apifox-status: released
      x-run-in-apifox: https://app.apifox.com/web/project/6014135/apis/api-275170469-run
components:
  schemas: {}
  securitySchemes: {}
servers:
  - url: https://testing.westmonth.com
    description: 外部-测试环境
security: []

```

## 品牌授权

### 获取授权平台列表信息

#### 请求方式&地址

```
[post] /openapi/brandAuthorizing/getPlatform
```

#### 请求参数

无

#### 返回参数

| 参数名称             | 参数类型 | 是否 回传 | 参数描述     | 示例值                                             |
|----------------------|----------|-----------|--------------|----------------------------------------------------|
| code                 | string   | Y         | 错误码       | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                  | string   | Y         | 错误描述     | 执行成功                                           |
| data                 | array    | Y         | 业务数据     |                                                    |
| -\| platform_id      | int      | Y         | 平台id       |                                                    |
| -\| platform_name_cn | string   | Y         | 平台中文名称 |                                                    |
| -\| platform_name_en | string   | Y         | 平台英文名称 |                                                    |

#### 返回参数示例

```
{

  "code": 0,

  "msg": "",

  "data": [

    {

      "platform\_id": "1",

      "platform\_name\_cn": "Allegro",

      "platform\_name\_en": "Allegro"

    },

    {

      "platform\_id": "7",

      "platform\_name\_cn": "coupang",

      "platform\_name\_en": "Coupang"

    },

    {

      "platform\_id": "10",

      "platform\_name\_cn": "eBay",

      "platform\_name\_en": "eBay"

    },

    {

      "platform\_id": "11",

      "platform\_name\_cn": "eMAG",

      "platform\_name\_en": "eMAG"

    },

    {

      "platform\_id": "16",

      "platform\_name\_cn": "Fruugo",

      "platform\_name\_en": "Fruugo"

    },

    {

      "platform\_id": "17",

      "platform\_name\_cn": "Fyndiq",

      "platform\_name\_en": "Fyndiq"

    },

    {

      "platform\_id": "21",

      "platform\_name\_cn": "Joom",

      "platform\_name\_en": "Joom"

    },

    {

      "platform\_id": "24",

      "platform\_name\_cn": "Lazada",

      "platform\_name\_en": "Lazada"

    },

    {

      "platform\_id": "28",

      "platform\_name\_cn": "Mercado Libre",

      "platform\_name\_en": "Mercado Libre"

    },

    {

      "platform\_id": "30",

      "platform\_name\_cn": "Miravia",

      "platform\_name\_en": "Miravia"

    },

    {

      "platform\_id": "34",

      "platform\_name\_cn": "ozon",

      "platform\_name\_en": "ozon"

    },

    {

      "platform\_id": "39",

      "platform\_name\_cn": "SHEIN",

      "platform\_name\_en": "SHEIN"

    },

    {

      "platform\_id": "40",

      "platform\_name\_cn": "Shopee",

      "platform\_name\_en": "Shopee"

    },

    {

      "platform\_id": "41",

      "platform\_name\_cn": "shopify",

      "platform\_name\_en": "shopify"

    },

    {

      "platform\_id": "42",

      "platform\_name\_cn": "Temu",

      "platform\_name\_en": "Temu"

    },

    {

      "platform\_id": "44",

      "platform\_name\_cn": "Tiktok",

      "platform\_name\_en": "Tiktok"

    },

    {

      "platform\_id": "47",

      "platform\_name\_cn": "Voghion",

      "platform\_name\_en": "Voghion"

    },

    {

      "platform\_id": "48",

      "platform\_name\_cn": "Walmart",

      "platform\_name\_en": "Walmart"

    },

    {

      "platform\_id": "51",

      "platform\_name\_cn": "阿里巴巴",

      "platform\_name\_en": "Alibaba"

    },

    {

      "platform\_id": "52",

      "platform\_name\_cn": "阿里巴巴国际站",

      "platform\_name\_en": "Alibaba"

    },

    {

      "platform\_id": "56",

      "platform\_name\_cn": "独立站",

      "platform\_name\_en": "standalone website"

    },

    {

      "platform\_id": "57",

      "platform\_name\_cn": "敦煌网",

      "platform\_name\_en": "DHgate"

    },

    {

      "platform\_id": "59",

      "platform\_name\_cn": "拼多多",

      "platform\_name\_en": "Pinduoduo"

    },

    {

      "platform\_id": "63",

      "platform\_name\_cn": "速卖通",

      "platform\_name\_en": "AliExpress"

    },

    {

      "platform\_id": "64",

      "platform\_name\_cn": "淘宝",

      "platform\_name\_en": "Taobao"

    },

    {

      "platform\_id": "68",

      "platform\_name\_cn": "亚马逊",

      "platform\_name\_en": "Amazon"

    },

    {

      "platform\_id": "72",

      "platform\_name\_cn": "Wildberries",

      "platform\_name\_en": "Wildberries"

    },

    {

      "platform\_id": "73",

      "platform\_name\_cn": "NOON",

      "platform\_name\_en": "NOON"

    },

    {

      "platform\_id": "76",

      "platform\_name\_cn": "Zalando",

      "platform\_name\_en": "Zalando"

    },

    {

      "platform\_id": "77",

      "platform\_name\_cn": "环球资源",

      "platform\_name\_en": null

    },

    {

      "platform\_id": "79",

      "platform\_name\_cn": "天猫",

      "platform\_name\_en": "Tmall"

    },

    {

      "platform\_id": "82",

      "platform\_name\_cn": "Yandex",

      "platform\_name\_en": "Yandex"

    },

    {

      "platform\_id": "88",

      "platform\_name\_cn": "PHH Group",

      "platform\_name\_en": "PHH Group"

    }

  ]

}
```

### 获取模板类型列表信息

#### 请求方式&地址

```
[post] /openapi/brandAuthorizing/getPlatform
```

#### 请求参数

| 参数名称    | 参数类型 | 是否必填 | 参数描述 |
|-------------|----------|----------|----------|
| platform_id | int      | Y        | 平台id   |

#### 请求参数示例

```
{

    "platform\_id":1

}
```

#### 返回参数

| 参数名称                   | 参数类型 | 是否 回传 | 参数描述     | 示例值                                             |
|----------------------------|----------|-----------|--------------|----------------------------------------------------|
| code                       | string   | Y         | 错误码       | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                        | string   | Y         | 错误描述     | 执行成功                                           |
| data                       | array    | Y         | 业务数据     |                                                    |
| -\| template_category      | int      | Y         | 模板类型id   |                                                    |
| -\| template_category_name | string   | Y         | 模板类型名称 |                                                    |

#### 返回参数示例

```
{

  "code": 0,

  "msg": "",

  "data": [

    {

      "template\_category": 1,

      "template\_category\_name": "英文通用模板"

    },

    {

      "template\_category": 0,

      "template\_category\_name": "中文通用模板"

    }

  ]

}
```

### 获取授权品牌列表信息

#### 请求方式&地址

```
[post] /openapi/brandAuthorizing/getBrand
```

#### 请求参数

| 参数名称          | 参数类型 | 是否必填 | 参数描述         |
|-------------------|----------|----------|------------------|
| platform_id       | int      | Y        | 平台id           |
| brand_name        | string   | N        | 品牌名称         |
| trademark_number  | string   | N        | 商标号           |
| brand_category_id | int      | N        | 品牌类别id       |
| country_id        | int      | N        | 国家id           |
| page              | int      | N        | 页码，默认1      |
| size              | int      | N        | 每页条数，默认10 |

#### 请求参数示例

```
{

    "brand\_name": "",

    "trademark\_number": "",

    "brand\_category\_id": "",

    "country\_id": "",

    "page": 1,

    "size": 10,

    "total": 0,

    "platform\_id": "1"

}
```

#### 返回参数

| 参数名称                    | 参数类型 | 是否 回传 | 参数描述              | 示例值                                             |
|-----------------------------|----------|-----------|-----------------------|----------------------------------------------------|
| code                        | string   | Y         | 错误码                | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                         | string   | Y         | 错误描述              | 执行成功                                           |
| data                        | object   | Y         | 业务数据              |                                                    |
| -\| brandType               | array    | Y         | 品牌类别列表          |                                                    |
| -\|-\| brand_category_id    | int      | Y         | 品牌类别id            |                                                    |
| -\|-\| brand_category_name  | string   | Y         | 品牌类别名称          |                                                    |
| -\| country                 | array    | Y         | 国家列表              |                                                    |
| -\|-\| country_id           | int      | Y         | 国家id                |                                                    |
| -\|-\| country_name_cn      | string   | Y         | 国家中文名称          |                                                    |
| -\|-\| country_name_en      | string   | Y         | 国家英文名称          |                                                    |
| -\| brands                  | array    | Y         | 品牌列表              |                                                    |
| -\|-\| brand_id             | int      | Y         | 品牌id                |                                                    |
| -\|-\| brand_name           | string   | Y         | 品牌名称              |                                                    |
| -\|-\| country_name_cn      | string   | Y         | 申请国家              |                                                    |
| -\|-\| brand_category_name  | string   | Y         | 类别名称              |                                                    |
| -\|-\| system_service_scope | string   | Y         | 核定使用商品/服务项目 |                                                    |
| -\| pagination              | object   | Y         | 分页信息              |                                                    |
| -\|-\| total                | int      | Y         | 总条数                |                                                    |
| -\|-\| perPage              | int      | Y         | 当前每页条数          |                                                    |
| -\|-\| currentPage          | int      | Y         | 当前页码              |                                                    |
| -\|-\| lastPage             | int      | Y         | 最后一页              |                                                    |

#### 返回参数示例

```
{

  "code": 0,

  "msg": "",

  "data": {

    "template\_category": 1,

    "template\_category\_name": "英文通用模板",

    "brandType": [

      {

        "brand\_category\_id": "0",

        "brand\_category\_name": "1类"

      },

      {

        "brand\_category\_id": "1",

        "brand\_category\_name": "2类"

      },

      {

        "brand\_category\_id": "2",

        "brand\_category\_name": "3类"

      },

      {

        "brand\_category\_id": "3",

        "brand\_category\_name": "4类"

      },

      {

        "brand\_category\_id": "4",

        "brand\_category\_name": "5类"

      },

      {

        "brand\_category\_id": "5",

        "brand\_category\_name": "6类"

      },

      {

        "brand\_category\_id": "6",

        "brand\_category\_name": "7类"

      },

      {

        "brand\_category\_id": "7",

        "brand\_category\_name": "8类"

      },

      {

        "brand\_category\_id": "8",

        "brand\_category\_name": "9类"

      },

      {

        "brand\_category\_id": "9",

        "brand\_category\_name": "10类"

      },

      {

        "brand\_category\_id": "10",

        "brand\_category\_name": "11类"

      },

      {

        "brand\_category\_id": "11",

        "brand\_category\_name": "12类"

      },

      {

        "brand\_category\_id": "12",

        "brand\_category\_name": "13类"

      },

      {

        "brand\_category\_id": "13",

        "brand\_category\_name": "14类"

      },

      {

        "brand\_category\_id": "14",

        "brand\_category\_name": "15类"

      },

      {

        "brand\_category\_id": "15",

        "brand\_category\_name": "16类"

      },

      {

        "brand\_category\_id": "16",

        "brand\_category\_name": "17类"

      },

      {

        "brand\_category\_id": "17",

        "brand\_category\_name": "18类"

      },

      {

        "brand\_category\_id": "18",

        "brand\_category\_name": "19类"

      },

      {

        "brand\_category\_id": "19",

        "brand\_category\_name": "20类"

      },

      {

        "brand\_category\_id": "20",

        "brand\_category\_name": "21类"

      },

      {

        "brand\_category\_id": "21",

        "brand\_category\_name": "22类"

      },

      {

        "brand\_category\_id": "22",

        "brand\_category\_name": "23类"

      },

      {

        "brand\_category\_id": "23",

        "brand\_category\_name": "24类"

      },

      {

        "brand\_category\_id": "24",

        "brand\_category\_name": "25类"

      },

      {

        "brand\_category\_id": "25",

        "brand\_category\_name": "26类"

      },

      {

        "brand\_category\_id": "26",

        "brand\_category\_name": "27类"

      },

      {

        "brand\_category\_id": "27",

        "brand\_category\_name": "28类"

      },

      {

        "brand\_category\_id": "28",

        "brand\_category\_name": "29类"

      },

      {

        "brand\_category\_id": "29",

        "brand\_category\_name": "30类"

      },

      {

        "brand\_category\_id": "30",

        "brand\_category\_name": "31类"

      },

      {

        "brand\_category\_id": "31",

        "brand\_category\_name": "32类"

      },

      {

        "brand\_category\_id": "32",

        "brand\_category\_name": "33类"

      },

      {

        "brand\_category\_id": "33",

        "brand\_category\_name": "34类"

      },

      {

        "brand\_category\_id": "34",

        "brand\_category\_name": "35类"

      },

      {

        "brand\_category\_id": "35",

        "brand\_category\_name": "36类"

      },

      {

        "brand\_category\_id": "36",

        "brand\_category\_name": "37类"

      },

      {

        "brand\_category\_id": "37",

        "brand\_category\_name": "38类"

      },

      {

        "brand\_category\_id": "38",

        "brand\_category\_name": "39类"

      },

      {

        "brand\_category\_id": "39",

        "brand\_category\_name": "3类,5类"

      },

      {

        "brand\_category\_id": "40",

        "brand\_category\_name": "40类"

      },

      {

        "brand\_category\_id": "41",

        "brand\_category\_name": "41类"

      },

      {

        "brand\_category\_id": "42",

        "brand\_category\_name": "42类"

      },

      {

        "brand\_category\_id": "43",

        "brand\_category\_name": "43类"

      },

      {

        "brand\_category\_id": "44",

        "brand\_category\_name": "44类"

      },

      {

        "brand\_category\_id": "45",

        "brand\_category\_name": "45类"

      },

      {

        "brand\_category\_id": "46",

        "brand\_category\_name": "35类，42类"

      }

    ],

    "country": [

      {

        "country\_id": "1",

        "country\_name\_cn": "中国",

        "country\_name\_en": "China"

      },

      {

        "country\_id": "2",

        "country\_name\_cn": "美国",

        "country\_name\_en":"the United\\ States"

      },

      {

        "country\_id": "3",

        "country\_name\_cn": "欧盟",

        "country\_name\_en": "EU"

      },

      {

        "country\_id": "4",

        "country\_name\_cn": "沙特阿拉伯",

        "country\_name\_en": "Saudi Arabia"

      },

      {

        "country\_id": "5",

        "country\_name\_cn": "英国",

        "country\_name\_en": "United Kingdom"

      },

      {

        "country\_id": "6",

        "country\_name\_cn": "越南",

        "country\_name\_en": "Vietnam"

      },

      {

        "country\_id": "7",

        "country\_name\_cn": "泰国",

        "country\_name\_en": "Thailand"

      },

      {

        "country\_id": "8",

        "country\_name\_cn": "马来西亚",

        "country\_name\_en": "Malaysia"

      },

      {

        "country\_id": "9",

        "country\_name\_cn": "菲律宾",

        "country\_name\_en": "the Philippines"

      },

      {

        "country\_id": "10",

        "country\_name\_cn": "新加坡",

        "country\_name\_en": "Singapore"

      },

      {

        "country\_id": "11",

        "country\_name\_cn": "印度尼西亚",

        "country\_name\_en": "Indonesia"

      },

      {

        "country\_id": "12",

        "country\_name\_cn": "巴西",

        "country\_name\_en": "Brazil"

      },

      {

        "country\_id": "13",

        "country\_name\_cn": "墨西哥",

        "country\_name\_en": "Mexico"

      },

      {

        "country\_id": "14",

        "country\_name\_cn": "加拿大",

        "country\_name\_en": "Canada"

      },

      {

        "country\_id": "15",

        "country\_name\_cn": "尼日利亚",

        "country\_name\_en": "Nigeria"

      },

      {

        "country\_id": "16",

        "country\_name\_cn": "中国台湾",

        "country\_name\_en": "Taiwan, China"

      },

      {

        "country\_id": "17",

        "country\_name\_cn": "哥伦比亚",

        "country\_name\_en": "Colombia"

      },

      {

        "country\_id": "18",

        "country\_name\_cn": "智利",

        "country\_name\_en": "Chile"

      },

      {

        "country\_id": "19",

        "country\_name\_cn": "新西兰",

        "country\_name\_en": "New Zealand"

      },

      {

        "country\_id": "20",

        "country\_name\_cn": "老挝",

        "country\_name\_en": "Laos"

      },

      {

        "country\_id": "21",

        "country\_name\_cn": "柬埔寨",

        "country\_name\_en": "Cambodia"

      },

      {

        "country\_id": "22",

        "country\_name\_cn": "俄罗斯",

        "country\_name\_en": "Russia"

      },

      {

        "country\_id": "23",

        "country\_name\_cn": "澳大利亚",

        "country\_name\_en": "Australia"

      },

      {

        "country\_id": "24",

        "country\_name\_cn": "阿根廷",

        "country\_name\_en": "Argentina"

      },

      {

        "country\_id": "32",

        "country\_name\_cn": "西班牙",

        "country\_name\_en": "Spain"

      },

      {

        "country\_id": "33",

        "country\_name\_cn": "德国",

        "country\_name\_en": "Germany"

      },

      {

        "country\_id": "34",

        "country\_name\_cn": "东南亚",

        "country\_name\_en": "Southeast Asia"

      },

      {

        "country\_id": "35",

        "country\_name\_cn": "全球",

        "country\_name\_en": "Global"

      },

      {

        "country\_id": "36",

        "country\_name\_cn": "日本",

        "country\_name\_en": "Japan"

      },

      {

        "country\_id": "37",

        "country\_name\_cn": "以色列",

        "country\_name\_en": "Israel"

      },

      {

        "country\_id": "38",

        "country\_name\_cn": "摩洛哥",

        "country\_name\_en": "Morocco"

      },

      {

        "country\_id": "39",

        "country\_name\_cn": "阿曼",

        "country\_name\_en": "Oman"

      },

      {

        "country\_id": "40",

        "country\_name\_cn": "叙利亚",

        "country\_name\_en": "Syrian Arab Republic"

      },

      {

        "country\_id": "41",

        "country\_name\_cn": "阿联酋",

        "country\_name\_en": "United Arab Emirates"

      },

      {

        "country\_id": "42",

        "country\_name\_cn": "埃及",

        "country\_name\_en": "Egyptian"

      },

      {

        "country\_id": "43",

        "country\_name\_cn": "韩国",

        "country\_name\_en": "Korea"

      },

      {

        "country\_id": "44",

        "country\_name\_cn": "洪都拉斯",

        "country\_name\_en": "Honduras"

      },

      {

        "country\_id": "45",

        "country\_name\_cn": "危地马拉",

        "country\_name\_en": "Guatemala"

      },

      {

        "country\_id": "46",

        "country\_name\_cn": "萨尔瓦多",

        "country\_name\_en": "El Salvador"

      },

      {

        "country\_id": "47",

        "country\_name\_cn": "尼加拉瓜",

        "country\_name\_en": "Nicaragua"

      },

      {

        "country\_id": "48",

        "country\_name\_cn": "巴拿马",

        "country\_name\_en": "Panama"

      },

      {

        "country\_id": "49",

        "country\_name\_cn": "罗马尼亚",

        "country\_name\_en": "Romania"

      },

      {

        "country\_id": "50",

        "country\_name\_cn": "印度",

        "country\_name\_en": "The Republic of India"

      },

      {

        "country\_id": "51",

        "country\_name\_cn": "土耳其",

        "country\_name\_en": "The Republic of Turkey"

      },

      {

        "country\_id": "52",

        "country\_name\_cn": "中国香港",

        "country\_name\_en": "Hong Kong, China"

      },

      {

        "country\_id": "53",

        "country\_name\_cn": "缅甸",

        "country\_name\_en": "Myanmar"

      },

      {

        "country\_id": "54",

        "country\_name\_cn": "伊朗",

        "country\_name\_en": "Iran"

      }

    ],

    "brands": [

      {

        "brand\_id": "3645",

        "brand\_name": "west month",

        "brand\_category\_id": "2",

        "brand\_category\_name": "3类",

        "trademark\_number": "82579246",

        "system\_service\_scope": "0301不含药物的个人私处清洗液；洗发液；洗衣剂；肥皂；防汗皂；除臭皂；香皂；0302挡风玻璃清洗剂；清洁制剂；0303抛光制剂；抛光蜡；皮革漂白制剂；0304研磨剂；0305香料；香精油；0306个人或动物用除臭剂；假指甲；减肥用化妆品；化妆品；化妆洗液；化妆用油；化妆用爽肤水；化妆用草本提取物；化妆用黏合剂；化妆笔；古龙水；唇膏；护肤用化妆剂；指甲护剂；指甲油；眉毛化妆品；睫毛膏；美容用凝胶眼贴；美容霜；美容面膜；脱毛剂；草本化妆品；0307个人卫生用口气清新剂；口气清新喷雾；牙膏；0308香；0309个人或动物用除臭剂；动物用化妆品；动物用沐浴露（不含药物的清洁制剂）；0310空气芳香剂；",

        "country\_id": "1",

        "country\_name\_cn": "中国",

        "country\_name\_en": "China",

        "authorized\_company": "1229",

        "authorized\_person\_id": null,

        "authorized\_person\_name": null,

        "legal\_representative\_name\_en": "Chen Jing",

        "authorized\_person\_id\_number": null,

        "authorized\_company\_name\_en": "Guangdong Xizhiyue Technology Co., Ltd",

        "certificate\_attachment": "556801"

      },

      {

        "brand\_id": "3609",

        "brand\_name": "west month",

        "brand\_category\_id": "4",

        "brand\_category\_name": "5类",

        "trademark\_number": "82558484",

        "system\_service\_scope": "0501乳脂；人参；人和动物用微量元素制剂；促进头发生长药物制剂；冻伤药膏；减肥用药剂；减肥茶；减肥药；医用氨基酸；医用甘油；医用糖；医用药膏；去灰指甲油；含药物的牙膏；性刺激用凝胶；抗菌剂；晒黑用药；杀真菌剂；枸杞；止痛药；气喘茶；治晒伤软膏；治疗烧伤制剂；治疗皮肤病药膏；治痔剂；洋参冲剂；消毒剂；淮山药（中药材）；灰指甲治疗制剂；痤疮治疗制剂；神经镇定剂；维生素制剂；罗汉果（中药材）；膳食纤维；药用亚麻籽；药用灵芝；药用草药茶；药用酵母；药用鹿茸；药草；藏红花（中药材）；蜂王精；赖氨酸冲剂；辅助戒烟用尼古丁咀嚼胶；辅助戒烟用尼古丁贴片；防寄生虫制剂；防尿制剂；阿胶；除口臭药片；除香精油外的医用草本提取物；隐形眼镜清洁剂；饮食疗法用或医用谷类加工副产品；0502亚麻籽膳食补充剂；婴儿食品；小麦胚芽膳食补充剂；花粉膳食补充剂；营养补充剂；葡萄糖膳食补充剂；藻酸盐膳食补充剂；蛋白质膳食补充剂；蜂王浆膳食补充剂；蜂胶膳食补充剂；酪蛋白膳食补充剂；酵母膳食补充剂；酶膳食补充剂；0503空气净化制剂；除霉化学制剂；0504动物用杀虫沐浴露；动物用洗涤剂（杀虫剂）；动物用膳食补充剂；动物用蛋白质补充剂；狗",

        "country\_id": "1",

        "country\_name\_cn": "中国",

        "country\_name\_en": "China",

        "authorized\_company": "1229",

        "authorized\_person\_id": null,

        "authorized\_person\_name": null,

        "legal\_representative\_name\_en": "Chen Jing",

        "authorized\_person\_id\_number": null,

        "authorized\_company\_name\_en": "Guangdong Xizhiyue Technology Co., Ltd",

        "certificate\_attachment": "554839"

      },

      {

        "brand\_id": "3603",

        "brand\_name": "googeer",

        "brand\_category\_id": "4",

        "brand\_category\_name": "5类",

        "trademark\_number": "98324787",

        "system\_service\_scope": "Acnetreatmentpreparations;Bittertastingpettrainingaidｉｎtheformofaspraytopreventpetsfromlicking,chewingａｎｄbitingonobjects;Deodorizingpreparationsforhousehold,commercialｏｒindustrialuseforpetlitterboxes,carpets,syntheticgrass;Dietpills;Medicatedbalmsfortreatmentofhair;Medicatedbarsoap;Nailfungustreatmentpreparations;Pharmaceuticalpreparationforskincare;Pharmaceuticalpreparationsforthetreatmentofmusculo-skeletaldisorders;Sexualstimulantpreparationsｉｎtheformofsprays,gelscontainingorganicingredients,fragrances",

        "country\_id": "2",

        "country\_name\_cn": "美国",

        "country\_name\_en":"the United\\ States",

        "authorized\_company": "1142",

        "authorized\_person\_id": null,

        "authorized\_person\_name": null,

        "legal\_representative\_name\_en": "Cai Hongcheng",

        "authorized\_person\_id\_number": null,

        "authorized\_company\_name\_en": "SHANTOU GOOGEER HEALTH FOOD CO., LTD. ",

        "certificate\_attachment": "734128"

      },

      {

        "brand\_id": "3515",

        "brand\_name": "GOOGEER",

        "brand\_category\_id": "4",

        "brand\_category\_name": "5类",

        "trademark\_number": "019084653",

        "system\_service\_scope": "痤疮治疗制剂；医疗用胶粘带；杀藻剂;拇外翻垫;治疗霉菌的化学制剂；冻疮的准备工作;玉米的补救措施;医疗用棉签；牙科乳香;痔的准备工作;驱蚊香；减肥用医药制剂；药皂;药用毛发生长制剂；药用油;药膏；皮肤护理用药物制剂；减肥药;土壤消毒制剂;疣铅笔。",

        "country\_id": "3",

        "country\_name\_cn": "欧盟",

        "country\_name\_en": "EU",

        "authorized\_company": "1142",

        "authorized\_person\_id": null,

        "authorized\_person\_name": null,

        "legal\_representative\_name\_en": "Cai Hongcheng",

        "authorized\_person\_id\_number": null,

        "authorized\_company\_name\_en": "Shantou Googeer Health Food Co., Ltd.",

        "certificate\_attachment": "657798"

      },

      {

        "brand\_id": "3512",

        "brand\_name": "EelJoy",

        "brand\_category\_id": "2",

        "brand\_category\_name": "3类",

        "trademark\_number": "98418781",

        "system\_service\_scope": "宠物除臭剂；宠物除臭剂；猫、狗、宠物、家畜用非药物牙科制剂，即牙膏、除菌斑制剂；用于猫、狗、宠物、牲畜的非药物美容制剂，即宠物洗发水和护发素；宠物用非药物漱口水；宠物洗发水和护发素性质的非药物、非兽医美容制剂；宠物除臭剂；宠物香水;宠物香波;宠物去污剂。",

        "country\_id": "2",

        "country\_name\_cn": "美国",

        "country\_name\_en":"the United\\ States",

        "authorized\_company": "1093",

        "authorized\_person\_id": null,

        "authorized\_person\_name": null,

        "legal\_representative\_name\_en": "Zhang Chaohe",

        "authorized\_person\_id\_number": null,

        "authorized\_company\_name\_en": "Shantou Xiyue E-commerce Co.,",

        "certificate\_attachment": "501601"

      },

      {

        "brand\_id": "3511",

        "brand\_name": "MoonSpry",

        "brand\_category\_id": "0",

        "brand\_category\_name": "1类",

        "trademark\_number": "7624500",

        "system\_service\_scope": "堆肥;人工植物栽培土壤；用于生产农业种子以促进植物生长的生物技术形成的基因、微生物和酶；木炭，用作农业、家庭或园艺用的土壤调节剂；混凝土养护用化合物；化学肥料;预防植物病原菌感染的化学制剂；用于制造肥皂和植物油的化学防腐剂；农业、园艺和林业用化学品，除杀菌剂、除草剂、杀虫剂和寄生虫剂外；复杂的肥料。",

        "country\_id": "2",

        "country\_name\_cn": "美国",

        "country\_name\_en":"the United\\ States",

        "authorized\_company": "1093",

        "authorized\_person\_id": null,

        "authorized\_person\_name": null,

        "legal\_representative\_name\_en": "Zhang Chaohe",

        "authorized\_person\_id\_number": null,

        "authorized\_company\_name\_en": "Shantou Xiyue E-commerce Co.,",

        "certificate\_attachment": "528124"

      },

      {

        "brand\_id": "3509",

        "brand\_name": "NORTH MOON",

        "brand\_category\_id": "4",

        "brand\_category\_name": "5类",

        "trademark\_number": "019116754",

        "system\_service\_scope": "chemicalreagentsformedicalｏｒveterinarypurposes;compresses;dentallacquer;dressings,medical;germicides;herbalextractsformedicalpurposes;herbicides;insectrepellentincense;insectrepellents;nutritionalsupplements;ointmentsforpharmaceuticalpurposes;pharmaceuticalpreparationsforskincare;sexualstimulantgels;slimmingpills;sterilizingpreparations;sunburnointments;vitaminpreparations;wartpencils;weedkillers",

        "country\_id": "3",

        "country\_name\_cn": "欧盟",

        "country\_name\_en": "EU",

        "authorized\_company": "1307",

        "authorized\_person\_id": null,

        "authorized\_person\_name": null,

        "legal\_representative\_name\_en": "Xie Xiaoyu",

        "authorized\_person\_id\_number": null,

        "authorized\_company\_name\_en": "Shantou Beizhiyue Biotechnology Co., Ltd.",

        "certificate\_attachment": "482554"

      },

      {

        "brand\_id": "3508",

        "brand\_name": "NORTH MOON",

        "brand\_category\_id": "2",

        "brand\_category\_name": "3类",

        "trademark\_number": "019116754",

        "system\_service\_scope": "Beautymasks;Cleaningpreparations;Cosmeticpencils;Cosmeticpreparationsforskincare;Cosmeticpreparationsforslimmingpurposes;Cosmetics;Cosmeticsforanimals;Deodorantsforpets;Hairconditioners;Hairdyes;Hairlotions;Leatherpreservatives[polishes];Make-up;Nailpolish;Shampoos;Skinwhiteningcreams;Toothpaste;Waxesforleather",

        "country\_id": "3",

        "country\_name\_cn": "欧盟",

        "country\_name\_en": "EU",

        "authorized\_company": "1307",

        "authorized\_person\_id": null,

        "authorized\_person\_name": null,

        "legal\_representative\_name\_en": "Xie Xiaoyu",

        "authorized\_person\_id\_number": null,

        "authorized\_company\_name\_en": "Shantou Beizhiyue Biotechnology Co., Ltd.",

        "certificate\_attachment": "482544"

      },

      {

        "brand\_id": "3463",

        "brand\_name": "Hanchobit",

        "brand\_category\_id": "4",

        "brand\_category\_name": "5类",

        "trademark\_number": "019099036",

        "system\_service\_scope": "空气除臭制剂；药用香脂制剂；药用口香糖；药用洗眼剂；隐形眼镜清洁制剂；鸡眼药；牙用漆；牙科胶粘剂；衣物及纺织品用除臭剂；供医疗用的适合糖尿病患者的面包；失禁者用尿布；膳食纤维；狗用洗涤剂（杀虫剂）；医用敷料；医用眼罩；已装药的急救箱；驱虫香；驱虫剂；含杀虫剂的动物用香波；含杀虫剂的兽用洗涤剂；医用减肥制剂；含药须后乳液；含药牙膏；含药洗眼剂；含药牙膏；生发用医药制剂；牙科用药；医用漱口水；用作戒烟辅助品的尼古丁口香糖；用作戒烟辅助品的尼古丁贴片；药用软膏；失禁者用吸收性内裤；护肤用药物制剂；蛋白质膳食补充剂；止汗药；减肥药片；晒伤软膏；维生素制剂；去疣笔。",

        "country\_id": "3",

        "country\_name\_cn": "欧盟",

        "country\_name\_en": "EU",

        "authorized\_company": "1308",

        "authorized\_person\_id": null,

        "authorized\_person\_name": null,

        "legal\_representative\_name\_en": "Zhang Nana",

        "authorized\_person\_id\_number": null,

        "authorized\_company\_name\_en": "Hanchoyeon Biotech Co., Ltd.",

        "certificate\_attachment": "452558"

      },

      {

        "brand\_id": "3462",

        "brand\_name": "Hanchobit",

        "brand\_category\_id": "2",

        "brand\_category\_name": "3类",

        "trademark\_number": "019099036",

        "system\_service\_scope": "剃须后护肤液；防汗香皂；防汗剂（化妆品）；美容面膜；口气清新喷雾；洁面乳（用于个人卫生或除臭）；护肤化妆品；减肥化妆品；化妆品；除臭香皂；用于人类或动物的除臭剂；用于宠物的除臭剂；脱毛剂；脱毛蜡；用于个人卫生或除臭的冲洗剂；眉部化妆品；非医疗用途的眼部冲洗液；假指甲；用于美容的凝胶眼贴；护发素；皮革保养剂（上光剂）；口红；睫毛膏；非医疗用途的口腔清洗液；指甲油；指甲油去除剂；用于香水和香精的油；香水；洗发水（非药物护理用品）；洗发水；剃须皂；皮肤美白霜；用于脚部出汗的香皂；指甲油去除剂；",

        "country\_id": "3",

        "country\_name\_cn": "欧盟",

        "country\_name\_en": "EU",

        "authorized\_company": "1308",

        "authorized\_person\_id": null,

        "authorized\_person\_name": null,

        "legal\_representative\_name\_en": "Zhang Nana",

        "authorized\_person\_id\_number": null,

        "authorized\_company\_name\_en": "Hanchoyeon Biotech Co., Ltd.",

        "certificate\_attachment": "452510"

      }

    ],

    "pagination": {

      "total": 196,

      "perPage": 10,

      "currentPage": 1,

      "lastPage": 20

    }

  }

}
```

### 获取执照列表信息

注：模板类型为：速卖通（直营店）可用

#### 请求方式&地址

#### 请求参数

无

#### 返回参数

| 参数名称                         | 参数类型 | 是否 回传 | 参数描述     | 示例值                                             |
|----------------------------------|----------|-----------|--------------|----------------------------------------------------|
| code                             | string   | Y         | 错误码       | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                              | string   | Y         | 错误描述     | 执行成功                                           |
| data                             | array    | Y         | 业务数据     |                                                    |
| -\| partner_license_id           | int      | Y         | 执照id       |                                                    |
| -\| partner_license_name_cn      | string   | Y         | 执照中文名称 |                                                    |
| -\| partner_license_name_en      | string   | Y         | 执照英文名称 |                                                    |
| -\| partner_legal_representative | int      | Y         | 法人id       |                                                    |

#### 返回参数示例

### 品牌授权申请

#### 请求方式&地址

#### 请求参数

| 参数名称                     | 参数类型 | 是否必填 | 参数描述                                                                                                                         |
|------------------------------|----------|----------|----------------------------------------------------------------------------------------------------------------------------------|
| platform_id                  | int      | Y        | 平台id                                                                                                                           |
| template_category            | int      | Y        | 模板类型id                                                                                                                       |
| partner_license_name_cn      | string   | Y        | 执照中文名称，模板为（速卖通（直营店））需读取执照列表信息                                                                       |
| partner_license_name_en      | string   | N        | 执照英文名称，模板为（中文通用模板，Tiktok英文模板，希音中英文模板）必填，模板为（速卖通（直营店））需读取执照列表信息           |
| partner_legal_representative | int      | N        | 法人id，模板为（速卖通（直营店））必填                                                                                           |
| partner_license_id           | int      | N        | 执照id，模板为（速卖通（直营店））必填                                                                                           |
| partner_address_en           | string   | N        | 地址英文，模板为（英文通用模板，Tiktok英文模板）必填                                                                             |
| shop_id                      | string   | Y        | 店铺id                                                                                                                           |
| include_shop_info            | int      | N·       | 是否补充店铺信息，模板为（中文通用模板，英文通用模板）必填，注：英文通用模板值为：0：否，1：是，中文通用模板值为：2：否，3：是， |
| additional_shop_info         | string   | N        | 补充店铺信息，是否补充店铺信息为是必填                                                                                           |
| brands                       | array    | Y        | 授权品牌信息                                                                                                                     |
| -\| brand_id                 | int      | Y        | 授权品牌id                                                                                                                       |

#### 请求参数示例

#### 返回参数

| 参数名称 | 参数类型 | 是否 回传 | 参数描述 | 示例值                                             |
|----------|----------|-----------|----------|----------------------------------------------------|
| code     | string   | Y         | 错误码   | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg      | string   | Y         | 错误描述 | 执行成功                                           |
| data     | array    | Y         | 业务数据 |                                                    |

#### 返回参数示例

### 品牌授权记录

#### 请求方式&地址

#### 请求参数

| 参数名称            | 参数类型 | 是否必填 | 参数描述                                                                         |
|---------------------|----------|----------|----------------------------------------------------------------------------------|
| shop_id             | string   | N        | 店铺id                                                                           |
| platform_name_cn    | string   | N        | 平台中文名称                                                                     |
| brand_name          | string   | N        | 品牌名称                                                                         |
| brand_category_name | string   | N        | 品牌类别名称                                                                     |
| status              | int      | N        | 状态 (1: 待审核, 2: 已完成, 3: 已拒绝, 4: 失效, 5: 作废, 6: 作废中, 7: 作废无效) |
| period_of_validity  | array    | N        | 授权结束日期，例：["2025-04-03", "2025-04-04"]                                   |
| apply_time          | array    | N        | 申请日期，例：["2025-04-03", "2025-04-04"]                                       |
| serial_number       | string   | N        | 流水号                                                                           |
| page                | int      | N        | 页码，默认1                                                                      |
| per_page            | int      | N        | 每页条数，默认10                                                                 |

#### 请求参数示例

#### 返回参数

| 参数名称                               | 参数类型 | 是否 回传 | 参数描述                                                                         | 示例值                                             |
|----------------------------------------|----------|-----------|----------------------------------------------------------------------------------|----------------------------------------------------|
| code                                   | string   | Y         | 错误码                                                                           | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                                    | string   | Y         | 错误描述                                                                         | 执行成功                                           |
| data                                   | object   | Y         | 业务数据                                                                         |                                                    |
| -\| ossPathFix                         | string   | Y         | OSS域名                                                                          |                                                    |
| -\| list                               | array    | Y         | 列表                                                                             |                                                    |
| -\| id                                 | int      | Y         | 申请记录分组id                                                                   |                                                    |
| -\| status                             | int      | Y         | 状态 (1: 待审核, 2: 已完成, 3: 已拒绝, 4: 失效, 5: 作废, 6: 作废中, 7: 作废无效) |                                                    |
| -\| brand_authorizings                 | array    | Y         | 授权申请记录                                                                     |                                                    |
| -\|-\| id                              | int      | Y         | 授权申请id                                                                       |                                                    |
| -\|-\| status                          | int      | Y         | 状态 (1: 待审核, 2: 已完成, 3: 已拒绝, 4: 失效, 5: 作废, 6: 作废中, 7: 作废无效) |                                                    |
| -\|-\| serial_number                   | string   | Y         | 流水号                                                                           |                                                    |
| -\|-\| shop_id                         | string   | Y         | 店铺id                                                                           |                                                    |
| -\|-\| template_category_name          | string   | Y         | 模板类型名称                                                                     |                                                    |
| -\|-\| platform_name_cn                | string   | Y         | 授权平台名称                                                                     |                                                    |
| -\|-\| brand_name                      | string   | Y         | 授权品牌名称                                                                     |                                                    |
| -\|-\| country_name_cn                 | string   | Y         | 品牌申请国家名称                                                                 |                                                    |
| -\|-\| brand_category_name             | string   | Y         | 品牌类别名称                                                                     |                                                    |
| -\|-\| authorization_start_date        | string   | Y         | 授权开始日期                                                                     |                                                    |
| -\|-\| authorization_end_date          | string   | Y         | 授权结束日期                                                                     |                                                    |
| -\|-\| statusText                      | string   | Y         | 状态                                                                             |                                                    |
| -\|-\| certificate_attachment_path     | array    | Y         | 证书/回执附件信息                                                                |                                                    |
| -\|-\|-\| filePath                     | string   | Y         | 文件路径                                                                         |                                                    |
| -\|-\|-\| fileName                     | string   | Y         | 文件名称                                                                         |                                                    |
| -\|-\| brand_authorization_letter_path | array    | Y         | 品牌授权书信息                                                                   |                                                    |
| -\|-\|-\| filePath                     | string   | Y         | 文件路径                                                                         |                                                    |
| -\|-\|-\| fileName                     | string   | Y         | 文件名称                                                                         |                                                    |
| -\|-\| applicant                       | string   | Y         | 申请人电话                                                                       |                                                    |
| -\|-\| created_at                      | string   | Y         | 申请时间                                                                         |                                                    |

#### 返回参数示例

### 品牌授权作废

#### 请求方式&地址

#### 请求参数

| 参数名称 | 参数类型 | 是否必填 | 参数描述                     |
|----------|----------|----------|------------------------------|
| id       | string   | Y        | 品牌授权记录id，多个逗号链接 |
| content  | string   | Y        | 作废原因                     |

#### 请求参数示例

#### 返回参数

| 参数名称 | 参数类型 | 是否 回传 | 参数描述 | 示例值                                             |
|----------|----------|-----------|----------|----------------------------------------------------|
| code     | string   | Y         | 错误码   | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg      | string   | Y         | 错误描述 | 执行成功                                           |
| data     | array    | Y         | 业务数据 |                                                    |

#### 返回参数示例

## 客户token

### 根据token获取用户信息

#### 请求方式&地址

```
[get]/openapi/getUserInfoByToken?token=3fv30szBr8f3OhQIx1urkuWr6TAQ8UdX
```

#### 请求参数

| 参数名称 | 参数类型 | 是否必填 | 参数描述   |
|----------|----------|----------|------------|
| token    | string   | Y        | 客户token  |

#### 返回参数

| 参数名称           | 参数类型 | 是否 回传 | 参数描述        | 示例值                                             |
|--------------------|----------|-----------|-----------------|----------------------------------------------------|
| code               | string   | Y         | 错误码          | 0=成功 1=服务错误 2=参数错误 3=授权无效 4=请求上限 |
| msg                | string   | Y         | 错误描述        | 执行成功                                           |
| data               | object   | Y         | 业务数据        |                                                    |
| -\| user_id        | int      | Y         | 客户id          |                                                    |
| -\| user_account   | string   | Y         | 客户账号（UID） |                                                    |
| -\| user_nick_name | string   | Y         | 客户昵称        |                                                    |
| -\| gmt_expire     | string   | Y         | token过期时间   |                                                    |

#### 返回参数示例

```
{

    "code": 0,

    "msg": "",

    "data": {

        "user\_id": 1268,

        "user\_account": "WM202406181539494611",

        "user\_nick\_name": "west\_G1Tyz3OGV4",

        "gmt\_expire": "2025-12-17 10:53:20"

    }

}
```

# 订单状态的枚举值

| 订单状态        | 订单状态描述 |
|-----------------|--------------|
| created         | 下单         |
| unpaid          | 待支付       |
| paid            | 支付成功     |
| cancelled       | 交易已取消   |
| failed          | 推单失败     |
| picking         | 仓库处理中   |
| picked          | 拣货完成     |
| unstowed        | 发货失败     |
| stowed          | 已发货       |
| shipped         | 运输中       |
| unshipped       | 派送失败     |
| completed       | 交易成功     |
| refunding       | 退款处理中   |
| refunded        | 已退款       |
| waitfconfirm    | 待审核       |
| question        | 审核失败     |
| confirmed       | 审核成功     |
| partial_shipped | 部分发货     |

