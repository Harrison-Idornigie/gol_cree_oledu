'use client';

import { useState } from 'react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AnalyticsOverview from '@/components/super/analytics/AnalyticsOverview';
import TenantUsageAnalytics from '@/components/super/analytics/TenantUsageAnalytics';

export default function AnalyticsPage() {
  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900">System Analytics</h1>
        <p className="text-gray-600">Monitor system performance and tenant usage</p>
      </div>

      {/* Analytics Tabs */}
      <Tabs defaultValue="overview" className="space-y-6">
        <TabsList>
          <TabsTrigger value="overview">System Overview</TabsTrigger>
          <TabsTrigger value="tenant-usage">Tenant Usage</TabsTrigger>
        </TabsList>
        
        <TabsContent value="overview">
          <AnalyticsOverview />
        </TabsContent>
        
        <TabsContent value="tenant-usage">
          <TenantUsageAnalytics />
        </TabsContent>
      </Tabs>
    </div>
  );
}
