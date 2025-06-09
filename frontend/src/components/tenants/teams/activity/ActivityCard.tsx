'use client';

import { ActivityItem, ActivityImpact, IMPACT_CONFIG } from '@/types/activity';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible';

const getImpactColor = (impact: ActivityImpact) => {
  const colors = {
    [ActivityImpact.CRITICAL]: 'bg-red-50 border-red-200 text-red-700',
    [ActivityImpact.IMPORTANT]: 'bg-orange-50 border-orange-200 text-orange-700',
    [ActivityImpact.STANDARD]: 'bg-blue-50 border-blue-200 text-blue-700',
    [ActivityImpact.INFO]: 'bg-green-50 border-green-200 text-green-700',
  };
  return colors[impact];
};

const formatDate = (date: string) => {
  const d = new Date(date);
  return d.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};

const formatValue = (value: unknown): string => {
  try {
    if (value === null || value === undefined) return '';
    if (typeof value === 'string') return value;
    return JSON.stringify(value, null, 2);
  } catch {
    return String(value);
  }
};

interface ActivityCardProps {
  activity: ActivityItem;
  onViewDetails?: (activity: ActivityItem) => void;
}

export function ActivityCard({ activity, onViewDetails }: ActivityCardProps) {
  const impactConfig = IMPACT_CONFIG[activity.impact];
  const colorClasses = getImpactColor(activity.impact);
  const showChanges = Boolean(activity.metadata.old_value || activity.metadata.new_value);

  const oldValue = activity.metadata.old_value ? formatValue(activity.metadata.old_value) : null;
  const newValue = activity.metadata.new_value ? formatValue(activity.metadata.new_value) : null;

  return (
    <Card className={cn('border-l-4', colorClasses)}>
      <CardHeader className="space-y-1">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-2">
            {activity.user_avatar && (
              <img
                src={activity.user_avatar}
                alt=""
                className="w-6 h-6 rounded-full"
              />
            )}
            <CardTitle className="text-sm font-medium">
              {activity.user_name}
            </CardTitle>
          </div>
          <span className={cn(
            'px-2 py-1 text-xs font-medium rounded-full',
            colorClasses
          )}>
            {impactConfig.badge}
          </span>
        </div>
        <CardDescription className="text-sm">
          {formatDate(activity.created_at)}
        </CardDescription>
      </CardHeader>
      <CardContent>
        <p className="text-sm">
          <span className="font-medium">
            {activity.action.toLowerCase().replace(/_/g, ' ')}
          </span>
          {' - '}
          <span className="text-muted-foreground">{activity.target_name}</span>
        </p>
      </CardContent>
      {showChanges && (
        <Collapsible>
          <CollapsibleTrigger asChild>
            <Button variant="ghost" size="sm" className="w-full justify-start px-6">
              View changes
            </Button>
          </CollapsibleTrigger>
          <CollapsibleContent className="px-6 pb-4">
            <div className="text-sm space-y-2">
              {oldValue && (
                <pre className="text-red-500 whitespace-pre-wrap font-mono text-xs p-2 bg-red-50 rounded">
                  - {oldValue}
                </pre>
              )}
              {newValue && (
                <pre className="text-green-500 whitespace-pre-wrap font-mono text-xs p-2 bg-green-50 rounded">
                  + {newValue}
                </pre>
              )}
            </div>
          </CollapsibleContent>
        </Collapsible>
      )}
      <CardFooter className="px-6 py-4">
        <div className="flex justify-between items-center w-full text-xs text-muted-foreground">
          <span>{activity.ip_address}</span>
          {onViewDetails && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => onViewDetails(activity)}
            >
              View Details
            </Button>
          )}
        </div>
      </CardFooter>
    </Card>
  );
}