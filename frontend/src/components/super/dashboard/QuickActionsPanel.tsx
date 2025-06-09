'use client';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { 
  Building2, 
  Users, 
  Activity, 
  TrendingUp
} from 'lucide-react';
import Link from 'next/link';

export default function QuickActionsPanel() {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Quick Actions</CardTitle>
      </CardHeader>
      <CardContent className="space-y-3">
        <Button asChild className="w-full justify-start">
          <Link href="/super/tenants" className="flex items-center gap-2">
            <Building2 className="h-4 w-4" />
            Manage Tenants
          </Link>
        </Button>
        <Button asChild variant="outline" className="w-full justify-start">
          <Link href="/super/analytics" className="flex items-center gap-2">
            <TrendingUp className="h-4 w-4" />
            View Analytics
          </Link>
        </Button>
        <Button asChild variant="outline" className="w-full justify-start">
          <Link href="/super/users" className="flex items-center gap-2">
            <Users className="h-4 w-4" />
            Global User Search
          </Link>
        </Button>
        <Button asChild variant="outline" className="w-full justify-start">
          <Link href="/super/health" className="flex items-center gap-2">
            <Activity className="h-4 w-4" />
            System Health
          </Link>
        </Button>
      </CardContent>
    </Card>
  );
}
