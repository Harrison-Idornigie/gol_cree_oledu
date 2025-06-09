'use client';

import { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { 
  Save, 
  AlertTriangle, 
  Settings as SettingsIcon,
  Shield,
  Database,
  Mail
} from 'lucide-react';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { useToast } from '@/hooks/use-toast';
import { 
  getSystemSettings, 
  updateSystemSettings,
  getMaintenanceMode,
  setMaintenanceMode
} from '@/app/_actions/super/system-actions';
import { SystemSettings as SystemSettingsType, MaintenanceMode } from '@/types/super/super-admin';

export default function SystemSettings() {
  const [settings, setSettings] = useState<SystemSettingsType | null>(null);
  const [maintenanceMode, setMaintenanceModeState] = useState<MaintenanceMode | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const { toast } = useToast();

  useEffect(() => {
    const fetchData = async () => {
      try {
        setLoading(true);
        
        const [settingsResult, maintenanceResult] = await Promise.all([
          getSystemSettings(),
          getMaintenanceMode()
        ]);

        if (settingsResult.data) {
          setSettings(settingsResult.data);
        }
        
        if (maintenanceResult.data) {
          setMaintenanceModeState(maintenanceResult.data);
        }
      } catch (error) {
        console.error('Error fetching settings:', error);
        toast({
          title: 'Error',
          description: 'Failed to load system settings',
          variant: 'destructive',
        });
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, [toast]);

  const handleSaveSettings = async () => {
    if (!settings) return;

    try {
      setSaving(true);
      
      const result = await updateSystemSettings(settings);
      
      if (result.error) {
        toast({
          title: 'Error',
          description: result.error,
          variant: 'destructive',
        });
        return;
      }
      
      toast({
        title: 'Success',
        description: 'System settings updated successfully',
      });
    } catch {
      toast({
        title: 'Error',
        description: 'Failed to update system settings',
        variant: 'destructive',
      });
    } finally {
      setSaving(false);
    }
  };

  const handleMaintenanceModeToggle = async (enabled: boolean) => {
    try {
      const result = await setMaintenanceMode({
        enabled,
        message: enabled ? 'System is under maintenance. Please try again later.' : undefined
      });
      
      if (result.error) {
        toast({
          title: 'Error',
          description: result.error,
          variant: 'destructive',
        });
        return;
      }
      
      if (result.data) {
        setMaintenanceModeState(result.data);
        toast({
          title: 'Success',
          description: `Maintenance mode ${enabled ? 'enabled' : 'disabled'}`,
        });
      }
    } catch {
      toast({
        title: 'Error',
        description: 'Failed to update maintenance mode',
        variant: 'destructive',
      });
    }
  };

  const updateSetting = (key: keyof SystemSettingsType, value: any) => {
    if (!settings) return;
    setSettings({ ...settings, [key]: value });
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">System Settings</h1>
          <p className="text-gray-600">Configure system-wide settings and preferences</p>
        </div>
        <Button 
          onClick={handleSaveSettings} 
          disabled={saving || !settings}
          className="flex items-center gap-2"
        >
          <Save className="h-4 w-4" />
          {saving ? 'Saving...' : 'Save Changes'}
        </Button>
      </div>

      {/* Maintenance Mode */}
      {maintenanceMode && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <AlertTriangle className="h-5 w-5 text-orange-500" />
              Maintenance Mode
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Enable Maintenance Mode</p>
                <p className="text-sm text-gray-600">
                  When enabled, only super admins can access the system
                </p>
              </div>
              <Switch
                checked={maintenanceMode.enabled}
                onCheckedChange={handleMaintenanceModeToggle}
              />
            </div>
          </CardContent>
        </Card>
      )}

      {/* General Settings */}
      {settings && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <SettingsIcon className="h-5 w-5" />
              General Settings
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-6">
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="max_tenants">Maximum Tenants</Label>
                <Input
                  id="max_tenants"
                  type="number"
                  value={settings.max_tenants}
                  onChange={(e) => updateSetting('max_tenants', parseInt(e.target.value))}
                />
              </div>
              
              <div className="space-y-2">
                <Label htmlFor="default_user_limit">Default User Limit per Tenant</Label>
                <Input
                  id="default_user_limit"
                  type="number"
                  value={settings.default_user_limit}
                  onChange={(e) => updateSetting('default_user_limit', parseInt(e.target.value))}
                />
              </div>
            </div>

            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Enable Registration</p>
                <p className="text-sm text-gray-600">Allow new tenant registration</p>
              </div>
              <Switch
                checked={settings.registration_enabled}
                onCheckedChange={(checked) => updateSetting('registration_enabled', checked)}
              />
            </div>
          </CardContent>
        </Card>
      )}

      {/* Storage Settings */}
      {settings && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Database className="h-5 w-5" />
              Storage & Performance
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-6">
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="default_storage_limit">Default Storage Limit (GB)</Label>
                <Input
                  id="default_storage_limit"
                  type="number"
                  value={Math.round(settings.default_storage_limit / 1024 / 1024 / 1024)}
                  onChange={(e) => updateSetting('default_storage_limit', parseInt(e.target.value) * 1024 * 1024 * 1024)}
                />
              </div>
              
              <div className="space-y-2">
                <Label htmlFor="api_rate_limit">API Rate Limit (requests/minute)</Label>
                <Input
                  id="api_rate_limit"
                  type="number"
                  value={settings.api_rate_limit}
                  onChange={(e) => updateSetting('api_rate_limit', parseInt(e.target.value))}
                />
              </div>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="session_timeout">Session Timeout (minutes)</Label>
                <Input
                  id="session_timeout"
                  type="number"
                  value={settings.session_timeout}
                  onChange={(e) => updateSetting('session_timeout', parseInt(e.target.value))}
                />
              </div>
              
              <div className="space-y-2">
                <Label htmlFor="log_retention_days">Log Retention (days)</Label>
                <Input
                  id="log_retention_days"
                  type="number"
                  value={settings.log_retention_days}
                  onChange={(e) => updateSetting('log_retention_days', parseInt(e.target.value))}
                />
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Backup & Notifications */}
      {settings && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Mail className="h-5 w-5" />
              Backup & Notifications
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-6">
            <div className="space-y-2">
              <Label htmlFor="backup_frequency">Backup Frequency</Label>
              <Select 
                value={settings.backup_frequency} 
                onValueChange={(value: 'daily' | 'weekly' | 'monthly') => updateSetting('backup_frequency', value)}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="daily">Daily</SelectItem>
                  <SelectItem value="weekly">Weekly</SelectItem>
                  <SelectItem value="monthly">Monthly</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Email Notifications</p>
                <p className="text-sm text-gray-600">Send system notifications via email</p>
              </div>
              <Switch
                checked={settings.email_notifications}
                onCheckedChange={(checked) => updateSetting('email_notifications', checked)}
              />
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
