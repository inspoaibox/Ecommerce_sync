import { IsString, IsOptional, IsEnum } from 'class-validator';
import { ApiProperty, ApiPropertyOptional, PartialType } from '@nestjs/swagger';
import { Status } from '@prisma/client';

export class CreatePlatformDto {
  @ApiProperty({ description: '平台名称' })
  @IsString()
  name: string;

  @ApiProperty({ description: '平台编码' })
  @IsString()
  code: string;

  @ApiPropertyOptional({ description: 'API基础地址' })
  @IsOptional()
  @IsString()
  apiBaseUrl?: string;

  @ApiPropertyOptional({ description: '描述' })
  @IsOptional()
  @IsString()
  description?: string;

  @ApiPropertyOptional({ enum: Status })
  @IsOptional()
  @IsEnum(Status)
  status?: Status;
}

export class UpdatePlatformDto extends PartialType(CreatePlatformDto) {}
