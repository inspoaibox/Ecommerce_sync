import { IsString, IsOptional, IsEnum, IsObject } from 'class-validator';
import { ApiProperty, ApiPropertyOptional, PartialType } from '@nestjs/swagger';
import { Status } from '@prisma/client';

export class CreateChannelDto {
  @ApiProperty({ description: '渠道名称' })
  @IsString()
  name: string;

  @ApiProperty({ description: '渠道编码' })
  @IsString()
  code: string;

  @ApiProperty({ description: '渠道类型' })
  @IsString()
  type: string;

  @ApiProperty({ description: 'API配置' })
  @IsObject()
  apiConfig: Record<string, any>;

  @ApiPropertyOptional({ description: '描述' })
  @IsOptional()
  @IsString()
  description?: string;

  @ApiPropertyOptional({ enum: Status })
  @IsOptional()
  @IsEnum(Status)
  status?: Status;
}

export class UpdateChannelDto extends PartialType(CreateChannelDto) {}
