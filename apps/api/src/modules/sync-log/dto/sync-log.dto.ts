import { IsOptional, IsUUID, IsEnum } from 'class-validator';
import { ApiPropertyOptional } from '@nestjs/swagger';
import { SyncLogStatus } from '@prisma/client';
import { PaginationDto } from '@/common/dto/pagination.dto';

export class SyncLogQueryDto extends PaginationDto {
  @ApiPropertyOptional()
  @IsOptional()
  @IsUUID()
  syncRuleId?: string;

  @ApiPropertyOptional({ enum: SyncLogStatus })
  @IsOptional()
  @IsEnum(SyncLogStatus)
  status?: SyncLogStatus;
}
