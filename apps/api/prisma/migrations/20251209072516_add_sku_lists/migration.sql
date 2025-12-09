-- AlterTable
ALTER TABLE "shop_sync_tasks" ADD COLUMN     "created_skus" JSONB,
ADD COLUMN     "skipped_skus" JSONB,
ADD COLUMN     "updated_skus" JSONB;
