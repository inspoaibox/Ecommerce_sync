import * as crypto from 'crypto';

const ALGORITHM = 'aes-256-gcm';
const IV_LENGTH = 16;
const AUTH_TAG_LENGTH = 16;

// 从环境变量获取加密密钥，如果没有则使用默认值（生产环境应该配置）
const getEncryptionKey = (): Buffer => {
  const key = process.env.ENCRYPTION_KEY || 'default-encryption-key-32-chars!';
  // 确保密钥长度为 32 字节
  return crypto.scryptSync(key, 'salt', 32);
};

/**
 * 加密字符串
 */
export function encrypt(text: string): string {
  const key = getEncryptionKey();
  const iv = crypto.randomBytes(IV_LENGTH);
  const cipher = crypto.createCipheriv(ALGORITHM, key, iv);

  let encrypted = cipher.update(text, 'utf8', 'hex');
  encrypted += cipher.final('hex');

  const authTag = cipher.getAuthTag();

  // 返回格式: iv:authTag:encrypted
  return `${iv.toString('hex')}:${authTag.toString('hex')}:${encrypted}`;
}

/**
 * 解密字符串
 */
export function decrypt(encryptedText: string): string {
  // 检查是否是加密格式
  if (!encryptedText.includes(':')) {
    // 如果不是加密格式，直接返回（兼容旧数据）
    return encryptedText;
  }

  const parts = encryptedText.split(':');
  if (parts.length !== 3) {
    return encryptedText;
  }

  const [ivHex, authTagHex, encrypted] = parts;

  try {
    const key = getEncryptionKey();
    const iv = Buffer.from(ivHex, 'hex');
    const authTag = Buffer.from(authTagHex, 'hex');
    const decipher = crypto.createDecipheriv(ALGORITHM, key, iv);
    decipher.setAuthTag(authTag);

    let decrypted = decipher.update(encrypted, 'hex', 'utf8');
    decrypted += decipher.final('utf8');

    return decrypted;
  } catch {
    // 解密失败，可能是旧格式数据
    return encryptedText;
  }
}

/**
 * 检查是否已加密
 */
export function isEncrypted(text: string): boolean {
  if (!text.includes(':')) return false;
  const parts = text.split(':');
  return parts.length === 3 && parts[0].length === IV_LENGTH * 2;
}

/**
 * 掩码显示（用于前端展示）
 */
export function maskApiKey(apiKey: string): string {
  if (!apiKey || apiKey.length < 8) return '****';
  return apiKey.substring(0, 4) + '****' + apiKey.substring(apiKey.length - 4);
}
