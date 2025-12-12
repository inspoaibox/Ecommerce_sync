# 🇨🇦 Canada Market Visible Structure Fix - Summary

## Problem

Canada market product sync was failing with error:
```
ERR_INT_DATA_01010092 - Malformed data
```

The Feed was using incorrect Visible structure with category wrapper:
```json
{
    "Visible": {
        "CA_FURNITURE": {
            "productName": "...",
            "mainImageUrl": "..."
        }
    }
}
```

But Walmart Canada API requires **direct fields** without category wrapper:
```json
{
    "Visible": {
        "productName": "...",
        "mainImageUrl": "..."
    }
}
```

## Root Cause

The ProductMapper class was using a hardcoded category wrapper structure for all markets:

1. **Line 291-311**: Initial `$item_data` structure always created category wrapper
2. **Line 390**: Image handling always used category wrapper
3. **Line 574**: Dynamic field mapping had market-aware logic, but it was overridden by the initial structure

## Solution

Modified [class-product-mapper.php](includes/class-product-mapper.php) to use market-aware Visible structure initialization:

### Changes Made:

#### 1. Market-Aware Initialization (Lines 289-329)
```php
if ($market_code === 'CA') {
    // Canada: Direct fields
    $item_data = [
        'Orderable' => [...],
        'Visible' => [
            'productName' => $product_name,
            'mainImageUrl' => $main_image_url,
        ]
    ];
} else {
    // US: Category wrapper
    $item_data = [
        'Orderable' => [...],
        'Visible' => [
            $walmart_category_name => [
                'productName' => $product_name,
                'mainImageUrl' => $main_image_url,
            ]
        ]
    ];
}
```

#### 2. Market-Aware Image Handling (Lines 386-432)
```php
if ($market_code === 'CA') {
    $item_data['Visible']['productSecondaryImageURL'] = $limited_images;
} else {
    $item_data['Visible'][$walmart_category_name]['productSecondaryImageURL'] = $limited_images;
}
```

#### 3. Market-Aware Dynamic Fields (Lines 569-589)
Already implemented in previous session - now working correctly due to initialization fix.

#### 4. Market-Aware Shipping Template (Lines 606-618)
Already implemented in previous session - now working correctly.

## Verification

### Test Results (2025-11-20 12:47:53)

✅ **CA Market Structure**
```json
{
    "productName": "...",
    "mainImageUrl": "..."
}
```
- ✅ No category wrapper
- ✅ Direct field access

✅ **US Market Structure** (preserved for compatibility)
```json
{
    "CA_FURNITURE": {
        "productName": "...",
        "mainImageUrl": "..."
    }
}
```
- ✅ Category wrapper maintained
- ✅ Backward compatible

✅ **Feed Header**
- version: 3.16 ✅
- mart: WALMART_CA ✅
- sellingChannel: marketplace ✅
- processMode: REPLACE ✅
- subset: EXTERNAL ✅

## Test Scripts

1. **test-visible-structure-fix.php** - Comprehensive validation script
   - Clears opcache
   - Tests both US and CA markets
   - Validates Feed structure
   - All checks passed ✅

2. **debug-ca-feed-format.php** - Updated diagnostic tool
   - Now detects CA format correctly
   - Shows multilingual field statistics
   - Validates against spec file

## Next Steps

### 1. Fix Attribute Mapping Table Name
The diagnostic scripts reference wrong table name:
- ❌ `wp_walmart_category_attributes`
- ✅ Should be: `wp_walmart_category_map`

### 2. Populate Attribute Rules
Currently showing 0 attribute rules. Need to:
- Verify attribute mapping table exists
- Add attribute mappings for Canada market
- Test multilingual field conversion

### 3. Test Real Sync
Once attributes are populated:
- Test single product sync to Walmart Canada API
- Verify no "Malformed data" error
- Check multilingual fields are properly converted

### 4. Test Batch Sync
- Verify market code is passed through batch sync
- Test multiple products
- Monitor for any errors

## Files Modified

- ✅ [includes/class-product-mapper.php](includes/class-product-mapper.php) - Lines 289-432, 569-618
- ✅ [debug-ca-feed-format.php](debug-ca-feed-format.php) - Lines 245-261

## Files Created

- ✅ [test-visible-structure-fix.php](test-visible-structure-fix.php) - Validation test script
- ✅ [CANADA-MARKET-FIX-SUMMARY.md](CANADA-MARKET-FIX-SUMMARY.md) - This document

---

**Status**: ✅ Visible Structure Fix Complete
**Date**: 2025-11-20
**Tested**: Yes - All validation checks passed
