import { IsString, IsOptional, IsEnum, IsObject } from 'class-validator';
import { ApiProperty, ApiPropertyOptional, PartialType } from '@nestjs/swagger';
import { Status } from '@prisma/client';

export class CreateShopDto {
  @ApiProperty({ description: '平台编码' })
  @IsString()
  platformCode: string;

  @ApiProperty({ description: '店铺名称' })
  @IsString()
  name: string;

  @ApiProperty({ description: '店铺编码' })
  @IsString()
  code: string;

  @ApiProperty({ description: 'API凭证' })
  @IsObject()
  apiCredentials: Record<string, any>;

  @ApiPropertyOptional({ description: '区域' })
  @IsOptional()
  @IsString()
  region?: string;

  @ApiPropertyOptional({ description: '描述' })
  @IsOptional()
  @IsString()
  description?: string;

  @ApiPropertyOptional({ enum: Status })
  @IsOptional()
  @IsEnum(Status)
  status?: Status;
}

export class UpdateShopDto extends PartialType(CreateShopDto) {}
