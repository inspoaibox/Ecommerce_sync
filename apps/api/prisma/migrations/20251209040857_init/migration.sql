-- CreateEnum
CREATE TYPE "Status" AS ENUM ('active', 'inactive');

-- CreateEnum
CREATE TYPE "SyncRuleStatus" AS ENUM ('active', 'paused', 'inactive');

-- CreateEnum
CREATE TYPE "SyncType" AS ENUM ('full', 'incremental');

-- CreateEnum
CREATE TYPE "SyncStatus" AS ENUM ('pending', 'synced', 'failed');

-- CreateEnum
CREATE TYPE "SyncLogStatus" AS ENUM ('running', 'success', 'partial', 'failed');

-- CreateEnum
CREATE TYPE "TriggerType" AS ENUM ('scheduled', 'manual');

-- CreateTable
CREATE TABLE "channels" (
    "id" TEXT NOT NULL,
    "name" VARCHAR(100) NOT NULL,
    "code" VARCHAR(50) NOT NULL,
    "type" VARCHAR(50) NOT NULL,
    "api_config" JSONB NOT NULL,
    "description" TEXT,
    "status" "Status" NOT NULL DEFAULT 'active',
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "channels_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "platforms" (
    "id" TEXT NOT NULL,
    "name" VARCHAR(100) NOT NULL,
    "code" VARCHAR(50) NOT NULL,
    "api_base_url" VARCHAR(255),
    "description" TEXT,
    "status" "Status" NOT NULL DEFAULT 'active',
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "platforms_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "shops" (
    "id" TEXT NOT NULL,
    "platform_id" TEXT NOT NULL,
    "name" VARCHAR(100) NOT NULL,
    "code" VARCHAR(50) NOT NULL,
    "api_credentials" JSONB NOT NULL,
    "region" VARCHAR(50),
    "description" TEXT,
    "status" "Status" NOT NULL DEFAULT 'active',
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "shops_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "sync_rules" (
    "id" TEXT NOT NULL,
    "name" VARCHAR(100) NOT NULL,
    "channel_id" TEXT NOT NULL,
    "shop_id" TEXT NOT NULL,
    "sync_type" "SyncType" NOT NULL DEFAULT 'incremental',
    "interval_days" INTEGER NOT NULL DEFAULT 1,
    "price_multiplier" DECIMAL(10,4) NOT NULL DEFAULT 1.0,
    "price_adjustment" DECIMAL(10,2) NOT NULL DEFAULT 0,
    "stock_multiplier" DECIMAL(10,4) NOT NULL DEFAULT 1.0,
    "stock_adjustment" INTEGER NOT NULL DEFAULT 0,
    "field_mapping" JSONB,
    "last_sync_at" TIMESTAMP(3),
    "next_sync_at" TIMESTAMP(3),
    "status" "SyncRuleStatus" NOT NULL DEFAULT 'active',
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "sync_rules_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "products" (
    "id" TEXT NOT NULL,
    "sync_rule_id" TEXT,
    "shop_id" TEXT,
    "channel_product_id" VARCHAR(100) NOT NULL,
    "sku" VARCHAR(100) NOT NULL,
    "title" VARCHAR(500) NOT NULL,
    "original_price" DECIMAL(10,2) NOT NULL,
    "final_price" DECIMAL(10,2) NOT NULL,
    "original_stock" INTEGER NOT NULL,
    "final_stock" INTEGER NOT NULL,
    "currency" VARCHAR(10) NOT NULL DEFAULT 'USD',
    "extra_fields" JSONB,
    "platform_product_id" VARCHAR(100),
    "source_channel" VARCHAR(100),
    "sync_status" "SyncStatus" NOT NULL DEFAULT 'pending',
    "last_sync_at" TIMESTAMP(3),
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "products_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "sync_logs" (
    "id" TEXT NOT NULL,
    "sync_rule_id" TEXT NOT NULL,
    "sync_type" "SyncType" NOT NULL,
    "trigger_type" "TriggerType" NOT NULL,
    "started_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "finished_at" TIMESTAMP(3),
    "total_count" INTEGER NOT NULL DEFAULT 0,
    "success_count" INTEGER NOT NULL DEFAULT 0,
    "fail_count" INTEGER NOT NULL DEFAULT 0,
    "status" "SyncLogStatus" NOT NULL DEFAULT 'running',
    "error_message" TEXT,
    "details" JSONB,
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "sync_logs_pkey" PRIMARY KEY ("id")
);

-- CreateIndex
CREATE UNIQUE INDEX "channels_code_key" ON "channels"("code");

-- CreateIndex
CREATE UNIQUE INDEX "platforms_code_key" ON "platforms"("code");

-- CreateIndex
CREATE UNIQUE INDEX "shops_code_key" ON "shops"("code");

-- CreateIndex
CREATE UNIQUE INDEX "products_source_channel_sku_key" ON "products"("source_channel", "sku");

-- AddForeignKey
ALTER TABLE "shops" ADD CONSTRAINT "shops_platform_id_fkey" FOREIGN KEY ("platform_id") REFERENCES "platforms"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "sync_rules" ADD CONSTRAINT "sync_rules_channel_id_fkey" FOREIGN KEY ("channel_id") REFERENCES "channels"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "sync_rules" ADD CONSTRAINT "sync_rules_shop_id_fkey" FOREIGN KEY ("shop_id") REFERENCES "shops"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "products" ADD CONSTRAINT "products_sync_rule_id_fkey" FOREIGN KEY ("sync_rule_id") REFERENCES "sync_rules"("id") ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "products" ADD CONSTRAINT "products_shop_id_fkey" FOREIGN KEY ("shop_id") REFERENCES "shops"("id") ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "sync_logs" ADD CONSTRAINT "sync_logs_sync_rule_id_fkey" FOREIGN KEY ("sync_rule_id") REFERENCES "sync_rules"("id") ON DELETE RESTRICT ON UPDATE CASCADE;
