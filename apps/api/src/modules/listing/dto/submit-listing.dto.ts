import { IsString, IsArray, IsOptional } from 'class-validator';

export class SubmitListingDto {
  @IsString()
  shopId: string;

  @IsArray()
  @IsString({ each: true })
  productIds: string[];

  @IsOptional()
  @IsString()
  categoryId?: string;
}

export class ValidateListingResultDto {
  valid: boolean;
  errors: {
    productId: string;
    sku: string;
    missingFields: string[];
  }[];
}

export class SubmitListingResultDto {
  taskId: string;
  status: string;
  totalCount: number;
}
