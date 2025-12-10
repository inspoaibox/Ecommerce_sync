-- AlterTable
ALTER TABLE "products" ADD COLUMN     "channel_id" TEXT;

-- AddForeignKey
ALTER TABLE "products" ADD CONSTRAINT "products_channel_id_fkey" FOREIGN KEY ("channel_id") REFERENCES "channels"("id") ON DELETE SET NULL ON UPDATE CASCADE;
