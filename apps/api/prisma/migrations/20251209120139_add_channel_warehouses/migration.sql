-- CreateTable
CREATE TABLE "channel_warehouses" (
    "id" TEXT NOT NULL,
    "channel_id" TEXT NOT NULL,
    "warehouse_code" VARCHAR(50) NOT NULL,
    "warehouse_name" VARCHAR(100) NOT NULL,
    "region" VARCHAR(50),
    "country" VARCHAR(50),
    "extra_data" JSONB,
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "channel_warehouses_pkey" PRIMARY KEY ("id")
);

-- CreateIndex
CREATE UNIQUE INDEX "channel_warehouses_channel_id_warehouse_code_key" ON "channel_warehouses"("channel_id", "warehouse_code");

-- AddForeignKey
ALTER TABLE "channel_warehouses" ADD CONSTRAINT "channel_warehouses_channel_id_fkey" FOREIGN KEY ("channel_id") REFERENCES "channels"("id") ON DELETE CASCADE ON UPDATE CASCADE;
