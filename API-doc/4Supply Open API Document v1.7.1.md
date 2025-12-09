# 4supply

# 4Supply Open API Document v1.7.1

<table><tr><td>更新日期</td><td>版本号</td><td>更新说明</td></tr><tr><td>2025/04/
09</td><td>v1.7.1</td><td>1. 新增了 4.2 中增值服务相关接口
2. 新增了 3.2 中当前商品是否可以启用priority Order Handling服务的标识
3. 新增了 4.3.2 中自提订单物流单号填写限制
4. 新增了 5.3.2 中outTradeNo 外部订单号字段</td></tr><tr><td>2025/01/
03</td><td>v1.7</td><td>1. 新增了 5.1.2 中的采购单分页查询新增时间筛选条件
2. 新增了 4.1.3 中订单状态中的推送失败状态 PUSH_FAILED
3. 修改了 4.3.2 自提销售单中填写物流信息格式以及物流信息填写的相关说明</td></tr><tr><td>2024/11/
28</td><td>v1.6</td><td>1. 新增了 3.2.2 中产品详情的请求参数，使用产品SKU 或产品ID 均可查询
2. 新增了 3.2.3 中产品详情的响应参数 doorPickup，产品是否支持自提
3. 新增了 4.2.2 和 4.3.2 中对于字段的限制说明
4. 新增了 销售单相关接口的部分说明：创建销售单时，会对同一订单内的不同产品进行 拆单 处理，多个产品会生成多个对应的saleOrderId
5. 修改了 4.3.2 中 labelFile 的部分说明，限制大小为 1 MB
6. 修改了 4.1.3 中订单状态说明</td></tr><tr><td>2024/11/
15</td><td>v1.5</td><td>1. 修改了 3.2.3 中 unavailablePlatform 字段数据类型 Object --> List
2. 修改了 3.2.3 中部分字段的说明
3. 修改了 5.3.2 中购买单价和交易方式 必填 --> 非必填</td></tr></table>

<table><tr><td></td><td></td><td>4. 删除了 3.2.3 中 weightKg 字段和 lengthCm 字段
5. 新增了收藏商品相关接口
6. 新增了 3.3.3 中字段 打包费 packingFee 和 自提打包费 doorPickupPackageFee</td></tr><tr><td>2024/11/
8</td><td>v1.4</td><td>1. 新增了采购单相关接口
2. 修改 3.4.3 中字段 sellerQty 的说明，更改为 卖家可售卖库存</td></tr><tr><td>2024/11/
1</td><td>v1.3</td><td>1. 修改了 2.1.2 中字段名 updateLastTime --> updateEndTime
2. 修改了 3.2.2 和 3.3.2 中 shipFrom 字段说明
3. 修改了 4.1.3 中物流信息结构
4. 修改了 Date 日期统一回传时间戳，避免出现因为时区问题导致的转换错误
5. 删除了 3.3.2 中 salesChannel 字段
6. 删除了 3.3.3 中 onsite 字段
7. 新增了 错误码说明
8. 新增测试环境和生产环境的说明
9. 新增了 4.2.3 中 labelFile 的说明</td></tr></table>

# 1. 各个环境的URL

1.1 测试环境 https://dev- open.4supply.com

1.2 生产环境 https://open.4supply.com

# 2. 平台验证规则

# 2.1 获取身份令牌

调用接口

POST：https://dev-open.4supply.com/sapi/openbuyer/auth/token

获取access token

token类型 ` Bearer`

# 2.1.1 接口描述

使用秘钥 appId & appSecret 调用接口获取access token 验证身份

# 2.1.2 请求参数

```
{
"appId":"19a7864c94189c71",（测试环境固定这个）
"appSecret":"MTlhNzg2NGM5NDE4OWM3MTE3Mjk0NzQ1NTk1NzliZGY0N2RjNDM4MzM0MjA3
"（测试环境固定这个）
}
```

<table><tr><td>字段名</td><td>数据类型</td><td>是否必填</td><td>说明</td></tr><tr><td>appId</td><td>String</td><td>Y</td><td></td></tr><tr><td>appSecret</td><td>String</td><td>Y</td><td></td></tr><tr><td>grantType</td><td>String</td><td>N</td><td></td></tr></table>

# 2.1.3 响应参数

```
{
"code": 200,
"msg": "ok",
"data": {
"userId": 4262949,
"role": "buyer",
"accessToken":
"Ney82A1X0ebjREGfZO4v0lEsXkQguGqiOvhFFkT7ZPVLZFQgVWKpgsAUMNtw7TGJW0_gg1SS
D-UwKV3T25gCQ_BmcAO5U8vIeV9pHvziH4Mj4B6F4oHK2QlXfi5Ffpmn",
"tokenType": "Bearer",
"scope": [
"server"
],
"expiresIn": 7200,
"expiresAt": 1729337949167
},
"result": true
}
```

<table><tr><td>字段名</td><td>数据类型</td><td>说明</td></tr><tr><td>code</td><td>Integer</td><td>只有200是正常</td></tr><tr><td>msg</td><td>String</td><td>信息</td></tr><tr><td>data</td><td>Object</td><td>响应的数据明细
见data详情</td></tr><tr><td>result</td><td>Boolean</td><td>是否成功</td></tr></table>

data详情

<table><tr><td>字段名</td><td>数据类型</td><td>说明</td></tr><tr><td>userIds</td><td>String</td><td>买家id</td></tr><tr><td>role</td><td>String</td><td>用户角色</td></tr><tr><td>accessToken</td><td>String</td><td>Token</td></tr><tr><td>expiresIn</td><td>Integer</td><td>有效期，单位秒</td></tr><tr><td>expiresAt</td><td>Date</td><td>过期时间</td></tr></table>

# 2.2 验证请求头

所有接口会校验请求头参数

<table><tr><td>key</td><td>value示例</td><td>说明</td><td>是否必须</td></tr><tr><td>appid</td><td>20010</td><td>appid固定值为20010</td><td>Y</td></tr><tr><td>Authorizatio
n</td><td>Bearer
RkqVdT7ZYm87_5HV5cvFKDrrXr
qK4ZdnLXy34pOhxmdw8lcB3Vx
-KIDq6m1thNZUEI-UrVB7-
ZqFTIdWacV57U3gnq6Zn6gulsi
QaLqaFfb-koW-
Mu0AObmysgn0sRZg</td><td>认证通过颁发的token
值为Bearer空格后面拼的验证接口拿到的
accessToken
不参与签名生成</td><td>Y</td></tr><tr><td></td><td></td><td></td><td></td></tr></table>

<table><tr><td>method</td><td>GET</td><td>请求方式，大写GET 或 POST</td><td>Y</td></tr><tr><td>uri</td><td>/api/xxx/a?q=query params</td><td>请求路径。此路径包含query参数，参数顺序同实际调用顺序。</td><td>Y</td></tr><tr><td>body</td><td>DD4034CF589875FEE94D34410 D98867A</td><td>对请求体转字符串取md5值。GET请求 body不参与签名计算。form-data中的file 使用md5值作为value。对参数key进行字典排序，使用&=拼接参数转为queryString格式字符串，最后对拼接字符串取md5值。
(大写)</td><td>Y</td></tr><tr><td>source</td><td>abcdxxxxxxx</td><td>open api生成的 client id (appid)</td><td>Y</td></tr><tr><td>source-type</td><td>10</td><td>系统类型，固定值10</td><td>Y</td></tr><tr><td>v</td><td>2.0</td><td>api版本号，固定值2.0</td><td>Y</td></tr><tr><td>timestamp</td><td>1727663261884</td><td>时间戳（毫秒）与服务器误差不能超过3分钟</td><td>Y</td></tr><tr><td>timezone</td><td>28800</td><td>与UTC相差的秒数</td><td>Y</td></tr><tr><td>x-once</td><td>123adf456aof2131ew</td><td>10-32位随机字符串</td><td>Y</td></tr><tr><td>sign</td><td>C1DFC961C4D472A22764B17A0 504809A</td><td>根据算法生成最终的签名（大写）</td><td>Y</td></tr></table>

# 示例代码

其中请求头Authorization不参与签名的生成

Java生成签名示例代码（JDK17）

```
public class SignUtil {
private static final String CHARACTERS =
"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
private SignUtil(){}
public static final Logger log =
LoggerFactory.getLogger(SignUtil.class);
public static String signature(SignModel signModel) {
Map<String,String> signMap =
JsonUtil.parseObject(JsonUtils.toJsonString(signModel), new TypeReference<>
() {
});
TreeMap<String, Object> treeMap = new TreeMap<>(signMap);
return getSignBySHA256(treeMap);
}
public static String buildString(SortedMap<String, Object> params) {
StringBuilder builder = new StringBuilder();
for (Map.Entry<String, Object> entry : params.entrySet()) {
builder.append(entry.getKey()).append("=").append(entry.getValue()).append("
&");
}
return builder.toString();
}
public static String getSignBySHA256(SortedMap<String, Object> params) {
String s = buildString(params);
return sha256(s);
}
public static String md5(String source) {
MessageDigest md;
try {
md = MessageDigest.getInstance("MD5");
} catch (NoSuchAlgorithmException e) {
log.error("", e);
return null;
}
byte[] digest = md.digest(source.getBytes(StandardCharsets.UTF_8));
return byte2hex(digest);
}
public static String sha256(String source) {
MessageDigest md;
try {
md = MessageDigest.getInstance("SHA-256");
} catch (NoSuchAlgorithmException e) {
log.error("", e);
return null;
}
byte[] digest = md.digest(source.getBytes(StandardCharsets.UTF_8));
return byte2hex(digest);
}
public static String byte2hex(byte[] data) {
StringBuilder buf = new StringBuilder();
for (byte b : data) {
String hex = Integer.toHexString(b & 0xFF);
if (hex.length() == 1) {
buf.append("0");
}
buf.append(hex.toUpperCase());
}
return buf.toString();
}
public static String generateNonce(int length) {
Random random = new Random();
StringBuilder sb = new StringBuilder();
for (int j = 0; j < length; j++) {
int index = random.nextInt(CHARACTERS.length());
sb.append(CHARACTERS.charAt(index));
}
return sb.toString();
}
}
@Data
public class SignModel {
/**
* appid固定值为20010
*/
private String appid;
/**
* 调⽤⽅法 POST | GET
*/
private String method;
/**
* 随机字符，混淆签名 (10-32位)
*/
@JsonProperty("x-nonce")
private String nonce;
/**
* 请求地址（包含query参数）
*/
private String uri;
/**
* 请求体（json请求体取md5值. form-data请求参数转为queryString后取md5值, file
参数转为md5值参与计算）
*/
private String body;
/**
* open api⽣成的 client id (appid)
*/
private String source;
/**
* 系统类型，固定值10
*/
@JsonProperty("source-type")
private String sourceType;
/**
* api版本号，固定值2.0
*/
private String v;
/**
* 毫秒级时间戳
*/
private String timestamp;
/**
* 与UTC相差的秒数
*/
private String timezone;
}
@UtilityClass
public class JsonUtil {
private static final ObjectMapper OBJECT_MAPPER = new ObjectMapper();
public static ObjectMapper getObjectMapper() {
return OBJECT_MAPPER;
}
public static String toJsonString(Object object) {
if (ObjectUtil.isNull(object)) {
return null;
}
try {
return OBJECT_MAPPER.writeValueAsString(object);
} catch (JsonProcessingException e) {
throw new RuntimeException(e);
}
}
public static <T> T parseObject(String text, Class<T> clazz) {
if (StringUtils.isBlank(text)) {
return null;
}
try {
return OBJECT_MAPPER.readValue(text, clazz);
} catch (IOException e) {
throw new RuntimeException(e);
}
}
public static <T> T parseObject(String text, TypeReference<T>
tTypeReference) {
if (StringUtils.isBlank(text)) {
return null;
}
try {
return OBJECT_MAPPER.readValue(text, tTypeReference);
} catch (IOException e) {
throw new RuntimeException(e);
}
}
}
@Slf4j
public class TestGenerateSign {
private static final String CHARACTERS =
"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
private static final int STRING_LENGTH = 10;
public static void main(String[] args) {
SignModel signModel = new SignModel();
signModel.setAppid("20010");
signModel.setMethod("GET");
signModel.setUri("/abc/def");
signModel.setBody("");
signModel.setSource("appid123456");
signModel.setSourceType("10");
signModel.setV("2.0");
signModel.setTimestamp(String.valueOf(System.currentTimeMillis()));
signModel.setTimezone("28800");
String nonce = generateNonce(STRING_LENGTH);
signModel.setNonce(nonce);
System.out.println(SignUtil.signature(signModel));
}
public static String generateNonce(int length) {
Random random = new Random();
StringBuilder sb = new StringBuilder();
for (int j = 0; j < length; j++) {
int index = random.nextInt(CHARACTERS.length());
sb.append(CHARACTERS.charAt(index));
}
return sb.toString();
}
}
```

将上述参数按照 key 的字典顺序（升序）来排序，key 和 value 之间用 = 连接，每个参数之间用 & 连接，得到如下字符串：

```
appid=20010&body=675C299BCD88EDF31C09C48F9C7FBA4B&method=POST&source=19a7864 c94189c71&sourcetype=10×tamp=1729607388428&timezone=28800&uri=/sapi/openbuyer/buyer/pro duct/pagingQuery&v=2.0&x-nonce=LGzimLGZPi&
```

接着对上一步得到的字符串使用 SHA- 256 算法加密，对结果使用16进制编码。

例如，对于上述的例子，通过签名算法得到的签名为

`1AD03B60462EA478C2E5A88596617DBE1EBFB8E063911527663E82400944677E`

# 2.3 限流规则

平台对所有接口统一限流为5qps，如有特殊需求请与平台技术支持沟通

# 3. 产品相关接口

# 3.1 产品列表

# 3.1.1 接口描述

获取所有产品列表

# 3.1.2 请求参数

```
POST https://devopen.4supply.com/sapi/openbuyer/buyer/product/pagingQuery
```

```
{
"pageNum": 1,
"pageSize": 10,
"updateStartTime": 1730368802000,
"updateEndTime": 1730408478000
}
```

<table><tr><td>字段名</td><td>数据类型</td><td>是否必填</td><td>说明</td></tr><tr><td>pageNum</td><td>Integer</td><td>N</td><td></td></tr><tr><td>pageSize</td><td>Integer</td><td>N</td><td></td></tr><tr><td>updateStartTime</td><td>Date</td><td>N</td><td>如果填了updateStartTime，那updateEndTime也必须填</td></tr><tr><td>me</td><td>Date</td><td>N</td><td></td></tr></table>

<table><tr><td>updateEndTime</td><td>e</td><td></td><td>如果填了updateEndTime，那updateStartTime也必须填</td></tr></table>

# 3.1.3 响应参数

```
{
"code": 200,
"msg": "ok",
"data": {
"pageMeta": {
"total": 22,
"limit": 10,
"page": 1
},
"list": [
{
"sku": "S09994449",
"updateDate": 1730408478000
},
{
"sku": "S09994467",
"updateDate": 1730408478000
},
{
"sku": "S09994477",
"updateDate": 1730408478000
},
{
"sku": "S09994479",
"updateDate": 1730408478000
},
{
"sku": "S09994480",
"updateDate": 1730408478000
},
{
"sku": "S09994484",
"updateDate": 1730408478000
},
{
"sku": "N00100061",
"updateDate": 1730368802000
},
{
"sku": "S09994493",
"updateDate": 1730408478000
},
{
"sku": "S09994500",
"updateDate": 1730408478000
},
{
"sku": "S09994501",
"updateDate": 1730408478000
}
]
},
"result": true
}
```

<table><tr><td>40</td><td rowspan="2">"sku": "S09994493",
"updateDate": 1730408478000</td></tr><tr><td>41</td></tr><tr><td>42</td><td>},</td></tr><tr><td>43</td><td>{</td></tr><tr><td>44</td><td>"sku": "S09994500",
"updateDate": 1730408478000</td></tr><tr><td>45</td><td>},</td></tr><tr><td>46</td><td>{</td></tr><tr><td>47</td><td rowspan="2">"sku": "S09994501",
"updateDate": 1730408478000</td></tr><tr><td>48</td></tr><tr><td>49</td><td>}</td></tr><tr><td>50</td><td>}</td></tr><tr><td>51</td><td>]</td></tr><tr><td>52</td><td>},</td></tr><tr><td>53</td><td>"result": true</td></tr><tr><td>54</td><td>}</td></tr></table>

data详情

<table><tr><td>字段名</td><td>数据类型</td><td>说明</td></tr><tr><td>code</td><td>Integer</td><td>只有200是正常</td></tr><tr><td>msg</td><td>String</td><td>信息</td></tr><tr><td>data</td><td>Object</td><td>响应的数据明细
见data详情</td></tr><tr><td>result</td><td>Boolean</td><td>是否成功</td></tr></table>

<table><tr><td>字段名</td><td>数据类型</td><td>说明</td></tr><tr><td>pageMeta</td><td>Object</td><td>见pageMeta详情</td></tr><tr><td>list</td><td>List<productpricerespvo=""></td><td>见ProductPricerespvo详情</td></tr></table>

</productpricerespvo></productpricerespvo>

# pageMeta详情

<table><tr><td>字段名</td><td>数据类型</td><td>说明</td></tr><tr><td>total</td><td>Integer</td><td>总条数</td></tr><tr><td></td><td></td><td></td></tr></table>

<table><tr><td>limit</td><td>Integer</td><td>pagesize</td></tr><tr><td>page</td><td>Integer</td><td>pagenum</td></tr></table>

# ProductPriceRespVO详情

<table><tr><td>字段名</td><td>数据类型</td><td>说明</td></tr><tr><td>sku</td><td>String</td><td>产品SKU, itemcode</td></tr><tr><td>updateDate</td><td>Date</td><td>更新时间</td></tr></table>

# 3.2 产品详情

# 3.2.1 接口描述

查询指定SKU的产品详情

# 3.2.2 请求参数

```
POST https://devopen.4supply.com/sapi/openbuyer/buyer/product/queryDetail
```

代码块

```1
{
"itemIds": [
480003
],
"itemCodes": [
"S02590007"
]
}
```

<table><tr><td>字段名</td><td>数据类型</td><td>是否必填</td><td>说明</td></tr><tr><td>itemCodes</td><td>List<strin g=""></td><td>N</td><td>产品SKU, itemcode</td></tr><tr><td>itemIds</td><td>List<long ></td><td>N</td><td>产品id
itemIds和itemCodes两者至少填一个，可以同时填</td></tr></table>

</strin></strin></strin></strin></strin></strin></strin></strin></strin></strin></strin></strin></strin></strin></strin></strin></strin></strin></strin></strin></strin></strin></strin></strin></strin></strin></strin></strin></strin></strin></strin></strin></strin></strin></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></str></str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></Str></str>

# 3.2.3 响应参数

# 代码块
```
{
"code": 200,
"msg": "ok",
"data": [
{
"id": 520383,
"code": "S02590007",
"mpn": "N44444444",
"weightUnit": "lb",
"lengthUnit": "in",
"weight": 66,
"length": 66,
"width": 66,
"height": 66,
"title": "商品3",
"desc": "<p>1</p>",
"productFeatures": "",
"imageUrls": [
"https://s3.springbeetle.eu/dev-de-s3-
flexispot/12350966/2024/11/14/1731572405545pexels-photo-29278100.jpeg"
],
"catId": 2043,
"catName": "Fitness",
"itemType": "General item",
"customized": 1,
"oriCountry": "Andorra",
"assembledWeight": 1,
"assembledLength": 10,
"assembledWidth": 10,
"assembledHeight": 10,
"associateProductList": [
],
"attributes": {
"mainColor": "Ancient Oak",
"mainMaterial": "ABS+PC"
},
"whiteLabelReady": 1,
"doorPickup": 1,
"onstatus": "onShelf",
"storeName": "张⼀BD卖家客⼾的店铺",
"storeCode": "S0259",
"sellerReturnRate": "low",
"updatedAt": 1732711698000,
"onsite": false,
"itemSpecification": false
},
{
"id": 480003,
"code": "L00160017",
"mpn": "TSR001-166",
"weightUnit": "lb",
"lengthUnit": "in",
"title": "Dining Table Sintered Stone Table Marble Table
Porcelain Dining Table for Kitchen, Living Room, 63 inch White Table
Only",
"desc": "",
"productFeatures": "Sintered stone Dining Table top with
Carbon steel legs:|Sintered stone / Porcelain /Marble Table top:|Heat
resistant, Stain resistant, Scratch resistant:|63'' White Table top
\nFor 4 Persons seat:|Easy to Assemble:",
"imageUrls": [
"https://s3.springbeetle.eu/dev-de-s3-
flexispot/4255727/2024/8/27/1724747488520下载.png"
],
"catId": 10053,
"catName": "Dining Tables",
"itemType": "Combo item",
"customized": 0,
"oriCountry": "China",
"assembledWeight": 108,
"assembledLength": 63,
"assembledWidth": 31.5,
"assembledHeight": 29.5,
"associateProductList": [
],
"attributes": {
"mainColor": "Mountain Grain White",
"mainMaterial": "Sintered Stone"
},
"whiteLabelReady": 1,
"comboInfo": [
"S01040002 1",
"S01040004 1"
],
"doorPickup": 1,
"onstatus": "onShelf"
```

<table><tr><td>字段名</td><td>数据类型</td><td>说明</td></tr><tr><td>code</td><td>Integer</td><td>只有200是正常</td></tr><tr><td>msg</td><td>String</td><td>信息</td></tr><tr><td>data</td><td>Object</td><td>响应的数据明细
见data详情</td></tr><tr><td>result</td><td>Boolean</td><td>是否成功</td></tr></table>

# data详情

<table><tr><td>字段名</td><td>数据类型</td><td>说明</td></tr><tr><td>code</td><td>String</td><td>产品SKU, itemcode</td></tr><tr><td>mpn</td><td>String</td><td>mpn码</td></tr><tr><td>onsite</td><td>Boolean</td><td>是否自有仓产品 onsite</td></tr><tr><td>weightUnit</td><td>String</td><td>重量单位</td></tr><tr><td>lengthUnit</td><td>String</td><td>长度单位</td></tr><tr><td>weight</td><td>BigDecimal</td><td>包装重量 itemType为General item才有</td></tr><tr><td>length</td><td>BigDecimal</td><td>包装长度 itemType为General item才有</td></tr><tr><td>width</td><td>BigDecimal</td><td>包装宽度 itemType为General item才有</td></tr><tr><td>height</td><td>BigDecimal</td><td>包装高度 itemType为General item才有</td></tr><tr><td>title</td><td>String</td><td>产品名称</td></tr><tr><td>desc</td><td>String</td><td>产品描述</td></tr><tr><td>productFeatures</td><td>String</td><td>产品特点</td></tr><tr><td></td><td></td><td></td></tr></table>

<table><tr><td>catId</td><td>Long</td><td>类目ID</td></tr><tr><td>catName</td><td>String</td><td>类目名称</td></tr><tr><td>itemType</td><td>String</td><td>产品类型
General item
Combe item</td></tr><tr><td>overSizeFlag</td><td>Boolean</td><td></td></tr><tr><td>partFlag</td><td>Boolean</td><td></td></tr><tr><td>upc</td><td>String</td><td></td></tr><tr><td>customized</td><td>Integer</td><td>是否可定制
0不支持 1支持</td></tr><tr><td>oriCountry</td><td>String</td><td>原产地</td></tr><tr><td>lithiumBatteryContained</td><td>Integer</td><td></td></tr><tr><td>assembledLength</td><td>BigDecimal</td><td>组装长度</td></tr><tr><td>assembledWidth</td><td>BigDecimal</td><td>组装宽度</td></tr><tr><td>assembledHeight</td><td>BigDecimal</td><td>组装高度</td></tr><tr><td>assembledWeight</td><td>BigDecimal</td><td>组装重量</td></tr><tr><td>customList</td><td>String[]</td><td></td></tr><tr><td>associateProductList</td><td>String[]</td><td>关联商品列表
Itemcode + mainColor + mainMaterial</td></tr><tr><td>certificationList</td><td>String[]</td><td>产品认证文件、原创文件地址</td></tr><tr><td>fileUrls</td><td>String[]</td><td>文档素材、产品说明书地址</td></tr><tr><td>fileUrlvideoUrls</td><td>String[]</td><td>相关视频地址</td></tr><tr><td>attributes</td><td>Object</td><td>产品属性
见attributes详情</td></tr><tr><td>whiteLabelReady</td><td>Integer</td><td>选择“1”，即标记为白牌产品确认此产品产品本身，其包装，说明书上无任何品牌和公司</td></tr></table>

<table><tr><td></td><td></td><td>名等标识以及平台信息且必须上传包装箱图片</td></tr><tr><td>doorPickup</td><td>Integer</td><td>是否支持自提
1：支持
0：不支持</td></tr><tr><td>comboInfo</td><td>String[]</td><td>组合商品的信息</td></tr><tr><td>crepresaleTimeatedAt</td><td>Date</td><td></td></tr><tr><td>onstatus</td><td>String</td><td>上架状态</td></tr><tr><td>storeName</td><td>String</td><td>店铺名字</td></tr><tr><td>storeCode</td><td>String</td><td>店铺编码</td></tr><tr><td>sellerReturnRate</td><td>String</td><td>卖家退货率</td></tr><tr><td>sellerReturnApprovalRate</td><td>String</td><td>卖家退货同意率</td></tr><tr><td>sellerMessageResponseRa
te</td><td>String</td><td>卖家消息回复率</td></tr><tr><td>updatedAt</td><td>Date</td><td>更新时间</td></tr><tr><td>unAvailablePlatform</td><td>List<unavailablePlatf
orm></td><td>见unAvailablePlatform详情</td></tr><tr><td>itemSpecification</td><td>Boolean</td><td>如果是false且是非onsite则代表可以勾选
priority Order Handling Fee</td></tr></table>

</unavailablePlatform></unavailablePlatform></unavailablePlatform></unavailablePlatform></unavailablePlatform></unavailablePlatform></unavailablePlatform></unavailablePlatform></unavailablePlatform></unavailablePlatform></unavailablePlatform></unavailablePlatform></unavailablePlatform></unavailablePlatform></unavailablePlatform></unavailablePlatform></unavailablePlatform></unavailablePlatform></unavailablePlatform></unavailablePlatform></unavailablePlatform></unavailablePlatform></unavailablePlatform></unavailablePlatform></unavailablePlatform></unavailablePlatform>

# attributes详情

<table><tr><td>字段名</td><td>类型</td><td>说明</td></tr><tr><td>mainColor</td><td>String</td><td>主要颜色</td></tr><tr><td>scene</td><td>String</td><td>场景</td></tr><tr><td>mainMaterial</td><td>String</td><td>主要材料</td></tr></table>

# unavailablePlatform详情

<table><tr><td>字段名</td><td>类型</td><td>说明</td></tr><tr><td></td><td></td><td></td></tr></table>

<table><tr><td>id</td><td>Integer</td><td></td></tr><tr><td rowspan="14">name</td><td rowspan="14">Integer</td><td>1 Wayfair</td></tr><tr><td>2 Amazon</td></tr><tr><td>3 Walmart</td></tr><tr><td>4 Ecommerce Website</td></tr><tr><td>5 eBay</td></tr><tr><td>6 Overstock</td></tr><tr><td>7 Home Depot</td></tr><tr><td>8 Lowe's</td></tr><tr><td>9 Wish</td></tr><tr><td>10 Newegg</td></tr><tr><td>11 AliExpress</td></tr><tr><td>12 SHEIN</td></tr><tr><td>13 temu</td></tr><tr><td>14 Tiktok</td></tr></table>

# 3.3 产品价格

# 3.3.1 接口描述

查询指定SKU的商品相关价格

# 3.3.2 请求参数

```
POST https://devopen.4supply.com/sapi/openbuyer/buyer/product/queryPrice
```
```
{
"itemCodes": [
"S01950004"
]
}
```

<table><tr><td>字段名</td><td>数据类型</td><td>是否必填</td><td>说明</td></tr><tr><td>itemCodes</td><td>List<strin g=""></td><td>Y</td><td>产品SKU, itemcode</td></tr></table>

</strin>

# 3.3.3 响应参数

# 代码块

```
{
"code": 200,
"msg": "ok",
"data": [
{
"code": "S01950004",
"currency": "USD",
"price": 20,
"shippingFee": 10,
"discountedPrice": 18,
"rebatesPrice": [
"{minQuantity=5, maxQuantity=10, price=3.000000}",
"{minQuantity=11, maxQuantity=20, price=2.000000}",
"{minQuantity=21, maxQuantity=50, price=1.000000}"
],
"marginPrice": [
"{minQuantity=2, maxQuantity=4, price=10.000000}"
],
"skuAvailable": true,
"storeName": "maomingcompany2",
"packingFee": 20,
"doorPickupPackageFee": 20,
"storeCode": "S0195",
"sellerReturnRate": "low"
}
],
"result": true
}
```

<table><tr><td>字段名</td><td>数据类型</td><td>说明</td></tr><tr><td>code</td><td>integer</td><td>只有200是正常</td></tr><tr><td>msg</td><td>String</td><td>信息</td></tr><tr><td>data</td><td>Object</td><td>具体返回的内容</td></tr></table>

<table><tr><td></td><td></td><td>返回的List<ProductPriceRespVO>,见 ProductPriceRespVO详情</td></tr><tr><td>result</td><td>Boolean</td><td>是否成功</td></tr></table>

# ProductPriceRespVO详情

<table><tr><td>字段名</td><td>数据类型</td><td>说明</td></tr><tr><td>code</td><td>String</td><td>产品SKU, itemcode</td></tr><tr><td>currency</td><td>String</td><td>货币种类</td></tr><tr><td>price</td><td>BigDecimal</td><td>价格</td></tr><tr><td>shippingFee</td><td>BigDecimal</td><td>产品运输价格</td></tr><tr><td>exclusivePrice</td><td>BigDecimal</td><td>卖方设置的专属价格</td></tr><tr><td>discountedPrice</td><td>BigDecimal</td><td>折扣价格</td></tr><tr><td>promotionFrom</td><td>String</td><td>促销开始时间</td></tr><tr><td>promotionTo</td><td>String</td><td>促销结束时间</td></tr><tr><td>map</td><td>BigDecimal</td><td>最低广告价格</td></tr><tr><td>futureMapPrice</td><td>BigDecimal</td><td>未来最低广告价格</td></tr><tr><td>effectMapTime</td><td>Date</td><td>未来最低广告价格的持续时间</td></tr><tr><td>spotPrice</td><td>String[]</td><td>以数组形式返回阶梯价格和对应的价格范围。</td></tr><tr><td>rebatesPrice</td><td>String[]</td><td>以数组形式返回返利价格和对应的价格范围。</td></tr><tr><td>marginPrice</td><td>String[]</td><td>以数组形式返回保证金价格和相应的价格范围。</td></tr><tr><td>futurePrice</td><td>String[]</td><td>以数组形式返回未来价格和相应的价格范围。</td></tr><tr><td>skuAvailable</td><td>Boolean</td><td>显示此卖家的 SKU 是否可供用户使用。</td></tr><tr><td>storeName</td><td>String</td><td>店铺名称</td></tr><tr><td>packingFee</td><td>BigDecimal</td><td>打包费
一件代发费用包括 shippingFee + packingFee</td></tr><tr><td>doorPickupPackageFee</td><td>BigDecimal</td><td>自提打包费</td></tr></table>

<table><tr><td></td><td></td><td>自提费用包括 doorPickupPackageFee</td></tr><tr><td>storeCode</td><td>String</td><td>店铺代码</td></tr><tr><td>sellerReturnRate</td><td>String</td><td>卖家退货率</td></tr><tr><td>sellerReturnApprovalRate</td><td>String</td><td>卖家退货同意率</td></tr><tr><td>sellerMessageResponseRate</td><td>String</td><td>卖家消息回复率</td></tr></table>

# 3.4 产品数量

# 3.4.1 接口描述

查询指定SKU的商品相关数量

# 3.4.2 请求参数

```代码块
POST https://dev-open.4supply.com/sapi/openbuyer/buyer/product/queryNum
```

```
{
"itemCodes": [
"S09994550"
]
}

```


<table><tr><td>字段名</td><td>数据类型</td><td>是否必填</td><td>说明</td></tr><tr><td>itemCodes</td><td>List<string></td><td>Y</td><td>产品SKU, itemcode</td></tr></table>

</string>

# 3.4.3 响应参数
```
{
"code": 200,
"msg": "ok",
"data": [
{
"code": "S09994550",
"realQty": 374,
"qtyDetail": {
"buyerQty": 0,
"sellerQty": 374,
"sellerQtyDistribution": [
{
"warehouseCode": "CESHI",
"sellerAvailableQtyMax": 298
},
{
"warehouseCode": "HOU03",
"sellerAvailableQtyMax": 76
}
]
}
],
"result": true
}

```

<table><tr><td>字段名</td><td>数据类型</td><td>说明</td></tr><tr><td>data</td><td>Object</td><td>响应的数据明细
见data详情</td></tr><tr><td>code</td><td>Integer</td><td>只有200是正常</td></tr><tr><td>msg</td><td>String</td><td>信息</td></tr><tr><td>result</td><td>Boolean</td><td>是否成功</td></tr></table>

# data详情

<table><tr><td>字段名</td><td>数据类型</td><td>说明</td></tr><tr><td>code</td><td>String</td><td>产品SKU, itemcode</td></tr><tr><td>realQty</td><td>Integer</td><td>产品总数量</td></tr><tr><td>qtyDetail</td><td>Object</td><td>库存详情</td></tr></table>

<table><tr><td></td><td></td><td>见qtyDetail详情</td></tr><tr><td>nextArrival</td><td>Object</td><td>下批到货详情
见nextArrival详情</td></tr></table>

# qtyDetail详情

<table><tr><td>字段名</td><td>数据类型</td><td>说明</td></tr><tr><td>buyerQty</td><td>Integer</td><td>买家库存</td></tr><tr><td>sellerQty</td><td>Integer</td><td>卖家库存</td></tr><tr><td>buyerQtyDistribution</td><td>List<buyerQtyDistribution=""></td><td>见 BuyerQtyDistribution详情</td></tr><tr><td>sellerQtyDistribution</td><td>List<sellerQtyDistribution=""></td><td>见SellerQtyDistribution详情</td></tr></table>

</sellerQuantityDistribution></buyerQtyDistribution></buyerQtyDistribution>

# BuyerQtyDistribution详情

<table><tr><td>字段名</td><td>数据类型</td><td>说明</td></tr><tr><td>warehouseCode</td><td>String</td><td>买家仓库</td></tr><tr><td>buyerAvailableQty</td><td>Integer</td><td>买家可用库存</td></tr></table>

# SellerQtyDistribution详情

<table><tr><td>字段名</td><td>数据类型</td><td>说明</td></tr><tr><td>warehouseCode</td><td>String</td><td>卖家仓库</td></tr><tr><td>sellerAvailableQtyMin</td><td>Integer</td><td>卖家最小可用库存</td></tr><tr><td>sellerAvailableQtyMax</td><td>Integer</td><td>卖家最大可用库存</td></tr></table>

# nextArrival详情

<table><tr><td>字段名</td><td>数据类型</td><td>说明</td></tr><tr><td>nextArrivalDate</td><td>Date</td><td>“nextArrivalDate”和“nextArrivalDateEnd”之间的日期是预计下次到达日期的范围。</td></tr><tr><td>nextArrivalDateEnd</td><td>Date</td><td></td></tr><tr><td>nextArrivalQty</td><td>Integer</td><td>“nextArrivalQty”和“nextArrivalMax”之间的数量是预计下次到达的库存数量。</td></tr><tr><td>nextArrivalQtyMax</td><td>Integer</td><td></td></tr></table>

# 3.5 买家库存

# 3.5.1 接口描述

查询买家库存

# 3.5.2 请求参数

```代码块
POST https://dev- open.4supply.com/sapi/openbuyer/buyer/product/queryBuyerInventory
```

```
{
"itemCode": "S09994550"
}
```


<table><tr><td>字段名</td><td>数据类型</td><td>是否必填</td><td>说明</td></tr><tr><td>itemCode</td><td>String</td><td>Y</td><td>产品SKU, itemcode</td></tr></table>

# 3.5.3 响应参数

```
{
"code": 200,
"msg": "ok",
"data": [
{
"warehouseCode": "CAT",
"buyerAvailableQty": 21
}
],
"result": true
}

```

<table><tr><td>字段名</td><td>数据类型</td><td>说明</td></tr><tr><td>data</td><td>Object</td><td>响应的数据明细 List<BuyerQtyDistribution> 
见BuyerQtyDistribution详情</td></tr><tr><td>code</td><td>integer</td><td>只有200是正常</td></tr><tr><td>msg</td><td>String</td><td>信息</td></tr><tr><td>result</td><td>Boolean</td><td>是否成功</td></tr></table>

</BuyerQtyDistribution>

BuyerQtyDistribution详情

<table><tr><td>字段名</td><td>数据类型</td><td>说明</td></tr><tr><td>warehouseCode</td><td>String</td><td>买家仓库代码</td></tr><tr><td>buyerAvailableQty</td><td>Integer</td><td>买家可用库存</td></tr></table>
# 5. 收藏相关接口

# 5.1 增加收藏商品

# 5.1.1 接口描述

将指定商品加入心愿单

# 5.1.2 请求参数

```
POST https://dev-open.4supply.com/sapi/openbuyer/buyer/wish/addWish
```

```
样例1 新增收藏商品
{
"addFlag":1,
"itemid":348001
}
样例2 删除收藏商品
{
"addFlag":0,
"itemidList": [
348001
],
"deleteAll": false
}

```

<table><tr><td>字段名</td><td>数据类型</td><td>是否必填</td><td>说明</td></tr><tr><td>itemid</td><td>Long</td><td>N</td><td>产品id
addFlag为1时, itemid必填
参考样例1</td></tr><tr><td>addFlag</td><td>Integer</td><td>Y</td><td>增加标识
1: 代表新增
0: 代表删除</td></tr><tr><td>itemidList</td><td>List<Long ></td><td>N</td><td>取消收藏商品的itemid列表
addFlag为0时, itemidList必填
参考样例2</td></tr><tr><td>deleteAll</td><td>Boolean</td><td>N</td><td>是否全部删除
addFlag为0时, deleteAll必填
参考样例2</td></tr></table>

# 5.1.3 响应参数
```
样例1 新增收藏商品响应
{
"code": 200,
"msg": "ok",
"data": {
"id": 236078,
"itemid": 348001,
"catid": 2041,
"storeid": 4255727,
"addQty": 9486,
"addPrice": 15.000000,
"buyerid": 12348944,
"storeName": "Berton",
"storeCode": "L0016"
},
"result": true
}
样例2 删除⼼愿单响应
{
"code":200,
"msg":"ok",
"result":true
}
```

<table><tr><td>字段名</td><td>数据类型</td><td>说明</td></tr><tr><td>code</td><td>Integer</td><td>只有200是正常</td></tr><tr><td>msg</td><td>String</td><td>信息</td></tr><tr><td>data</td><td>Object</td><td>响应的数据明细
见data详情</td></tr><tr><td>result</td><td>Boolean</td><td>是否成功</td></tr></table>

# data详情

<table><tr><td>字段名</td><td>数据类型</td><td>说明</td></tr><tr><td>id</td><td>Long</td><td>收藏商品id</td></tr><tr><td>itemid</td><td>Long</td><td>商品id</td></tr><tr><td></td><td></td><td></td></tr></table>

<table><tr><td>catid</td><td>Long</td><td>类目id</td></tr><tr><td>storeid</td><td>Long</td><td>店铺id</td></tr><tr><td>addQty</td><td>Integer</td><td>添加时的数量</td></tr><tr><td>addPrice</td><td>BigDecimal</td><td>添加时的价格</td></tr><tr><td>buyerid</td><td>Long</td><td>买家id</td></tr><tr><td>storeName</td><td>String</td><td>店铺名字</td></tr><tr><td>storeCode</td><td>String</td><td>店铺编码</td></tr></table>

# 5.2 收藏商品列表

# 5.2.1 接口描述

查看所有的收藏商品

# 5.2.2 请求参数
```
POST https://dev-open.4supply.com/sapi/openbuyer/buyer/wish/pagingWish
```
```
{
"pageNum": 1,
"pageSize": 10
}
```

<table><tr><td>字段名</td><td>数据类型</td><td>是否必填</td><td>说明</td></tr><tr><td>pageNum</td><td>Integer</td><td>N</td><td></td></tr><tr><td>pageSize</td><td>Integer</td><td>N</td><td></td></tr></table>

# 5.2.3 响应参数
```
{
"code": 200,
"msg": "ok",
"data": {
"pageMeta": {
"total": 24,
"limit": 10,
"page": 3
},
"list": [
{
"id": 236085,
"createdAt": 1731058346000,
"updatedAt": 1731058346000,
"isDeleted": 0,
"deletedAt": 0,
"addPrice": 30,
"addQty": 256,
"wishProductVO": {
"storeid": 4192255,
"storeCode": "S0999",
"storeName": "乐仓对接指定店铺",
"realQty": 0,
"occupyQty": 1,
"price": 25.66,
"onsite": false
}
},
{
"id": 236084,
"createdAt": 1731058342000,
"updatedAt": 1731058342000,
"isDeleted": 0,
"deletedAt": 0,
"addPrice": 100,
"addQty": 86,
"wishProductVO": {
"storeid": 4192255,
"storeCode": "S0999",
"storeName": "乐仓对接指定店铺",
"realQty": 72,
"occupyQty": 3,
"price": 100,
"onsite": false
}
},
{
"id": 236083,
"createdAt": 1731058340000,
"updatedAt": 1731058340000,
"isDeleted": 0,
"deletedAt": 0,
"addPrice": 100,
"addQty": 4,
"wishProductVO": {
"storeid": 4192255,
"storeCode": "S0999",
"storeName": "乐仓对接指定店铺",
"realQty": 0,
"occupyQty": 3,
"price": 100,
"onsite": false
}
},
{
"id": 236077,
"createdAt": 1730988548000,
"updatedAt": 1730988548000,
"isDeleted": 0,
"deletedAt": 0,
"addPrice": 100,
"addQty": 144,
"wishProductVO": {
"storeid": 4192255,
"storeCode": "S0999",
"storeName": "乐仓对接指定店铺",
"realQty": 479,
"occupyQty": 14,
"price": 100,
"onsite": false
}
}
]
},
"result": true
}
```

<table><tr><td>字段名</td><td>数据类型</td><td>说明</td></tr><tr><td>code</td><td>Integer</td><td>只有200是正常</td></tr><tr><td>msg</td><td>String</td><td>信息</td></tr></table>

data详情

<table><tr><td>data</td><td>Object</td><td>具体返回的内容
见data详情</td></tr><tr><td>result</td><td>Boolean</td><td>是否成功</td></tr></table>

<table><tr><td>字段名</td><td>数据类型</td><td>说明</td></tr><tr><td>pageMeta</td><td>Object</td><td>见pageMeta详情</td></tr><tr><td>list</td><td>List<w>见WishVO详情</td><td></td></tr></table>

</w>

# pageMeta详情

<table><tr><td>字段名</td><td>数据类型</td><td>说明</td></tr><tr><td>total</td><td>Integer</td><td>总条数</td></tr><tr><td>limit</td><td>Integer</td><td>pagesize</td></tr><tr><td>page</td><td>Integer</td><td>pagenum</td></tr></table>

</w>

# WishVO详情

<table><tr><td>字段名</td><td>数据类型</td><td>说明</td></tr><tr><td>id</td><td>Long</td><td>id</td></tr><tr><td>createdAt</td><td>Date</td><td>创建日期</td></tr><tr><td>updatedAt</td><td>Date</td><td>更新日期</td></tr><tr><td>isDeleted</td><td>Integer</td><td>是否删除</td></tr><tr><td>deletedAt</td><td>Long</td><td>删除日期</td></tr><tr><td>addPrice</td><td>BigDecimal</td><td>加入收藏商品时的价格</td></tr><tr><td>addQty</td><td>Integer</td><td>加入收藏商品时的库存</td></tr><tr><td>wishProductV
O</td><td>Object</td><td>见WishProductVO详情</td></tr></table>

# wishProductVO详情

<table><tr><td>字段名</td><td>数据类型</td><td>说明</td></tr><tr><td>storeid</td><td>Long</td><td>店铺id</td></tr><tr><td>storeCode</td><td>String</td><td>店铺code</td></tr><tr><td>storeName</td><td>String</td><td>店铺名称</td></tr><tr><td>realQty</td><td>Integer</td><td>真实库存</td></tr><tr><td>occupyQty</td><td>Integer</td><td>锁定库存</td></tr><tr><td>price</td><td>BigDecimal</td><td>价格</td></tr><tr><td>onsite</td><td>Boolean</td><td>是否onsite</td></tr></table>

# 7. 错误码

<table><tr><td>响应码</td><td>说明</td></tr><tr><td>200</td><td>SUCCESS_CODE</td></tr><tr><td>401</td><td>UNAUTHORIZED</td></tr><tr><td>403</td><td>INSUFFICIENT FUNDS</td></tr><tr><td>404</td><td>NOT FOUND</td></tr><tr><td>500</td><td>FAIL_CODE</td></tr></table>

