'use client';

import React, { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import axios from '@/lib/axios';

interface CreateTenantFormProps {
  onSuccess: () => void;
  onCancel: () => void;
}

interface CreateTenantData {
  name: string;
  slug: string;
  domain: string;
  description: string;
  admin_name: string;
  admin_email: string;
  admin_password: string;
  admin_password_confirmation: string;
}

export default function CreateTenantForm({ onSuccess, onCancel }: CreateTenantFormProps) {
  const [formData, setFormData] = useState<CreateTenantData>({
    name: '',
    slug: '',
    domain: '',
    description: '',
    admin_name: '',
    admin_email: '',
    admin_password: '',
    admin_password_confirmation: '',
  });
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const { toast } = useToast();

  const handleInputChange = (field: keyof CreateTenantData, value: string) => {
    setFormData(prev => ({
      ...prev,
      [field]: value,
    }));

    // Auto-generate slug from name
    if (field === 'name' && !formData.slug) {
      const slug = value
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .trim();
      setFormData(prev => ({
        ...prev,
        slug,
      }));
    }

    // Clear errors for this field
    if (errors[field]) {
      setErrors(prev => ({
        ...prev,
        [field]: [],
      }));
    }
  };

  const validateForm = (): boolean => {
    const newErrors: Record<string, string[]> = {};

    if (!formData.name.trim()) {
      newErrors.name = ['Tenant name is required'];
    }

    if (!formData.slug.trim()) {
      newErrors.slug = ['Slug is required'];
    } else if (!/^[a-z0-9-]+$/.test(formData.slug)) {
      newErrors.slug = ['Slug can only contain lowercase letters, numbers, and hyphens'];
    }

    if (formData.domain && !/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(formData.domain)) {
      newErrors.domain = ['Please enter a valid domain'];
    }

    if (!formData.admin_name.trim()) {
      newErrors.admin_name = ['Administrator name is required'];
    }

    if (!formData.admin_email.trim()) {
      newErrors.admin_email = ['Administrator email is required'];
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.admin_email)) {
      newErrors.admin_email = ['Please enter a valid email address'];
    }

    if (!formData.admin_password) {
      newErrors.admin_password = ['Password is required'];
    } else if (formData.admin_password.length < 8) {
      newErrors.admin_password = ['Password must be at least 8 characters'];
    }

    if (formData.admin_password !== formData.admin_password_confirmation) {
      newErrors.admin_password_confirmation = ['Passwords do not match'];
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!validateForm()) {
      return;
    }

    setLoading(true);
    try {
      await axios.post('/api/admin/tenants', formData);
      toast({
        title: 'Success',
        description: 'Tenant created successfully',
      });
      onSuccess();
    } catch (error: any) {
      if (error.response?.data?.errors) {
        setErrors(error.response.data.errors);
      } else {
        toast({
          title: 'Error',
          description: error.response?.data?.message || 'Failed to create tenant',
          variant: 'destructive',
        });
      }
    } finally {
      setLoading(false);
    }
  };

  const getFieldError = (field: string): string | undefined => {
    return errors[field]?.[0];
  };

  return (
    <Card className="w-full max-w-2xl mx-auto">
      <CardHeader>
        <CardTitle>Create New Tenant</CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Tenant Information */}
          <div className="space-y-4">
            <h3 className="text-lg font-medium text-gray-900">Tenant Information</h3>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <Label htmlFor="name">Tenant Name *</Label>
                <Input
                  id="name"
                  value={formData.name}
                  onChange={(e) => handleInputChange('name', e.target.value)}
                  placeholder="e.g., Springfield School District"
                  className={getFieldError('name') ? 'border-red-500' : ''}
                />
                {getFieldError('name') && (
                  <p className="text-sm text-red-600 mt-1">{getFieldError('name')}</p>
                )}
              </div>

              <div>
                <Label htmlFor="slug">Slug *</Label>
                <Input
                  id="slug"
                  value={formData.slug}
                  onChange={(e) => handleInputChange('slug', e.target.value)}
                  placeholder="e.g., springfield-schools"
                  className={getFieldError('slug') ? 'border-red-500' : ''}
                />
                {getFieldError('slug') && (
                  <p className="text-sm text-red-600 mt-1">{getFieldError('slug')}</p>
                )}
              </div>
            </div>

            <div>
              <Label htmlFor="domain">Custom Domain (Optional)</Label>
              <Input
                id="domain"
                value={formData.domain}
                onChange={(e) => handleInputChange('domain', e.target.value)}
                placeholder="e.g., learn.springfieldschools.edu"
                className={getFieldError('domain') ? 'border-red-500' : ''}
              />
              {getFieldError('domain') && (
                <p className="text-sm text-red-600 mt-1">{getFieldError('domain')}</p>
              )}
            </div>

            <div>
              <Label htmlFor="description">Description</Label>
              <Textarea
                id="description"
                value={formData.description}
                onChange={(e) => handleInputChange('description', e.target.value)}
                placeholder="Brief description of the tenant organization"
                rows={3}
              />
            </div>
          </div>

          {/* Administrator Account */}
          <div className="space-y-4">
            <h3 className="text-lg font-medium text-gray-900">Administrator Account</h3>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <Label htmlFor="admin_name">Administrator Name *</Label>
                <Input
                  id="admin_name"
                  value={formData.admin_name}
                  onChange={(e) => handleInputChange('admin_name', e.target.value)}
                  placeholder="e.g., John Smith"
                  className={getFieldError('admin_name') ? 'border-red-500' : ''}
                />
                {getFieldError('admin_name') && (
                  <p className="text-sm text-red-600 mt-1">{getFieldError('admin_name')}</p>
                )}
              </div>

              <div>
                <Label htmlFor="admin_email">Administrator Email *</Label>
                <Input
                  id="admin_email"
                  type="email"
                  value={formData.admin_email}
                  onChange={(e) => handleInputChange('admin_email', e.target.value)}
                  placeholder="e.g., admin@springfieldschools.edu"
                  className={getFieldError('admin_email') ? 'border-red-500' : ''}
                />
                {getFieldError('admin_email') && (
                  <p className="text-sm text-red-600 mt-1">{getFieldError('admin_email')}</p>
                )}
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <Label htmlFor="admin_password">Password *</Label>
                <Input
                  id="admin_password"
                  type="password"
                  value={formData.admin_password}
                  onChange={(e) => handleInputChange('admin_password', e.target.value)}
                  placeholder="Minimum 8 characters"
                  className={getFieldError('admin_password') ? 'border-red-500' : ''}
                />
                {getFieldError('admin_password') && (
                  <p className="text-sm text-red-600 mt-1">{getFieldError('admin_password')}</p>
                )}
              </div>

              <div>
                <Label htmlFor="admin_password_confirmation">Confirm Password *</Label>
                <Input
                  id="admin_password_confirmation"
                  type="password"
                  value={formData.admin_password_confirmation}
                  onChange={(e) => handleInputChange('admin_password_confirmation', e.target.value)}
                  placeholder="Confirm password"
                  className={getFieldError('admin_password_confirmation') ? 'border-red-500' : ''}
                />
                {getFieldError('admin_password_confirmation') && (
                  <p className="text-sm text-red-600 mt-1">{getFieldError('admin_password_confirmation')}</p>
                )}
              </div>
            </div>
          </div>

          {/* Actions */}
          <div className="flex justify-end gap-3 pt-4 border-t">
            <Button type="button" variant="outline" onClick={onCancel}>
              Cancel
            </Button>
            <Button type="submit" disabled={loading}>
              {loading ? 'Creating...' : 'Create Tenant'}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  );
}
