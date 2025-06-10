'use client';

import { useEffect, useState } from 'react';
import { Progress } from '@/components/ui/progress';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { CheckCircle, XCircle, Loader2 } from 'lucide-react';
import axiosInstance from '@/lib/axios';

interface ProgressData {
  stage: string;
  message: string;
  percentage: number;
  timestamp: string;
  error?: string;
}

interface TenantCreationProgressProps {
  progressId: string;
  onComplete?: (success: boolean, error?: string) => void;
  onError?: (error: string) => void;
}

const STAGE_DESCRIPTIONS = {
  validating: 'Validating organization data...',
  creating_tenant: 'Creating organization record...',
  creating_domains: 'Setting up domains...',
  creating_database: 'Creating tenant database...',
  creating_admin: 'Creating admin user...',
  setting_up_data: 'Setting up default data...',
  seeding_data: 'Seeding initial content...',
  completed: 'Organization created successfully!',
  failed: 'Failed to create organization'
};

export function TenantCreationProgress({ 
  progressId, 
  onComplete, 
  onError 
}: TenantCreationProgressProps) {
  const [progress, setProgress] = useState<ProgressData | null>(null);
  const [isPolling, setIsPolling] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!progressId || !isPolling) return;

    const pollProgress = async () => {
      try {
        const response = await axiosInstance.get(`/auth/tenant-creation-progress/${progressId}`);
        const progressData = response.data as ProgressData;
        
        setProgress(progressData);

        // Check if completed or failed
        if (progressData.stage === 'completed') {
          setIsPolling(false);
          onComplete?.(true);
        } else if (progressData.stage === 'failed') {
          setIsPolling(false);
          const errorMessage = progressData.error || 'Unknown error occurred';
          setError(errorMessage);
          onComplete?.(false, errorMessage);
          onError?.(errorMessage);
        }
      } catch (err) {
        console.error('Failed to fetch progress:', err);
        const errorMessage = err instanceof Error ? err.message : 'Failed to check progress';
        setError(errorMessage);
        setIsPolling(false);
        onError?.(errorMessage);
      }
    };

    // Initial poll
    pollProgress();

    // Set up polling interval
    const interval = setInterval(pollProgress, 1000); // Poll every second

    return () => clearInterval(interval);
  }, [progressId, isPolling, onComplete, onError]);

  if (error) {
    return (
      <Card className="w-full max-w-md mx-auto">
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-red-600">
            <XCircle className="h-5 w-5" />
            Creation Failed
          </CardTitle>
          <CardDescription>
            An error occurred while creating your organization
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="text-sm text-red-600 bg-red-50 p-3 rounded-md">
            {error}
          </div>
        </CardContent>
      </Card>
    );
  }

  if (!progress) {
    return (
      <Card className="w-full max-w-md mx-auto">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Loader2 className="h-5 w-5 animate-spin" />
            Initializing...
          </CardTitle>
          <CardDescription>
            Starting organization creation process
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Progress value={0} className="w-full" />
        </CardContent>
      </Card>
    );
  }

  const isCompleted = progress.stage === 'completed';
  const isFailed = progress.stage === 'failed';

  return (
    <Card className="w-full max-w-md mx-auto">
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          {isCompleted ? (
            <CheckCircle className="h-5 w-5 text-green-600" />
          ) : isFailed ? (
            <XCircle className="h-5 w-5 text-red-600" />
          ) : (
            <Loader2 className="h-5 w-5 animate-spin text-blue-600" />
          )}
          {isCompleted ? 'Success!' : isFailed ? 'Failed' : 'Creating Organization...'}
        </CardTitle>
        <CardDescription>
          {isCompleted 
            ? 'Your organization has been created successfully'
            : isFailed 
            ? 'Organization creation failed'
            : 'Please wait while we set up your organization'
          }
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="space-y-2">
          <div className="flex justify-between text-sm">
            <span>{progress.message}</span>
            <span className="text-muted-foreground">{progress.percentage}%</span>
          </div>
          <Progress 
            value={progress.percentage} 
            className={`w-full ${
              isCompleted ? 'bg-green-100' : 
              isFailed ? 'bg-red-100' : 
              'bg-blue-100'
            }`}
          />
        </div>

        {/* Stage indicators */}
        <div className="space-y-2">
          {Object.entries(STAGE_DESCRIPTIONS).map(([stage, description]) => {
            if (stage === 'failed') return null; // Don't show failed in the list
            
            const isCurrentStage = progress.stage === stage;
            const isCompletedStage = getStageOrder(progress.stage) > getStageOrder(stage);
            
            return (
              <div 
                key={stage}
                className={`flex items-center gap-2 text-sm ${
                  isCurrentStage ? 'text-blue-600 font-medium' :
                  isCompletedStage ? 'text-green-600' :
                  'text-muted-foreground'
                }`}
              >
                {isCompletedStage ? (
                  <CheckCircle className="h-4 w-4" />
                ) : isCurrentStage ? (
                  <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                  <div className="h-4 w-4 rounded-full border-2 border-muted-foreground/30" />
                )}
                <span>{description}</span>
              </div>
            );
          })}
        </div>

        {progress.timestamp && (
          <div className="text-xs text-muted-foreground">
            Last updated: {new Date(progress.timestamp).toLocaleTimeString()}
          </div>
        )}
      </CardContent>
    </Card>
  );
}

function getStageOrder(stage: string): number {
  const stages = [
    'validating',
    'creating_tenant', 
    'creating_domains',
    'creating_database',
    'creating_admin',
    'setting_up_data',
    'seeding_data',
    'completed'
  ];
  return stages.indexOf(stage);
}
