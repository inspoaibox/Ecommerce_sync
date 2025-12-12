import { Injectable, Logger } from '@nestjs/common';
import * as sharp from 'sharp';
import * as fs from 'fs';
import * as path from 'path';
import * as crypto from 'crypto';
import {
  ImageAnalysisResult,
  ImageProcessOptions,
  ImageProcessResult,
} from './dto/image.dto';

// 默认处理规则
const DEFAULT_OPTIONS: ImageProcessOptions = {
  maxSizeMB: 5,
  forceSquare: true,
  targetWidth: 2000,
  quality: 85,
  outputFormat: 'jpeg',
};

@Injectable()
export class ImageService {
  private readonly logger = new Logger(ImageService.name);
  private readonly uploadDir: string;

  constructor() {
    // 图片存储目录
    this.uploadDir = path.join(process.cwd(), 'uploads', 'images');
    this.ensureUploadDir();
  }

  private ensureUploadDir() {
    if (!fs.existsSync(this.uploadDir)) {
      fs.mkdirSync(this.uploadDir, { recursive: true });
    }
  }

  /**
   * 分析远程图片信息（只读取头部，不下载完整图片）
   */
  async analyzeImage(url: string, maxSizeMB = 5): Promise<ImageAnalysisResult> {
    try {
      // 先用 HEAD 请求获取文件大小
      const headResponse = await fetch(url, { method: 'HEAD' });
      if (!headResponse.ok) {
        throw new Error(`HTTP ${headResponse.status}`);
      }

      const contentLength = headResponse.headers.get('content-length');
      const fileSize = contentLength ? parseInt(contentLength, 10) : 0;
      const fileSizeMB = fileSize / (1024 * 1024);

      // 使用 Range 请求只下载前 64KB 来获取图片尺寸
      const rangeResponse = await fetch(url, {
        headers: { Range: 'bytes=0-65535' },
      });

      let width = 0;
      let height = 0;
      let format = 'unknown';

      if (rangeResponse.ok || rangeResponse.status === 206) {
        const buffer = Buffer.from(await rangeResponse.arrayBuffer());
        try {
          const metadata = await sharp(buffer).metadata();
          width = metadata.width || 0;
          height = metadata.height || 0;
          format = metadata.format || 'unknown';
        } catch {
          // 如果头部数据不够，需要下载完整图片
          this.logger.warn(`Range request insufficient for ${url}, fetching full image`);
          const fullResponse = await fetch(url);
          const fullBuffer = Buffer.from(await fullResponse.arrayBuffer());
          const metadata = await sharp(fullBuffer).metadata();
          width = metadata.width || 0;
          height = metadata.height || 0;
          format = metadata.format || 'unknown';
        }
      }

      const aspectRatio = height > 0 ? width / height : 0;
      const isSquare = Math.abs(aspectRatio - 1) < 0.01; // 允许1%误差
      const isOversized = fileSizeMB > maxSizeMB;

      const processingReasons: string[] = [];
      if (isOversized) processingReasons.push(`文件过大(${fileSizeMB.toFixed(2)}MB > ${maxSizeMB}MB)`);
      if (!isSquare) processingReasons.push(`非1:1比例(${aspectRatio.toFixed(2)})`);

      return {
        url,
        fileSize,
        fileSizeMB: parseFloat(fileSizeMB.toFixed(2)),
        width,
        height,
        aspectRatio: parseFloat(aspectRatio.toFixed(2)),
        isSquare,
        format,
        needsProcessing: isOversized || !isSquare,
        processingReasons,
        success: true,
      };
    } catch (error: any) {
      this.logger.error(`Failed to analyze image: ${url}`, error.message);
      return {
        url,
        fileSize: 0,
        fileSizeMB: 0,
        width: 0,
        height: 0,
        aspectRatio: 0,
        isSquare: false,
        format: 'unknown',
        needsProcessing: false,
        processingReasons: [],
        success: false,
        error: error.message,
      };
    }
  }

  /**
   * 批量分析图片
   */
  async batchAnalyze(urls: string[], maxSizeMB = 5): Promise<ImageAnalysisResult[]> {
    const results: ImageAnalysisResult[] = [];
    
    // 并发限制为5
    const concurrency = 5;
    for (let i = 0; i < urls.length; i += concurrency) {
      const batch = urls.slice(i, i + concurrency);
      const batchResults = await Promise.all(
        batch.map(url => this.analyzeImage(url, maxSizeMB))
      );
      results.push(...batchResults);
    }

    return results;
  }


  /**
   * 处理单张图片（下载、压缩、裁剪）
   */
  async processImage(
    url: string,
    options: ImageProcessOptions = {},
  ): Promise<ImageProcessResult> {
    const opts = { ...DEFAULT_OPTIONS, ...options };

    try {
      // 先分析图片
      const analysis = await this.analyzeImage(url, opts.maxSizeMB);
      if (!analysis.success) {
        return {
          originalUrl: url,
          processedUrl: url,
          wasProcessed: false,
          original: { fileSize: 0, width: 0, height: 0 },
          operations: [],
          success: false,
          error: analysis.error,
        };
      }

      // 判断是否需要处理
      const needsCompress = analysis.fileSizeMB > (opts.maxSizeMB || 5);
      const needsCrop = opts.forceSquare && !analysis.isSquare;
      const needsResize = opts.targetWidth && analysis.width > opts.targetWidth;

      if (!needsCompress && !needsCrop && !needsResize) {
        // 不需要处理，返回原URL
        return {
          originalUrl: url,
          processedUrl: url,
          wasProcessed: false,
          original: {
            fileSize: analysis.fileSize,
            width: analysis.width,
            height: analysis.height,
          },
          operations: [],
          success: true,
        };
      }

      // 下载图片
      const response = await fetch(url);
      if (!response.ok) {
        throw new Error(`Failed to download: HTTP ${response.status}`);
      }
      const imageBuffer = Buffer.from(await response.arrayBuffer());

      // 使用 sharp 处理
      let sharpInstance = sharp(imageBuffer);
      const operations: string[] = [];

      // 1. 裁剪为1:1（居中裁剪）
      if (needsCrop) {
        const size = Math.min(analysis.width, analysis.height);
        const left = Math.floor((analysis.width - size) / 2);
        const top = Math.floor((analysis.height - size) / 2);
        sharpInstance = sharpInstance.extract({
          left,
          top,
          width: size,
          height: size,
        });
        operations.push(`裁剪为1:1 (${size}x${size})`);
      }

      // 2. 调整尺寸
      if (needsResize || (needsCrop && opts.targetWidth)) {
        const targetSize = opts.targetWidth || 2000;
        sharpInstance = sharpInstance.resize(targetSize, targetSize, {
          fit: 'inside',
          withoutEnlargement: true,
        });
        operations.push(`调整尺寸至最大${targetSize}px`);
      }

      // 3. 压缩
      if (opts.outputFormat === 'jpeg' || opts.outputFormat === 'jpg') {
        sharpInstance = sharpInstance.jpeg({ quality: opts.quality || 85 });
        operations.push(`JPEG压缩(质量${opts.quality || 85})`);
      } else if (opts.outputFormat === 'webp') {
        sharpInstance = sharpInstance.webp({ quality: opts.quality || 85 });
        operations.push(`WebP压缩(质量${opts.quality || 85})`);
      } else if (opts.outputFormat === 'png') {
        sharpInstance = sharpInstance.png({ compressionLevel: 9 });
        operations.push('PNG压缩');
      }

      // 生成文件名
      const hash = crypto.createHash('md5').update(url).digest('hex').slice(0, 12);
      const ext = opts.outputFormat || 'jpeg';
      const filename = `${hash}_${Date.now()}.${ext}`;
      const filepath = path.join(this.uploadDir, filename);

      // 保存文件
      const outputBuffer = await sharpInstance.toBuffer();
      await fs.promises.writeFile(filepath, outputBuffer);

      // 获取处理后的信息
      const processedMetadata = await sharp(outputBuffer).metadata();

      return {
        originalUrl: url,
        processedUrl: `/uploads/images/${filename}`,
        wasProcessed: true,
        original: {
          fileSize: analysis.fileSize,
          width: analysis.width,
          height: analysis.height,
        },
        processed: {
          fileSize: outputBuffer.length,
          width: processedMetadata.width || 0,
          height: processedMetadata.height || 0,
        },
        operations,
        success: true,
      };
    } catch (error: any) {
      this.logger.error(`Failed to process image: ${url}`, error.message);
      return {
        originalUrl: url,
        processedUrl: url,
        wasProcessed: false,
        original: { fileSize: 0, width: 0, height: 0 },
        operations: [],
        success: false,
        error: error.message,
      };
    }
  }

  /**
   * 批量处理图片（保持顺序）
   */
  async batchProcess(
    urls: string[],
    options: ImageProcessOptions = {},
  ): Promise<ImageProcessResult[]> {
    const results: ImageProcessResult[] = [];

    // 顺序处理以保持顺序
    for (const url of urls) {
      const result = await this.processImage(url, options);
      results.push(result);
    }

    return results;
  }

  /**
   * 智能处理商品图片（主图+附图）
   * 返回处理后的图片URL列表，保持顺序
   */
  async processProductImages(
    mainImageUrl: string,
    imageUrls: string[],
    options: ImageProcessOptions = {},
  ): Promise<{
    mainImageUrl: string;
    imageUrls: string[];
    results: ImageProcessResult[];
    summary: {
      total: number;
      processed: number;
      failed: number;
      skipped: number;
    };
  }> {
    const allUrls = [mainImageUrl, ...imageUrls].filter(Boolean);
    const results = await this.batchProcess(allUrls, options);

    let processed = 0;
    let failed = 0;
    let skipped = 0;

    results.forEach(r => {
      if (!r.success) failed++;
      else if (r.wasProcessed) processed++;
      else skipped++;
    });

    return {
      mainImageUrl: results[0]?.processedUrl || mainImageUrl,
      imageUrls: results.slice(1).map(r => r.processedUrl),
      results,
      summary: {
        total: allUrls.length,
        processed,
        failed,
        skipped,
      },
    };
  }

  /**
   * 获取本地图片的绝对路径
   */
  getLocalImagePath(relativePath: string): string | null {
    if (!relativePath.startsWith('/uploads/images/')) {
      return null;
    }
    const filename = path.basename(relativePath);
    const filepath = path.join(this.uploadDir, filename);
    return fs.existsSync(filepath) ? filepath : null;
  }

  /**
   * 判断URL是本地还是远程
   */
  isLocalImage(url: string): boolean {
    return url.startsWith('/uploads/images/');
  }

  /**
   * 清理过期的本地图片（可选，定时任务调用）
   */
  async cleanupOldImages(maxAgeDays = 30): Promise<number> {
    const files = await fs.promises.readdir(this.uploadDir);
    const now = Date.now();
    const maxAge = maxAgeDays * 24 * 60 * 60 * 1000;
    let deleted = 0;

    for (const file of files) {
      const filepath = path.join(this.uploadDir, file);
      const stat = await fs.promises.stat(filepath);
      if (now - stat.mtimeMs > maxAge) {
        await fs.promises.unlink(filepath);
        deleted++;
      }
    }

    this.logger.log(`Cleaned up ${deleted} old images`);
    return deleted;
  }
}
