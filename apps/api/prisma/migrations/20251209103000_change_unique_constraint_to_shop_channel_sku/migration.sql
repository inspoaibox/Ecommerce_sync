-- DropIndex
DROP INDEX IF EXISTS "products_source_channel_sku_key";

-- CreateIndex
CREATE UNIQUE INDEX "products_shop_id_source_channel_sku_key" ON "products"("shop_id", "source_channel", "sku");
