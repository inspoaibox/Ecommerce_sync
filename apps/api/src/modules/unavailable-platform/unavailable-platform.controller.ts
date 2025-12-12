import { Controller, Get, Post, Body, Query } from '@nestjs/common';
import { UnavailablePlatformService } from './unavailable-platform.service';

@Controller('unavailable-platforms')
export class UnavailablePlatformController {
  constructor(private readonly service: UnavailablePlatformService) {}

  @Get()
  async findAll(@Query('channelId') channelId?: string) {
    return this.service.findAll(channelId);
  }

  @Post('save')
  async saveMany(
    @Body() body: { platforms: { platformId: string; platformName: string }[]; channelId?: string },
  ) {
    return this.service.saveMany(body.platforms, body.channelId);
  }
}
