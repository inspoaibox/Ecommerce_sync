-- CreateEnum
CREATE TYPE "ShopSyncTaskStatus" AS ENUM ('pending', 'running', 'paused', 'completed', 'failed', 'cancelled');

-- CreateTable
CREATE TABLE "shop_sync_tasks" (
    "id" TEXT NOT NULL,
    "shop_id" TEXT NOT NULL,
    "shop_name" VARCHAR(100) NOT NULL,
    "status" "ShopSyncTaskStatus" NOT NULL DEFAULT 'pending',
    "progress" INTEGER NOT NULL DEFAULT 0,
    "total" INTEGER NOT NULL DEFAULT 0,
    "created" INTEGER NOT NULL DEFAULT 0,
    "updated" INTEGER NOT NULL DEFAULT 0,
    "skipped" INTEGER NOT NULL DEFAULT 0,
    "error_message" TEXT,
    "started_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "finished_at" TIMESTAMP(3),
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "shop_sync_tasks_pkey" PRIMARY KEY ("id")
);

-- AddForeignKey
ALTER TABLE "shop_sync_tasks" ADD CONSTRAINT "shop_sync_tasks_shop_id_fkey" FOREIGN KEY ("shop_id") REFERENCES "shops"("id") ON DELETE CASCADE ON UPDATE CASCADE;
