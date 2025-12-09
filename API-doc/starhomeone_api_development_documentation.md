# 状态码说明

创建时间: Invalid Date

说明： 状态码是针对项目而言的，所有接口状态码都可参考此文档

| 状态码 | 中文描述         |
| --- | ------------ |
| 200 | 请求成功         |
| 400 | 数据异常相关验证     |
| 100 | token相关验证未通过 |

# 对外api

## 商品接口

### 获取商品分配列表

#### 基本信息

* 接口状态:已完成

* 接口URL:GET   /retailers/getProductListData?page=1&limit=15&custom_sn=&warehouse_id=&warehouse_area_id=

* Content-Type: multipart/form-data

* 认证方式:无需认证

#### 环境URL

默认环境 : https://rtl.chshopapi.cc/

#### 请求参数

##### Header 请求参数

| 参数名   | 参数值                  | 是否必填 | 参数类型   | 描述说明                                                                               |
| ----- | -------------------- | ---- | ------ | ---------------------------------------------------------------------------------- |
| token | {{retailers_token}} | 是    | String | 身份认证登录之后返回的token（6个小时内有效），调用接口时需要进行加密处理，并通过bearer auth认证方式传参。加密方法请查看《DESCBC模式加密示例》 |
| time  | {{timestamp}}        | 是    | String | 时间戳                                                                                |

##### Query 请求参数

| 参数名                 | 参数值 | 是否必填 | 参数类型    | 描述说明   |
| ------------------- | --- | ---- | ------- | ------ |
| page                | 1   | 是    | Integer | 当前页码   |
| limit               | 15  | 是    | Integer | 每页条数   |
| custom_sn          |     | 否    | String  | 商品SKU  |
| warehouse_id       |     | 否    | Integer | 仓库ID   |
| warehouse_area_id |     | 否    | Integer | 仓库区域ID |

##### Body 请求参数

| 参数名 | 参数值 | 是否必填 | 参数类型   | 描述说明 |
| --- | --- | ---- | ------ | ---- |
|     |     | 是    | String |      |

#### 响应示例

成功 (200)

```
{
	"code": 200,
	"msg": "商品列表",
	"data": {
		"total": 12,
		"per_page": 15,
		"current_page": 1,
		"last_page": 1,
		"data": [
			{
				"operator_id": 1,
				"warehouse_id": 1,
				"warehouse_area_id": 1,
				"product_id": 20,
				"custom_sn": "F091-O",
				"goods_sn": "M00001-004354",
				"erp_merchant_uuid": "w9L4OR0lkfJO0UMEK4VHy2vONRqlHW8w",
				"relation_id": 0,
				"preview_image_path": "https://file.starhome.cc/erp/image/8563457011703039746.jpg",
				"price": 0,
				"title": "Black Faux Leather Storage Ottoman Living Room Sof",
				"sub_title": "Black Faux Leather Ottoman",
				"type": 1,
				"line_image_path": "http://file.starhome.cc/erp/image/2608805471703039751.jpg",
				"param_image_path": [
					"http://file.starhome.cc/erp/image/4172848701703039786.jpg"
				],
				"detail_image_path": [
					"http://file.starhome.cc/erp/image/7085486131703039756.jpg"
				],
				"product_length": "35.00",
				"product_width": "23.50",
				"product_height": "17.50",
				"pack_length": "34.00",
				"pack_width": "32.00",
				"pack_height": "12.50",
				"pack_weight": "37.40",
				"product_weight": "33.00",
				"cubic_metre": "0.000",
				"cubic_foot": "0.000",
				"attach_resource_path": "",
				"postage_price": "0.0",
				"total_price": 0,
				"is_postage": true,
				"warehouse_province": "",
				"warehouse_city": "",
				"warehouse_country": "",
				"warehouse_address": "12005 Cabernet Dr,Fontana,CA",
				"area_name": "public"
			}
		]
	}
}
```

| 参数名                              | 参数值                                                         | 是否必填 | 参数类型    | 描述说明                 |
| -------------------------------- | ----------------------------------------------------------- | ---- | ------- | -------------------- |
| code                             | 200                                                         | 是    | Integer |                      |
| msg                              | 商品列表                                                        | 是    | String  |                      |
| data                             |                                                             | 是    | Object  |                      |
| data.total                       | 12                                                          | 是    | Integer | 总数                   |
| data.per_page                   | 15                                                          | 是    | Integer | 页数                   |
| data.current_page               | 1                                                           | 是    | Integer | 当前页                  |
| data.last_page                  | 1                                                           | 是    | Integer | 总页树                  |
| data.data                        |                                                             | 是    | Array   | 接口返回数据               |
| data.data.operator_id           | 1                                                           | 是    | Integer |                      |
| data.data.warehouse_id          | 1                                                           | 是    | Integer |                      |
| data.data.warehouse_area_id    | 1                                                           | 是    | Integer |                      |
| data.data.product_id            | 20                                                          | 是    | Integer |                      |
| data.data.custom_sn             | F091-O                                                      | 是    | String  | 商品SKU                |
| data.data.goods_sn              | M00001-004354                                               | 是    | String  | 商品唯一编号               |
| data.data.erp_merchant_uuid    | w9L4OR0lkfJO0UMEK4VHy2vONRqlHW8w                            | 是    | String  |                      |
| data.data.relation_id           | 0                                                           | 是    | Integer |                      |
| data.data.preview_image_path   | https\://file.starhome.cc/erp/image/8563457011703039746.jpg | 是    | String  | 商品预览图地址              |
| data.data.price                  | 0                                                           | 是    | Integer | 商品价格                 |
| data.data.title                  | Black Faux Leather Storage Ottoman Living Room Sof          | 是    | String  | 商品标题                 |
| data.data.sub_title             | Black Faux Leather Ottoman                                  | 是    | String  | 商品副标题                |
| data.data.type                   | 1                                                           | 是    | Integer | 商品类型: 1.散件 2.配件 3.套件 |
| data.data.line_image_path      | http://file.starhome.cc/erp/image/2608805471703039751.jpg  | 是    | String  | 商品线框图地址              |
| data.data.param_image_path     | http://file.starhome.cc/erp/image/4172848701703039786.jpg  | 是    | Array   | 商品参数图地址合集            |
| data.data.detail_image_path    | http://file.starhome.cc/erp/image/7085486131703039756.jpg  | 是    | Array   | 商品详情图地址合集            |
| data.data.product_length        | 35.00                                                       | 是    | String  | 商品长(单位：英寸)           |
| data.data.product_width         | 23.50                                                       | 是    | String  | 商品宽(单位：英寸)           |
| data.data.product_height        | 17.50                                                       | 是    | String  | 商品高(单位：英寸)           |
| data.data.pack_length           | 34.00                                                       | 是    | String  | 包装长度(单位：英寸)          |
| data.data.pack_width            | 32.00                                                       | 是    | String  | 包装宽度(单位：英寸)          |
| data.data.pack_height           | 12.50                                                       | 是    | String  | 包装高度(单位：英寸)          |
| data.data.pack_weight           | 37.40                                                       | 是    | String  | 包装重量(单位：磅)           |
| data.data.product_weight        | 33.00                                                       | 是    | String  | 商品重量(单位：磅)           |
| data.data.cubic_metre           | 0.000                                                       | 是    | String  | 立方米                  |
| data.data.cubic_foot            | 0.000                                                       | 是    | String  | 立方英尺                 |
| data.data.attach_resource_path |                                                             | 是    | String  | 图片包下载链接              |
| data.data.postage_price         | 0.0                                                         | 是    | String  | 运费价格                 |
| data.data.total_price           | 0                                                           | 是    | Integer | 价格总计                 |
| data.data.is_postage            | true                                                        | 是    | Boolean | 是否为包邮商品              |
| data.data.warehouse_province    |                                                             | 是    | String  | 仓库省/州                |
| data.data.warehouse_city        |                                                             | 是    | String  | 仓库城市                 |
| data.data.warehouse_country     |                                                             | 是    | String  | 仓库国家                 |
| data.data.warehouse_address     | 12005 Cabernet Dr,Fontana,CA                                | 是    | String  | 仓库地址                 |
| data.data.area_name             | public                                                      | 是    | String  | 仓库区域名称               |

### 获取某个商品的库存信息

#### 基本信息

* 接口状态:已完成

* 接口URL:GET   retailers/getProductStockData?product_id=57&warehouse_area_id=1&relation_id=

* Content-Type: multipart/form-data

* 认证方式:无需认证

#### 环境URL

默认环境 : https://rtl.chshopapi.cc/

#### 请求参数

##### Header 请求参数

| 参数名   | 参数值                  | 是否必填 | 参数类型   | 描述说明                                                                               |
| ----- | -------------------- | ---- | ------ | ---------------------------------------------------------------------------------- |
| token | {{retailers_token}} | 是    | String | 身份认证登录之后返回的token（6个小时内有效），调用接口时需要进行加密处理，并通过bearer auth认证方式传参。加密方法请查看《DESCBC模式加密示例》 |
| time  | {{{timestamp}}     | 是    | String | 时间戳                                                                                |

##### Query 请求参数

| 参数名                 | 参数值 | 是否必填 | 参数类型    | 描述说明           |
| ------------------- | --- | ---- | ------- | -------------- |
| product_id         | 57  | 是    | Integer | 商品ID           |
| warehouse_area_id | 1   | 是    | Integer | 仓库区域ID         |
| relation_id       |     | 是    | String  | 组合ID(非组合商品可为0) |

#### 响应示例

成功 (200)

```
{"code": 200,"msg": "获取成功!","data": {}"stock_quantity": 138 //库存数量}
```

| 参数名                  | 参数值   | 是否必填 | 参数类型    | 描述说明 |
| -------------------- | ----- | ---- | ------- | ---- |
| code                 | 200   | 是    | Integer |      |
| msg                  | 获取成功! | 是    | String  |      |
| data                 |       | 是    | Object  |      |
| data.stock\_quantity | 138   | 是    | Integer | 库存数量 |

##### 失败 (404)

暂无响应示例数据

## 订单接口

### 订单推送创建

#### 基本信息

* 接口状态:已完成

* 接口URL:POST  retailers/createOrderData

* Content-Type: multipart/form-data

* 认证方式:无需认证

### 环境URL

默认环境 : https://rtl.chshopapi.cc/

### 请求参数

#### Header 请求参数

| 参数名   | 参数值                  | 是否必填 | 参数类型   | 描述说明                                                                               |
| ----- | -------------------- | ---- | ------ | ---------------------------------------------------------------------------------- |
| token | {{retailers_token}} | 是    | String | 身份认证登录之后返回的token（6个小时内有效），调用接口时需要进行加密处理，并通过bearer auth认证方式传参。加密方法请查看《DESCBC模式加密示例》 |
| time  | {{timestamp}}        | 是    | String | 时间戳                                                                                |

#### Body 请求参数

| 参数名                    | 参数值                                                        | 是否必填 | 参数类型    | 描述说明              |
| ---------------------- | ---------------------------------------------------------- | ---- | ------- | ----------------- |
| warehouse_area_id    | 1                                                          | 是    | Number  | 仓库区域id            |
| shopapi_order_number | test-123456-123456                                         | 是    | String  | 商城第三方订单号          |
| product_list         | [{"product_id":179,"relation_id":689,"quantity":1}]    | 是    | String  | 商品数据              |
| ship_type          | 1                                                          | 是    | Integer | 提货方式(1自提,2快递,3卡车) |
| face_sheet_path   | http://file.starhome.cc/erp/other/2487885351730795981.pdf | 否    | String  | 面单地址(注：pdf文件链接)   |
| receiver               | 测试                                                         | 否    | String  | 收货人（包邮时必传）        |
| receiver_email       | test@163.com                    | 否    | String  | 收货人邮件（包邮时必传）      |
| receiver_phone       | 92337-7703                                                 | 否    | String  | 收货人手机号（包邮时必传）     |
| receiver_address      | Cabernet Dr,Fontana,CA                                     | 否    | String  | 收货人地址（包邮时必传）      |
|receiver_zip_code    | 12005                                                      | 否    | String  | 收货人邮编（包邮时必传）      |

#### 响应示例

成功 (200)

```
{
	"code": 200,
	"msg": "订单处理成功",
	"data": {
		"shopapi_order_number": "test-123456-789",
		"order_number": "API01-2411048436-002",
		"starhome_order_number": "M01-2411043431-002",
		"warehouse_data": {
			"contact_name": "Michael",
			"contact_phone": "713-896-8877",
			"contact_email": "goldenfurniture11@yahoo.com",
			"address": "12315 Parc Crest Bl Unt #100 ,Stafford,TX,77477",
			"longitude": "-95.5668145",
			"latitude": "29.6428602"
		}
	}
}
```

| 参数名                                 | 参数值                                             | 是否必填 | 参数类型    | 描述说明          |
| ----------------------------------- | ----------------------------------------------- | ---- | ------- | ------------- |
| code                                | 200                                             | 是    | Integer |               |
| msg                                 | 订单处理成功                                          | 是    | String  |               |
| data                                |                                                 | 是    | Object  |               |
| data.shopapi_order_number        | test-123456-789                                 | 是    | String  | 商城第三方订单号      |
| data.order_number                  | API01-2411048436-002                            | 是    | String  | 订单号           |
| data.starhome_order_number       | M01-2411043431-002                              | 是    | String  | starhome商城订单号 |
| data.warehouse_data             |                                                 | 是    | Object  | 仓库信息          |
| data.warehouse_data.contact_name  | Michael                                         | 是    | String  | 负责人名称         |
| data.warehouse_data.contact_phone | 713-896-8877                                    | 是    | String  | 电话号码          |
| data.warehouse_data.contact_email | 	goldenfurniture11@yahoo.com                    | 是    | String  | 邮箱            |
| data.warehouse_data.address        | 12315 Parc Crest Bl Unt #100 ,Stafford,TX,77477 | 是    | String  | 仓库地址          |
| data.warehouse_data.longitude    | -95.5668145                                     | 是    | String  | 经度            |
| data.warehouse_data.latitude      | 29.6428602                                      | 是    | String  | 纬度            |

#### 失败 (404)

暂无响应示例数据

### 订单列表(包含订单商品数据)

开发中

## 身份认证登录

### 认证登录接口

#### 基本信息

* 接口状态:已完成

* 接口URL:POST  retailers/authorToken

* 认证方式:无需认证

#### 环境URL

默认环境 : https://rtl.chshopapi.cc/

#### 请求参数

##### Header 请求参数

| 参数名           | 参数值              | 是否必填 | 参数类型   | 描述说明                                                              |
| ------------- | ---------------- | ---- | ------ | ----------------------------------------------------------------- |
| time          | {{timestamp}}    | 是    | String | 时间戳，认证时效性，10分钟                                                    |
| Authorization |  {{basic_token}} | 是    | String | 电商账号密码登录（联系平台获取）：调用接口时需要进行加密处理，并通过basic auth认证方式传参，《DESCBC模式加密示例》 |

#### 响应示例

 成功 (200)

```
{
"code": 200,
"msg": "获取成功!",
"data": "a19844e8b3edbde3aad18c2245cf01c8" //token信息
}
```

| 参数名  | 参数值                              | 是否必填 | 参数类型    | 描述说明    |
| ---- | -------------------------------- | ---- | ------- | ------- |
| code | 200                              | 是    | Integer |         |
| msg  | 获取成功!                            | 是    | String  |         |
| data | a19844e8b3edbde3aad18c2245cf01c8 | 是    | String  | token信息 |

#### 失败 (404)

暂无响应示例数据

### DES CBC模式加密示例

####  PHP示例

```
 <?php
        // 示例账号和密码
       $account = "test";
       $passwordStr = "123456";
       $password = base64_encode($passwordStr);

       // 获取当前时间戳
       $timestamp = time();

       $encyStr = $account.":".$timestamp.":".$password;
       $ency = base64_encode($encyStr);//要加密的字符串

       $key = "chshopapi68"; // 8字节的密钥
       $iv  = "y5jKfs6q"; // 8字节的初始化向量

       $encrypted = openssl_encrypt($ency, 'DES-CBC', $key, OPENSSL_RAW_DATA, $iv);//转换为二进制字符串
       $encr_str = bin2hex($encrypted);//将二进制转换成十六进制
       $basic_token = base64_encode($account . ":" . $encr_str);//base64加密
       //验证Authorization头的格式
       $str_basic_token = 'Basic '.$basic_token;
	   ```
 #### java示例
 
```
   import javax.crypto.Cipher;
import javax.crypto.SecretKey;
import javax.crypto.SecretKeyFactory;
import javax.crypto.spec.DESKeySpec;
import javax.crypto.spec.IvParameterSpec;
import java.nio.charset.StandardCharsets;
import java.util.Base64;
public class AuthEncryption {
   public static void main(String[] args) throws Exception {
       // 模拟设置全局变量：URL和时间戳
       String url = "http://chshop.api.cc";
       String timestamp = String.valueOf(System.currentTimeMillis() / 1000);

       // 设置账号和密码
       String account = "admin";
       String password = Base64.getEncoder().encodeToString("dedede".getBytes(StandardCharsets.UTF_8));
       String message = account + ":" + timestamp + ":" + password;

       // 对消息进行Base64编码
       String base64Message = Base64.getEncoder().encodeToString(message.getBytes(StandardCharsets.UTF_8));

       // DES加密参数
       String key = "chshopap"; // 密钥（要求8位以上）
       String ivStr = "y5jKfs6q"; // 偏移量（要求8位以上）

       // 使用DES在CBC模式下加密消息
       String encryptedData = encryptByDES(base64Message, key, ivStr);

       // 生成Basic认证令牌
       String basicToken = Base64.getEncoder().encodeToString((account + ":" + encryptedData).getBytes(StandardCharsets.UTF_8));

       // 模拟设置Authorization头
       String authorizationHeader = "Basic " + basicToken;
       System.out.println("Authorization: " + authorizationHeader);

       // 占位符：实际应用中需设置请求头（例如通过HTTP客户端）
       // apt.setRequestHeader("Authorization", authorizationHeader);
   }

   public static String encryptByDES(String message, String key, String iv) throws Exception {
       // 准备DES密钥
       DESKeySpec desKeySpec = new DESKeySpec(key.getBytes(StandardCharsets.UTF_8));
       SecretKeyFactory keyFactory = SecretKeyFactory.getInstance("DES");
       SecretKey secretKey = keyFactory.generateSecret(desKeySpec);

       // 准备偏移量
       IvParameterSpec ivSpec = new IvParameterSpec(iv.getBytes(StandardCharsets.UTF_8));

       // 初始化加密器
       Cipher cipher = Cipher.getInstance("DES/CBC/PKCS5Padding");
       cipher.init(Cipher.ENCRYPT_MODE, secretKey, ivSpec);

       // 执行加密
       byte[] encrypted = cipher.doFinal(message.getBytes(StandardCharsets.UTF_8));

       // 将加密结果转换为十六进制字符串
       StringBuilder hexString = new StringBuilder();
       for (byte b : encrypted) {
           String hex = Integer.toHexString(0xff & b);
           if (hex.length() == 1) {
               hexString.append('0');
           }
           hexString.append(hex);
       }
       return hexString.toString();
   }
}

``` 

#### GO示例
```
package main

  import (
          "crypto/cipher"
          "crypto/des"
          "encoding/base64"
          "fmt"
          "log"
          "time"
  )

  func main() {
          account := "admin"
          // Base64解码密码
          password := base64Encode("dedede") //Base64加密
          timestamp := time.Now().Unix() // 获取当前时间戳（秒级别）
          ivStr := "y5jKfs6q"             // 偏移量
          key := "chshopapi68"            // 密钥，要求8位以上

          // 1. 构造加密消息
          message := fmt.Sprintf("%s:%d:%s", account, timestamp, password)

          // 2. DES CBC加密
          encryptedMessage, err := encryptByDES(message, key, ivStr)
          if err != nil {
                  log.Fatalf("加密失败: %v", err)
          }

          // 3. 使用Base64生成认证Token
          basicToken := base64Encode(account + ":" + encryptedMessage)

          // 4. 输出Authorization头
          fmt.Println("Authorization: Basic " + basicToken)
  }

  // DES加密函数
  func encryptByDES(message, key, ivStr string) (string, error) {
          // 将密钥和IV字符串转换为字节数组
          keyBytes := []byte(key)
          ivBytes := []byte(ivStr)

          // 创建DES cipher块
          block, err := des.NewCipher(keyBytes)
          if err != nil {
                  return "", err
          }

          // 创建CBC模式的加密器
          mode := cipher.NewCBCEncrypter(block, ivBytes)

          // 将消息转换为字节数组并进行PKCS5Padding填充
          messageBytes := []byte(message)
          padding := 8 - len(messageBytes)%8
          paddedMessage := append(messageBytes, make([]byte, padding)...)

          // 执行加密
          encryptedBytes := make([]byte, len(paddedMessage))
          mode.CryptBlocks(encryptedBytes, paddedMessage)

          // 返回十六进制字符串
          return bytesToHex(encryptedBytes), nil
  }

  // 将字节数组转换为十六进制字符串
  func bytesToHex(bytes []byte) string {
          hexStr := ""
          for _, b := range bytes {
                  hexStr += fmt.Sprintf("%02x", b)
          }
          return hexStr
  }

  // Base64编码
  func base64Encode(input string) string {
          return base64.StdEncoding.EncodeToString([]byte(input))
  }

  // Base64解码
  func base64Decode(input string) string {
          decodedBytes, err := base64.StdEncoding.DecodeString(input)
          if err != nil {
                  log.Fatalf("Base64解码失败: %v", err)
          }
          return string(decodedBytes)
  }
```

### 测试账号信息 
账号：test9
密码：123456
测试环境域名：https://rtl.test.chshopapi.cc/
key：chshopap
ivstr：y5jKfs6q
默认测试的仓库区域ID（warehouse_area_id）：1