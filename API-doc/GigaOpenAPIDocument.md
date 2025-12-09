**1. Version Records**

| Version | Update on  | Summary                                                                                                                                                                                                                                                                                         |
| :------ | :--------- | :---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| V2.7.1  | 6/29/2023  | Sync self-arranged shipping orders (B2): [shipMethod] and [ShipServiceLevel] were modified.[payAccountPostalCode] was added.                                                                                                                                                                    |
| V2.7.2  | 7/6/2023   | Product details: [whiteLabel] was added.                                                                                                                                                                                                                                                        |
| V2.8    | 8/3/2023   | Product details:[firstAvailableDate], [gigaIndex],[sellerType], [sellerCode] were added. [comboInfo] was modified. Product price:[spotPrice], [rebatesPrice] and   [marginPrice] were added. Product quantity:[nextArrival] was added.                                                          |
| V2.8.1  | 8/17/2023  | Sync drop shipping orders & Sync self-arranged shipping orders (B3): [brand LabelName] and[numberOfLabels] were added.                                                                                                                                                                          |
| V2.8.2  | 8/31/2023  | Sync self-arranged shipping orders (B3): [salesChannel] was modified.                                                                                                                                                                                                                           |
| V2.8.3  | 9/7/2023   | Product price:[sellerInfo], [futurePrice], [skuAvailable] were added, [spotPrice] was modified.Product details: [sellerInfo] was added.                                                                                                                                                         |
| V2.8.4  | 9/21/2023  | Sync drop shipping orders (B1) & Sync self-arranged shipping orders (B2) & Sync self-arranged shipping    orders (B3): [orderID] were modified.                                                                                                                                                 |
| V2.8.5  | 10/12/2023 | For the DE Marketplace, Sync drop shipping orders (B1):Request params [valueAddedServices] was     added;Sales order logistics information:Response   params [returnInfo] was added.                                                                                                            |
| V2.8.6  | 10/26/2023 | For the US Marketplace, Sync drop shipping orders (B1):Request params [deliveryService] was added.                                                                                                                                                                                              |
| V2.8.7  | 11/16/2023 | 1\.“ASR” updated to “ DSR” .Involving the Request Params of the [Sync drop shipping orders(B1)] .2\.The response num 128 in Order Status Response of the [Sales order status] has been deprecated.3\.Product details:Response params [ToBePublished] and [UnavailablePlatforms] were added. |
| V3.0.0  | 12/21/2023 | To make the OpenAPI document more readable and accurate, this document has been reorganized. No    change was made to the interface content, so thesystem did not need to make any adjustments.                                                                                                 |
| V3.0.1  | 01/02/2023 | Sync self-arranged orders (Buyer supplied label)                                                                                                                                                                                                                                                |

|        |            | available for LTL trucking                                                                                                                                                                                                                                                                                                                |
| :----- | :--------- | :---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| V3.0.2 | 05/23/2024 | Product quantity: [buyerQtyDistribution],   [buyerPartnerQty], [sellerQtyDistribution],[nextArrivalDateEnd] and [nextArrivalQtyMax] wereadded; [buyerQty], [nextArrivalDate] and [nextArrivalQty] were adjusted.                                                                                                                          |
| V3.0.3 | 06/27/2024 | Sync self-arranged orders (GIGA supplied label): [sku] was modified.                                                                                                                                                                                                                                                                      |
| V3.0.4 | 09/26/2024 | 1\. For the US Marketplace, Sync drop shipping orders (B1): New field [amazonOrderItemId] was added, not   required.2\. Order status:Update order status description.Delete order status:[Pending Charges],[Uploading Package    Label],[Abnormal order] and [Waiting for Pick-up]3\. Product price:Response Params [currency] was added. |
| V3.0.5 | 10/17/2024 | Product list&Product details: The range of products accessible was updated, able to access the valid    products from Saved Items.                                                                                                                                                                                                        |
| V3.0.6 | 10/21/2024 | Product list&Product details: The range of products accessible was updated, able to access the valid    products.                                                                                                                                                                                                                         |
| V3.0.7 | 11/18/2024 | Uploaded label files should be consistent with the number of packages of SKU.                                                                                                                                                                                                                                                             |
| V3.0.8 | 1/2/2025   | Product list&Product details: The range of productsaccessible was updated, able to access the validproducts from Saved Items or access the products with stockpiled inventory.                                                                                                                                                            |
| V3.0.9 | 2/25/2025  | 1\. Sync self-arranged shipping orders (B3)：[packingSlip] was added ，[I-labelFile] was modified.The product price interface supports querying therange of shipping   fee, and the interface will return   ShippingFeeRange field, applicable to LTL products  and products with Amazon Preferential Shipping Fee applied.               |

**2. URLs for Each Environment**

Test Environment URL：

[https://test.gigacloudlogistics.com](https://test.gigacloudlogistics.com)

Production Environment URL:

[https://api.gigacloudlogistics.com](https://api.gigacloudlogistics.com)

**3. Authorization**

**3.1.1 Interface description**

| Access token |                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| :----------- | :-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| URL          | /api-auth-v1/oauth/token                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| method       | POST                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| note         | Authentication is performed using an OAuth client-credentialsworkflow. You POST your client id and secret to the token retrieval  endpoint, to get a new access token. This authenticated token must be included in all future API calls. Tokens have an expiration of 2hours, so check the exp field in the token for the expiration date/time in seconds to see when you will need to re-authenticate by grabbing a new token. In order to use the GIGA API you will need to have a     client id and secret key. You will pass the client id and secret key to  the token endpoint.The token response will include your access token (access\_token), an expiration length in seconds (expires\_in), the scopes currently    assigned to the token (scope), and the type of the token(token\_type). When making requests to the API, you will need to set your HTTP Authorization header to {token\_type} {access\_token}from your token response. This will look somethinglike Authorization: BearereyJ0eXAiOiJKV1Qi...X1rNJFNVGBwmFQ5tepKwno7DEIjDg. |

**3.1.2 Request parameters**

### 3.1.2 Request parameters

| Name          | Description                                                    | Required | Data Type |
| :------------ | :------------------------------------------------------------- | :------- | :-------- |
| grant_type    | Authorization method, and only client_credentials is available | Yes      | string    |
| client_id     | Client ID                                                      | Yes      | string    |
| client_secret | client_secret                                                  | Yes      | string    |

**3.1.3 Response parameters**

| Name           | Description                                                            | Data Type |
| :------------- | :--------------------------------------------------------------------- | :-------- |
| access_token   | Developer account login credentials                                    | string    |
| token_type     | Type of token                                                          | string    |
| expires_in     | Remaining validity of token, in seconds                                | number    |
| scope          | The scope of authorization credentials, has not been distinguished yet | string    |
| addtional_info | Additional information                                                 | array     |
| ⊢customerId   | Account ID bound to token                                              | number    |
| jti            | The receipt ID for the current JSON Web Tokens                         | string    |

**3.1.4 Example of normal return**

```
{

"access_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJhZGR0aW9uYWxfaW5mbyI6eyJjdXN0b21lcklkIjoxMzkxfSwic2NvcGUiOlsiYWxsIl0sImV4cCI6MTcwMTc2NTk2MywiYXV0aG9yaXRpZXMiOlsicmVsZWFzZSJdLCJqdGkiOiI4NTcyOThiMy03MWI3LTRlMGYtOGRiNC1iMDA0ZTk0NGVhNDMiLCJjbGllbnRfaWQiOiIyMjk1MTYxMV9VU19yZWxlYXNlIn0.XcOl_OqEpujQPbVoGFkBzRWl5y7y1OYstFMJC_CenMx6UdHris64FTK3gYxYh2kIe9hHm5uNkrpByDU9kGmrJ3GHTI9DXAwE5O0xqI_X8QDTmlyTJUs_2r7H2EGfXJzFpEdtPE9dPOJdGY8go4VadPBohtWfuMuQ1FI3hsTe5aq_oxjchObeJdKmh9h9i8KAhhGRep5Z_RcToh-opc0MCiXy7B3K6uCB6QdxDH8nm20heJjHpS5o66TSwLTLjFIyVLZtNE71q7ST2RH25zB7d2R47OIEZSpn1yKcRCj_veYlYdK6vMUx_mVjN4I5n4TTWNXIm6O8HLxEUM3MhSPLrg",

"token_type": "bearer",

"expires_in": 7199,

"scope": "all",

"addtional_info": {

"customerId": 1391

},

"jti": "857298b3-71b7-4e0f-8db4-b004e944ea43"

}
```

**4. Product Related Interfaces**

**4.1 Product list**

**4.1.1 Interface description**

| Name       | Value                                                                                                                                                 |
| ---------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| URL        | /api-b2b-v1/product/skus                                                                                                                              |
| method     | GET                                                                                                                                                   |
| consumes   | ["*/*"]                                                                                                                                               |
| produces   | ["*/*"]                                                                                                                                               |
| note       | Get the list of products on the B2B Marketplace, able to access the valid products from Saved Items or access the products with stockpiled inventory. |
| rate limit | 10 times in 10 seconds                                                                                                                                |

**4.1.2 Request Params**
Query Params

| Name             | Description                                                                                                                                                                                                                      | Required | Data Type | Verification                          |
| ---------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- | --------- | ------------------------------------- |
| sort             | Product sorting by:1: updatTime:asc;2: updatTime:desc;3: datePosted:asc;4: datePosted:desc                                                                                                                                       | No       | int       |                                       |
| firstArrivalDate | The date when the product’s first batch of inventory arrived at the warehouse. Format: yyyy-mm-dd.Example: if firstArrivalDate=A, then products with First Arrival Date no earlier than A will be returned.                     | No       | date      |                                       |
| lastUpdatedAfter | When this field is filled, query the SKU with updateTime later than the filled time; No limit when left blank.                                                                                                                   | No       | string    | YYYY-MM-DD hh:mm:ss;Time zone: GMT-8. |
| limit            | Maximum quantity of SKUs returned per page; No less than 100 and no more than 10,000; The default value is 5,000 when left blank.                                                                                                | No       | integer   |                                       |
| page             | Page number, no less than 1; The default value is 1 when left blank.The interface will return data from the limit*(page-1)+1 to the limit*page; For example, if page=10, limit=100, the data from 901 to 1,000 will be returned. | No       | integer   |                                       |

**Request Example**

```
https://api.gigacloudlogistics.com/api-b2b-v1/product/skus?sort=4&firstArrivalDate=2025-03-01&lastUpdatedAfter=2025-01-01 20:02:02&limit=100&page=1
```

**4.1.3 Response Params Response Body**

| Name                  | Description                                                                                       | Data Type         |
| --------------------- | ------------------------------------------------------------------------------------------------- | ----------------- |
| pageMeta              | Paging Information                                                                                | object            |
| ├─ next             | The request URL for the data of next page, and is empty when there is no next page.               | string            |
| ├─ total            | Total eligible data                                                                               | integer           |
| ├─ limit            | The limit requested this time                                                                     | integer           |
| ├─ page             | The page requested this time                                                                      | integer           |
| success               | Whether succeeded                                                                                 | boolean           |
| data                  | Interface returns content                                                                         | array             |
| ├─ sku              | SKU                                                                                               | string            |
| ├─ updateTime       | Last update time. Time zone: GMT-8.                                                               | string(date-time) |
| ├─ firstArrivalDate | The date when the product’s first batch of inventory arrived at the warehouse.Format: yyyy-mm-dd | date              |

**Example of normal return**

```
{

"pageMeta": {

"next": "",

"total": 10,

"limit": 100,

"page": 1

},

"success": true,

"data": [

{

"sku": "W223P199791",

"updateTime": "2025-04-28 22:56:57",

"firstArrivalDate": "2025-04-28"

},

{

"sku": "B100S00002",

"updateTime": "2025-03-31 22:45:31",

"firstArrivalDate": "2025-03-31"

},

{

"sku": "B216S00006",

"updateTime": "2025-03-31 04:35:08",

"firstArrivalDate": "2025-03-27"

},

{

"sku": "B216S00005",

"updateTime": "2025-03-28 18:46:44",

"firstArrivalDate": "2025-03-27"

},

{

"sku": "B216S00001",

"updateTime": "2025-03-27 19:51:05",

"firstArrivalDate": "2025-03-27"

},

{

"sku": "B216P199617",

"updateTime": "2025-03-27 19:41:51",

"firstArrivalDate": "2025-03-27"

},

{

"sku": "W980S00003",

"updateTime": "2025-03-24 06:10:46",

"firstArrivalDate": "2025-03-21"

},

{

"sku": "W980S00002",

"updateTime": "2025-03-21 23:52:53",

"firstArrivalDate": "2025-03-21"

},

{

"sku": "W980S00001",

"updateTime": "2025-03-21 19:11:27",

"firstArrivalDate": "2025-03-21"

},

{

"sku": "N415P199495",

"updateTime": "2025-03-12 19:05:56",

"firstArrivalDate": "2025-03-12"

}

]

}
```

**4.2 Product details**

**4.2.1 Interface description**

| Field      | Value                                                                                                                                                   |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| URL        | /api-b2b-v1/product/detailInfo                                                                                                                          |
| method     | POST                                                                                                                                                    |
| consumes   | ["application/json"]                                                                                                                                    |
| produces   | ["*/*"]                                                                                                                                                 |
| note       | Query the detailed information of products by SKU, able to access the valid products from Saved Items or access the products with stockpiled inventory. |
| rate limit | 20 times in 10 seconds                                                                                                                                  |
|            |                                                                                                                                                         |

**4.2.2 Request Params Request Body**

| Name | Description                                            | Required | Data Type |
| ---- | ------------------------------------------------------ | -------- | --------- |
| skus | The list of SKU. The number of SKUs cannot exceed 200. | Yes      | string[]  |

**Request Example**

```
{

"skus": [

"W223P199791"

]}
```

**4.2.3 Response Params Response Body**

## Response - Product Detail Info

| Name      | Description                                                        | Data Type |
|-----------|--------------------------------------------------------------------|-----------|
| success   | Whether succeeded                                                  | boolean   |
| data      | Interface returns content                                          | array     |
| ├─ sku    |                                                                    | string    |
| ├─ mpn    |                                                                    | string    |
| ├─ weightUnit |                                                                | string    |
| ├─ lengthUnit |                                                                | string    |
| ├─ weight |                                                                    | number    |
| ├─ length |                                                                    | number    |
| ├─ width  |                                                                    | number    |
| ├─ height |                                                                    | number    |
| ├─ weightKg |                                                                  | number    |
| ├─ lengthCm |                                                                  | number    |
| ├─ name   |                                                                    | string    |
| ├─ description |                                                              | string    |
| ├─ characteristics |                                                           | string[]  |
| ├─ imageUrls | URL of product images (excluding main image)                   | string[]  |
| ├─ mainImageUrl | URL of product main image                                   | string    |
| ├─ categoryCode |                                                             | string    |
| ├─ category |                                                                 | string    |
| ├─ comboFlag |                                                                | boolean   |
| ├─ overSizeFlag |                                                             | boolean   |
| ├─ partFlag |                                                                 | boolean   |
| ├─ upc     |                                                                  | string    |
| ├─ customized | Returns ‘yes/no’                                              | string    |
| ├─ placeOfOrigin |                                                           | string    |
| ├─ lithiumBatteryContained |                                                 | string    |
| ├─ assembledLength | Returns ‘Not Applicable’ if left blank                   | number    |
| ├─ assembledWidth | Returns ‘Not Applicable’ if left blank                    | number    |
| ├─ assembledHeight | Returns ‘Not Applicable’ if left blank                   | number    |
| ├─ assembledWeight | Returns ‘Not Applicable’ if left blank                   | number    |
| ├─ customList |                                                              | string[]  |
| ├─ associateProductList |                                                    | string[]  |
| ├─ certificationList |                                                       | string[]  |
| ├─ fileUrls |                                                                | string[]  |
| ├─ videoUrls |                                                               | string[]  |
| ├─ attributes | Product Attributes                                           | array     |
| ⊢⊢ Main Color |                                                             | string    |
| ⊢⊢ Scene |                                                                  | string    |
| ⊢⊢ Main Material |                                                         | string    |
| ├─ whiteLabel | Yes/No/""                                                   | boolean   |
| ├─ comboInfo | Return the dimensions, weight and MPN in details of the combo item | string[] |
| ├─ firstArrivalDate | The date when the product’s first batch of inventory arrived at the warehouse. Format: yyyy-mm-dd. Note: The previous name is “firstAvailableDate” | date      |
| ├─ sellerInfo |                                                              | object    |
| ⊢⊢ sellerStore |                                                           | string    |
| ⊢⊢ sellerType | Show seller type: ONSITE/GENERAL                           | string    |
| ⊢⊢ gigaIndex |                                                             | string    |
| ⊢⊢ sellerCode |                                                            | string    |
| ⊢⊢ sellerReturnRate |                                                      | string    |
| ⊢⊢ sellerReturnApprovalRate |                                              | string    |
| ⊢⊢ sellerMessageResponseRate |                                             | string    |
| ├─ toBePublished | If the product is available, the value will be "true", otherwise "false". | boolean |
| ├─ unAvailablePlatform | Unavailable platform info. Refer to the Appendix below for detailed status description | array   |
| ⊢⊢ id | Unavailable Platform id                                            | string    |
| ⊢⊢ name | Unavailable Platforms name                                       | string    |

**Appendix (unAvailablePlatform)**

```
| Unavailable Platform ID | Unavailable Platform Name |
|-------------------------|---------------------------|
| 1                       | Wayfair                   |
| 2                       | Amazon                    |
| 3                       | Walmart                   |
| 4                       | Ecommerce Website         |
| 5                       | eBay                      |
| 6                       | Overstock                 |
| 7                       | Home Depot                |
| 8                       | Lowe's                    |
| 9                       | Wish                      |
| 10                      | Newegg                    |
| 11                      | AliExpress                |
| 12                      | SHEIN                     |
| 13                      | Temu                      |
| 14                      | Tiktok                    |
| 15                      | COSTCO                    |
| 16                      | Lowes                     |
```

**Example of normal return**

```
{

"success": true,

"data": [

{

"sku": "W223P199791",

"mpn": "PISSVEVE001",

"weightUnit": "lb",

"lengthUnit": "in",

"weight": 12,

"length": 12,

"width": 12,

"height": 12,

"weightKg": 5.44,

"lengthCm": 30.48,

"widthCm": 30.48,

"heightCm": 30.48,

"name": "PISSVEVE001",

"description": "",

"characteristics": [

"GBFRT TRTR RTTR GBFRT TRTR RTTR GBFRT TRTR RTTR GBFRT TRTR RTTR",

"GBFRT TRTR RTTR GBFRT TRTR RTTR GBFRT TRTR RTTR GBFRT TRTR RTTR",

"GBFRT TRTR RTTR GBFRT TRTR RTTR GBFRT TRTR RTTR GBFRT TRTR RTTR",

"GBFRT TRTR RTTR GBFRT TRTR RTTR GBFRT TRTR RTTR GBFRT TRTR RTTR"

],

"imageUrls": [

"https://btbfile.oss-cn-hongkong.aliyuncs.com/image/wkseller/77/af56b8dcdf85a9833e583f0983346089.jpg?x-cc=20&x-cu=2703&x-ct=1747119600&x-cs=05dc0099df69f2783ad068322ba652c4",

"https://btbfile.oss-cn-hongkong.aliyuncs.com/image/wkseller/77/c874a307179016195d8cf094f074c7b3.jpg?x-cc=20&x-cu=2703&x-ct=1747119600&x-cs=2da2ab78a6b80dc3a63efed9ed4a1684",

"https://btbfile.oss-cn-hongkong.aliyuncs.com/image/wkseller/77/4e6fb51e67327b8e80b241fb85603cd2.jpg?x-cc=20&x-cu=2703&x-ct=1747119600&x-cs=45321e4eac1544110a9829fc8d555092",

"https://btbfile.oss-cn-hongkong.aliyuncs.com/image/wkseller/77/d74ca17bc82b72a2cb3d5f0e47537672.jpg?x-cc=20&x-cu=2703&x-ct=1747119600&x-cs=4197473695b8e6cfe6da0e95ddb7c996",

"https://btbfile.oss-cn-hongkong.aliyuncs.com/image/wkseller/77/e8ad6b5bb471eb6522e46b383a0c6136.jpg?x-cc=20&x-cu=2703&x-ct=1747119600&x-cs=9de0503b1493c65d097dbc76be227cc1",

"https://btbfile.oss-cn-hongkong.aliyuncs.com/image/wkseller/77/31d65cc3d568f1ee7ffb728e6a62c004.jpg?x-cc=20&x-cu=2703&x-ct=1747119600&x-cs=c5b8e5530ad6732513d319ecbdb72acf"

],

"mainImageUrl": "https://btbfile.oss-cn-hongkong.aliyuncs.com/image/wkseller/77/af56b8dcdf85a9833e583f0983346089.jpg?x-cc=20&x-cu=2703&x-ct=1747119600&x-cs=05dc0099df69f2783ad068322ba652c4",

"categoryCode": 10089,

"category": "Outdoor Bikes",

"comboFlag": false,

"overSizeFlag": false,

"partFlag": false,

"upc": "",

"customized": "No",

"placeOfOrigin": "",

"lithiumBatteryContained": "No",

"assembledLength": "Not Applicable",

"assembledWidth": "Not Applicable",

"assembledHeight": "Not Applicable",

"assembledWeight": "Not Applicable",

"customList": [],

"associateProductList": [],

"certificationList": [],

"fileUrls": [],

"videoUrls": [],

"attributes": {

"Main Color": "Acacia Wood",

"Main Material": "ABS"

},

"whiteLabel": "No",

"comboInfo": [],

"firstArrivalDate": "2025-04-28",

"sellerInfo": {

"sellerStore": "Wondersign Store",

"sellerType": "GENERAL",

"gigaIndex": null,

"sellerCode": "W223",

"sellerReturnRate": "Low",

"sellerReturnApprovalRate": "Moderate",

"sellerMessageResponseRate": "N/A"

},

"toBePublished": true,

"unAvailablePlatform": [],

"newArrivalFlag": true

}

]

}
```

**4.3. Product price**

**4.3.1 Interface description**

| Name       | Value                                |
| ---------- | ------------------------------------ |
| URL        | /api-b2b-v1/product/price            |
| method     | POST                                 |
| consumes   | ["application/json"]                 |
| produces   | ["*/*"]                              |
| note       | Query the price of product with SKU. |
| rate limit | 10 times in 10 seconds               |

**4.3.2 Request Params Request Body**

| Name | Description                                            | Required | Data Type |
| ---- | ------------------------------------------------------ | -------- | --------- |
| skus | The list of SKU. The number of SKUs cannot exceed 200. | Yes      | string[]  |

**Request Example**

```
{

"skus": [

"sku": "W59463028"

]}
```

**4.3.3 Response Params Response Body**

**Response Body**

| Name                          | Description                                                                                                                                                                                                                  | Data Type |
|-------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-----------|
| success                       | Whether succeeded                                                                                                                                                                                                            | boolean   |
| data                          | Interface returns content                                                                                                                                                                                                    | array     |
| ├─ sku                        | SKU                                                                                                                                                                                                                          | string    |
| ├─ currency                   | Currency: US USD, UK GBP, Germany EUR, Japan JPY                                                                                                                                                                             | string    |
| ├─ price                      | The price of SKU                                                                                                                                                                                                            | number    |
| ├─ shippingFee                | The freight price of SKU                                                                                                                                                                                                    | number    |
| ├─ shippingFeeRange           | The shipping fee range of the product, with packing fee included. This field is used for LTL products or products with Amazon Preferential Shipping Fee applied. When the quotes are the same or there are no different shipping services, minAmount=maxAmount=shippingFee | Object[]  |
| ⊢⊢ minAmount                 | minimum shipping fee                                                                                                                                                                                                        | string    |
| ⊢⊢ maxAmount                 | maximum shipping fee                                                                                                                                                                                                        | string    |
| ├─ exclusivePrice             | The exclusive price of SKU set by seller                                                                                                                                                                                   | number    |
| ├─ discountedPrice            | The discounted price if there is an ongoing promotion                                                                                                                                                                       | number    |
| ├─ promotionFrom              | Promotion start time if there is an ongoing promotion. Time zone: GMT-8.                                                                                                             | string    |
| ├─ promotionTo                | Promotion end time if there is an ongoing promotion. Time zone: GMT-8.                                                                                                               | string    |
| ├─ mapPrice                   | Minimum advertised pricing (MAP) is the lowest price a retailer can advertise the product for sale.                                                                                   | number    |
| ├─ futureMapPrice             | Future MAP                                                                                                                                                                           | number    |
| ├─ effectMapTime              | Effect Time of the Future MAP. Time zone: GMT-8.                                                                                                                                      | string    |
| ├─ sellerInfo                 | Return the seller information in array form.                                                                                                                                         | array     |
| ⊢⊢ sellerStore               |                                                                                                                                                                                                                             | string    |
| ⊢⊢ sellerType                | Show seller type: ONSITE/GENERAL                                                                                                                                                     | string    |
| ⊢⊢ gigaIndex                 |                                                                                                                                                                                                                             | string    |
| ⊢⊢ sellerCode                |                                                                                                                                                                                                                             | string    |
| ⊢⊢ sellerReturnRate          |                                                                                                                                                                                                                             | string    |
| ⊢⊢ sellerReturnApprovalRate  |                                                                                                                                                                                                                             | string    |
| ⊢⊢ sellerMessageResponseRate |                                                                                                                                                                                                                             | string    |
| ├─ spotPrice                  | Return the spot price and corresponding price range in array form.                                                                                                                  | string[]  |
| ├─ rebatesPrice               | Return the rebates price and corresponding price range in array form.                                                                                                               | string[]  |
| ├─ marginPrice                | Return the margin price and corresponding price range in array form.                                                                                                                | string[]  |
| ├─ futurePrice                | Return the future price and corresponding price range in array form.                                                                                                                | string[]  |
| ├─ skuAvailable               | Show if this seller’s SKU is available for user. If false, the product price will be null.                                                                                           | boolean   |


**Example of normal return**

```
{

"success": true,

"data": [

{

"sku": "W1675P198161","currency": USD,

"price": 111,

"shippingFee": 457.28,

"shippingFeeRange": {,

"minAmount": 448.51,

"maxAmount": 749.15,

},

"exclusivePrice": null,

"discountedPrice": null,

"promotionFrom": null,

"promotionTo": null,

"mapPrice": null,

"futureMapPrice": null,

"effectMapTime": null,

"sellerInfo": {

"sellerStore": "Bath Factory",

"sellerType": "GENERAL",

"gigaIndex": null,

"sellerCode": "W1675",

"sellerReturnRate": "Moderate",

"sellerReturnApprovalRate": "High",

"sellerMessageResponseRate": "N/A"

},

"spotPrice": [],

"rebatesPrice": [],

"marginPrice": [],

"skuAvailable": true,

"futurePrice": []

}

]}
```

**5. List of Response Codes**

| code | description  | schema                 |
| ---- | ------------ | ---------------------- |
| 200  | OK           | ResponseBean«string» |
| 201  | Created      |                        |
| 401  | Unauthorized |                        |
| 403  | Forbidden    |                        |
| 404  | Not Found    |                        |

**6. Inventory Related Warehouses**

# **6.1 Inventory query**

**6****.1.1 ****Interface description**

|                 |                                                                                                                                                                                                                                           |
| --------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Inventory Query |                                                                                                                                                                                                                                           |
| URL             | /api-b2b-v1/inventory/quantity-query                                                                                                                                                                                                      |
| method          | POST                                                                                                                                                                                                                                      |
| consumes        | \["application/json"]                                                                                                                                                                                                                     |
| produces        | \["\*/\*"]                                                                                                                                                                                                                                |
| note            | Query product’s available inventory or owned inventory on GIGAB2B by SKU. Product promotional inventory and storage fee are also available for query. For US Self-arranged Shipping Buyers, querying inventory by warehouse is available. |
| rate limit      | 10 times in 10 seconds                                                                                                                                                                                                                    |

**6****.****1****.****2**** ****Request Params**

******Request Body**

|      |                                                      |          |           |
| ---- | ---------------------------------------------------- | -------- | --------- |
| Name | Description                                          | Required | Data Type |
| skus | Item Code on GIGAB2B, max 200 Item Codes per request | true     | string\[] |

# **Request Example**
```

{

"skus":\["XX1"]

}
```

### ****

### **6****.****1****.****3**** Response Params**

**Response Body**

---

|                                |                                                                                                                                                                                                                                                            |           |
| ------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------- |
| Name                           | Description                                                                                                                                                                                                                                                | Data Type |
| success                        | Whether succeeded                                                                                                                                                                                                                                          | bool      |
| code                           | Error code                                                                                                                                                                                                                                                 | string    |
| msg                            | Error message, returned when error occurs                                                                                                                                                                                                                  | string    |
| data                           | Interface returns content                                                                                                                                                                                                                                  | array     |
| ⊢sku                           | Item Code on GIGAB2B                                                                                                                                                                                                                                       | string    |
| ⊢buyerInventoryInfo            | Buyer-owned inventory information                                                                                                                                                                                                                          | object    |
| ⊢⊢totalBuyerAvailableInventory | Quantity of fully-paid inventory, excluding Buyer Locked QTY                                                                                                                                                                                               | int       |
| ⊢⊢totalMarginInventory         | Quantity of Margin Agreement inventory with unpaid due amount                                                                                                                                                                                              | int       |
| ⊢⊢totalFutureInventory         | Quantity of Future Goods Agreement inventory with unpaid due amount                                                                                                                                                                                        | int       |
| ⊢⊢totalSystemLockedInventory   | Quantity of inventory locked by the system                                                                                                                                                                                                                 | int       |
| ⊢⊢totalBuyerLockedInventory    | Quantity of inventory locked by Buyer                                                                                                                                                                                                                      | int       |
| ⊢⊢buyerInventoryDistribution   | Warehouse distribution of Buyer's inventory                                                                                                                                                                                                                | array     |
| ⊢⊢⊢warehouseCode               | Warehouse code                                                                                                                                                                                                                                             | string    |
| ⊢⊢⊢buyerAvailableInventory     | Quantity of fully-paid inventory, excluding Buyer Locked QTY for specific warehouse                                                                                                                                                                        | int       |
| ⊢⊢⊢marginInventory             | Quantity of Margin Agreement inventory with unpaid due amount for specific warehouse                                                                                                                                                                       | int       |
| ⊢⊢⊢systemLockedInventory       | Indicate the inventory already assigned to Shipments, blocked by RMA requests and inventory loss, cannot be used for specific warehouse                                                                                                                    | int       |
| ⊢⊢⊢buyerLockedInventory        | Indicate the inventory locked by Buyer, cannot be used during the locking period for specific warehouse. Buyer can click on the number on Inventory Management → Inventory Lookup page to open the Inventory Details window and then unlock the inventory. | int       |
| ⊢⊢totalStorageFee              | Total storage fees incurred as of yesterday, including the amount that has been paid already in debt statement.                                                                                                                                            | string    |
| ⊢⊢unpaidStorageFee             | Total unpaid storage fee as of yesterday                                                                                                                                                                                                                   | string    |
| ⊢⊢currency                     | The currency of Storage Fee                                                                                                                                                                                                                                | string    |
| ⊢sellerInventoryInfo           | Seller’s inventory distribution, only available for US Self-arranged Shipping Buyers (Specified Warehouse is supported)                                                                                                                                    | object    |
| ⊢⊢sellerAvailableInventory     | Quantity of inventory available for purchase                                                                                                                                                                                                               | int       |
| ⊢⊢discountAvailableInventory   | Quantity available for discounted products during limited-time promotions                                                                                                                                                                                  | int       |
| ⊢⊢sellerInventoryDistribution  | Warehouse distribution of Seller's inventory. Self-arranged Shipping Buyers can specify warehouse to purchase inventory from.                                                                                                                              | array     |
| ⊢⊢⊢warehouseCode               | Warehouse code                                                                                                                                                                                                                                             | string    |
| ⊢⊢⊢availableQtyMin             | Minimum inventory quantity requirement for purchase                                                                                                                                                                                                        | int       |
| ⊢⊢⊢availableQtyMax             | Maximum inventory quantity available for purchase                                                                                                                                                                                                          | int       |
| ⊢⊢nextArrivalInventory         | Expected arrival time for next incoming shipment, if available.                                                                                                                                                                                            | array     |
| ⊢⊢⊢nextArrivalBegin            | Expected arrival time for next incoming shipment if available. nextArrivalBegin is the earliest estimated arrival time.                                                                                                                                    | date      |
| ⊢⊢⊢nextArrivalEnd              | Expected arrival time for next incoming shipment if available. nextArrivalEnd is the latest estimated arrival time.                                                                                                                                        | date      |
| ⊢⊢⊢nextArrivalQtyMin           | The minimum quantity of the expected inventory for the next arrival                                                                                                                                                                                        | int       |
| ⊢⊢⊢nextArrivalQtyMax           | The maximum quantity of the expected inventory for the next arrival                                                                                                                                                                                        | int       |

**Example of normal return**

```

{

"success": true,

"code": "200",

"msg": "success",

"data": \[

{

"sku": "XX1",

"buyerInventoryInfo": {

"totalBuyerAvailableInventory": 11,

"totalMarginInventory": 0,

"totalFutureInventory": 0,

"totalSystemLockedInventory": 0,

"totalBuyerLockedInventory": 0,

"buyerInventoryDistribution": \[

{

"warehouseCode": "NJX1",

"buyerAvailableInventory": 6,

"marginInventory": 0,

"systemLockedInventory": 0,

"buyerLockedInventory": 0

},

{

"warehouseCode": "CA1",

"buyerAvailableInventory": 5,

"marginInventory": 0,

"systemLockedInventory": 0,

"buyerLockedInventory": 0

}

],

"totalStorageFee": 0,

"unpaidStorageFee": 0,

"currency": "USD"

},

"sellerInventoryInfo": {

"sellerAvailableInventory": 548,

"discountAvailableInventory": 0,

"sellerInventoryDistribution": \[

{

"warehouseCode": "NJX1",

"availableQtyMin": 500,

"availableQtyMax": 548

},

{

"warehouseCode": "CA1",

"availableQtyMin": 500,

"availableQtyMax": 522

}

],

"nextArrivalInventory": {

"nextArrivalBegin": "2025-09-27",

"nextArrivalEnd": "2025-10-04",

"nextArrivalQtyMin": 50,

"nextArrivalQtyMax": 99

}

}

}

]

}


```