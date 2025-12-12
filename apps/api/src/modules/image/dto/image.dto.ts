import { IsString, IsArray, IsOptional, IsNumber, IsBoolean } from 'class-validator';

/**
 * 图片分析结果
 */
export interface ImageAnalysisResult {
  url: string;
  /** 文件大小（字节） */
  fileSize: number;
  /** 文件大小（MB） */
  fileSizeMB: number;
  /** 宽度（像素） */
  width: number;
  /** 高度（像素） */
  height: number;
  /** 宽高比 */
  aspectRatio: number;
  /** 是否为1:1 */
  isSquare: boolean;
  /** 图片格式 */
  format: string;
  /** 是否需要处理（超大或非1:1） */
  needsProcessing: boolean;
  /** 需要处理的原因 */
  processingReasons: string[];
  /** 分析是否成功 */
  success: boolean;
  /** 错误信息 */
  error?: string;
}

/**
 * 图片处理选项
 */
export interface ImageProcessOptions {
  /** 最大文件大小（MB） */
  maxSizeMB?: number;
  /** 是否强制1:1裁剪 */
  forceSquare?: boolean;
  /** 目标宽度（像素） */
  targetWidth?: number;
  /** 目标高度（像素） */
  targetHeight?: number;
  /** 输出格式 */
  outputFormat?: 'jpeg' | 'jpg' | 'png' | 'webp';
  /** JPEG质量（1-100） */
  quality?: number;
}

/**
 * 图片处理结果
 */
export interface ImageProcessResult {
  /** 原始URL */
  originalUrl: string;
  /** 处理后的URL（本地路径或新URL） */
  processedUrl: string;
  /** 是否已处理（false表示保持原URL） */
  wasProcessed: boolean;
  /** 原始信息 */
  original: {
    fileSize: number;
    width: number;
    height: number;
  };
  /** 处理后信息 */
  processed?: {
    fileSize: number;
    width: number;
    height: number;
  };
  /** 处理操作 */
  operations: string[];
  /** 是否成功 */
  success: boolean;
  /** 错误信息 */
  error?: string;
}

/**
 * 批量分析请求
 */
export class BatchAnalyzeDto {
  @IsArray()
  @IsString({ each: true })
  urls: string[];

  @IsOptional()
  @IsNumber()
  maxSizeMB?: number;
}

/**
 * 批量处理请求
 */
export class BatchProcessDto {
  @IsArray()
  @IsString({ each: true })
  urls: string[];

  @IsOptional()
  @IsNumber()
  maxSizeMB?: number;

  @IsOptional()
  @IsBoolean()
  forceSquare?: boolean;

  @IsOptional()
  @IsNumber()
  targetWidth?: number;

  @IsOptional()
  @IsNumber()
  quality?: number;
}

/**
 * 单个图片处理请求
 */
export class ProcessImageDto {
  @IsString()
  url: string;

  @IsOptional()
  @IsNumber()
  maxSizeMB?: number;

  @IsOptional()
  @IsBoolean()
  forceSquare?: boolean;

  @IsOptional()
  @IsNumber()
  targetWidth?: number;

  @IsOptional()
  @IsNumber()
  quality?: number;
}
