import { BaseChannelAdapter } from './base.adapter';
import { MockChannelAdapter } from './mock.adapter';
import { GigaCloudAdapter } from './gigacloud.adapter';

export * from './base.adapter';
export * from './mock.adapter';
export * from './gigacloud.adapter';

export class ChannelAdapterFactory {
  static create(type: string, config: Record<string, any>): BaseChannelAdapter {
    switch (type.toLowerCase()) {
      case 'mock':
        return new MockChannelAdapter(config);
      case 'gigacloud':
        return new GigaCloudAdapter(config);
      default:
        throw new Error(`Unknown channel type: ${type}`);
    }
  }
}
