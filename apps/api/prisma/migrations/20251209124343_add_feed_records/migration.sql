-- CreateEnum
CREATE TYPE "FeedStatus" AS ENUM ('RECEIVED', 'INPROGRESS', 'PROCESSED', 'ERROR');

-- CreateEnum
CREATE TYPE "FeedSyncType" AS ENUM ('price', 'inventory', 'both');

-- CreateTable
CREATE TABLE "feed_records" (
    "id" TEXT NOT NULL,
    "shop_id" TEXT NOT NULL,
    "feed_id" VARCHAR(100) NOT NULL,
    "sync_type" "FeedSyncType" NOT NULL,
    "item_count" INTEGER NOT NULL DEFAULT 0,
    "status" "FeedStatus" NOT NULL DEFAULT 'RECEIVED',
    "success_count" INTEGER NOT NULL DEFAULT 0,
    "fail_count" INTEGER NOT NULL DEFAULT 0,
    "error_message" TEXT,
    "feed_detail" JSONB,
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP(3) NOT NULL,
    "completed_at" TIMESTAMP(3),

    CONSTRAINT "feed_records_pkey" PRIMARY KEY ("id")
);

-- CreateIndex
CREATE UNIQUE INDEX "feed_records_shop_id_feed_id_key" ON "feed_records"("shop_id", "feed_id");

-- AddForeignKey
ALTER TABLE "feed_records" ADD CONSTRAINT "feed_records_shop_id_fkey" FOREIGN KEY ("shop_id") REFERENCES "shops"("id") ON DELETE CASCADE ON UPDATE CASCADE;
