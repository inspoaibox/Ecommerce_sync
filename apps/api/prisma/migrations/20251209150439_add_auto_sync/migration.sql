-- CreateEnum
CREATE TYPE "AutoSyncStage" AS ENUM ('fetch_channel', 'update_local', 'push_platform', 'completed', 'failed', 'cancelled');

-- CreateTable
CREATE TABLE "auto_sync_configs" (
    "id" TEXT NOT NULL,
    "shop_id" TEXT NOT NULL,
    "enabled" BOOLEAN NOT NULL DEFAULT false,
    "interval_days" INTEGER NOT NULL DEFAULT 1,
    "sync_type" TEXT NOT NULL DEFAULT 'both',
    "last_sync_at" TIMESTAMP(3),
    "next_sync_at" TIMESTAMP(3),
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "auto_sync_configs_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "auto_sync_tasks" (
    "id" TEXT NOT NULL,
    "shop_id" TEXT NOT NULL,
    "sync_type" TEXT NOT NULL DEFAULT 'both',
    "stage" "AutoSyncStage" NOT NULL DEFAULT 'fetch_channel',
    "channel_stats" JSONB,
    "local_updated" INTEGER NOT NULL DEFAULT 0,
    "platform_feed_id" TEXT,
    "platform_status" TEXT,
    "total_products" INTEGER NOT NULL DEFAULT 0,
    "success_count" INTEGER NOT NULL DEFAULT 0,
    "fail_count" INTEGER NOT NULL DEFAULT 0,
    "error_message" TEXT,
    "started_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "finished_at" TIMESTAMP(3),
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "auto_sync_tasks_pkey" PRIMARY KEY ("id")
);

-- CreateIndex
CREATE UNIQUE INDEX "auto_sync_configs_shop_id_key" ON "auto_sync_configs"("shop_id");

-- AddForeignKey
ALTER TABLE "auto_sync_configs" ADD CONSTRAINT "auto_sync_configs_shop_id_fkey" FOREIGN KEY ("shop_id") REFERENCES "shops"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "auto_sync_tasks" ADD CONSTRAINT "auto_sync_tasks_shop_id_fkey" FOREIGN KEY ("shop_id") REFERENCES "shops"("id") ON DELETE CASCADE ON UPDATE CASCADE;
